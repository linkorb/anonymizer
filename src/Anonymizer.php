<?php

namespace Anonymizer;

use Anonymizer\Method\FakerMethod;
use Anonymizer\Method\FixedMethod;
use Boost\BoostTrait;
use Boost\Accessors\ProtectedAccessorsTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use PDO;
use RuntimeException;

class Anonymizer
{
    protected array $columns = [];
    protected array $truncate = [];
    protected array $drop = [];
    protected array $flags = [];
    protected array $schema = [];

    use BoostTrait;
    use ProtectedAccessorsTrait;

    private function loadSchema(PDO $pdo, OutputInterface $output): void
    {
        $stmt = $pdo->prepare("SHOW tables;");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tableName = $row[0];
            $this->schema[$tableName]=[];
        }
        foreach ($this->schema as $tableName => $columns) {
            $stmt = $pdo->prepare("describe " . $tableName);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fieldName = $row['Field'];
                $this->schema[$tableName][$fieldName] = [
                    'type' => $row['Type'],
                    'null' => $row['Null'],
                    'default' => $row['Default'],
                    'extra' => $row['Extra']
                ];
            }
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
          foreach ($this->schema as $tableName => $columns) {
              foreach ($columns as $name=>$details) {
                  $output->writeLn($tableName . '.' . $name);
              }
          }
        }
        //exit();
        //print_r($this->schema); exit("DONE");
    }

    public function expandTables($pattern): array
    {
        $tableNames = [];
        foreach ($this->schema as $tableName => $columns) {
            if (fnmatch($pattern, $tableName)) {
                $tableNames[] = $tableName;
            }
        }
        return $tableNames;
    }

    public function expandColumns($tableName, $pattern): array
    {
        $columnNames = [];
        foreach ($this->schema[$tableName] as $columnName => $data) {
            if (fnmatch($pattern, $columnName)) {
                $columnNames[] = $columnName;
            }
        }
        return $columnNames;
    }

    public function execute(PDO $pdo, OutputInterface $output): void
    {
        $this->loadSchema($pdo, $output);

        foreach ($this->columns as $column) {
            $output->writeLn("Anonymizing column: <info>" . $column->identifier() . "</info> ({$column->displayMethod()})");
            switch ($column->getMethod()) {
                case 'faker':
                    $method = new FakerMethod($column->getArguments());
                    break;
                case 'fixed':
                    $method = new FixedMethod($column->getArguments());
                    break;
                default:
                    throw new RuntimeException("Unsupported method: " . $column->getMethod());
            }

            if ($method->getScope()=='table') {
                $stmt = $pdo->prepare(
                    "UPDATE " . $column->getTableName() . ' SET ' . $column->getName() . '=:newValue'
                );
                $stmt->execute(
                    [
                        'newValue' => $method->apply(null, null)
                    ]
                );
            }

            if ($method->getScope()=='row') {
                $stmt = $pdo->prepare("SELECT " . $column->getName() . ' FROM ' . $column->getTableName());
                $stmt->execute();
                $map = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $oldValue = $row[$column->getName()];
                    $newValue = $method->apply($oldValue, $row);
                    $map[$oldValue] = $newValue;
                }
                $max = count($map) * (1+count($column->getCascades()));
                //print_r($map);

                $progress = new ProgressBar($output, $max);
                $progress->setRedrawFrequency(1000);
                $progress->start();
                $missing = [];

                // Sanity check
                foreach ($column->getCascades() as $cascade) {
                    $missing[$cascade] = [];
                    $part = explode('.', $cascade);
                    if (count($part)!=2) {
                        throw new RuntimeException("Expected cascade with 2 parts: " . $cascade);
                    }
                    $cascadeTable = $part[0];
                    $cascadeColumn = $part[1];
                    $stmt = $pdo->prepare("SELECT " . $cascadeColumn . ' FROM ' . $cascadeTable);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rows as $row) {
                        $value = $row[$cascadeColumn];
                        if ($value && !isset($map[$value])) {
                            $missing[$cascade][] = $value;
                        }
                    }
                }

                // Fix main table
                foreach ($map as $oldValue => $newValue) {
                    //echo $oldValue . ' => ' . $newValue . "\n";
                    $stmt = $pdo->prepare(
                        "UPDATE " . $column->getTableName() . ' SET ' . $column->getName() . '=:newValue WHERE ' . $column->getName() . '=:oldValue'
                    );
                    $stmt->execute(
                        [
                            'oldValue' => $oldValue,
                            'newValue' => $newValue
                        ]
                    );
                    $progress->advance();

                }

                // fix cascades
                foreach ($column->getCascades() as $cascade) {
                    $part = explode('.', $cascade);
                    if (count($part)!=2) {
                        throw new RuntimeException("Expected cascade with 2 parts: " . $cascade);
                    }
                    $cascadeTable = $part[0];
                    $cascadeColumn = $part[1];

                    // Fix referencing values
                    foreach ($map as $oldValue=>$newValue) {
                        $sql = "UPDATE " . $cascadeTable . ' SET ' . $cascadeColumn . '=:newValue WHERE ' . $cascadeColumn . '=:oldValue';
                        $subStmt = $pdo->prepare(
                            $sql
                        );
                        $subStmt->execute(
                            [
                                'oldValue' => $oldValue,
                                'newValue' => $newValue
                            ]
                        );
                        $progress->advance();
                    }
                    // Fix missing values
                    foreach ($missing[$cascade] as $missingValue) {
                        $sql = "UPDATE " . $cascadeTable . ' SET ' . $cascadeColumn . '=null WHERE ' . $cascadeColumn . '=:missingValue';
                        $subStmt = $pdo->prepare(
                            $sql
                        );
                        $subStmt->execute(
                            [
                                'missingValue' => $missingValue
                            ]
                        );
                    }
                }
            }
        }
        $output->writeLn("");

        // truncate tables
        foreach ($this->truncate as $tableName) {
            $output->writeLn("Truncating table: <info>" . $tableName . "</info>");

            $subStmt = $pdo->prepare(
                "TRUNCATE " . $tableName . ';'
            );
            $subStmt->execute();
        }

        foreach ($this->drop as $drop) {
            $part = explode('.', $drop);
            //print_r($part);
            $tableNames = $this->expandTables($part[0]);
            foreach ($tableNames as $tableName) {
                switch (count($part)) {
                    case 1:
                        $output->writeLn("Dropping table: <info>{$tableName}</info>");

                        $subStmt = $pdo->prepare(
                            "DROP TABLE " . $tableName . ';'
                        );
                        $subStmt->execute();
                        break;
                    case 2:
                        $columnNames = $this->expandColumns($tableName, (string)$part[1]);
                        foreach ($columnNames as $columnName) {
                            if (isset($this->schema[$tableName][$columnName])) {
                                $output->writeLn("Dropping column: <info>{$tableName}.{$columnName}</info>");

                                $subStmt = $pdo->prepare(
                                    "ALTER TABLE " . $tableName . ' DROP COLUMN ' . $columnName
                                );
                                $subStmt->execute();
                            }
                        }
                        break;
                    default:
                        throw new RuntimeException("Unexpected part count: " . count($part));
                }
            }
        }

        if ($this->getFlag('drop-empty-tables')) {
            $dbName = $pdo->query('select database()')->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT table_name, SUM(TABLE_ROWS) as c FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = :dbName group by table_name having c=0;"
            );
            $stmt->execute(
                [
                    'dbName' => $dbName
                ]
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $tableName = $row['table_name'];
                $output->writeLn("Dropping empty table: <info>{$tableName}</info>");

                $subStmt = $pdo->prepare(
                    "DROP TABLE " . $tableName . ';'
                );
                $subStmt->execute();
            }

        }

        if ($this->getFlag('drop-null-columns')) {
            foreach ($this->schema as $tableName => $columns) {
                foreach ($columns as $columnName => $columns) {
                    // echo " ? " . $tableName . '.' . $columnName . PHP_EOL;

                    $stmt = $pdo->prepare(
                        "SELECT count(*) as c FROM " . $tableName . "
                        WHERE " . $columnName . " is not null"
                    );
                    $stmt->execute(
                        []
                    );
                    $c = $stmt->fetch(PDO::FETCH_ASSOC)['c'];
                    if ($c==0) {
                        $output->writeLn("Dropping null column <info>$tableName.$columnName</info>");
                        $subStmt = $pdo->prepare(
                            "ALTER TABLE " . $tableName . ' DROP COLUMN ' . $columnName
                        );
                        $subStmt->execute();
                    }
                }
            }
        }


    }

    public function setFlag($key, $value): void
    {
        $this->flags[$key] = $value;
    }

    public function getFlag($key)
    {
        return $this->flags[$key] ?? null;
    }


}
