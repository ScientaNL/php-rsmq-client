<?php

namespace Scienta\RSMQClientTests;

use PHPUnit\Framework\TestCase;
use Scienta\RSMQClient\Config;
use Scienta\RSMQClient\Exception\QueueException;
use Scienta\RSMQClient\Message;
use Scienta\RSMQClient\Queue;
use Scienta\RSMQClientTests\Mocks\RedisMock;

class QueueTest extends TestCase
{
    public function getMockPhpredis(string ...$methods)
    {
        $mockRedis = $this->getMockBuilder('Redis')
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(empty($methods) ? null : $methods)
            ->getMock();

        return $mockRedis;
    }

    public function getMockAdapter(array $mockMethods, array $proxyMethods = [], $redisClient = null)
    {
        $mockRedisAdapter = $this->getMockBuilder(RedisMock::class)
            ->setConstructorArgs([$redisClient])
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethodsExcept($proxyMethods)
            ->getMock();

        $objectMethods  = preg_grep('/^[^_]+/', get_class_methods(RedisMock::class));
        $mockedMethods = array_diff($objectMethods, $proxyMethods);
        foreach (array_diff($mockedMethods, $mockMethods) as $method) {
            $mockRedisAdapter->expects($this->never())
                ->method($method);
        }

        return $mockRedisAdapter;
    }

    /**
     * @throws \ReflectionException
     * @throws QueueException
     */
    public function testCreateQueueExists(): void
    {
        $configData = [
            'vt' => 50,
            'delay' => 10,
            'maxsize' => 1024
        ];

        $mockRedisAdapter = $this->getMockAdapter(['hMGet']);
        $mockRedisAdapter->expects($this->once())
            ->method('hMGet')
            ->willReturn($configData);

        $queueConfig = new Config('test',
            'nsTest',
            true,
            $configData['vt'],
            $configData['delay'],
            $configData['maxsize']
        );
        $queue = new Queue($queueConfig, $mockRedisAdapter);
        $reflector = new \ReflectionProperty(Queue::class, 'config');
        $reflector->setAccessible(true);
        /** @var Config $newConfig */
        $reflectedConfig = $reflector->getValue($queue);

        $this->assertSame($reflectedConfig, $queueConfig);
    }

    /**
     * @throws \ReflectionException
     * @throws QueueException
     */
    public function testCreateSyncQueue(): void
    {
        $configData = [
            'vt' => 50,
            'delay' => 10,
            'maxsize' => 1024
        ];

        $mockRedisAdapter = $this->getMockAdapter(['hMGet']);
        $mockRedisAdapter->expects($this->once())
            ->method('hMGet')
            ->willReturn($configData);

        $queue = new Queue(new Config('test', 'nsTest'), $mockRedisAdapter);
        $reflector = new \ReflectionProperty(Queue::class, 'config');
        $reflector->setAccessible(true);
        /** @var Config $newConfig */
        $reflectedConfig = $reflector->getValue($queue);

        $this->assertSame($reflectedConfig->getVt(), $configData['vt']);
        $this->assertSame($reflectedConfig->getDelay(), $configData['delay']);
        $this->assertSame($reflectedConfig->getMaxSize(), $configData['maxsize']);
    }

    /**
     * @expectedException \Scienta\RSMQClient\Exception\QueueException
     * @throws QueueException
     */
    public function testCreateNewQueueDissalowed(): void
    {
        $mockRedisAdapter = $this->getMockAdapter(['hMGet']);
        $mockRedisAdapter->expects($this->once())
            ->method('hMGet')
            ->willReturn([false, false, false]);

        new Queue(new Config('testQ', 'nsTest'), $mockRedisAdapter, false);
    }

    /**
     * @throws QueueException
     */
    public function testCreateNewQueue(): void
    {
        $queueName = 'test';
        $vt        = 550;
        $delay     = 110;
        $maxSize   = 2048;
        $time      = ['1519053999', '494416'];
        $namespacer = $this->getQueueNamespacer('nsTest');
        $qConfig = new Config($queueName, 'nsTest', true, $vt, $delay, $maxSize);

        $mockRedis = $this->getMockPhpredis('multi', 'exec');

        $mockRedis->expects($this->once())
            ->method('exec')
            ->willReturn([1,1,1,1,1,1]);

        $mockRedis->expects($this->once())
            ->method('multi')
            ->willReturn($mockRedis);

        $mockRedisAdapter = $this->getMockAdapter([
            'hMGet',
            'time',
            'createTransactionAdapter',
            'hSetNx',
            'sAdd',
        ], ['transaction'], $mockRedis);

        $mockRedisAdapter->expects($this->once())
            ->method('hMGet')
            ->with($namespacer($queueName, true), ['vt', 'delay', 'maxsize'])
            ->willReturn([false, false, false]);

        $mockRedisAdapter->expects($this->once())
            ->method('time')
            ->willReturn($time);

        $mockRedisAdapter->expects($this->once())
            ->method('createTransactionAdapter')
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->exactly(5))
            ->method('hSetNx')
            ->withConsecutive(
                [$this->equalTo($namespacer($queueName, true)), $this->equalTo('vt'), $this->equalTo($vt)],
                [$this->equalTo($namespacer($queueName, true)), $this->equalTo('delay'), $this->equalTo($delay)],
                [$this->equalTo($namespacer($queueName, true)), $this->equalTo('maxsize'), $this->equalTo($maxSize)],
                [$this->equalTo($namespacer($queueName, true)), $this->equalTo('created'), $this->equalTo($time[0])],
                [$this->equalTo($namespacer($queueName, true)), $this->equalTo('modified'), $this->equalTo($time[0])]
            )
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->once())
            ->method('sAdd')
            ->with($this->equalTo($namespacer('QUEUES')), $queueName)
            ->will($this->returnSelf());

        new Queue($qConfig, $mockRedisAdapter, true);
    }

    /**
     * @throws QueueException
     */
    public function testSendPublishMessage(): void
    {
        $queueName = 'test';
        $vt        = 65;
        $delay     = 70;
        $maxSize   = 1536;
        $message   = 'fooBar';
        $namespacer = $this->getQueueNamespacer('nsTest');
        $qConfig = new Config($queueName, 'nsTest', true, $vt, $delay, $maxSize);

        $mockRedis = $this->getMockPhpredis('multi', 'exec');

        $mockRedis->expects($this->once())
            ->method('exec')
            ->willReturn([1,1,1,1]);

        $mockRedis->expects($this->once())
            ->method('multi')
            ->willReturn($mockRedis);

        $mockRedisAdapter = $this->getMockAdapter([
            'hMGet',
            'time',
            'createTransactionAdapter',
            'zAdd',
            'hSet',
            'hIncrBy',
            'zCard',
            'publish',
        ], ['transaction'], $mockRedis);

        $mockRedisAdapter->expects($this->once())
            ->method('hMGet')
            ->with($namespacer($queueName, true), ['vt', 'delay', 'maxsize'])
            ->willReturn(['vt' => $vt, 'delay' => $delay, 'maxsize' => $maxSize]);

        $mockRedisAdapter->expects($this->once())
            ->method('time')
            ->willReturn(['1506053999', '291216']);

        $mockRedisAdapter->expects($this->once())
            ->method('createTransactionAdapter')
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->once())
            ->method('zAdd')
            ->with($this->equalTo($namespacer($queueName, false)), $this->anything(), $this->anything())
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->once())
            ->method('hSet')
            ->with($this->equalTo($namespacer($queueName, true)), $this->anything(), $this->equalTo($message))
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->once())
            ->method('hIncrBy')
            ->with($this->equalTo($namespacer($queueName, true)), $this->equalTo('totalsent'), $this->equalTo(1))
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->once())
            ->method('zCard')
            ->with($this->equalTo($namespacer($queueName, false)))
            ->will($this->returnSelf());

        $mockRedisAdapter->expects($this->once())
            ->method('publish')
            ->with($this->equalTo($namespacer("rt:$queueName", false)), $this->equalTo(1))
            ->willReturn(1);

        $queue = new Queue($qConfig, $mockRedisAdapter, true);
        $queue->sendMessage(new Message($message));
    }

    /**
     * @param $namespace
     * @return callable
     */
    protected function getQueueNamespacer(string $namespace): callable
    {
        return static function ($queueName, $addQ = false) use ($namespace) {
            return "{$namespace}:{$queueName}" . ($addQ ? ':Q' : '');
        };
    }
}
