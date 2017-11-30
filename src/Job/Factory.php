<?php

namespace Percurri\Job;

class Factory
{
    public function createJob($id, $data): JobInterface
    {
        if (in_array($data[0], ['{', '['])) {
            $json = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return new JsonJob($id, $json);
            }
        }
        return new RawJob($id, $data);
    }
}
