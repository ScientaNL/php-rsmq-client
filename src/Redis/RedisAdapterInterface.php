<?php

namespace Scienta\RSMQClient\Redis;

interface RedisAdapterInterface
{
    /**
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * @param callable $redisExecutor
     * @return array
     */
    public function transaction(callable $redisExecutor): array;

    /**
     * @param string $channel
     * @param string $message
     * @return int
     */
    public function publish(string $channel, string $message): int;

    /**
     * @return string[]|self
     */
    public function time();

    /**
     * @param string $key
     * @param string $hashKey
     * @param int $value
     * @return int|self
     */
    public function hIncrBy(string $key, string $hashKey, int $value);

    /**
     * @param string $key
     * @param array $hashKeys
     * @return array|self
     */
    public function hMGet(string $key, array $hashKeys);

    /**
     * @param string $key
     * @param string $hashKey
     * @param string $value
     * @return bool|int|self
     */
    public function hSet(string $key, string $hashKey, string $value);

    /**
     * @param string $key
     * @param string $hashKey
     * @param string $value
     * @return array|self
     */
    public function hSetNx(string $key, string $hashKey, string $value);

    /**
     * @param string $key
     * @param string ...$values
     * @return int|self
     */
    public function sAdd(string $key, string ...$values);

    /**
     * @param string $key
     * @param mixed ...$args
     * @return int|self
     */
    public function zAdd(string $key, ...$args);

    /**
     * @param string $key
     * @return int|self
     */
    public function zCard(string $key);
}
