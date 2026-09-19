<?php

namespace Eduskit;

class Eduskit
{
    private ?ClassroomClient $client = null;
    private ?WhiteboardClient $whiteboardClient = null;

    public function __construct(array $options = [])
    {
        $timeout = $options['timeoutMs'] ?? 10000;
        $lang = $options['lang'] ?? 'zh-CN';
        $fetch = $options['fetch'] ?? null;
        if (isset($options['client'])) {
            $c = $options['client'];
            $this->client = new ClassroomClient(new HttpTransport($c['baseUrl'], $c['appKey'], $c['appSecret'], 'classroom', $timeout, $lang, $fetch), $c['appId'], $c['appSecret']);
        }
        if (isset($options['whiteboardClient'])) {
            $c = $options['whiteboardClient'];
            $this->whiteboardClient = new WhiteboardClient(new HttpTransport($c['baseUrl'], $c['appKey'], $c['appSecret'], 'whiteboard', $timeout, $lang, $fetch), $c['appId'], $c['appSecret']);
        }
    }

    public function __get(string $name): object
    {
        if ($name === 'client') {
            if ($this->client === null) {
                throw new EduskitError('classroom client is not configured', null, 'SDK_CLIENT_NOT_CONFIGURED', null, null, 'classroom');
            }
            return $this->client;
        }
        if ($name === 'whiteboardClient') {
            if ($this->whiteboardClient === null) {
                throw new EduskitError('whiteboard client is not configured', null, 'SDK_CLIENT_NOT_CONFIGURED', null, null, 'whiteboard');
            }
            return $this->whiteboardClient;
        }
        throw new EduskitError("unknown property {$name}", null, 'SDK_UNKNOWN_PROPERTY');
    }
}
