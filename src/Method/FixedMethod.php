<?php

namespace Anonymizer\Method;

class FixedMethod
{
    protected $faker;
    public function __construct($arguments = [])
    {
        $this->value = $arguments['value'];
    }

    public function apply($value, $row)
    {
        return $this->value;
    }

    public function getScope()
    {
        return 'table';
    }
}
