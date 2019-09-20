<?php

namespace Scienta\RSMQClientTests\Mocks;

use Scienta\RSMQClient\Redis\RedisAdapter;
use Scienta\RSMQClient\Redis\RedisAdapterInterface;

class RedisMock extends RedisAdapter
{
    public function __construct($redis = null)
    {
        $this->redis = $redis;
    }

    public function createTransactionAdapter($redis = null): RedisAdapterInterface
    {
        return $this;
    }
}
