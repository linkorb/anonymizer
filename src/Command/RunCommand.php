<?php

namespace Anonymizer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Anonymizer\Loader\YamlLoader;
use RuntimeException;
use Connector\Connector;
use Connector\Backend\IniBackend;

class RunCommand extends Command
{
    protected function configure(): void
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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $_ENV['ANONYMIZER_FILENAME'];
        $dsn = $_ENV['ANONYMIZER_DSN'];
        $configPath = $_ENV['ANONYMIZER_CONFIG_PATH'];

        if (!$dsn) {
            $dsn = $_ENV['PDO'];
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
        if ($configPath) {
            $backend = new IniBackend($configPath, '.conf');
            $connector->registerBackend($backend);
        }

        $config = $connector->getConfig($dsn);
        if (!$connector->exists($config)) {
            throw new RuntimeException("Failed to connect to database");
        }
        $pdo = $connector->getPdo($config);

        $loader = new YamlLoader();
        $anonymizer = $loader->loadFile($filename);
        $anonymizer->execute($pdo, $output);
        $output->writeLn("Done");
        return Command::SUCCESS;
    }
}
