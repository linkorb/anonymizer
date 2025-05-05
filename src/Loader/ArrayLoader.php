<?php

namespace Anonymizer\Loader;

use Anonymizer\Anonymizer;
use Anonymizer\Model\Column;
use RuntimeException;

class ArrayLoader
{
    public function loadData($data): Anonymizer
    {
        $anonymizer = new Anonymizer();

        if (isset($data['config'])) {
            $anonymizer->setConfig($data['config']);
        }
        if (isset($data['columns'])) {
            foreach ($data['columns'] as $columnName => $columnData) {
                $part = explode('.', $columnName);
                if (count($part)!=2) {
                    throw new RuntimeException("Expected 2 parts as column name: " . $columnName);
                }
                $column = new Column($part[0], $part[1]);
                if (isset($columnData['method'])) {
                    $column->setMethod($columnData['method']);
                }
                if (isset($columnData['arguments'])) {
                    $column->setArguments($columnData['arguments']);
                }
                if (isset($columnData['cascades'])) {
                    $column->setCascades($columnData['cascades']);
                }
                $anonymizer->getColumns()->append($column);
            }
        }
        if (isset($data['truncate'])) {
            $anonymizer->setTruncate($data['truncate']);
        }
        if (isset($data['drop'])) {
            $anonymizer->setDrop($data['drop']);
        }
        if (isset($data['flags'])) {
            foreach ($data['flags'] as $key=>$value) {
                $anonymizer->setFlag($key, $value);
            }
        }
        return $anonymizer;
    }

}
