<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-04-30
 */

namespace Overlord\Model\Support;

use Illuminate\Support\Str;

/**
 * This class represents a column of an table
 */
class ColumnSchema
{
    protected array $attributes = [];
    /** @var string */
    public $tableCatalog;
    /** @var string */
    public $tableSchema;
    /** @var string */
    public $tableName;
    /** @var string */
    public $columnName;
    /** @var integer */
    public $ordinalPosition;
    /** @var integer|null */
    public $columnDefault;
    /** @var boolean */
    public $isNullable;
    /** @var string */
    public $dataType;
    /** @var integer|null */
    public $characterMaximumLength;
    /** @var integer|null */
    public $characterOctetLength;
    /** @var integer */
    public $numericPrecision;
    /** @var integer */
    public $numericScale;
    /** @var integer|null */
    public $datetimePrecision;
    /** @var integer|null */
    public $characterSetName;
    /** @var integer|null */
    public $collationName;
    /** @var string */
    public $columnType;
    /** @var string */
    public $columnKey;
    /** @var string */
    public $extra;
    /** @var string */
    public $privileges;
    /** @var string */
    public $columnComment;
    /** @var boolean */
    public $unsigned = false;
    /** @var integer */
    public $size;
    /** @var array|null Array for `enum` */
    public $values = null;
//    protected $generationExpression = '';

    public function __construct($columns)
    {
        foreach ($columns as $column => $value) {
//            if (strpos($column, '_') !== false) {
            $column = Str::camel(strtolower($column));
//            }
            $this->$column = $this->getValue($column, $value);
        }
//        dd($this);
    }

    protected function getValue($column, $value)
    {
        switch ($column) {
            case 'isNullable':
                return $value === 'YES';
                break;
            case 'columnType':
                return $this->parseColumnType($value);
            default:
                return $value;
        }
    }

    protected function parseColumnType($value)
    {
        if (preg_match('/(\w+)(?:\((\d+(?:,?\d+)?)\))?(?: +(\w+))?/', $value, $matches)) {
            $type = $matches[1];
            if (false !== strpos($type, 'int') || $type === 'decimal' || $type === 'float') {
                $this->size = $matches[2];
                $this->unsigned = ($matches[3] ?? '') === 'unsigned' ? true : false;
            }

            /* enum('no','yes') */
            if ($type === 'enum') {
                $this->values = array_map(function ($v) {
                    return trim($v, '\'""');
                }, explode(',', substr($value, 5, -1)));;
            }

            return $type;
        } else {
            return $value;
        }
    }

    private static function camelCase($string)
    {
        $string = strtolower($string);
        return preg_replace_callback('/_(\w)/', function ($match) {
            $first = $match[1];
            return strtoupper($first);
        }, $string);
    }

}