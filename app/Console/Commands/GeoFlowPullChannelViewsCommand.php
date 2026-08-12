<?php

namespace App\Console\Commands;

use App\Models\DistributionChannel;
use App\Services\GeoFlow\ChannelViewLogPullService;
use Illuminate\Console\Command;
use Throwable;

class GeoFlowPullChannelViewsCommand extends Command
{
    protected $signature = 'geoflow:pull-channel-views
        {--channel= : 只拉取指定渠道 ID}
        {--batches=5 : 每个渠道单次最多拉取的批数}';

    protected $description = '从目标站点拉取访问日志并写入 view_logs';

    public function handle(ChannelViewLogPullService $service): int
    {
        $batches = max(1, (int) $this->option('batches'));
        $channelId = (int) $this->option('channel');

        if ($channelId > 0) {
            $channel = DistributionChannel::query()->with('activeSecret')->find($channelId);
            if (! $channel) {
                $this->error('渠道不存在：'.$channelId);

                return self::FAILURE;
            }

            try {
                $imported = $service->pullChannel($channel, $batches);
            } catch (Throwable $exception) {
                $this->error('拉取失败：'.$exception->getMessage());

                return self::FAILURE;
            }

            $this->info("渠道 {$channel->name}：入库 {$imported} 条");

            return self::SUCCESS;
        }

        $result = $service->pullAll($batches);
        $this->info(sprintf(
            '渠道 %d 个，入库 %d 条，失败 %d 个',
            $result['channels'],
            $result['imported'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
