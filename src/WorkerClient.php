<?php

namespace Percurri;

use Percurri\Exception\JobNotFoundException;
use Percurri\Job\Factory;
use Percurri\Job\JobInterface;
use Psr\Log\LoggerInterface;

class WorkerClient
{
    protected $connection;
    protected $logger;
    protected $jobFactory;
    protected $decoder;

    public function __construct(Connection $connection, LoggerInterface $logger, Factory $jobFactory, DecoderInterface $decoder)
    {
        $this->decoder = $decoder;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->jobFactory = $jobFactory;
    }

    /**
     * Reserve a job (with a timeout).
     *
     * @param integer $timeout If given specifies number of seconds to wait for a job. `0` returns immediately.
     * @return JobInterface
     * @throws JobNotFoundException
     */
    public function reserve(int $timeout = null): JobInterface
    {
        if (isset($timeout)) {
            $this->connection->write('reserve-with-timeout', [$timeout]);
        } else {
            $this->connection->write('reserve');
        }

        $status = strtok($this->connection->read(), ' ');
        if ($status === 'RESERVED') {
            $id = (integer) strtok(' ');
            $data = $this->connection->read((integer) strtok(' '));
            return $this->jobFactory->createJob($id, $data);
        }

        $this->logger->error($status);
        throw new JobNotFoundException($status);
    }

    /**
     * Removes a job from the server entirely.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @return bool
     */
    public function delete($idOrJob): bool
    {
        $id = $this->getJobId($idOrJob);
        $this->connection->write('delete', [$id]);
        $status = $this->connection->read();
        if ($status === 'DELETED') {
            return true;
        }
        $this->logger->error($status);
        return false;
    }

    /**
     * Puts a reserved job back into the ready queue.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @param integer $pri Priority to assign to the job.
     * @param integer $delay Number of seconds to wait before putting the job in the ready queue.
     * @return bool
     */
    public function release($idOrJob, int $pri, int $delay): bool
    {
        $id = $this->getJobId($idOrJob);
        $this->connection->write('release', [$id, $pri, $delay]);
        $status = $this->connection->read();

        switch ($status) {
            case 'RELEASED':
            case 'BURIED':
                return true;
            case 'NOT_FOUND':
            default:
                $this->logger->error($status);
                return false;
        }
    }

    /**
     * Puts a job into the `buried` state Buried jobs are put into a FIFO
     * linked list and will not be touched until a client kicks them.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @param integer $pri *New* priority to assign to the job.
     * @return bool
     */
    public function bury($idOrJob, int $pri): bool
    {
        $id = $this->getJobId($idOrJob);
        $this->connection->write('bury', [$id, $pri]);
        $status = $this->connection->read();
        if ($status === 'BURIED') {
            return true;
        }
        $this->logger->error($status);
        return false;
    }

    /**
     * Allows a worker to request more time to work on a job.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @return bool
     */
    public function touch($idOrJob): bool
    {
        $id = $this->getJobId($idOrJob);
        $this->connection->write('touch', [$id]);
        $status = $this->connection->read();
        if ($status === 'TOUCHED') {
            return true;
        }
        $this->logger->error($status);
        return false;
    }

    /**
     * Adds the named tube to the watch list for the current connection.
     *
     * @param string $tube Name of tube to watch.
     * @return integer 0 on error otherwise number of tubes in watch list.
     */
    public function watch(string $tube): int
    {
        $this->connection->write('watch', [$tube]);
        $status = strtok($this->connection->read(), ' ');
        if ($status === 'WATCHING') {
            return (integer) strtok(' ');
        }

        $this->logger->error($status);
        return 0;
    }

    /**
     * Returns a list of tubes currently being watched by the worker.
     *
     * @return array
     */
    public function listTubes(): array
    {
        $this->connection->write('list-tubes-watched');
        $status = strtok($this->connection->read(), ' ');
        if ($status === 'OK') {
            $data = $this->connection->read((integer) strtok(' '));
            return $this->decoder->decode($data);
        }
        return [];
    }

    /**
     * Remove the named tube from the watch list.
     *
     * @param string $tube Name of tube to ignore.
     * @return integer|boolean `false` on error otherwise number of tubes in watch list.
     */
    public function ignore(string $tube)
    {
        $this->connection->write(sprintf('ignore %s', $tube));
        $status = strtok($this->connection->read(), ' ');
        switch ($status) {
            case 'WATCHING':
                return (integer) strtok(' ');
            case 'NOT_IGNORED':
            default:
                $this->logger->error($status);
                return false;
        }
    }

    /**
     * Inspect a job by its id.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @return JobInterface
     * @throws JobNotFoundException
     */
    public function peek($idOrJob): JobInterface
    {
        $id = $this->getJobId($idOrJob);
        $this->connection->write('peek', [$id]);
        return $this->peekParseRead();
    }

    /**
     * Inspect the next ready job.
     *
     * @return JobInterface
     * @throws JobNotFoundException
     */
    public function peekReady(): JobInterface
    {
        $this->connection->write('peek-ready');
        return $this->peekParseRead();
    }

    /**
     * Inspect the job with the shortest delay left.
     *
     * @return JobInterface
     * @throws JobNotFoundException
     */
    public function peekDelayed(): JobInterface
    {
        $this->connection->write('peek-delayed');
        return $this->peekParseRead();
    }

    /**
     * Inspect the next job in the list of buried jobs.
     *
     * @return JobInterface
     * @throws JobNotFoundException
     */
    public function peekBuried(): JobInterface
    {
        $this->connection->write('peek-buried');
        return $this->peekParseRead();
    }

    /**
     * Handles response for all peek methods.
     *
     * @return JobInterface
     * @throws JobNotFoundException
     */
    protected function peekParseRead(): JobInterface
    {
        $status = strtok($this->connection->read(), ' ');
        if ($status === 'FOUND') {
            $id = (integer) strtok(' ');
            $data = $this->connection->read((integer) strtok(' '));
            return $this->jobFactory->createJob($id, $data);
        }
        $this->logger->error($status);
        throw new JobNotFoundException($status);
    }

    /**
     * Moves jobs into the ready queue (applies to the current tube).
     *
     * If there are buried jobs those get kicked only otherwise delayed
     * jobs get kicked.
     *
     * @param integer $bound Upper bound on the number of jobs to kick.
     * @return int Return 0 on error
     */
    public function kick(int $bound): int
    {
        $this->connection->write('kick', [$bound]);
        $status = strtok($this->connection->read(), ' ');
        switch ($status) {
            case 'KICKED':
                return (integer) strtok(' ');
            default:
                $this->logger->error($status);
                return 0;
        }
    }

    /**
     * This is a variant of the kick command that operates with a single
     * job identified by its job id. If the given job id exists and is in a
     * buried or delayed state, it will be moved to the ready queue of the
     * the same tube where it currently belongs.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @return bool
     */
    public function kickJob($idOrJob): bool
    {
        $id = $this->getJobId($idOrJob);
        $this->connection->write('kick-job', [$id]);
        $status = strtok($this->connection->read(), ' ');
        switch ($status) {
            case 'KICKED':
                return true;
            case 'NOT_FOUND':
            default:
                $this->logger->error($status);
                return false;
        }
    }

    private function getJobId($idOrJob)
    {
        if ($idOrJob instanceof JobInterface) {
            return $idOrJob->getId();
        } elseif (is_int($idOrJob)) {
            return (int) $idOrJob;
        }
        throw new \InvalidArgumentException('id must be integer or instance of JobInterface');
    }
}
