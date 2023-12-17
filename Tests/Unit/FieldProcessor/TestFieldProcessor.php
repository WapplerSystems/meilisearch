<?php

namespace WapplerSystems\Meilisearch\Tests\Unit\FieldProcessor;

use WapplerSystems\Meilisearch\FieldProcessor\FieldProcessor;

class TestFieldProcessor implements FieldProcessor
{
    public function process(array $values): array
    {
        foreach ($values as $no => $value) {
            if ($value === 'foo') {
                $values[$no] = 'bar';
            }
        }

        return $values;
    }
}
