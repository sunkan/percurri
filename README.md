# Percurri

## Installation


The preferred method of installing this library is with
[Composer](https://getcomposer.org/) by running the following from your project
root:

    $ composer require sunkan/percurri

## Using

### Simple example

```php
$connection = new Percurri\Connection($host, $port);
$logger = new Psr3Logger();

$workerClient = new Percurri\WorkerClient($connection, $logger);
$producerClient = new Percurri\ProducerClient($connection, $logger);

$data = 'test data';
$tube = 'test-tube';

$producerClient->tube($tube)->put($data);

$workerClient->watch($tube);
$job = $workerClient->reserve();

//do job

//then delete from beanstalkd
$workerClient->delete($job);

```


## Api

### Connection

#### Constructor

```
Connection(string $host, int $port = 11300, bool $persistent = true, int $timeout = 1)
```

#### Connecting related functions
```
connect(): bool
disconnect(): bool
isConnected(): bool
```

#### Read from socket. 

If length is specified reads that amount from buffer otherwise looks for first newline
Returns raw string from buffer
```
read($length = null): string
```

#### Write to socket

You can specify a format  if you want a payload formatted a specific way. 
Like this:
`write('put', $payload, "%d %d %d %d\r\n%s");`


```
write(string $command, array $payload, string $format = null): int
```

### Producer client

#### Constructor

Logger must be an instance of a Psr3 compatible logger

```
__construct(Connection $connection, LoggerInterface $logger)
```

#### Put
Priority is specified with `0` being most important and `4294967295` least important

If no tube have been selected it puts job in to `default` tube
```
put(string $data, int $pri = 100, int $delay = 0, int $ttr = 30): int
```

#### Select tube
```
tube(string $tube): self
```

#### Get current tube
```
currentTube(): string|bool
```

#### Pause tube

Prevent workers from reserving any new job in tube for `delay` seconds

```
pause(string $tube, int $delay): bool
```

### Worker client

#### Constructor

Logger must be an instance of a Psr3 compatible logger

```
__construct(Connection $connection, LoggerInterface $logger, Factory $jobFactory, DecoderInterface $decoder)
```

#### Watch tube
Select tubes to watch.
Returns number of tubes in watch list

```
watch(string $tube): int
```

#### Ignore tube
Remove tube from watch list
```
ignore(string $tube): int|false
```

#### List tubes watched
```
listTubes(): array
```


#### Reserve job
If timeout is specified will only wait that long for a job 

```
reserve(int $timeout = null): JobInterface
```

#### Delete job

`idOrJob` can either be an instance of `JobInterface` or an `integer`

```
delete($idOrJob): bool
```

#### Release job

Puts a reserved job back into the ready queue.

`idOrJob` can either be an instance of `JobInterface` or an `integer`

```
release($idOrJob, int $pri, int $delay): bool
```

#### Bury job
`idOrJob` can either be an instance of `JobInterface` or an `integer`
```
bury($idOrJob, int $pri): bool
```

#### Touch job
Worker request more time to work on job

`idOrJob` can either be an instance of `JobInterface` or an `integer`
```
touch($idOrJob, int $pri): bool
```

#### Peek job
Look at job but don't reserves it

`idOrJob` can either be an instance of `JobInterface` or an `integer`

```
peek($idOrJob): JobInterface
```

#### Peek ready queue
```
peekReady(): JobInterface
```

#### Peek delayed job
```
peekDelayed(): JobInterface
```

#### Peek buried queue
```
peekBuried(): JobInterface
```

#### Kick job from buried queue

`bound` number of jobs to kick into ready queue

```
kick(int $bound): int
```


#### Kick job
Kick job into ready queue

`idOrJob` can either be an instance of `JobInterface` or an `integer`

```
kickJob($idOrJob): JobInterface
```

### Stats client

#### Constructor

Logger must be an instance of a Psr3 compatible logger

```
__construct(Connection $connection, LoggerInterface $logger, DecoderInterface $decoder)
```

#### Stats about job
`idOrJob` can either be an instance of `JobInterface` or an `integer`
```
statsJob($idOrJob): array
```

#### Stats about tube
```
statsTube(string $tube): array
```

#### Stats about system
```
stats(): array
```

#### List available tubes
```
listTubes(): array
```
