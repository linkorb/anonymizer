<?php

namespace Anonymizer\Method;

class FakerMethod
{
    protected $faker;
    protected $formatter = 'email';
    public function __construct($arguments = [])
    {
        $locale = 'en_US';
        if (isset($arguments['locale'])) {
            $locale = $arguments['locale'];
        }
        if (isset($arguments['formatter'])) {
            $this->formatter = $arguments['formatter'];
        }
        $this->faker = \Faker\Factory::create($locale);
        $this->faker->seed(0);
    }

    public function apply($value, $row)
    {
        $value = $this->faker->unique()->{$this->formatter};
        return $value;
    }
}
