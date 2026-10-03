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
$segment = explode('.', $token['token'])[1];
$claims = json_decode(base64_decode(strtr($segment, '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
assertSame(true, array_key_exists('access_generation', $claims));
assertSame(null, $claims['access_generation']);
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


$privateLog = new CallLog();
$privateSdk = new Eduskit([
    'whiteboardClient' => ['baseUrl' => 'http://wb.test', 'appId' => 'app_wb', 'appKey' => 'wk', 'appSecret' => 'ws'],
    'fetch' => mockFetch($privateLog, ['code' => 0, 'data' => ['marker' => 'server-result']]),
]);
$rooms = $privateSdk->whiteboardClient->rooms;
$outputs = [
    $rooms->provisionPrivateRoom('room_a', 'assignment_a'),
    $rooms->changePrivateRoomGrant('room_a', ['userId' => 'student_a', 'requestId' => 'grant_a', 'expectedGeneration' => '9223372036854775806', 'action' => 'grant', 'role' => 'participant']),
    $rooms->getPrivateRoomAccess('room_a', 'student_a'),
    $rooms->issuePrivateRoomToken('room_a', ['userId' => 'student_a', 'role' => 'participant']),
    $rooms->sealPrivateRoom('room_a'),
    $rooms->createFrozenSnapshot('room_a', 'snapshot_a'),
    $rooms->getFrozenSnapshot('room_a', 'snapshot_a'),
    $rooms->getFrozenSnapshotDownload('room_a', 'snapshot_a'),
    $rooms->initializePrivateWorkspace('room_a', 'assignment_a', null),
    $rooms->initializePrivateWorkspace('room_a', 'assignment_a', 'snapshot_a'),
    $rooms->getPrivateWorkspaceInitialization('room_a'),
    $rooms->schedulePrivateRoomWrites('room_a', 'window_a', '2026-10-02T00:00:00.000Z', '2026-10-02T00:10:00.000Z'),
];
$suffixes = ['', '/grants', '/access/query', '/token', '/seal', '/snapshots', '/snapshots/query', '/snapshots/download', '/initializations', '/initializations', '/initializations/query', '/write-window'];
assertSame(12, count($privateLog->calls));
foreach ($privateLog->calls as $index => $call) {
    assertSame('http://wb.test/v1/rooms/private' . $suffixes[$index], $call['url']);
    assertSame('POST', $call['method']);
    assertSame(true, in_array('x-app-key: wk', $call['headers'], true));
    assertSame('room_a', $call['body']['roomId']);
    assertSame(['marker' => 'server-result'], $outputs[$index]);
}
assertSame('9223372036854775806', $privateLog->calls[1]['body']['expectedGeneration']);
assertSame(false, array_key_exists('accessGeneration', $privateLog->calls[3]['body']));
assertSame(true, array_key_exists('sourceSnapshotId', $privateLog->calls[8]['body']));
assertSame(null, $privateLog->calls[8]['body']['sourceSnapshotId']);
assertSame('snapshot_a', $privateLog->calls[9]['body']['sourceSnapshotId']);
assertSame(['roomId' => 'room_a'], $privateLog->calls[10]['body']);

echo "php tests passed\n";

assertSame(['roomId'=>'room_a','requestId'=>'window_a','opensAt'=>'2026-10-02T00:00:00.000Z','closesAt'=>'2026-10-02T00:10:00.000Z'], $privateLog->calls[11]['body']);
