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
            ->addArgument(
                'config',
                InputArgument::OPTIONAL,
                'Configuration filename',
                'anonymizer.yml'
            )
            ->addArgument(
                'dsn',
                InputArgument::OPTIONAL,
                'Data source name (i.e. mysql://username:password:host/dbname)'
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = getenv('ANONYMIZER_FILENAME');
        $dsn = getenv('ANONYMIZER_DSN');
        if (!$dsn) {
            $dsn = getenv('PDO');
        }

        if ($input->getArgument('config')) {
            $filename = $input->getArgument('config');
        }
        if ($input->getArgument('dsn')) {
            $dsn = $input->getArgument('dsn');
        }

        if (!$filename) {
            throw new RuntimeException("Config file not specified (use argument or environment variable)");
        }
        if (!$dsn) {
            throw new RuntimeException("DSN not specified (use argument or environment variable)");
        }

        $output->writeLn("<info>Anonymizer</info>");
        $output->writeLn(" * DSN: " . $dsn);
        $output->writeLn(" * Config: " . $filename);

        $connector = new Connector();
        $config = $connector->getConfig($dsn);
        if (!$connector->exists($config)) {
            throw new RuntimeException("Failed to connect to database");
        }
        $pdo = $connector->getPdo($config);

        $loader = new YamlLoader();
        $anonymizer = $loader->loadFile($filename);
        $anonymizer->execute($pdo, $output);
        $output->writeLn("Done");

    }
}
