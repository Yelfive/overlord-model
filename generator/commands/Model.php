<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-04-30
 */

namespace fk\reference\commands;

use fk\helpers\Str;
use fk\reference\exceptions\FileNotFoundException;
use fk\reference\exceptions\InvalidVariableException;
use fk\reference\IdeReferenceServiceProvider;
use Overlord\Model\Support\ColumnSchema;
use Overlord\Model\Support\DumperExpression;
use Overlord\Model\Support\Helper;
use Overlord\Model\Support\TableSchema;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Model extends Command
{

    protected $name = 'reference:model';

    protected $description = 'Generate model references or create model instead of `php artisan make:model`';

    protected $uses = [];

    public function handle()
    {
        if ($this->hasArgument('tables')) {
            $this->generateModels();
        } else {
            $this->generateDocs();
        }
    }

    protected function init()
    {
        $this->uses = [];
    }

    protected function generateDocs()
    {

    }

    protected function generateModels()
    {
        $tables = $this->argument('tables');
        foreach ($tables as $table) {
            $this->init();
            $this->generate($table);
        }
    }

    protected function compareModel($filename, $content)
    {
        $tmp = sys_get_temp_dir() . '/php_temp';
        file_put_contents($tmp, $content);
        exec(<<<CMD
diff $filename $tmp
CMD
            , $output);
        unlink($tmp);
        if (!$output) {
            $this->info('The model is untouched');
            return;
        } else {
            $this->warn(str_repeat('\<', 10) . " diff\n");
            $this->warn("# old" . str_repeat("\t", 8) . '# new');
            $this->warn(implode("\n", $output));
            $this->info(str_repeat('\<', 10) . " model\n");
            $this->info($content);
        }
    }

    protected function doubleConfirm($model)
    {
        return $this->ask(<<<QUESTION
------------------------------------------------
| Are you sure you want to overwrite the model?  |
| Enter model's short name to overwrite          |
| Notice: the name is case-sensitive             |
 ------------------------------------------------
 Enter '$model'
QUESTION
        );
    }

    protected function write($modelShortName, $content)
    {
        $file = base_path($this->config('dir')) . ($this->option('abstract') ? '/Contracts' : '') . "/$modelShortName.php";
        $file = str_replace('\\', '/', $file);
        $existedAlready = file_exists($file);
        // Contract does not need to confirm when overwriting
        if (!$this->option('abstract')) {
            if (
                $existedAlready
                &&
                (
                    $this->option('overwrite') === false
                    || $this->doubleConfirm($modelShortName) !== $modelShortName
                )
            ) {
                if ($this->option('overwrite')) {
                    $this->error("Confirm failed. The answer is `$modelShortName`");
                    sleep(1);
                }
                $this->warn("Model `$modelShortName` already exists at : $file");
                $this->compareModel($file, $content);
                $this->error('Use option --overwrite if you want to overwrite the existed file.');
                return;
            }
        }

        $dir = dirname($file);
        if (!file_exists($dir)) {
            if ($this->confirm("Directory [$dir] does not exist! Create?", false)) {
                mkdir($dir, 0755, true);
                $this->comment('Directory created');
            } else {
                return;
            }
        }
        $handler = fopen($file, 'w');
        fwrite($handler, $content);
        fclose($handler);
        $this->comment(sprintf("%s `%s` %s", $this->option('abstract') ? 'Contract' : 'Model', $modelShortName, ($existedAlready ? 'updated.' : 'created.')));
    }

    protected function config($name, $default = '')
    {
        return config(IdeReferenceServiceProvider::CONFIG_NAMESPACE . ".model.$name", $default);
    }

    protected function generate($tableWithoutPrefix)
    {
        $schema = $this->getTableSchema(strtolower($tableWithoutPrefix));
        if (!$schema->columns) {
            $tableWithoutPrefix = Str::toSnakeCase($tableWithoutPrefix);
            $schema = $this->getTableSchema($tableWithoutPrefix);
        }
        if (!$schema->columns) {
            $this->alert('No column found, maybe prefix missing ?');
            if ($this->option('force')) {
                sleep(2);
            } else {
                return;
            }
        }
        $tableWithoutPrefix = substr($schema->tableName, strlen(DB::getTablePrefix()));
        $isAbstract = $this->option('abstract');
        $namespace = $this->config('namespace', 'App\Models');
        if ($isAbstract) $namespace .= '\Contracts';

        $columns = $rules = [];
        $useSoftDeletes = false;
        foreach ($schema->columns as $column) {
            if ($column->columnName === 'deleted_at') $useSoftDeletes = true;
            $description = ($column->columnDefault === null ? '' : "[Default " . Helper::dump($column->columnDefault, true) . "] ") . $column->columnComment;
            $columns[] = [
                $this->getColumnType($column->columnType), $column->columnName, $description
            ];

            // Do not set rules for primary key, for they are always auto increment
            if ($column->columnKey !== 'PRI' || $column->extra !== 'auto_increment') {
                $rules[$column->columnName] = $this->getColumnRules($column);
            }
        }

        $modelName = ucfirst(ColumnSchema::camelCase($tableWithoutPrefix)) . ($isAbstract ? 'Contract' : '');
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
            'fullTableName' => $schema->tableName,
            'tableName' => $tableWithoutPrefix,
            'relations' => $relations,
            'uses' => $this->uses,
            'isAbstract' => $isAbstract
        ]);
        if ($this->option('without-writing')) {
            $this->line($content);
        } else {
            $this->write($modelName, $content);
        }

        if ($this->option('abstract')) {
            $this->generateModelIfNotExists($namespace, $tableWithoutPrefix, $useSoftDeletes);
        }
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

    protected function generateModelIfNotExists(string $namespace, string $table, bool $useSoftDeletes)
    {
        $namespacePartials = explode('\\', $namespace);
        array_pop($namespacePartials);
        $namespace = implode('\\', $namespacePartials);
        $model = ucfirst(ColumnSchema::camelCase($table));
        $filename = $this->concatPath(base_path(), $this->config('dir'), "{$model}.php");
        if (file_exists($filename)) {
            $this->line(sprintf('<comment>Skipping</comment> <info>%s</info>, exited already.', substr($filename, strlen(base_path()) + 1)));
            return false;
        } else {
            if ($useSoftDeletes) {
                $useHead = "\nuse " . SoftDeletes::class . ";\n";
                $useBody = "\n    use SoftDeletes;";
            } else {
                $useHead = '';
                $useBody = '';
            }

            file_put_contents(
                $filename,
                <<<PHP
<?php

namespace {$namespace};
{$useHead}
class {$model} extends Contracts\\{$model}Contract
{{$useBody}
}

PHP
            );
            $this->line("Model <info>$model</info> created.");
            return true;
        }
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
     * @return array
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
                $rules = [new DumperExpression('Rule::in(' . Helper::dump($column->values, true) . ')')];
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
                return '\Carbon\Carbon';
            default:
                return $type;
        }
    }

    protected function render($params)
    {
        $path = __DIR__ . '/../templates/model.php';
        if (!file_exists($path)) throw new FileNotFoundException($path);
        extract($params);

        ob_start();
        include $path;
        return ob_get_clean();
    }

    protected function getTableSchema($table)
    {
        return new TableSchema($table);
    }

    protected function getArguments()
    {
        return [
            ['tables', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Name(s)/Model(s) for the table']
        ];
    }

    protected function getOptions()
    {
        return [
            ['dir', 'd', InputOption::VALUE_OPTIONAL, 'Directory for the models to be placed', 'App\Models'],
            ['overwrite', null, InputOption::VALUE_NONE, 'Overwrite if model exists when passed'],
            ['force', 'f', InputOption::VALUE_NONE, 'Force to create model even when no column fetched from database'],
            ['without-writing', null, InputOption::VALUE_NONE, 'Return the content without writing into file'],
            ['abstract', null, InputOption::VALUE_NONE, 'Create an abstract class'],
        ];
    }

}
