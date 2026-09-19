# eduskit/server-sdk-php

PHP 8.1+ B 端 Server SDK。`$sdk->client` 调课堂 server-api，`$sdk->whiteboardClient` 调白板 server-api。

方法名为 camelCase，JSON 字段 camelCase。无 Composer 运行时依赖（使用 curl）。

概念与方法表见 [docs/overview.md](docs/overview.md)、[docs/api.md](docs/api.md)。

## 安装

```bash
composer require eduskit/server-sdk-php
```

本仓库无 Composer 也可直接 `require` `src/*.php`：

```bash
php tests/EduskitTest.php
```

## 初始化

```php
use Eduskit\Eduskit;
use Eduskit\EduskitError;

$sdk = new Eduskit([
    'timeoutMs' => 10000,
    'lang' => 'zh-CN',
    'client' => [
        'baseUrl' => 'http://localhost:3112',
        'appId' => getenv('EDU_APP_ID'),
        'appKey' => getenv('EDU_APP_KEY'),
        'appSecret' => getenv('EDU_APP_SECRET'),
    ],
    'whiteboardClient' => [
        'baseUrl' => 'http://localhost:3012',
        'appId' => getenv('WB_APP_ID'),
        'appKey' => getenv('WB_APP_KEY'),
        'appSecret' => getenv('WB_APP_SECRET'),
    ],
]);
```

两侧均可选。测试可传入 `'fetch' => callable` 拦截 HTTP。

## 课堂 `$sdk->client`

```php
$user = $sdk->client->users->register([
    'originId' => 'stu_001',
    'nickname' => '小明',
    'avatar' => 'https://example.com/a.png',
]);
$token = $sdk->client->auth->issueToken(['eduUserId' => $user['eduUserId'], 'originId' => 'stu_001']);
$classroom = $sdk->client->classrooms->create([
    'name' => '一年级数学',
    'startsAt' => '2026-08-17T10:00:00.000Z',
    'endsAt' => '2026-08-17T11:00:00.000Z',
    'teacherEduUserId' => $user['eduUserId'],
]);
$id = $classroom['classroomId'];
$sdk->client->classrooms->start($id);
$sdk->client->classrooms->members->add($id, ['eduUserId' => $user['eduUserId'], 'role' => 'student']);
$sdk->client->classrooms->members->replaceStudents($id, ['eduUserIds' => [$user['eduUserId']]]);
$sdk->client->classrooms->permissions->set($id, $user['eduUserId'], [
    'permission' => 'camera',
    'effect' => 'grant',
    'operatorEduUserId' => $user['eduUserId'],
]);
$sdk->client->classrooms->coursewares->bind($id, ['coursewareIds' => ['cw_xxx']]);
$sdk->client->app->getUiConfig();
```

| 方法 | 说明 |
|------|------|
| `users->register` | 注册/更新 C 端用户 |
| `auth->issueToken` | 签发 C 端 accessToken |
| `classrooms->create/start/end` | 课堂生命周期 |
| `classrooms->members->add/list/replaceStudents` | 成员 |
| `classrooms->permissions->get/set/clear` | 权限 |
| `classrooms->coursewares->list/bind/unbind` | 课件 |
| `app->getUiConfig` / `setUiConfig` | App UI |

## 白板 `$sdk->whiteboardClient`

```php
$sdk->whiteboardClient->auth->issueRoomToken([
    'roomId' => 'room_1',
    'userId' => $user['eduUserId'],
    'role' => 'host',
    'expiresIn' => 3600,
]);
$session = $sdk->whiteboardClient->recordings->start('room_1', ['externalRef' => 'lesson_001']);
$sdk->whiteboardClient->recordings->enqueueVideoExport($session['recordingId'], ['profile' => 'hd']);
$sdk->whiteboardClient->files->convert(['sourceUrl' => 'https://cdn.example.com/lesson.pptx']);
$sdk->whiteboardClient->files->getConvertJob('job_xxx');
```

| 方法 | 说明 |
|------|------|
| `auth->issueRoomToken` | 签发 Room Token |
| `recordings->start/stop/list/get` | 录制 |
| `recordings->registerMediaAsset` / `deleteMediaAsset` | 媒体资产 |
| `recordings->enqueueVideoExport` / `getVideoExport` | 视频导出 |
| `captures->create/list` | 截图 |
| `files->convert` / `getConvertJob` | 转码 |

## 错误

```php
try {
    $sdk->client->auth->issueToken(['eduUserId' => '']);
} catch (EduskitError $e) {
    error_log($e->errorCode . ' ' . $e->status . ' ' . $e->traceId);
}
```

`$sdk->client->lastTraceId()` / `$sdk->whiteboardClient->lastTraceId()`。
