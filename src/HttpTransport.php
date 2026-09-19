<?php

namespace Eduskit;

class HttpTransport
{
    public string $lastTraceId = '';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $source,
        private readonly int $timeoutMs = 10000,
        private readonly string $lang = 'zh-CN',
        private readonly mixed $fetch = null,
    ) {
    }

    public function request(string $method, string $path, mixed $body = null): mixed
    {
        $traceId = bin2hex(random_bytes(8));
        $url = rtrim($this->baseUrl, '/') . $path;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-app-key: ' . $this->appKey,
            'x-app-secret: ' . $this->appSecret,
            'x-lang: ' . $this->lang,
            'x-trace-id: ' . $traceId,
        ];
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
        if (is_callable($this->fetch)) {
            [$status, $responseHeaders, $raw] = ($this->fetch)($url, $method, $headers, $payload, $this->timeoutMs);
        } else {
            [$status, $responseHeaders, $raw] = $this->curl($url, $method, $headers, $payload);
        }

        $data = $raw === '' ? [] : json_decode($raw, true);
        if ($raw !== '' && !is_array($data)) {
            throw new EduskitError($raw, $status, 'SDK_INVALID_JSON', $traceId, $path, $this->source);
        }
        $headerTrace = $responseHeaders['x-trace-id'] ?? $traceId;
        $this->lastTraceId = (string) ($data['traceId'] ?? $headerTrace);
        $code = $data['code'] ?? 0;
        if ($status >= 400 || $code !== 0) {
            throw new EduskitError(
                (string) ($data['message'] ?? ('HTTP ' . $status)),
                $status,
                (string) ($data['errorCode'] ?? ('HTTP_' . $status)),
                $this->lastTraceId,
                (string) ($data['path'] ?? $path),
                $this->source,
            );
        }
        return $data['data'] ?? $data;
    }

    private function curl(string $url, string $method, array $headers, ?string $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new EduskitError($err, null, 'SDK_NETWORK_ERROR', null, $url, $this->source);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        $parsed = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $parsed[strtolower(trim($k))] = trim($v);
            }
        }
        return [$status, $parsed, $body];
    }
}
