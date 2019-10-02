[![Latest Stable Version](https://poser.pugx.org/scienta/php-rsmq-client/v/stable?format=flat)](https://packagist.org/packages/scienta/php-rsmq-client)
[![Total Downloads](https://poser.pugx.org/syslogic/php-rsmq-client/downloads?format=flat)](https://packagist.org/packages/syslogic/php-rsmq-client/stats)
[![License](https://poser.pugx.org/scienta/php-rsmq-client/license)](https://packagist.org/packages/scienta/php-rsmq-client)


# php-rsmq-client
A library for queueing [RSMQ](https://www.npmjs.com/package/rsmq) messages in [Redis](https://redis.io/).


### TL;DR
A php implementation of the enqueue-code from [RSMQ](https://www.npmjs.com/package/rsmq) for adding messages to the queue.
Supports [realtime](https://www.npmjs.com/package/rsmq#realtime) PUBLISH of new messages.


Installation
------------
The recommended way to install php-rsmq-client is through [Composer](https://getcomposer.org/).
Add the following dependency to your composer.json
```json
{
	"require": {
		"scienta/php-rsmq-client": "~1.0"
	}
}
```
Alternatively, you can download the [source code as a file](https://github.com/ScientaNL/php-rsqm-client/releases) and extract it.


Usage
-----

Configuration for queues and messages can be more elaborate than specified below, all [RSMQ](https://www.npmjs.com/package/rsmq) options are supported.
The library makes use of a Redis adapter make the usage of other php redis clients possible.
Default (used in this example) is the [phpredis](https://github.com/phpredis/phpredis) C extension.

### Creating a basic queue
```php
use Scienta\RSMQClient\Config;
use Scienta\RSMQClient\Message;
use Scienta\RSMQClient\Queue;
use Scienta\RSMQClient\Redis\RedisAdapter;

//Create a redis connection
$redis = new \Redis();
$redis->connect('127.0.0.1', '6379');

//Configure and create/sync a queue
$config = new Config('myqueue');
$redisAdapter = new RedisAdapter($redis);
$queue = new Queue($config, $redisAdapter);

//Create a message
$message = new Message('Hello World');

//Send the message
$sentId = $queue->sendMessage($message);
```
