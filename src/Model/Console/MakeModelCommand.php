<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-04-30
 */

namespace Overlord\Model\Console;

use Carbon\Carbon;
use Exception;
use fk\helpers\Dumper;
use fk\helpers\DumperExpression;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Overlord\Exceptions\InvalidArgumentException;
use Overlord\Model\Support\ColumnSchema;
use Overlord\Model\Support\TableSchema;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * The generator follows Laravel convention:
 * plural name for the table, singular for middle table(many-to-many)
 * and the model
 */
class MakeModelCommand extends ModelMakeCommand
{

    protected $name = 'overlord:model';

    protected $description = 'Generate model class instead of using `php artisan make:model`';

    protected $type = 'Model(s)';

    /**
     * Contains all class imports and their aliases.
     *
     * @var array
     */
    protected array $uses = [];

    /**
     * Index to indicates which table is used, in case more than one tables are given
     *
     * @var int
     */
    protected int $offset = 0;

    /**
     * Current table schema that is used to generate model.
     * This is internally used by generator.
     *
     * @var TableSchema|null
     */
    protected ?TableSchema $currentSchema = null;

    protected array $globalTransKeys = [];

    public function __construct(Filesystem $files)
    {
        parent::__construct($files);

        $this->globalTransKeys = $this->config('global_trans_keys');
    }

    protected function init()
    {
        $this->uses = [];
        $this->currentSchema = null;
        $this->offset = 0;
    }

    /**
     * @return void|bool
     * @throws FileNotFoundException
     */
    public function handle()
    {
        do {
            $this->init();
            $this->modelHandler();
        } while ($this->next());

        return true;
    }

    /**
     * Copy of {@see ModelMakeCommand::handle()},
     * custom generator to replace the original {@see GeneratorCommand::handle()}.
     *
     * @return bool
     * @throws FileNotFoundException
     */
    protected function modelHandler()
    {
        if ($this->generatorHandler() === false && !$this->option('force')) {
            return false;
        }

        if ($this->option('all')) {
            $this->input->setOption('factory', true);
            $this->input->setOption('seed', true);
            $this->input->setOption('migration', true);
            $this->input->setOption('controller', true);
            $this->input->setOption('resource', true);
        }

        if ($this->option('factory')) {
            $this->createFactory();
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }

        if ($this->option('seed')) {
            $this->createSeeder();
        }

        if ($this->option('controller') || $this->option('resource') || $this->option('api')) {
            $this->createController();
        }

        if ($this->option('oc')) {
            $this->createOverlordController();
        }
        return true;
    }

    protected function modelNamespace(): string
    {
        return $this->config('namespace', 'App\Models');
    }

    protected function createOverlordController()
    {
        $model = $this->modelNamespace() . '\\' . $this->toModelName($this->currentSchema->getBareTableName());

        $this->call('overlord:controller', ['--model' => $model, 'name' => class_basename($model) . 'Controller']);
    }

    /**
     * Copy of {@see GeneratorCommand::handle()}, remove the file writes part.
     *
     * @return bool
     * @throws FileNotFoundException
     */
    public function generatorHandler()
    {
        // First we need to ensure that the given name is not a reserved word within the PHP
        // language and that the class name will actually be valid. If it is not valid we
        // can error now and prevent from polluting the filesystem using invalid files.
        if ($this->isReservedName($this->getNameInput())) {
            $this->error('The name "' . $this->getNameInput() . '" is reserved by PHP . ');

            return false;
        }

        $name = $this->qualifyClass($this->getNameInput());

        $path = $this->getPath($name);

        // Next, We will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if ((!$this->hasOption('force') ||
                !$this->option('force')) &&
            $this->alreadyExists($this->getNameInput())) {
            $this->error($this->type . ' already exists!');

            return false;
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        $this->buildClass($name);

        $this->info($this->type . ' created successfully.');
        return true;
    }

    /**
     * Return one `name` when queried
     *
     * @param null|string $key
     * @return array|mixed|string|null
     */
    public function argument($key = null)
    {
        if ($key === 'name') {
            return $this->argument('names')[$this->offset];
        } else {
            return parent::argument($key);
        }
    }

    /**
     * @param string $rawName
     * @return false Indicates that the corresponding class should always be (re-)generated.
     */
    protected function alreadyExists($rawName)
    {
        return false;
    }

    protected function getStub()
    {
        return $this->option('pivot')
            ? $this->resolveStubPath('/stubs/model.pivot.stub')
            : __DIR__ . '/stubs/model.contract.stub.php';
    }

    /**
     * Build model class here only.
     * @param string $name
     * @return string|void
     * @throws FileNotFoundException|Exception
     */
    protected function buildClass($name)
    {
        if (Str::endsWith($this->getStub(), '.php')) {
            return $this->generateModel();
        } else {
            return parent::buildClass($name);
        }
    }

    /**
     * @throws Exception
     */
    protected function generateModel()
    {
        $this->generate($this->argument('name'));
    }

    protected function write(string $modelName, string $content)
    {
        $file = base_path($this->config('dir')) . "/Contracts/$modelName.php";
        $file = str_replace('\\', '/', $file);
        $existedAlready = file_exists($file);

        $this->writeFile($file, $content);

        $this->comment(sprintf("%s `%s` %s", 'Contract', $modelName, ($existedAlready ? 'updated.' : 'created.')));
    }

    protected function writeFile($filename, $content)
    {
        if ($this->option('raw')) {
            $this->output->writeln("<info># {$filename}</info>");
            $this->output->writeln($content);
        } else {
            $this->makeDirectory($filename);
            $this->files->put($filename, $content);
        }
    }

    protected function config($name, $default = '')
    {
        return config('overlord-model.' . $name, $default);
    }

    /**
     * @param string $name
     * @throws Exception
     */
    protected function generate(string $name)
    {
        $this->currentSchema = $schema = $this->getTableSchema($name);

        if (!$schema->columns) {
            throw new InvalidArgumentException(sprintf('No column found for <info>%s</info>, maybe prefix missing ?', $name));
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
        return ucfirst(Str::singular(Str::camel($tableName)));
    }

    protected function generateModelIfNotExists(string $namespace, string $table, bool $useSoftDeletes)
    {
        $schema = $this->currentSchema;
        $namespacePartials = explode('\\', $namespace);
        array_pop($namespacePartials);
        $namespace = implode('\\', $namespacePartials);
        $model = $this->toModelName($table);
        $filename = $this->concatPath(base_path(), $this->config('dir'), "{$model}.php");
        if (file_exists($filename)) {
            $this->line(sprintf('<comment>Skipping</comment> <info>%s</info>, existed already.', substr($filename, strlen(base_path()) + 1)));
            return false;
        }

        $this->writeFile(
            $filename,
            $this->render([
                'useSoftDeletes' => $useSoftDeletes,
                'namespace' => $namespace,
                'model' => $model,
                'columns' => $schema->columns,
            ], __DIR__ . '/stubs/model.stub.php')
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
        // todo, not implemented, should return Model methods like Model::findOrFail
        //      to generate for @method annotation.
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
     * @param string $model Name of the model, either fully qualified or not
     * @return string
     */
    protected function compareModelNamespace(string $namespace, string $model): string
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
     * @throws Exception
     */
    protected function getColumnRules(ColumnSchema $column)
    {
        $preferArray = $this->config('prefer_array_rules');
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
                $preferArray = true;
                break;
        }

        if ($column->columnKey === 'UNI') {
            $this->willUse(Rule::class);
            array_unshift($rules, new DumperExpression('Rule::unique($this->table)->ignore($this->id)'));
            $preferArray = true;
        }
        if ($column->isNullable) {
            array_unshift($rules, 'nullable');
        } else if ($column->columnDefault === null) {
            array_unshift($rules, 'required');
        }

        if (!$rules) $rules[] = '';

        return $preferArray ? $rules : implode('|', $rules);
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
                $this->willUse(Carbon::class);
                return class_basename(Carbon::class);
            default:
                return $type;
        }
    }

    protected function render($params, $file = null)
    {
        extract($params);
        ob_start();
        include($file ?? $this->getStub());
        return ob_get_clean();
    }

    /**
     * todo, should allow --table option to specify which table to generate base on.
     * @param string $table Name of the table, or one of its plural, singular, singular Model name form
     * @return TableSchema
     */
    protected function getTableSchema(string $table): TableSchema
    {
        // $table = App\Models\Users, should generate User model under directory app\Models
        /*
         * Users/users/user     -> App\Models\User
         * App\Models\User(s)   -> App\Models\User
         * App\Entities\User(s) -> App\Entities\User
         *
         * overlord:model A --table b ----> App\Models\A protected $table = 'b';
         */
        if (Str::contains($table, '\\')) {
            $this->laravel->getNamespace();
            $table = class_basename($table);
        }

        $schema = new TableSchema($table);
        if ($schema->columns) return $schema;

        $snake = Str::snake($table);

        if (strcasecmp($table, $snake) !== 0) {
            $schema = new TableSchema($snake);
            if ($schema->columns) return $schema;
        }

        $plural = Str::plural($snake);
        if (strcasecmp($plural, $snake) !== 0) {
            $schema = new TableSchema($plural);
            if ($schema->columns) return $schema;
        }

        return $schema;
    }

    protected function getArguments()
    {
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
            ['raw', null, InputOption::VALUE_NONE, 'Return the content to stdout instead of file'],
            ['oc', null, InputOption::VALUE_NONE, 'To create a <info>O</info>verlord <info>C</info>ontroller. This option requires installation of package <comment>yelfive/laravel-overlord</comment>'],
            // todo, --table to specify table, to generate model not by name convention
        ]);
    }

    /**
     * @param TableSchema $schema
     * @return array
     * @throws Exception
     */
    protected function generateContract(TableSchema $schema)
    {
        $table = $schema->getBareTableName();
        $namespace = $this->modelNamespace() . '\Contracts';

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
        $baseModelName = $this->config('base_model', 'App\Models\Model');
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
        $this->write($modelName, $content);

        return [$namespace, $table, $useSoftDeletes];
    }

}
