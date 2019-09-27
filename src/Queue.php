<?php

namespace Scienta\RSMQClient;

use Scienta\RSMQClient\Redis\RedisAdapterInterface;
use Scienta\RSMQClient\Exception\QueueException;

class Queue
{
    /** @var Config */
    protected $config;

    /** @var RedisAdapterInterface */
    protected $redisAdapter;

    /**
     * Synchronize object with Redis queue (@see syncWithRedis)
     *
     * Queue constructor.
     * @param Config $config
     * @param RedisAdapterInterface $redisAdapter
     * @param bool $allowQueueCreation
     * @throws QueueException
     */
    public function __construct(Config $config, RedisAdapterInterface $redisAdapter, $allowQueueCreation = true)
    {
        $this->config = $config;
        $this->redisAdapter = $redisAdapter;
        $this->syncWithRedis($allowQueueCreation);
    }

    /**
     * Finds the queue in Redis by name from $config and update its attributes or create new one.
     *
     * @param bool $allowQueueCreation
     * @throws QueueException
     */
    protected function syncWithRedis($allowQueueCreation): void
    {
        $hash = $this->redisAdapter->hMGet($this->config->getName(true).':Q', ['vt', 'delay', 'maxsize']);

        if (in_array(false, $hash, true)) {
            if ($allowQueueCreation) {
                $this->create();
            } else {
                throw new QueueException("Can\'t find queue with name {$this->config->getName(true)}");
            }
        } elseif (
            $this->config->getVt() !== (int)$hash['vt'] ||
            $this->config->getDelay() !== (int)$hash['delay'] ||
            $this->config->getMaxSize() !== (int)$hash['maxsize']
        ) {
            $this->config = new Config(
                $this->config->getName(),
                $this->config->getNamespace(),
                $this->config->isRealtime(),
                (int)$hash['vt'],
                (int)$hash['delay'],
                (int)$hash['maxsize']
            );
        }
    }

    /**
     * Creates a new queue.
     *
     * @throws QueueException
     */
    protected function create(): void
    {
        $time = $this->redisAdapter->time();
        $result = $this->redisAdapter->transaction(function (RedisAdapterInterface $redis) use ($time) {
            $queueKey = $this->config->getName(true) . ':Q';
            $redis->hSetNx($queueKey, 'vt', $this->config->getVt())
                ->hSetNx($queueKey, 'delay', $this->config->getDelay())
                ->hSetNx($queueKey, 'maxsize', $this->config->getMaxSize())
                ->hSetNx($queueKey, 'created', $time[0])
                ->hSetNx($queueKey, 'modified', $time[0])
                ->sAdd($this->config->namespaceKey('QUEUES'), $this->config->getName());
        });
        if (in_array(0, $result, true)) {
            throw new QueueException('Queue already exists');
        }
    }

    /**
     * @param Message $message
     * @return string $messageId
     * @throws QueueException
     */
    public function sendMessage(Message $message): string
    {
        if (!$message->checkSize($this->config->getMaxSize())) {
            throw new QueueException('Message too large to send');
        }

        /** @var string[] $time */
        $time = $this->redisAdapter->time();
        $messageId = null;
        $result = $this->redisAdapter->transaction(function (RedisAdapterInterface $redis) use ($time, $message, &$messageId) {
            // Make sure to always have correct 6digit millionth seconds from redis
            $ms = sprintf('%06d', $time[1]);
            $messageId = $message->generateUid($time[0] . $ms);
            $messageScore = $message->generateQueueScore(
                (int)($time[0] . substr($ms, 0, 3)),
                $this->config->getDelay()
            );
            $key = $this->config->getName(true);

            $redis->zAdd($key, $messageScore, $messageId)
                ->hSet($key.':Q', $messageId, $message->getMessage())
                ->hIncrBy($key.':Q', 'totalsent', 1);

            if ($this->config->isRealtime()) {
                $redis->zCard($key);
            }
        });

        if ($this->config->isRealtime()) {
            $this->redisAdapter->publish($this->config->getPublishKey(), $result[3]);
        }

        return $messageId;
    }
}
