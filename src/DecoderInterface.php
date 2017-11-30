<?php

namespace Percurri;

interface DecoderInterface
{
    public function decode(string $data): array;
}
