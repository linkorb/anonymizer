<?php

namespace Anonymizer\Loader;

use Anonymizer\Anonymizer;
use Anonymizer\Model\Column;
use RuntimeException;

class ArrayLoader
{
    public function loadData($data)
    {
        $anonymizer = new Anonymizer();
        foreach ($data as $columnName => $columnData) {
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
            $anonymizer->getColumns()->add($column);
        }
        return $anonymizer;
    }

}
