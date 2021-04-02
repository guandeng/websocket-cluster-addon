<?php

declare(strict_types=1);
/**
 * This file is part of websocket-cluster-addon.
 *
 * @link     https://github.com/friendofhyperf/websocket-cluster-addon
 * @document https://github.com/friendofhyperf/websocket-cluster-addon/blob/main/README.md
 * @contact  huangdijia@gmail.com
 * @license  https://github.com/friendofhyperf/websocket-cluster-addon/blob/main/LICENSE
 */
namespace FriendsOfHyperf\WebsocketClusterAddon\Provider;

use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

class RedisOnlineProvider implements OnlineProviderInterface
{
    /**
     * @var string
     */
    protected $redisPool = 'default';

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix = 'wssa:online';

    public function __construct(ContainerInterface $container)
    {
        $this->redis = $container->get(RedisFactory::class)->get($this->redisPool);
    }

    public function add(int $uid): void
    {
        $this->redis->sAdd($this->getKey(), $uid);
    }

    public function del(int $uid): void
    {
        $this->redis->sRem($this->getKey(), $uid);
    }

    public function get(int $uid): bool
    {
        return $this->redis->sIsMember($this->getKey(), $uid);
    }

    public function multiGet(array $uids): array
    {
        $uids = array_map('intval', $uids);
        $uids = array_filter($uids);
        $result = array_fill_keys($uids, 0);
        $tmpKey = $this->getTmpKey();

        try {
            // 临时集合
            $this->redis->sAdd($tmpKey, ...$uids);

            // 取交集
            $onlines = $this->redis->sInter($tmpKey, $this->getKey());

            // array_merge 有坑
            $result = array_replace($result, $onlines);
        } finally {
            $this->redis->del($tmpKey);
        }

        return $result;
    }

    protected function getTmpKey(): string
    {
        return join(':', [$this->prefix, uniqid()]);
    }

    protected function getKey(): string
    {
        return $this->prefix;
    }
}