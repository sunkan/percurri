<?php

namespace Percurri\Job;

class JsonJob implements JobInterface
{
    protected $id;
    protected $payload;

    public function __construct(int $id, $json)
    {
        $this->id = $id;
        $this->payload = $json;
    }

    public function __get($name)
    {
        if (isset($this->payload[$name])) {
            return $this->payload[$name];
        }
        return null;
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
