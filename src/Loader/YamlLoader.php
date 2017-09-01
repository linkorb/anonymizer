<?php

namespace Anonymizer\Loader;

use Symfony\Component\Yaml\Yaml;


class YamlLoader extends ArrayLoader
{
    public function loadFile($filename)
    {
        $yaml = file_get_contents($filename);
        $data = Yaml::parse($yaml);
        return $this->loadData($data);
    }
}
