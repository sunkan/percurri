<?php

namespace Percurri\Job;

class RawJob implements JobInterface
{
    protected $id;
    protected $payload;

    public function __construct(int $id, $data)
    {
        $this->id = $id;
        $this->payload = $data;
    }

    public function getData()
    {
        return $this->payload;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
