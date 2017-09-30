<?php

namespace Anonymizer;

use Boost\BoostTrait;
use Boost\Accessors\ProtectedAccessorsTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Collection\TypedArray;
use Anonymizer\Model\Column;
use PDO;
use RuntimeException;

class Anonymizer
{
    protected $name;
    protected $columns = [];
    protected $truncate = [];
    protected $drop = [];

    use BoostTrait;
    use ProtectedAccessorsTrait;

    public function __construct()
    {
        $this->columns = new TypedArray(Column::class);
    }

    private function loadSchema(PDO $pdo, OutputInterface $output)
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

        // foreach ($this->schema as $tableName => $columns) {
        //     foreach ($columns as $name=>$details) {
        //         echo $tableName . '.' . $name . "\n";
        //     }
        // }
        //exit();
        //print_r($this->schema); exit("DONE");
    }

    public function expandTables($pattern)
    {
        $tableNames = [];
        foreach ($this->schema as $tableName => $columns) {
            if (fnmatch($pattern, $tableName)) {
                $tableNames[] = $tableName;
            }
        }
        return $tableNames;
    }

    public function execute(PDO $pdo, OutputInterface $output)
    {
        $this->loadSchema($pdo, $output);

        foreach ($this->columns as $column) {
            $output->writeLn("Anonymizing column: <info>" . $column->identifier() . "</info> ({$column->displayMethod()})");
            $method = new \Anonymizer\Method\FakerMethod($column->getArguments());

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
                foreach ($missing[$cascade] as $k => $missingValue) {
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
                        $columnName = (string)$part[1];
                        if (isset($this->schema[$tableName][$columnName])) {
                            $output->writeLn("Dropping column: <info>{$tableName}.{$columnName}</info>");

                            $subStmt = $pdo->prepare(
                                "ALTER TABLE " . $tableName . ' DROP COLUMN ' . $columnName
                            );
                            $subStmt->execute();
                        }
                        break;
                    default:
                        throw new RuntimeException("Unexpected part count: " . count($part));
                }
            }
        }

    }


}
