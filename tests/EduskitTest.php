<?php

require_once __DIR__ . '/../src/EduskitError.php';
require_once __DIR__ . '/../src/HttpTransport.php';
require_once __DIR__ . '/../src/TokenSigner.php';
require_once __DIR__ . '/../src/ClassroomClient.php';
require_once __DIR__ . '/../src/WhiteboardClient.php';
require_once __DIR__ . '/../src/Eduskit.php';

use Eduskit\Eduskit;
use Eduskit\EduskitError;

class CallLog
{
    public array $calls = [];
}

function mockFetch(CallLog $log, array $body, int $status = 200): callable
{
    return function (string $url, string $method, array $headers, ?string $payload) use ($log, $body, $status) {
        $log->calls[] = [
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'body' => $payload === null ? null : json_decode($payload, true),
        ];
        return [$status, ['x-trace-id' => 'wb-trace'], json_encode($body)];
    };
}

function assertSame($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($message ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}

$log = new CallLog();
$sdk = new Eduskit([
    'client' => ['baseUrl' => 'http://edu.test', 'appId' => 'app_edu', 'appKey' => 'k', 'appSecret' => 'secret-edu'],
    'fetch' => mockFetch($log, ['code' => 0, 'data' => ['eduUserId' => 'eu_1'], 'traceId' => 'body-trace']),
]);
$user = $sdk->client->users->register(['nickname' => 'n', 'avatar' => 'https://a']);
assertSame('eu_1', $user['eduUserId']);
assertSame('body-trace', $sdk->client->lastTraceId());
assertSame('http://edu.test/v1/users', $log->calls[0]['url']);

$log = new CallLog();
$sdk = new Eduskit([
    'whiteboardClient' => ['baseUrl' => 'http://wb.test', 'appId' => 'app_wb', 'appKey' => 'wk', 'appSecret' => 'secret-wb'],
    'fetch' => mockFetch($log, ['code' => 0, 'data' => ['token' => 'rt']]),
]);
$token = $sdk->whiteboardClient->auth->issueRoomToken(['roomId' => 'r1', 'userId' => 'u1', 'role' => 'host']);
assertSame('app_wb', $token['appId']);
assertSame(3, count(explode('.', $token['token'])));
assertSame([], $log->calls);

$sdk = new Eduskit([
    'client' => ['baseUrl' => 'http://edu.test', 'appId' => 'app_edu', 'appKey' => 'k', 'appSecret' => 'secret-edu'],
    'fetch' => mockFetch(new CallLog(), ['code' => 404, 'errorCode' => 'EDU_USER_NOT_FOUND', 'message' => 'missing'], 404),
]);
try {
    $sdk->client->auth->issueToken(['eduUserId' => '']);
    throw new RuntimeException('expected error');
} catch (EduskitError $e) {
    assertSame('SDK_TOKEN_INPUT_INVALID', $e->errorCode);
}

$sdk = new Eduskit([
    'client' => ['baseUrl' => 'http://edu.test', 'appId' => 'app_edu', 'appKey' => 'k', 'appSecret' => 'secret-edu'],
]);
try {
    $sdk->whiteboardClient;
    throw new RuntimeException('expected missing whiteboard');
} catch (EduskitError $e) {
    assertSame('SDK_CLIENT_NOT_CONFIGURED', $e->errorCode);
}

echo "php tests passed\n";
