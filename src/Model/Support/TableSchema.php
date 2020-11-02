<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-04-30
 */

namespace Overlord\Model\Support;

use Illuminate\Support\Facades\DB;

class TableSchema
{
    protected string $tableName;
    protected string $tablePrefix;
    protected string $databaseName;

    /**
     * @var ColumnSchema[]
     */
    public array $columns = [];

    public function __construct($table)
    {
        $this->tablePrefix = DB::getTablePrefix();
        $this->databaseName = DB::getDatabaseName();
        $this->queryColumns($this->tableName = $table);
    }

    protected function queryColumns(string $table, $isFinal = false)
    {
        $columns = DB::select('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME =?', [$this->databaseName, $table]);
        if (empty($columns) && !$isFinal) $this->queryColumns($this->tableName = "{$this->tablePrefix}{$table}", true);
        foreach ($columns as $column) {
            $this->columns[] = new ColumnSchema($column);
        }
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Table name without prefix
     * @return string
     */
    public function getBareTableName()
    {
        return substr($this->getTableName(), strlen($this->getTablePrefix()));
    }
}
