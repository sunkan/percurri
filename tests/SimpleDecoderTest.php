<?php

namespace Percurri;

use PHPUnit\Framework\TestCase;

class SimpleDecoderTest extends TestCase
{
    public function testDecodeKeyValue()
    {
        $testYaml = "---\ncurrent-jobs-urgent: 0\ncurrent-jobs-ready: 0\ncurrent-jobs-reserved: 0";

        $decoder = new SimpleDecoder();
        $result = $decoder->decode($testYaml);
        $this->assertInternalType('array', $result);
        $this->assertEquals(0, $result['current-jobs-urgent']);
    }

    public function testDecodeList()
    {
        $testYaml = "---\n-default\n- test";

        $decoder = new SimpleDecoder();
        $result = $decoder->decode($testYaml);
        $this->assertInternalType('array', $result);
        $this->assertCount(2, $result);
        $this->assertArraySubset(['default', 'test'], $result);
    }
}
