<?php

namespace Percurri;

use Psr\Log\LoggerInterface;
use Percurri\Exception\CommandException;

class ProducerClient
{
    protected $connection;
    protected $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * The `put` command is for any process that wants to insert a job into the queue.
     *
     * @param string $data The job body.
     * @param integer $pri Jobs with smaller priority values will be scheduled
     *        before jobs with larger priorities. The most urgent priority is
     *        0; the least urgent priority is 4294967295.
     * @param integer $delay Seconds to wait before putting the job in the
     *        ready queue.  The job will be in the "delayed" state during this time.
     * @param integer $ttr Time to run - Number of seconds to allow a worker to
     *        run this job.  The minimum ttr is 1.
     * @return integer the job id or 0 if error
     */
    public function put(string $data, int $pri = 100, int $delay = 0, int $ttr = 30): int
    {
        $payload = [$pri, $delay, $ttr, strlen($data), $data];
        $this->connection->write('put', $payload, "%d %d %d %d\r\n%s");

        $status = strtok($this->connection->read(), ' ');
        switch ($status) {
            case 'INSERTED':
            case 'BURIED':
                return (integer) strtok(' '); // job id
            case 'EXPECTED_CRLF':
            case 'JOB_TOO_BIG':
            default:
                $this->logger->error($status);
                return 0;
        }
    }

    /**
     * The `use` command is for producers. Subsequent put commands will put
     * jobs into the tube specified by this command. If no use command has
     * been issued, jobs will be put into the tube named `default`.
     *
     * @param string $tube A name at most 200 bytes. It specifies the tube to
     *        use. If the tube does not exist, it will be created.
     * @return self
     */
    public function tube(string $tube): self
    {
        $this->connection->write('use', [$tube]);
        $status = strtok($this->connection->read(), ' ');
        if ($status === 'USING') {
            return $this;
        }
        $this->logger->error($status);
        throw new CommandException($status);
    }

    /**
     * Returns the tube currently being used by the producer.
     *
     * @return string|boolean `false` on error otherwise a string with the name of the tube.
     */
    public function currentTube()
    {
        $this->connection->write('list-tube-used');
        $status = strtok($this->connection->read(), ' ');
        if ($status === 'USING') {
            return strtok(' ');
        }
        $this->logger->error($status);
        return false;
    }

    /**
     * Pause a tube delaying any new job in it being reserved for a given time.
     *
     * @param string $tube The name of the tube to pause.
     * @param integer $delay Number of seconds to wait before reserving any more jobs from the queue.
     * @return bool
     */
    public function pauseTube(string $tube, int $delay): bool
    {
        $this->connection->write('pause-tube', [$tube, $delay]);
        $status = strtok($this->connection->read(), ' ');
        switch ($status) {
            case 'PAUSED':
                return true;
            case 'NOT_FOUND':
            default:
                $this->logger->error($status);
                return false;
        }
    }
}
