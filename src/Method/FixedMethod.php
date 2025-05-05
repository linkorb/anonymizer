<?php

namespace Anonymizer\Method;

class FixedMethod
{
    protected $faker;
    /**
     * @var mixed
     */
    private $value;

    public function __construct($arguments = [])
    {
        $this->value = $arguments['value'];
    }

    public function apply($value, $row)
    {
        return $this->value;
    }

    public function getScope(): string
    {
        return 'table';
    }
}
