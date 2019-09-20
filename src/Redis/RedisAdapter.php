<?php

namespace Scienta\RSMQClient\Redis;

class RedisAdapter implements RedisAdapterInterface
{
    /** @var \Redis */
    protected $redis;

    public function __construct(\Redis $redis)
    {
        if ($redis->getOption(\Redis::OPT_SERIALIZER) !== \Redis::SERIALIZER_NONE) {
            throw new \InvalidArgumentException('Redis must not serialize data for rsmq');
        }
        $this->redis = $redis;
    }

    public function isConnected(): bool
    {
        try {
            $this->redis->ping();
        } catch (\RedisException $e) {
            return false;
        }
        return true;
    }

    /**
     * @param string $channel
     * @param string $message
     * @return int
     */
    public function publish(string $channel, string $message): int
    {
        return $this->redis->publish($channel, $message);
    }

    /**
     * @param callable $redisExecutor
     * @return array
     */
    public function transaction(callable $redisExecutor): array
    {
        $redis = $this->redis->multi();
        $redisExecutor($this->createTransactionAdapter($redis));
        return $redis->exec();
    }

    /**
     * @return string[]|$this
     * @see \Redis::time()
     */
    public function time()
    {
        return $this->returnCommandResult($this->redis->time());
    }

    /**
     * @param string $key
     * @param string $hashKey
     * @param int $value
     * @return int|$this
     * @see \Redis::hIncrBy()
     */
    public function hIncrBy(string $key, string $hashKey, $value)
    {
        return $this->returnCommandResult($this->redis->hIncrBy($key, $hashKey, $value));
    }

    /**
     * @param string $key
     * @param array $hashKeys
     * @return array|$this
     * @see \Redis::hMGet()
     */
    public function hMGet(string $key, array $hashKeys)
    {
        return $this->returnCommandResult($this->redis->hMGet($key, $hashKeys));
    }

    /**
     * @param string $key
     * @param string $hashKey
     * @param string $value
     * @return bool|int|$this
     * @see \Redis::hSet()
     */
    public function hSet(string $key, string $hashKey, string $value)
    {
        return $this->returnCommandResult($this->redis->hSet($key, $hashKey, $value));
    }

    /**
     * @param string $key
     * @param string $hashKey
     * @param string $value
     * @return bool|$this
     * @see \Redis::hSetNx()
     */
    public function hSetNx(string $key, string $hashKey, string $value)
    {
        return $this->returnCommandResult($this->redis->hSetNx($key, $hashKey, $value));
    }

    /**
     * @param string $key
     * @param string ...$values
     * @return int|$this
     * @see \Redis::sAdd()
     */
    public function sAdd(string $key, string ...$values)
    {
        return $this->returnCommandResult($this->redis->sAdd($key, ...$values));
    }

    /**
     * @param string $key
     * @param mixed ...$args
     * @return int|$this
     * @see \Redis::zAdd()
     */
    public function zAdd(string $key, ...$args)
    {
        array_unshift($args, $key);
        return $this->returnCommandResult(call_user_func_array([$this->redis, 'zAdd'], $args));
    }

    /**
     * @param string $key
     * @return int|$this
     * @see \Redis::zCard()
     */
    public function zCard(string $key)
    {
        return $this->returnCommandResult($this->redis->zCard($key));
    }

    /**
     * @param \Redis $redis
     * @return $this
     */
    protected function createTransactionAdapter(\Redis $redis): RedisAdapterInterface
    {
        if ($redis->getMode() !== \Redis::MULTI) {
            throw new \InvalidArgumentException('Redis must be in multi-mode for transactions');
        }
        return new self($redis);
    }

    /**
     * @param $result \Redis|int|bool|string|array
     * @return $this|int|bool|string|array
     */
    protected function returnCommandResult($result)
    {
        if ($this->redis->getMode() === \Redis::MULTI) {
            return $this;
        }
        return $result;
    }
}
