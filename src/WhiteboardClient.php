<?php

namespace Eduskit;

class WhiteboardClient
{
    public object $rooms;
    public object $auth;
    public object $recordings;
    public object $captures;
    public object $files;

    public function __construct(private readonly HttpTransport $http, string $appId, string $appSecret)
    {
        $this->rooms = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function schedulePrivateRoomWrites(string $roomId, string $requestId, string $opensAt, string $closesAt): mixed { return $this->http->request('POST', '/v1/rooms/private/write-window', ['roomId' => $roomId, 'requestId' => $requestId, 'opensAt' => $opensAt, 'closesAt' => $closesAt]); }
            public function initializePrivateWorkspace(string $roomId, string $assignmentId, ?string $sourceSnapshotId): mixed { return $this->http->request('POST', '/v1/rooms/private/initializations', ['roomId' => $roomId, 'assignmentId' => $assignmentId, 'sourceSnapshotId' => $sourceSnapshotId]); }
            public function getPrivateWorkspaceInitialization(string $roomId): mixed { return $this->http->request('POST', '/v1/rooms/private/initializations/query', ['roomId' => $roomId]); }
            public function provisionPrivateRoom(string $roomId, string $assignmentId): mixed { return $this->http->request('POST', '/v1/rooms/private', ['roomId' => $roomId , 'assignmentId' => $assignmentId]); }
            public function changePrivateRoomGrant(string $roomId, array $input): mixed { return $this->http->request('POST', '/v1/rooms/private/grants', array_replace($input, ['roomId' => $roomId])); }
            public function getPrivateRoomAccess(string $roomId, string $userId): mixed { return $this->http->request('POST', '/v1/rooms/private/access/query', ['roomId' => $roomId , 'userId' => $userId]); }
            public function issuePrivateRoomToken(string $roomId, array $input): mixed { return $this->http->request('POST', '/v1/rooms/private/token', array_replace($input, ['roomId' => $roomId])); }
            public function sealPrivateRoom(string $roomId): mixed { return $this->http->request('POST', '/v1/rooms/private/seal', ['roomId' => $roomId]); }
            public function createFrozenSnapshot(string $roomId, string $snapshotId): mixed { return $this->http->request('POST', '/v1/rooms/private/snapshots', ['roomId' => $roomId , 'snapshotId' => $snapshotId]); }
            public function getFrozenSnapshot(string $roomId, string $snapshotId): mixed { return $this->http->request('POST', '/v1/rooms/private/snapshots/query', ['roomId' => $roomId , 'snapshotId' => $snapshotId]); }
            public function getFrozenSnapshotDownload(string $roomId, string $snapshotId): mixed { return $this->http->request('POST', '/v1/rooms/private/snapshots/download', ['roomId' => $roomId , 'snapshotId' => $snapshotId]); }
            public function attachCourseware(string $roomId, array $input): mixed { return $this->http->request('POST', '/v1/rooms/coursewares', array_replace($input, ['roomId' => $roomId])); }
        };
        $this->auth = new class($appId, $appSecret) {
            public function __construct(private string $appId, private string $appSecret) {}
            public function issueRoomToken(array $input): mixed { return TokenSigner::room($this->appId, $this->appSecret, $input); }
        };
        $this->recordings = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function start(string $roomId, array $input = []): mixed { return $this->http->request('POST', '/v1/rooms/recording/start', array_replace($input, ['roomId' => $roomId])); }
            public function stop(string $roomId, array $input): mixed { return $this->http->request('POST', '/v1/rooms/recording/stop', array_replace($input, ['roomId' => $roomId])); }
            public function list(string $roomId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/rooms/recordings', ['roomId' => $roomId])); }
            public function get(string $recordingId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/recordings', ['recordingId' => $recordingId])); }
            public function registerMediaAsset(string $recordingId, array $input): mixed { return $this->http->request('POST', '/v1/recordings/media-assets', array_replace($input, ['recordingId' => $recordingId])); }
            public function deleteMediaAsset(string $recordingId, string $assetId): mixed { return $this->http->request('DELETE', HttpTransport::withQuery('/v1/recordings/media-assets', ['recordingId' => $recordingId, 'assetId' => $assetId])); }
            public function enqueueVideoExport(string $recordingId, array $input = []): mixed { return $this->http->request('POST', '/v1/recordings/video-exports', array_replace($input, ['recordingId' => $recordingId])); }
            public function getVideoExport(string $recordingId, string $jobId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/recordings/video-exports', ['recordingId' => $recordingId, 'jobId' => $jobId])); }
        };
        $this->captures = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function create(string $roomId, array $input): mixed {
                $input['roomId'] = $roomId;
                return $this->http->request('POST', '/v1/rooms/captures', array_replace($input, ['roomId' => $roomId]));
            }
            public function list(string $roomId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/rooms/captures', ['roomId' => $roomId])); }
        };
        $this->files = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function convert(array $input): mixed { return $this->http->request('POST', '/v1/files/convert', $input); }
            public function getConvertJob(string $jobId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/files/convert', ['jobId' => $jobId])); }
        };
    }

    public function lastTraceId(): string
    {
        return $this->http->lastTraceId;
    }
}
