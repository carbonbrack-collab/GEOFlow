<?php

namespace App\Services\GeoFlow;

use App\Models\DistributionChannel;
use App\Models\DistributionChannelSecret;
use App\Services\Outbound\SafeOutboundHttpClient;
use App\Services\Outbound\SafeOutboundRequest;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WordPressRestRequestFactory
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly SafeOutboundHttpClient $safeHttp,
    ) {}

    public function request(DistributionChannel $channel, int $timeout = 30): SafeOutboundRequest
    {
        $channel->loadMissing('activeSecret');
        $config = $channel->resolvedChannelConfig();
        $username = (string) $config['wordpress_username'];
        $secret = $channel->activeSecret;
        if (! $secret instanceof DistributionChannelSecret || $username === '') {
            throw new RuntimeException(__('admin.runtime.wp.creds_missing'));
        }

        $applicationPassword = $this->apiKeyCrypto->decrypt((string) $secret->secret_ciphertext);
        if ($applicationPassword === '') {
            throw new RuntimeException(__('admin.runtime.wp.decrypt_failed'));
        }

        $request = Http::timeout($timeout)
            ->connectTimeout(5)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($username, $applicationPassword);

        return new SafeOutboundRequest(
            $this->safeHttp,
            $request,
            (int) config('geoflow.outbound_json_max_bytes', 4 * 1024 * 1024),
        );
    }
}
