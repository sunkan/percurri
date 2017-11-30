<?php

namespace Percurri;

use Percurri\Exception\ConnectionException;
use Percurri\Exception\MissingConnectionException;
use Percurri\Exception\ReadException;
use Percurri\Exception\ReadTimeoutException;
use Percurri\Exception\WriteException;

class Connection
{
    /**
     * The connection time
     * @var int
     */
    protected $connectionTime;

    /**
     * The current connection resource handle (if any).
     * @var resource
     */
    protected $socket;

    /**
     * @var bool
     */
    protected $persistent = true;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * Timeout in seconds when establishing the connection.
     * @var int
     */
    protected $timeout;

    /**
     * Create connection object
     *
     * @param string $host
     * @param int $port
     * @param bool $persistent `persistent` is using the recommended setting from the Beanstalkd FAQ
     * @param int $timeout
     */
    public function __construct(string $host, int $port = 11300, bool $persistent = true, int $timeout = 1)
    {
        $this->host = $host;
        $this->port = $port;
        $this->persistent = $persistent;
        $this->timeout = $timeout;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Create connection to Beanstalkd server
     *
     * If connection is successful we set the read timeout to -1
     * so we can listen for data on socket. Needed for blocking reads
     *
     * @return bool
     * @throws ConnectionException
     */
    public function connect(): bool
    {
        if (isset($this->socket)) {
            $this->disconnect();
        }
        if ($this->persistent) {
            $this->socket = @pfsockopen($this->host, $this->port, $errNum, $errStr, $this->timeout);
        } else {
            $this->socket = @fsockopen($this->host, $this->port, $errNum, $errStr, $this->timeout);
        }
        if (!empty($errNum) || !empty($errStr)) {
            throw new ConnectionException($errStr, $errNum);
        }
        if ($this->isConnected()) {
            stream_set_timeout($this->socket, -1);
            $this->connectionTime = time();
        }
        return $this->isConnected();
    }

    /**
     * Close connection to Beanstalkd server
     *
     * Sends a proper `quit` command to server then closes the connection
     *
     * @return bool
     */
    public function disconnect(): bool
    {
        if (!$this->isConnected()) {
            return true;
        }

        $this->write('quit');
        $response = fclose($this->socket);
        $this->socket = null;
        $this->connectionTime = 0;

        return $response;
    }

    /**
     * Check if connection is active
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return is_resource($this->socket);
    }

    /**
     * Read data from connection
     *
     * Auto connected if no connection is established
     *
     * @param int $length Buffer length. Default is 16384
     * @return string Raw data returned from socket
     * @throws MissingConnectionException
     * @throws ReadTimeoutException
     */
    public function read(int $length = null): string
    {
        if (!$this->isConnected()) {
            if ($this->connectionTime) {
                throw new ConnectionException('Missing connection when reading data from socket');
            }
            $this->connect();
        }

        if ($length) {
            if (feof($this->socket)) {
                return false;
            }
            $data = stream_get_contents($this->socket, $length + 2);
            $meta = stream_get_meta_data($this->socket);
            if ($meta['timed_out']) {
                throw new ReadTimeoutException('Connection timed out');
            }
        } else {
            $data = stream_get_line($this->socket, 16384, "\r\n");
        }

        if ($data === false) {
            throw new ReadException("Failed to read socket");
        }

        return rtrim($data, "\r\n");
    }

    /**
     * Write data to connection
     *
     * Auto connected if no connection is established
     *
     * @param string $command
     * @param array $payload
     * @param string $format    Format to be used in sprintf the payload
     * @return int Length of written data
     */
    public function write(string $command, array $payload = [], string $format = null): int
    {
        if (!$this->isConnected()) {
            if ($this->connectionTime) {
                throw new MissingConnectionException('Writing data to socket');
            }
            $this->connect();
        }
        if ($format) {
            $data = vsprintf($format, $payload);
        } else {
            $data = implode(' ', $payload);
        }

        $payload = $command . ($data ? ' ' . $data : '');

        $payload .= "\r\n";
        $length = fwrite($this->socket, $payload, strlen($payload));

        if ($length === false) {
            throw new WriteException("Failed write to socket");
        }

        return $length;
    }
}
