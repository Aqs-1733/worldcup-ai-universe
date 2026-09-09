<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class HttpClient
{
    /** @param array<string, string> $headers */
    public function get(string $url, array $headers = [], int $timeout = 20): string
    {
        return $this->request('GET', $url, null, $headers, $timeout);
    }

    /** @param array<string, string> $headers */
    public function postJson(string $url, array $payload, array $headers = [], int $timeout = 60): array
    {
        $body = $this->request('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), array_merge([
            'Content-Type' => 'application/json',
        ], $headers), $timeout);
        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException('Remote service did not return JSON.');
        }
        return $json;
    }

    /** @param array<string, string> $headers */
    private function request(string $method, string $url, ?string $body, array $headers, int $timeout): string
    {
        $headerLines = [];
        $headers['User-Agent'] ??= (string) env('SYNC_USER_AGENT', 'worldcup-ai-universe/1.0');
        foreach ($headers as $key => $value) {
            $headerLines[] = "{$key}: {$value}";
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headerLines,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $proxy = $this->proxyFor($url);
            if ($proxy) {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
            }
            $resolve = $this->resolveOverride($url);
            if ($resolve) {
                curl_setopt($ch, CURLOPT_RESOLVE, [$resolve]);
            }
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false || $status >= 400) {
                throw new RuntimeException("HTTP {$method} {$url} failed: status={$status} {$error}");
            }
            return (string) $response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
                'timeout' => $timeout,
                'ignore_errors' => false,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException("HTTP {$method} {$url} failed.");
        }
        return $response;
    }

    private function proxyFor(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $noProxy = array_filter(array_map('trim', explode(',', (string) env('NO_PROXY', ''))));
        foreach ($noProxy as $domain) {
            if ($domain !== '' && str_contains($host, $domain)) {
                return null;
            }
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return (string) env($scheme === 'https' ? 'HTTPS_PROXY' : 'HTTP_PROXY', '') ?: null;
    }

    private function resolveOverride(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if ($host !== 'ark.cn-beijing.volces.com') {
            return null;
        }

        $ip = trim((string) env('ARK_DNS_FALLBACK_IP', ''));
        if ($ip === '') {
            return null;
        }

        return "{$host}:443:{$ip}";
    }
}
