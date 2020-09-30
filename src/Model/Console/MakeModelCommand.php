<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-04-30
 */

namespace Overlord\Model\Console;

use Carbon\Carbon;

//use fk\reference\exceptions\FileNotFoundException;
//use fk\reference\exceptions\InvalidVariableException;
//use fk\reference\IdeReferenceServiceProvider;
use fk\helpers\Dumper;
use Overlord\Model\Exceptions\BadTableNameException;
use Overlord\Model\Support\ColumnSchema;
use Overlord\Model\Support\DumperExpression;

//use Overlord\Model\Support\Helper;
use Overlord\Model\Support\TableSchema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Console\ModelMakeCommand;

//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

//use Symfony\Component\VarDumper\VarDumper;

class MakeModelCommand extends ModelMakeCommand
{

    /* @noinspection PhpMissingPropertyType */
    protected $name = 'overlord:model';

    protected $description = 'Generate model class instead of using `php artisan make:model`';

    protected array $uses = [];

    protected int $offset = 0;

    public function handle()
    {
        do {
            try {
                parent::handle();
            } catch (BadTableNameException $e) {
                return false;
            }
        } while ($this->next());

        return true;
    }

    public function argument($key = null)
    {
        if ($key === 'name') {
            return $this->argument('names')[$this->offset];
        } else {
            return parent::argument($key);
        }
    }

    protected function alreadyExists($rawName)
    {
        return false;
    }

    protected function getStub()
    {
        return $this->option('pivot')
            ? $this->resolveStubPath('/stubs/model.pivot.stub')
            : __DIR__ . '/stubs/model.stub.php';
    }

    /**
     * Build model class here only.
     * @param string $name
     * @return string|void
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name)
    {
        if (Str::endsWith($this->getStub(), '.php')) {
            return $this->generateModel();
        } else {
            return parent::buildClass($name); // TODO: Change the autogenerated stub
        }
    }

    protected function init()
    {
        $this->uses = [];
    }

    protected function generateModel()
    {
        $this->init();
        $this->generate($this->argument('name'));
    }

    protected function write($modelName, $content)
    {
        $file = base_path($this->config('dir')) . "/Contracts/$modelName.php";
        $file = str_replace('\\', '/', $file);
        $existedAlready = file_exists($file);

        $this->files->put($file, $content);

        // todo, also make model if absent. eg. App\Models\User & App\Models\Contracts\UserContract
        $this->comment(sprintf("%s `%s` %s", 'Contract', $modelName, ($existedAlready ? 'updated.' : 'created.')));
    }

    protected function config($name, $default = '')
    {
        return config('overlord-model.' . $name, $default);
    }

    protected function trySnake()
    {

    }

    /**
     * @param string $tableName
     * @throws BadTableNameException
     */
    protected function generate(string $tableName)
    {
        $singular = Str::singular($tableName);
        $isSingular = $singular === $tableName;

        $schema = $this->getTableSchema(strtolower($tableName));
        // if $tableWithoutPrefix is not a table, it must be a class name
//        if (!$schema->columns) {
//            $table = Str::snake($tableName);
//            $schema = $this->getTableSchema($table);
//        }
//        if (!$schema->columns) {
//            if ($isSingular) {
//                // try plural
//                $plural = Str::pluralStudly($tableName);
//            } else {
//                // try singular
//            }
////            return $this->generate($isSingular);
//        }
        if (!$schema->columns) {
            $this->alert(sprintf('No column found for <info>%s</info>, maybe prefix missing ?', $tableName));
            throw new BadTableNameException();
        }

        [$namespace, $table, $useSoftDeletes] = $this->generateContract($schema);

        $this->generateModelIfNotExists($namespace, $table, $useSoftDeletes);
    }

    protected function concatPath(...$partials)
    {
        if (is_array($partials[0])) $partials = $partials[0];

        // If the first element starts with a DIRECTORY_SEPARATOR, it must be an absolute path
        // otherwise, it's a relative one
        $prefix = in_array($partials[0][0], ['\\', '/']) ? DIRECTORY_SEPARATOR : '';

        return $prefix . implode(DIRECTORY_SEPARATOR, array_map(
                function ($part) {
                    // Remove redundant DIRECTORY_SEPARATOR
                    return trim(str_replace('\\', DIRECTORY_SEPARATOR, $part), '/\\');
                },
                $partials
            ));
    }

    protected function toModelName(string $tableName)
    {
        return ucfirst(Str::camel($tableName));
    }

    protected function generateModelIfNotExists(string $namespace, string $table, bool $useSoftDeletes)
    {
        $namespacePartials = explode('\\', $namespace);
        array_pop($namespacePartials);
        $namespace = implode('\\', $namespacePartials);
        $model = $this->toModelName($table);
        $filename = $this->concatPath(base_path(), $this->config('dir'), "{$model}.php");
        if (file_exists($filename)) {
            $this->line(sprintf('<comment>Skipping</comment> <info>%s</info>, exited already.', substr($filename, strlen(base_path()) + 1)));
            return false;
        }

        if ($useSoftDeletes) {
            $useHead = "\nuse " . SoftDeletes::class . ";\n";
            $useBody = "\n    use SoftDeletes;";
        } else {
            $useHead = '';
            $useBody = '';
        }

//        file_put_contents(
        $this->files->put(
            $filename,
            <<<EOF
<?php

namespace {$namespace};
{$useHead}
class {$model} extends Contracts\\{$model}Contract
{{$useBody}
}

EOF
        );
        $this->line("Model <info>$model</info> created.");
        return true;
    }

    protected function willUse($class)
    {
        if (!in_array($class, $this->uses)) {
            $this->uses[] = $class;
            sort($this->uses);
        }
    }

    protected function getMethods()
    {
        return [];
    }

    /**
     * Strip `$model`'s namespace if under the `$namespace`
     * e.g.
     *  $namespace = 'App\Models';
     *  $model = 'App\Models\Model';
     *
     *  // result:
     *  $model = 'Model';
     * @param string $namespace
     * @param string $model
     * @return false|string
     */
    protected function compareModelNamespace($namespace, $model)
    {
        $namespace = $namespace . '\\';
        if (strpos($model, $namespace) === 0) {
            return substr($model, strlen($namespace));
        }
        if (strpos($model, '\\') !== false) {
            /*
             * $namespace = App\Models\Contracts
             * $model = App\Models
             */
            $this->willUse($model);
            return substr($model, strrpos($model, '\\') + 1);
        }
        return $model;
    }

    /**
     * Rule of one column
     * @param ColumnSchema $column
     * @return array|string
     * @throws InvalidVariableException
     */
    protected function getColumnRules($column)
    {
        $returnArray = false;
        $rules = [];
        switch ($column->columnType) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                $rules = ['integer'];
                if ($column->columnType === 'tinyint') {
                    if ($column->unsigned) {
                        $rules[] = 'min:0';
                        $rules[] = 'max:255';
                    } else {
                        $rules[] = 'min:-128';
                        $rules[] = 'max:127';
                    }
                }
                if ($column->columnType === 'smallint') {
                    if ($column->unsigned) {
                        $rules[] = 'min:0';
                        $rules[] = 'max:65535';
                    } else {
                        $rules[] = 'min:32768';
                        $rules[] = 'max:-32767';
                    }
                } else if ($column->columnType === 'mediumint') {
                    if ($column->unsigned) {
                        $rules[] = 'min:0';
                        $rules[] = 'max:16777215';
                    } else {
                        $rules[] = 'min:-8388608';
                        $rules[] = 'max:8388607';
                    }
                } else if ($column->columnType === 'int') {
                    if ($column->unsigned) {
                        $rules[] = 'min:0';
                        $rules[] = 'max:4294967295';
                    } else {
                        $rules[] = 'min:-2147683648';
                        $rules[] = 'max:2147683647';
                    }
                }
                break;
            case 'decimal':
            case 'float':
            case 'double':
                $rules = [
                    'numeric'
                ];
                break;
            case 'varchar':
            case 'char':
                $rules = [
                    'string',
                    'max:' . $column->characterMaximumLength
                ];
                break;
            case 'text':
                $rules[] = 'string';
                break;
            case 'date':
            case 'timestamp':
                $rules = ['date'];
                break;
            case 'enum':
                $this->willUse(Rule::class);
                $rules = [new DumperExpression('Rule::in(' . Dumper::dump($column->values, true) . ')')];
                break;
        }

        if ($column->columnKey === 'UNI') {
            $this->willUse(Rule::class);
            array_unshift($rules, new DumperExpression('Rule::unique($this->table)->ignore($this->id)'));
        }
        if ($column->isNullable) {
            array_unshift($rules, 'nullable');
        } else if ($column->columnDefault === null) {
            array_unshift($rules, 'required');
        }

        if (!$rules) $rules[] = '';

        return $returnArray || $this->config('preferArrayRules') ? $rules : implode('|', $rules);
    }

    protected function getColumnType($type)
    {
        switch ($type) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return 'integer';
            case 'decimal':
            case 'float':
            case 'double':
                return 'float';
            case 'char':
            case 'varchar':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'enum':
                return 'string';
            case 'date':
            case 'datetime':
            case 'time':
            case 'timestamp':
                return '\\' . Carbon::class;
            default:
                return $type;
        }
    }

    protected function render($params)
    {
        extract($params);
        ob_start();
        include $this->getStub();
        return ob_get_clean();
    }

    /**
     * todo, should allow --table option to specify which table to generate base on.
     * @param string $table Name of the table, or one of its plural, singular, singular Model name form
     * @return TableSchema
     */
    protected function getTableSchema(string $table): TableSchema
    {
        $schema = new TableSchema($table);
        if (!$schema->columns) {
            $snake = Str::snake($table);
            if ($table !== $snake) {
                $schema = new TableSchema($snake);
            }

            if (!$schema->columns) {
                $plural = Str::plural($snake);
                if (strcasecmp($plural, $snake) !== 0) {
                    $schema = new TableSchema($plural);
                }
            }
        }

        return $schema;
    }

    protected function getArguments()
    {
        // todo, plural of the table name, singular for the model
//        return [
//            ['tables', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Name(s)/Model(s) for the table']
//        ];
        return [
            ['names', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The name(s) of the class(es) or table(s)'],
        ];
    }

    /**
     * @return bool True if there's next, false otherwise
     */
    protected function next()
    {
        return count($this->argument('names')) !== ++$this->offset;
    }

    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['dir', 'd', InputOption::VALUE_OPTIONAL, 'Directory for the models to be placed', 'App\Models'],
//            ['overwrite', null, InputOption::VALUE_NONE, 'Overwrite if model exists when passed'],
//            ['force', 'f', InputOption::VALUE_NONE, 'Force to create model even when no column fetched from database'],
            ['raw', null, InputOption::VALUE_NONE, 'Return the content without writing into file'],
        ]);
    }

    protected function generateContract(TableSchema $schema)
    {
        $table = $schema->getBareTableName();
        $namespace = $this->config('namespace', 'App\Models') . '\Contracts';

        $columns = $rules = [];
        $useSoftDeletes = false;
        foreach ($schema->columns as $column) {
            if ($column->columnName === 'deleted_at') $useSoftDeletes = true;
            $description = ($column->columnDefault === null ? '' : "[Default " . Dumper::dump($column->columnDefault, true) . "] ") . $column->columnComment;
            $columns[] = [
                $this->getColumnType($column->columnType), $column->columnName, $description
            ];

            // Do not set rules for primary key, for they are always auto increment
            if ($column->columnKey !== 'PRI' || $column->extra !== 'auto_increment') {
                $rules[$column->columnName] = $this->getColumnRules($column);
            }
        }

        $modelName = $this->toModelName($table) . 'Contract';
        $baseModelName = $this->config('baseModel', 'App\Models\Model');
        // todo add relations
        $relations = [];
        $content = $this->render([
            'namespace' => $namespace,
            'columns' => $columns,
            'methods' => $this->getMethods(),
            'modelName' => $modelName,
            'baseModelName' => $this->compareModelNamespace($namespace, $baseModelName),
            'rules' => $rules,
            'fullTableName' => $schema->getTableName(),
            'tableName' => $table,
            'relations' => $relations,
            'uses' => $this->uses,
        ]);
        if ($this->option('raw')) {
            $this->line($content);
        } else {
            $this->write($modelName, $content);
        }

        return [$namespace, $table, $useSoftDeletes];
    }

}
