<?php

namespace Anonymizer\Loader;

use Anonymizer\Anonymizer;
use Symfony\Component\Yaml\Yaml;


class YamlLoader extends ArrayLoader
{
    public function loadFile($filename): Anonymizer
    {
        $yaml = file_get_contents($filename);
        $data = Yaml::parse($yaml);
        return $this->loadData($data);
    }
}
