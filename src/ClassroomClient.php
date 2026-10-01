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
            public function add(string $classroomId, array $input): mixed { return $this->http->request('POST', '/v1/classrooms/members', array_replace($input, ['classroomId' => $classroomId])); }
            public function list(string $classroomId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/classrooms/members', ['classroomId' => $classroomId])); }
            public function replaceStudents(string $classroomId, array $input): mixed { return $this->http->request('PUT', '/v1/classrooms/members/students', array_replace($input, ['classroomId' => $classroomId])); }
        };
        $permissions = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function get(string $classroomId, string $eduUserId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/classrooms/members/permissions', ['classroomId' => $classroomId, 'eduUserId' => $eduUserId])); }
            public function set(string $classroomId, string $eduUserId, array $input): mixed { return $this->http->request('POST', '/v1/classrooms/members/permissions', array_replace($input, ['classroomId' => $classroomId, 'eduUserId' => $eduUserId])); }
            public function clear(string $classroomId, string $eduUserId, string $permission, array $input): mixed { return $this->http->request('DELETE', HttpTransport::withQuery('/v1/classrooms/members/permissions', ['classroomId' => $classroomId, 'eduUserId' => $eduUserId, 'permission' => $permission]), $input); }
        };
        $coursewares = new class($http) {
            public function __construct(private HttpTransport $http) {}
            public function list(string $classroomId): mixed { return $this->http->request('GET', HttpTransport::withQuery('/v1/classrooms/coursewares', ['classroomId' => $classroomId])); }
            public function bind(string $classroomId, array $input): mixed { return $this->http->request('POST', '/v1/classrooms/coursewares', array_replace($input, ['classroomId' => $classroomId])); }
            public function unbind(string $classroomId, array $input): mixed { return $this->http->request('DELETE', HttpTransport::withQuery('/v1/classrooms/coursewares', ['classroomId' => $classroomId]), $input); }
        };
        $this->classrooms = new class($http, $members, $permissions, $coursewares) {
            public function __construct(private HttpTransport $http, public object $members, public object $permissions, public object $coursewares) {}
            public function create(array $input): mixed { return $this->http->request('POST', '/v1/classrooms', $input); }
            public function start(string $classroomId): mixed { return $this->http->request('POST', '/v1/classrooms/start', array_replace([], ['classroomId' => $classroomId])); }
            public function end(string $classroomId): mixed { return $this->http->request('POST', '/v1/classrooms/end', array_replace([], ['classroomId' => $classroomId])); }
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
