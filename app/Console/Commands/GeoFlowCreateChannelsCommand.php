<?php

namespace App\Console\Commands;

use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Services\GeoFlow\DistributionTargetSitePackageBuilder;
use App\Services\GeoFlow\TargetThemeRegistry;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * 从 CSV 批量创建自建站点渠道，并可一次性导出全部站点包。
 *
 * CSV 需要表头，至少包含 name 与 endpoint_url：
 *   name,domain,endpoint_url,template_key,front_mode
 *   站点一,a.example.com,https://a.example.com,toutiao,static
 */
class GeoFlowCreateChannelsCommand extends Command
{
    protected $signature = 'geoflow:create-channels
        {file : CSV 文件路径}
        {--packages= : 同时把站点包导出到该目录}
        {--auto-template : 未指定 template_key 时按可用主题轮流分配}
        {--dry-run : 只校验并预览，不写入数据库}';

    protected $description = '从 CSV 批量创建自建站点渠道并导出站点包';

    public function handle(ApiKeyCrypto $crypto, DistributionTargetSitePackageBuilder $builder): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error('文件不存在：'.$file);

            return self::FAILURE;
        }

        $rows = $this->readCsv($file);
        if ($rows === []) {
            $this->error('CSV 没有可用数据行。');

            return self::FAILURE;
        }

        $themes = array_values(array_filter(
            TargetThemeRegistry::keys(),
            static fn (string $k): bool => $k !== TargetThemeRegistry::DEFAULT_KEY
        ));
        $autoTemplate = (bool) $this->option('auto-template');
        $dryRun = (bool) $this->option('dry-run');

        $packageDir = (string) ($this->option('packages') ?? '');
        if ($packageDir !== '' && ! $dryRun && ! is_dir($packageDir) && ! mkdir($packageDir, 0755, true) && ! is_dir($packageDir)) {
            $this->error('无法创建导出目录：'.$packageDir);

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $preview = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $endpoint = rtrim(trim((string) ($row['endpoint_url'] ?? '')), '/');
            if ($name === '' || $endpoint === '') {
                $this->warn('第 '.($index + 2).' 行缺少 name 或 endpoint_url，已跳过。');
                $skipped++;

                continue;
            }

            if (! preg_match('#^https?://#i', $endpoint)) {
                $this->warn('第 '.($index + 2).' 行 endpoint_url 必须以 http(s):// 开头，已跳过。');
                $skipped++;

                continue;
            }

            if (DistributionChannel::query()->where('name', $name)->exists()) {
                $this->warn('渠道已存在，跳过：'.$name);
                $skipped++;

                continue;
            }

            $template = trim((string) ($row['template_key'] ?? ''));
            if ($template === '' && $autoTemplate && $themes !== []) {
                $template = $themes[$created % count($themes)];
            }

            $frontMode = trim((string) ($row['front_mode'] ?? 'static'));
            $frontMode = in_array($frontMode, ['static', 'rewrite'], true) ? $frontMode : 'static';

            $domain = trim((string) ($row['domain'] ?? '')) ?: (string) (parse_url($endpoint, PHP_URL_HOST) ?? '');

            $preview[] = [$name, $domain, $endpoint, $template ?: '(默认)', $frontMode];

            if ($dryRun) {
                $created++;

                continue;
            }

            try {
                $channel = DB::transaction(function () use ($name, $domain, $endpoint, $template, $frontMode, $crypto): DistributionChannel {
                    $channel = DistributionChannel::query()->create([
                        'name' => $name,
                        'domain' => $domain,
                        'endpoint_url' => $endpoint,
                        'channel_type' => 'geoflow_agent',
                        'front_mode' => $frontMode,
                        'template_key' => $template !== '' ? $template : null,
                        'status' => 'active',
                    ]);

                    DistributionChannelSecret::query()->create([
                        'distribution_channel_id' => (int) $channel->id,
                        'key_id' => 'gfk_'.Str::lower(Str::random(18)),
                        'secret_ciphertext' => $crypto->encrypt('gfsec_'.Str::random(40)),
                        'status' => 'active',
                        'scopes' => ['article.publish', 'article.update', 'article.delete', 'site.settings.update', 'health.check', 'frontend.capabilities', 'views.pull'],
                    ]);

                    return $channel;
                });
            } catch (Throwable $exception) {
                $this->error('创建失败 ['.$name.']：'.$exception->getMessage());
                $skipped++;

                continue;
            }

            $created++;

            if ($packageDir !== '') {
                $this->exportPackage($builder, $crypto, $channel, $packageDir);
            }
        }

        $this->table(['名称', '域名', '接口地址', '模板', '前台模式'], $preview);
        $this->info(sprintf('%s：成功 %d 个，跳过 %d 个', $dryRun ? '预览' : '创建完成', $created, $skipped));

        if (! $dryRun && $packageDir !== '') {
            $this->info('站点包已导出到：'.$packageDir);
        }

        return $skipped > 0 && $created === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function exportPackage(
        DistributionTargetSitePackageBuilder $builder,
        ApiKeyCrypto $crypto,
        DistributionChannel $channel,
        string $packageDir
    ): void {
        $channel->loadMissing('activeSecret');
        $secret = $channel->activeSecret;
        if (! $secret) {
            $this->warn('缺少密钥，未导出站点包：'.$channel->name);

            return;
        }

        try {
            $result = $builder->build(
                $channel,
                (string) $secret->key_id,
                $crypto->decrypt((string) $secret->secret_ciphertext),
            );
            $target = rtrim($packageDir, '/').'/'.$result['filename'];
            copy($result['path'], $target);
            @unlink($result['path']);
        } catch (Throwable $exception) {
            $this->warn('站点包导出失败 ['.$channel->name.']：'.$exception->getMessage());
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $file): array
    {
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);

            return [];
        }

        $header = array_map(static fn ($v): string => strtolower(trim((string) $v)), $header);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = (string) ($line[$i] ?? '');
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
