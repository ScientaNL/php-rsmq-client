<?php

namespace Scienta\RSMQClient;

class Config
{
    /** @var string */
    protected $redisNamespace;

    /** @var bool */
    protected $realtime;

    /** @var string $name Queue name. */
    protected $name;

    /** @var string $vt Visibility time. */
    protected $vt;

    /** @var string $delay Default message delay in seconds. */
    protected $delay;

    /** @var string $maxSize Maximal message size in bytes. */
    protected $maxSize;

    /**
     * Config constructor.
     * @param string $name
     * @param string $namespace
     * @param bool $realtime
     * @param int $vt
     * @param int $delay
     * @param int $maxSize
     */
    public function __construct(string $name, string $namespace = 'rsmq', bool $realtime = true, int $vt = 30, int $delay = 0, int $maxSize = 65536)
    {
        $this->realtime = $realtime;
        $this->setNamespace($namespace);
        $this->setName($name);
        $this->setVt($vt);
        $this->setDelay($delay);
        $this->setMaxSize($maxSize);
    }

    /**
     * @param string $key
     * @return string
     */
    public function namespaceKey(string $key): string
    {
        return "{$this->redisNamespace}:{$key}";
    }

    /**
     * Is the queue realtime
     *
     * @return bool
     */
    public function isRealtime(): bool
    {
        return $this->realtime;
    }

    /**
     * Maximum 160 characters; alphanumeric characters, hyphens (-), and underscores (_) are allowed.
     *
     * @param string $name
     * @throws \InvalidArgumentException
     */
    protected function setName(string $name): void
    {
        if (($name = trim($name)) === '') {
            throw new \InvalidArgumentException('The name can\'t be an empty string.');
        }
        if (strlen($name) > 160) {
            throw new \InvalidArgumentException('The maximum length of the name is 160 characters.');
        }
        if (!preg_match('/^([a-zA-Z0-9-_]+)$/', $name)) {
            throw new \InvalidArgumentException('Allowed name characters are alphanumeric, hyphens and underscores.');
        }
        $this->name = $name;
    }

    /**
     * @param bool $namespaced
     * @return string
     */
    public function getName(bool $namespaced = false): string
    {
        return $namespaced ? $this->namespaceKey($this->name) : $this->name;
    }

    /**
     * Maximum 160 characters; alphanumeric characters, hyphens (-), and underscores (_) are allowed.
     *
     * @param string $namespace
     * @throws \InvalidArgumentException
     */
    protected function setNamespace(string $namespace): void
    {
        if (($namespace = trim($namespace)) === '') {
            throw new \InvalidArgumentException('The name can\'t be an empty string.');
        }
        if (substr($namespace, -1) === ':') {
            throw new \InvalidArgumentException('The redis namespace can\'t end on a `:`');
        }
        $this->redisNamespace = $namespace;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->redisNamespace;
    }

    /**
     * @return string
     */
    public function getPublishKey(): string
    {
        return $this->redisNamespace . ':rt:' . $this->name;
    }

    /**
     * Set the length in seconds, that a message received from a queue will be invisible to
     * other receiving components when they ask to receive messages. Allowed values: 0-9999999.
     *
     * @param int $vt
     * @throws \InvalidArgumentException
     */
    protected function setVt(int $vt): void
    {
        if ($vt < 0 || $vt > 9999999) {
            throw new \InvalidArgumentException('Visibility is out of range!');
        }
        $this->vt = $vt;
    }

    /**
     * @return int
     */
    public function getVt(): int
    {
        return $this->vt;
    }

    /**
     * Set the time in seconds that the delivery of all new messages in the queue will be delayed.
     * Allowed values: 0-9999999.
     * @param int $delay
     * @throws \InvalidArgumentException
     */
    protected function setDelay(int $delay): void
    {
        if ($delay < 0 || $delay > 9999999) {
            throw new \InvalidArgumentException('Delay is out of range!');
        }
        $this->delay = $delay;
    }

    /**
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * Set the maximum message size in bytes. Allowed values: 1024-65536 and -1 (for unlimited size).
     * @param int $maxSize
     */
    protected function setMaxSize(int $maxSize): void
    {
        if ($maxSize !== -1 && ($maxSize < 1024 || $maxSize > 65536)) {
            var_dump($maxSize);
            throw new \InvalidArgumentException('Invalid max size!');
        }
        $this->maxSize = $maxSize;
    }

    /**
     * @return int
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}
