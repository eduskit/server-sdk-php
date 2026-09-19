<?php

namespace Eduskit;

class ClassroomClient
{
    public object $users;
    public object $auth;
    public object $classrooms;
    public object $app;

    public function __construct(private readonly HttpTransport $http, string $appId, string $appSecret)
    {
        $this->users = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function register(array $input): mixed { return $this->http->request('POST', '/v1/users', $input); }
        };
        $this->auth = new class($appId, $appSecret) {
            public function __construct(private string $appId, private string $appSecret) {}
            public function issueToken(array $input): mixed { return TokenSigner::edu($this->appId, $this->appSecret, $input); }
        };
        $members = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function add(string $classroomId, array $input): mixed { return $this->http->request('POST', "/v1/classrooms/{$classroomId}/members", $input); }
            public function list(string $classroomId): mixed { return $this->http->request('GET', "/v1/classrooms/{$classroomId}/members"); }
            public function replaceStudents(string $classroomId, array $input): mixed { return $this->http->request('PUT', "/v1/classrooms/{$classroomId}/members/students", $input); }
        };
        $permissions = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function get(string $classroomId, string $eduUserId): mixed { return $this->http->request('GET', "/v1/classrooms/{$classroomId}/members/{$eduUserId}/permissions"); }
            public function set(string $classroomId, string $eduUserId, array $input): mixed { return $this->http->request('POST', "/v1/classrooms/{$classroomId}/members/{$eduUserId}/permissions", $input); }
            public function clear(string $classroomId, string $eduUserId, string $permission, array $input): mixed { return $this->http->request('DELETE', "/v1/classrooms/{$classroomId}/members/{$eduUserId}/permissions/{$permission}", $input); }
        };
        $coursewares = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function list(string $classroomId): mixed { return $this->http->request('GET', "/v1/classrooms/{$classroomId}/coursewares"); }
            public function bind(string $classroomId, array $input): mixed { return $this->http->request('POST', "/v1/classrooms/{$classroomId}/coursewares", $input); }
            public function unbind(string $classroomId, array $input): mixed { return $this->http->request('DELETE', "/v1/classrooms/{$classroomId}/coursewares", $input); }
        };
        $this->classrooms = new class($http, $members, $permissions, $coursewares) {
            public function __construct(private HttpTransport $http, public object $members, public object $permissions, public object $coursewares) {}
            public function create(array $input): mixed { return $this->http->request('POST', '/v1/classrooms', $input); }
            public function start(string $classroomId): mixed { return $this->http->request('POST', "/v1/classrooms/{$classroomId}/start"); }
            public function end(string $classroomId): mixed { return $this->http->request('POST', "/v1/classrooms/{$classroomId}/end"); }
        };
        $this->app = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function getUiConfig(): mixed { return $this->http->request('GET', '/v1/app/ui-config'); }
            public function setUiConfig(array $input): mixed { return $this->http->request('PUT', '/v1/app/ui-config', $input); }
        };
    }

    public function lastTraceId(): string
    {
        return $this->http->lastTraceId;
    }
}
