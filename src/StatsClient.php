<?php

namespace Percurri;

use Percurri\Exception\StatsReadException;
use Percurri\Job\JobInterface;
use Psr\Log\LoggerInterface;

class StatsClient
{
    protected $connection;
    protected $logger;
    protected $decoder;

    public function __construct(Connection $connection, LoggerInterface $logger, DecoderInterface $decoder)
    {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->decoder = $decoder;
    }
    private function getJobId($idOrJob)
    {
    }

    /**
     * Gives statistical information about the specified job if it exists.
     *
     * @param integer|JobInterface $idOrJob The id of the job or an instance of JobInterface.
     * @return array
     * @throws StatsReadException
     */
    public function statsJob($idOrJob): array
    {
        if ($idOrJob instanceof JobInterface) {
            $id = $idOrJob->getId();
        } elseif (is_int($idOrJob)) {
            $id = (int) $idOrJob;
        } else {
            throw new \InvalidArgumentException('id must be integer or instance of JobInterface');
        }

        $this->connection->write('stats-job', [$id]);
        return $this->statsRead();
    }

    /**
     * Gives statistical information about the specified tube if it exists.
     *
     * @param string $tube Name of the tube.
     * @return array
     * @throws StatsReadException
     */
    public function statsTube(string $tube): array
    {
        $this->connection->write('stats-tube', [$tube]);
        return $this->statsRead();
    }

    /**
     * Gives statistical information about the system as a whole.
     *
     * @return array
     * @throws StatsReadException
     */
    public function stats(): array
    {
        $this->connection->write('stats');
        return $this->statsRead();
    }

    /**
     * Returns a list of all existing tubes.
     *
     * @return array
     * @throws StatsReadException
     */
    public function listTubes(): array
    {
        $this->connection->write('list-tubes');
        return $this->statsRead();
    }

    /**
     * Handles responses for all stat methods.
     *
     * @return array statistical data.
     * @throws StatsReadException
     */
    protected function statsRead(): array
    {
        $status = strtok($this->connection->read(), ' ');
        if ($status === 'OK') {
            $data = $this->connection->read((integer) strtok(' '));
            return $this->decoder->decode($data);
        }
        $this->logger->error($status);
        throw new StatsReadException($status);
    }
}
