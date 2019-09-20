<?php

namespace Scienta\RSMQClient;

class Message
{
    /**
     * @var string $message
     */
    protected $message;

    /**
     * @var int|null  $delay
     */
    protected $delay;

    public function __construct(string $message = null)
    {
        $this->setMessage($message);
    }

    /**
     * @param $message string
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param int|null $seconds
     * @throws \InvalidArgumentException
     */
    public function setDelay(int $seconds = null): void
    {
        if ($seconds !== null && ($seconds < 0 || $seconds > 9999999)) {
            throw new \InvalidArgumentException('Delay must be null or 0-9999999.');
        }
        $this->delay = $seconds;
    }

    /**
     * @return int|null
     */
    public function getDelay(): ?int
    {
        return $this->delay;
    }

    /**
     * @param int $maxSize
     * @return bool
     */
    public function checkSize(int $maxSize): bool
    {
        return strlen((string)$this->message) <= $maxSize;
    }

    /**
     * @param string $microTimeStamp
     * @return string
     * @throws \Exception
     */
    public function generateUid(string $microTimeStamp): string
    {
        $randString = '';
        $possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $randMax = strlen($possible) - 1;
        for ($i = 0; $i < 22; $i++) {
            $randString .= $possible[random_int(0, $randMax)];
        }

        return base_convert($microTimeStamp, 10, 36) . $randString;
    }

    /**
     * @param int $queueMsTimestamp
     * @param int $defaultDelay
     * @return string
     */
    public function generateQueueScore(int $queueMsTimestamp, int $defaultDelay = 0): string
    {
        $delayMs = ($this->delay ?? $defaultDelay) * 1000;
        return $queueMsTimestamp + $delayMs;
    }
}
