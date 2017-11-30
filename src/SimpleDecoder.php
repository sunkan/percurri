<?php

namespace Percurri;

class SimpleDecoder implements DecoderInterface
{
    /**
     * Decodes YAML data. This is a super naive decoder which just works on
     * a subset of YAML which is commonly returned by beanstalk.
     *
     * @param string $data The data in YAML format, can be either a list or a dictionary.
     * @return array An (associative) array of the converted data.
     */
    public function decode(string $data): array
    {
        $data = array_slice(explode("\n", $data), 1);
        $result = [];

        foreach ($data as $key => $value) {
            if ($value[0] === '-') {
                $value = ltrim($value, '- ');
            } elseif (strpos($value, ':') !== false) {
                list($key, $value) = explode(':', $value);
                $value = ltrim($value, ' ');
            }
            if (is_numeric($value)) {
                $value = (integer) $value == $value ? (integer) $value : (float) $value;
            }
            $result[$key] = $value;
        }
        return $result;
    }
}
