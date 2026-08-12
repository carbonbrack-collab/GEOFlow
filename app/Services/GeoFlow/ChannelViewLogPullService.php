<?php

namespace App\Services\GeoFlow;

use App\Models\DistributionChannel;
use App\Services\Outbound\SafeOutboundHttpClient;
use App\Support\Analytics\TrafficClassifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 从目标站点拉取访问日志，写入 view_logs（source=channel）。
 *
 * 目标站点只落盘、不外连；中心按渠道轮询拉取，入库成功后再回传 commit
 * 推进对方游标，避免网络中断导致数据丢失（代价是极端情况下可能重复一批）。
 */
class ChannelViewLogPullService
{
    public const SOURCE = 'channel';

    private const PATH = '/geoflow-agent/v1/views/pull';

    public function __construct(
        private readonly DistributionSigningService $signingService,
        private readonly SafeOutboundHttpClient $safeHttp,
    ) {}

    /**
     * 拉取全部启用的自建站点渠道。
     *
     * @return array{channels:int,imported:int,failed:int}
     */
    public function pullAll(int $maxBatchesPerChannel = 5): array
    {
        $channels = DistributionChannel::query()
            ->with('activeSecret')
            ->where('status', 'active')
            ->get()
            ->filter(static fn (DistributionChannel $c): bool => $c->isGeoFlowAgent());

        $imported = 0;
        $failed = 0;
        foreach ($channels as $channel) {
            try {
                $imported += $this->pullChannel($channel, $maxBatchesPerChannel);
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('拉取渠道访问日志失败', [
                    'channel_id' => (int) $channel->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return ['channels' => $channels->count(), 'imported' => $imported, 'failed' => $failed];
    }

    /** 单个渠道，最多拉 $maxBatches 批，返回入库条数。 */
    public function pullChannel(DistributionChannel $channel, int $maxBatches = 5): int
    {
        if (! $channel->activeSecret) {
            return 0;
        }

        $imported = 0;
        $commit = null;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $payload = ['limit' => 500];
            if ($commit !== null) {
                $payload['commit'] = $commit;
            }
            $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $request = Http::timeout(20)->connectTimeout(5)->acceptJson()
                ->withHeaders($this->signingService->headers(
                    $channel->activeSecret,
                    'POST',
                    self::PATH,
                    $body,
                    'views.pull',
                    'views-pull-'.(int) $channel->id.'-'.time().'-'.$batch,
                ))
                ->withBody($body, 'application/json');

            $endpoint = rtrim((string) $channel->endpoint_url, '/').self::PATH;
            $response = $this->safeHttp->send($request, 'POST', $endpoint, [], $this->maxBytes());

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }

            $data = $response->json();
            if (! is_array($data) || ($data['ok'] ?? false) !== true) {
                throw new \RuntimeException('目标站点返回异常载荷');
            }

            $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
            if ($entries === []) {
                break;
            }

            $imported += $this->store($channel, $entries);

            $cursor = $data['cursor'] ?? null;
            if (! is_array($cursor) || ! isset($cursor['file'], $cursor['offset'])) {
                break;
            }
            $commit = ['file' => (string) $cursor['file'], 'offset' => (int) $cursor['offset']];

            if (count($entries) < 500) {
                // 已经读到当前文件末尾，再发一次只为确认游标。
                $this->commitOnly($channel, $commit);
                break;
            }
        }

        return $imported;
    }

    /** 只发确认、不取数据，用于推进目标站点游标。 */
    private function commitOnly(DistributionChannel $channel, array $commit): void
    {
        $body = (string) json_encode(['limit' => 1, 'commit' => $commit], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $request = Http::timeout(15)->connectTimeout(5)->acceptJson()
            ->withHeaders($this->signingService->headers(
                $channel->activeSecret,
                'POST',
                self::PATH,
                $body,
                'views.pull',
                'views-commit-'.(int) $channel->id.'-'.time(),
            ))
            ->withBody($body, 'application/json');

        $this->safeHttp->send(
            $request,
            'POST',
            rtrim((string) $channel->endpoint_url, '/').self::PATH,
            [],
            $this->maxBytes(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function store(DistributionChannel $channel, array $entries): int
    {
        $rows = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $path = mb_substr(trim((string) ($entry['p'] ?? '/')), 0, 2000);
            if ($path === '') {
                $path = '/';
            }

            $viewedAt = $this->parseTime($entry['t'] ?? null);
            $userAgent = mb_substr((string) ($entry['ua'] ?? ''), 0, 500);

            $rows[] = [
                'article_id' => null,
                'channel_id' => (int) $channel->id,
                'source' => self::SOURCE,
                'method' => 'GET',
                'path' => $path,
                'route_name' => TrafficClassifier::classify($userAgent),
                'status_code' => 200,
                'ip_address' => mb_substr((string) ($entry['ip'] ?? ''), 0, 64),
                'user_agent' => $userAgent,
                'referer' => mb_substr((string) ($entry['r'] ?? ''), 0, 2000),
                'created_at' => $viewedAt,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('view_logs')->insert($chunk);
        }

        return count($rows);
    }

    private function maxBytes(): int
    {
        return (int) config('geoflow.outbound_json_max_bytes', 4194304);
    }

    private function parseTime(mixed $value): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return now();
        }
    }
}
