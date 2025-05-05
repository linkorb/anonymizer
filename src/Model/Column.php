<?php

namespace Anonymizer\Model;

use Boost\BoostTrait;
use Boost\Accessors\ProtectedAccessorsTrait;
use Collection\Identifiable;

class Column implements Identifiable
{
    protected $name;
    protected $tableName;
    protected $method;
    protected $arguments;
    protected $cascades = [];

    use BoostTrait;
    use ProtectedAccessorsTrait;

    public function __construct($tableName, $name)
    {
        $this->tableName = $tableName;
        $this->name = $name;
    }

    public function displayMethod()
    {
        return $this->method;
    }

    public function identifier(): string
    {
       return $this->tableName . '.' . $this->name;
    }
}
