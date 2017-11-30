<?php

namespace Percurri;

use Percurri\Job\JsonJob;
use Percurri\Job\RawJob;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    public function testRawJobFactory()
    {
        $id = 1;
        $data = 'raw data';
        $factory = new Job\Factory();
        $job = $factory->createJob($id, $data);
        $this->assertInstanceOf(RawJob::class, $job);
        $this->assertSame($id, $job->getId());

        $this->assertSame($data, $job->getData());
    }

    public function testJsonJobFactory()
    {
        $id = 1;
        $factory = new Job\Factory();
        $job = $factory->createJob($id, '{"test":1}');
        $this->assertInstanceOf(JsonJob::class, $job);
        $this->assertSame($id, $job->getId());
        $this->assertSame(1, $job->test);
        $this->assertNull($job->not_found);

        $this->assertInternalType('array', $job->getData());
    }

    public function testInvalidJson()
    {
        $factory = new Job\Factory();
        $job = $factory->createJob(1, '{"test":1');
        $this->assertInstanceOf(RawJob::class, $job);
        $this->assertSame(1, $job->getId());
    }
}
