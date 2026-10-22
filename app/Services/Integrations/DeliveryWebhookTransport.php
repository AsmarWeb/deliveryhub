<?php

namespace App\Services\Integrations;

use App\Services\Catalog\CatalogFeedClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeliveryWebhookTransport
{
    public function send(string $url, string $body, array $headers): int
    {
        $client = app(CatalogFeedClient::class);
        $host = $client->hostname($url);
        $ip = $client->resolve($host)[0];
        if (! extension_loaded('curl')) {
            throw new RuntimeException('Pinned HTTP transport unavailable.');
        }
        $pin = str_contains($ip, ':') ? '['.$ip.']' : $ip;

        return Http::withHeaders($headers)->withBody($body, 'application/json')->connectTimeout(5)->timeout(20)
            ->withOptions(['allow_redirects' => false, 'proxy' => '', 'decode_content' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$pin}"]],
                'progress' => function ($total, $downloaded) {
                    if ($total > 32768 || $downloaded > 32768) {
                        throw new RuntimeException('Callback response too large.');
                    }
                }])->post($url)->status();
    }
}
