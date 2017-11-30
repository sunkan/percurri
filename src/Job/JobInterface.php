<?php

namespace Percurri\Job;

interface JobInterface
{
    public function getId(): int;
    public function getData();
}
