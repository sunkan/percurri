<?php

namespace Percurri;

use Percurri\Exception\ConnectionException;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    const FAIL_HOST = '10.0.0.1';
    const HOST = '127.0.0.1';
    const PORT = 11300;

    public function testConnect()
    {
        $connection = new Connection(self::HOST);

        $this->assertFalse($connection->isConnected());
        $connection->connect();
        $this->assertTrue($connection->isConnected());

        $this->assertTrue($connection->disconnect());
    }

    public function testReConnect()
    {
        $connection = new Connection(self::HOST);

        $this->assertFalse($connection->isConnected());
        $connection->connect();
        $this->assertTrue($connection->isConnected());
        $connection->connect();
        $this->assertTrue($connection->isConnected());
    }

    public function testConnectError()
    {
        $this->expectException(ConnectionException::class);
        $connection = new Connection(self::FAIL_HOST, self::PORT, false);
        $this->assertFalse($connection->isConnected());
        $connection->connect();
    }

    public function testAutoConnectAndWrite()
    {
        $connection = new Connection(self::HOST);
        $this->assertFalse($connection->isConnected());
        $response = $connection->write('ping');
        $this->assertTrue($connection->isConnected());
        $this->assertGreaterThan(4, $response);
    }

    public function testWriteWithFormat()
    {
        $connection = new Connection(self::HOST);
        $length = $connection->write('stats-tube', ['test'], '%s');
        $this->assertSame(17, $length);
    }

    public function testRead()
    {
        $connection = new Connection(self::HOST);
        $connection->write("list-tubes");
        $data = $connection->read();
        $this->assertStringStartsWith('OK ', $data);
    }
}
