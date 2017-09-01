<?php

namespace Anonymizer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Anonymizer\Loader\YamlLoader;
use RuntimeException;
use Connector\Connector;

class RunCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run anonymizer')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $pdoUrl = getenv('ANONYMIZER_PDO');
        $filename = getenv('ANONYMIZER_FILENAME');

        $output->writeLn("<info>Anonymizer</info>");
        $output->writeLn(" * DSN: " . $pdoUrl);
        $output->writeLn(" * Config: " . $filename);

        $connector = new Connector();
        $config = $connector->getConfig($pdoUrl);
        if (!$connector->exists($config)) {
            throw new RuntimeException("Failed to connect to database");
        }
        $pdo = $connector->getPdo($config);

        $loader = new YamlLoader();
        $anonymizer = $loader->loadFile($filename);
        //print_r($anonymizer);
        $anonymizer->execute($pdo, $output);
        $output->writeLn("Done");

    }
}
