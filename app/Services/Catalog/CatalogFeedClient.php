<?php

namespace App\Services\Catalog;

use App\Models\CatalogConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CatalogFeedClient
{
    public function hostname(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || ! $host || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || isset($parts['query']) || (($parts['port'] ?? 443) !== 443)
            || filter_var($host, FILTER_VALIDATE_IP)
            || ! in_array($host, array_map('strtolower', config('catalog.feed_hosts', [])), true)) {
            throw ValidationException::withMessages(['feed_url' => 'Use an approved HTTPS store feed URL without query, credentials or fragment.']);
        }

        return $host;
    }

    public function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! $ip) {
                continue;
            }
            if (! $this->isPublicAddress($ip)) {
                throw new RuntimeException('Feed resolves to an unsafe address.');
            }
            $ips[] = $ip;
        }
        if (! $ips) {
            throw new RuntimeException('Feed DNS lookup failed.');
        }

        return array_values(array_unique($ips));
    }

    public function isPublicAddress(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (str_contains($ip, ':')) {
            // Only global unicast IPv6; excludes mapped IPv4, multicast and link-local.
            return (ord(inet_pton($ip)[0]) & 0xE0) === 0x20;
        }
        $parts = array_map('intval', explode('.', $ip));

        return $parts[0] > 0 && $parts[0] < 224
            && ! ($parts[0] === 100 && $parts[1] >= 64 && $parts[1] <= 127)
            && ! ($parts[0] === 198 && in_array($parts[1], [18, 19], true));
    }

    public function page(CatalogConnection $connection, ?string $cursor): array
    {
        $host = $this->hostname($connection->feed_url);
        $ip = $this->resolve($host)[0];
        if (! extension_loaded('curl')) {
            throw new RuntimeException('cURL is required for pinned feed connections.');
        }
        $pin = str_contains($ip, ':') ? '['.$ip.']' : $ip;
        $max = (int) config('catalog.max_response_bytes');
        $response = Http::acceptJson()->withToken($connection->feed_token)->connectTimeout(5)->timeout(20)
            ->withOptions([
                'allow_redirects' => false,
                'decode_content' => false,
                // Pin the validated IP to prevent DNS rebinding; disable environment proxies.
                'proxy' => '',
                'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$pin}"]],
                'progress' => function ($total, $downloaded) use ($max) {
                    if ($total > $max || $downloaded > $max) {
                        throw new RuntimeException('Feed response is too large.');
                    }
                },
            ])->get($connection->feed_url, $cursor === null ? [] : ['cursor' => $cursor, 'limit' => 100]);
        if (! $response->successful() || strlen($response->body()) > $max) {
            throw new RuntimeException('Feed request failed.');
        }
        $data = $response->json();
        if (! is_array($data) || ! isset($data['products']) || ! is_array($data['products']) || ! array_is_list($data['products'])
            || count($data['products']) > 100 || ! array_key_exists('next_cursor', $data)
            || ($data['next_cursor'] !== null && (! is_string($data['next_cursor']) || $data['next_cursor'] === '' || strlen($data['next_cursor']) > 200))) {
            throw new RuntimeException('Feed response does not match the catalog contract.');
        }

        return $data;
    }
}
