<?php

namespace Ibex\CrudGenerator\Commands;

use Ibex\CrudGenerator\ModelGenerator;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Class GeneratorCommand.
 */
abstract class GeneratorCommand extends Command
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Do not make these columns fillable in Model or views.
     *
     * @var array
     */
    protected $unwantedColumns = [
        'id',
        'password',
        'email_verified_at',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Table name from argument.
     *
     * @var string
     */
    protected $table = null;

    /**
     * Formatted Class name from Table.
     *
     * @var string
     */
    protected $name = null;

    /**
     * Store the DB table columns.
     *
     * @var array
     */
    private $tableColumns = null;

    /**
     * Model Namespace.
     *
     * @var string
     */
    protected $modelNamespace = 'App';

    /**
     * Controller Namespace.
     *
     * @var string
     */
    protected $controllerNamespace = 'App\Http\Controllers';

    /**
     * Entity Namespace.
     *
     * @var string
     */
    protected $entityNamespace = 'Domain\Entity';

    /**
     * Repository Namespace.
     *
     * @var string
     */
    protected $repositoryNamespace = 'Infrastructure\Repository';

    /**
     * RepositoryInterface Namespace.
     *
     * @var string
     */
    protected $repositoryInterfaceNamespace = 'Domain\Repository';

    /**
     * Repository Exception Namespace.
     *
     * @var string
     */
    protected $exceptionRepositoryNamespace = 'Domain\Exception\Repository';

    /**
     * UseCase Namespace.
     *
     * @var string
     */
    protected $usecaseNamespace = 'Application\Usecase';

    /**
     * Application Layout
     *
     * @var string
     */
    protected $layout = 'layouts.app';

    /**
     * Custom Options name
     *
     * @var array
     */
    protected $options = [];

    protected $architecture = 'default';
    protected $srcPath = 'Src';
    protected $front = 'default';

    /**
     * Create a new controller creator command instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
        $this->unwantedColumns = config('crud.model.unwantedColumns', $this->unwantedColumns);
        $this->modelNamespace = config('crud.model.namespace', $this->modelNamespace);
        $this->controllerNamespace = config('crud.controller.namespace', $this->controllerNamespace);
        $this->srcPath = config('crud.src', $this->srcPath);
        $this->architecture = config('crud.architecture_mode', $this->architecture);
        $this->layout = config('crud.layout', $this->layout);
        $this->front = config('crud.front', $this->front);
    }

    protected function correctPaths()
    {
        $path = $this->option('path');
        $prefix = '';
        $subfix = '';

        if ($path) {
            if ($this->architecture == 'ddd') {
                $prefix = '';
                $subfix = '\\'.$path;
            } else if ($this->architecture == 'hexagonal') {
                $prefix = $path.'\\';
                $subfix = '';
            }
        }

        $this->entityNamespace = $this->srcPath.'\\'.$prefix.$this->entityNamespace.$subfix;
        $this->repositoryNamespace = $this->srcPath.'\\'.$prefix.$this->repositoryNamespace.$subfix;
        $this->repositoryInterfaceNamespace = $this->srcPath.'\\'.$prefix.$this->repositoryInterfaceNamespace.$subfix;
        $this->exceptionRepositoryNamespace = $this->srcPath.'\\'.$prefix.$this->exceptionRepositoryNamespace.$subfix;
        $this->usecaseNamespace = $this->srcPath.'\\'.$prefix.$this->usecaseNamespace.$subfix;
    }

    /**
     * Generate the controller.
     *
     * @return $this
     */
    abstract protected function buildController();

    /**
     * Generate the Model.
     *
     * @return $this
     */
    abstract protected function buildModel();

    /**
     * Generate the views.
     *
     * @return $this
     */
    abstract protected function buildViews();

    /**
     * Build the directory if necessary.
     *
     * @param string $path
     *
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }

        return $path;
    }

    /**
     * Write the file/Class.
     *
     * @param $path
     * @param $content
     */
    protected function write($path, $content)
    {
        $this->files->put($path, $content);
    }

    /**
     * Get the stub file.
     *
     * @param string $type
     * @param boolean $content
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function getStub($type, $content = true)
    {
        $stub_path = config('crud.stub_path', 'default');
        if ($stub_path == 'default') {
            $stub_path = __DIR__ . '/../stubs/';
        }

        $path = Str::finish($stub_path, '/') . "{$type}.stub";

        if (!$content) {
            return $path;
        }

        return $this->files->get($path);
    }

    /**
     * @param $no
     *
     * @return string
     */
    private function _getSpace($no = 1)
    {
        $tabs = '';
        for ($i = 0; $i < $no; $i++) {
            $tabs .= "\t";
        }

        return $tabs;
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getControllerPath($name)
    {
        return app_path($this->_getNamespacePath($this->controllerNamespace) . "{$name}Controller.php");
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getEntityPath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePathOutOfApp($this->entityNamespace) . "{$name}Entity.php"));
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getRepositoryPath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePathOutOfApp($this->repositoryNamespace) . "{$name}Repository.php"));
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getRepositoryInterfacePath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePathOutOfApp($this->repositoryInterfaceNamespace) . "{$name}RepositoryInterface.php"));
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getRepositoryExceptionPath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePathOutOfApp($this->exceptionRepositoryNamespace) . "{$name}RepositoryException.php"));
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getUseCasePath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePathOutOfApp($this->usecaseNamespace) . "{$name}UseCase.php"));
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function _getModelPath($name)
    {
        return $this->makeDirectory(app_path($this->_getNamespacePath($this->modelNamespace) . "{$name}.php"));
    }

    /**
     * Get the path from namespace.
     *
     * @param $namespace
     *
     * @return string
     */
    private function _getNamespacePath($namespace)
    {
        $str = Str::start(Str::finish(Str::after($namespace, 'App'), '\\'), '\\');

        return str_replace('\\', '/', $str);
    }

    /**
     * Get the path from namespace.
     *
     * @param $namespace
     *
     * @return string
     */
    private function _getNamespacePathOutOfApp($namespace)
    {
        $str = lcfirst($namespace)."/";

        return str_replace('\\', '/', '../'.$str);
    }

    /**
     * Get the default layout path.
     *
     * @return string
     */
    private function _getLayoutPath()
    {
        return $this->makeDirectory(resource_path("/views/layouts/app.blade.php"));
    }

    /**
     * @param $view
     *
     * @return string
     */
    protected function _getViewPath($view)
    {
        $name = Str::kebab($this->name);

        return $this->makeDirectory(resource_path("/views/{$name}/{$view}.blade.php"));
    }

    /**
     * Build the replacement.
     *
     * @return array
     */
    protected function buildReplacements()
    {
        return [
            '{{layout}}' => $this->layout,
            '{{modelName}}' => $this->name,
            '{{modelTitle}}' => Str::title(Str::snake($this->name, ' ')),
            '{{modelNamespace}}' => $this->modelNamespace,
            '{{controllerNamespace}}' => $this->controllerNamespace,
            '{{entityNamespace}}' => $this->entityNamespace,
            '{{usecaseNamespace}}' => $this->usecaseNamespace,
            '{{repositoryNamespace}}' => $this->repositoryNamespace,
            '{{repositoryInterfaceNamespace}}' => $this->repositoryInterfaceNamespace,
            '{{exceptionRepositoryNamespace}}' => $this->exceptionRepositoryNamespace,
            '{{modelNamePluralLowerCase}}' => Str::camel(Str::plural($this->name)),
            '{{modelNamePluralUpperCase}}' => ucfirst(Str::plural($this->name)),
            '{{modelNameLowerCase}}' => Str::camel($this->name),
            '{{modelRoute}}' => $this->options['route'] ?? Str::kebab(Str::plural($this->name)),
            '{{modelView}}' => Str::kebab($this->name),
        ];
    }

    /**
     * Build the form fields for form.
     *
     * @param $title
     * @param $column
     * @param string $type
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function getField($title, $column, $type = 'form-field')
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
            '{{column}}' => $column,
        ]);

        return str_replace(
            array_keys($replace), array_values($replace), $this->getStub("default_front/views/{$type}")
        );
    }

    /**
     * Build the form fields for form.
     *
     * @param $title
     * @param $column
     * @param string $type
     *
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function getFieldReact($title, $column)
    {
        $stub = "react_front/form_stubs/form-field-";

        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
            '{{column}}' => $column->Field,
            '{{value}}' => $column->Default
        ]);

        switch (true) {
            case str_starts_with($column->Type, "tinyint(1)"):
                $stub .= "onoff";
                break;
            case str_starts_with($column->Type, "int"):
                $stub .= "number";
                break;
            case str_starts_with($column->Type, "double") || str_starts_with($column->Type, "float"):
                $stub .= "decimal-number";
                break;
            case str_starts_with($column->Type, "enum"):
                $stub .= "select";
                break;
            case str_starts_with($column->Type, "text"):
                $stub .= "textarea";
                break;
            default:
                $stub .= "text";
                break;
        }

        return str_replace(
            array_keys($replace), array_values($replace), $this->getStub($stub)
        );
    }

    protected function getFieldAttributeReact($column)
    {
        switch (true) {
            case str_starts_with($column->Type, "tinyint(1)"):
                return ($column->Default)? 1 : 0;
            case str_starts_with($column->Type, "int"):
                return ($column->Default)?? 0;
            case str_starts_with($column->Type, "double") || str_starts_with($column->Type, "float"):
                return ($column->Default)?? 0;
            case str_starts_with($column->Type, "enum"):
                $value = $column->Default?? "";
                return "'".$value."'";
            case str_starts_with($column->Type, "text"):
                return "''";
            default:
                $value = $column->Default?? "";
                return "'".$value."'";
        }
    }

    protected function getListFromEnum($string)
    {
        $result = '[';
        preg_match("/\(([^)]+)\)/", $string, $matches);
        $array = explode(",", $matches[1]);
        foreach($array as $item) {
            $result .= "
                \t\t{ name: ".Str::title(Str::snake($item, ' ')).", code: ".$item."},";
        }
        return $result."\n\t\t\t\t]";
    }

    protected function getFilterReact($title, $column)
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
            '{{column}}' => $column,
        ]);

        return str_replace(
            array_keys($replace), array_values($replace), $this->getStub("react_front/filter-li")
        );
    }

    /**
     * @param $title
     *
     * @return mixed
     */
    protected function getHead($title)
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
        ]);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->_getSpace(10) . '<th>{{title}}</th>' . "\n"
        );
    }

    /**
     * @param $title
     *
     * @return mixed
     */
    protected function getHeadReact($title)
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{title}}' => $title,
        ]);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->_getSpace(11) . '<th scope="col" className="text-sm font-medium text-white px-6 py-2">{{title}}</th>' . "\n"
        );
        
    }

    /**
     * @param $column
     *
     * @return mixed
     */
    protected function getBody($column)
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{column}}' => $column,
        ]);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->_getSpace(11) . '<td>{{ ${{modelNameLowerCase}}->{{column}} }}</td>' . "\n"
        );
    }

    protected function buildWebRoutes()
    {
        $routes = "\n\tRoute::prefix('{{modelNameLowerCase}}')->group(function () {
        Route::get('/', function () { return Inertia::render('{{modelName}}/CRUD/index'); })->name('{{modelNameLowerCase}}.index');
        Route::get('/create', function () { return Inertia::render('{{modelName}}/CRUD/manage'); })->name('{{modelNameLowerCase}}.manage.create');
        Route::get('/edit/{id}', function (\$id) { return Inertia::render('{{modelName}}/CRUD/manage', ['id' => \$id]); })->name('{{modelNameLowerCase}}.manage.edit');
    });";

        $replace = array_merge($this->buildReplacements(), []);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $routes
        );
    }

    protected function buildApiRoutes()
    {
        $routes = "\nRoute::group(['prefix' => '{{modelNameLowerCase}}'], function() {
    Route::controller(\App\Http\Controllers\{{modelName}}Controller::class)->group(function () {
        Route::get('/get', 'getAction');
        Route::delete('/delete', 'deleteAction');
        Route::post('/create', 'createAction');
        Route::put('/update', 'updateAction');
    });
});";

        $replace = array_merge($this->buildReplacements(), []);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $routes
        );
    }

    protected function buildBindings()
    {
        $path = ($this->option('path')) ? '\\'.$this->option('path') : '';

        $replace = array_merge($this->buildReplacements(), []);
        $controller_bind = "\$this->app->bind({{modelName}}Controller::class, \App\Http\Controllers\{{modelName}}Controller::class);";
        $repository_bind = "\$this->app->bind(\Src".$path."\Domain\Repository\{{modelName}}RepositoryInterface::class, \Src".$path."\Infrastructure\Repository\{{modelName}}Repository::class);";

        return [
            'controller_bind' => str_replace(
                array_keys($replace),
                array_values($replace),
                $controller_bind
            ),
            'repository_bind' => str_replace(
                array_keys($replace),
                array_values($replace),
                $repository_bind
            )
        ];
    }

    /**
     * @param $column
     *
     * @return mixed
     */
    protected function getBodyReact($column)
    {
        $replace = array_merge($this->buildReplacements(), [
            '{{column}}' => $column,
        ]);

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $this->_getSpace(12) . '<td className="px-6 py-2 whitespace-nowrap text-sm font-medium text-gray-900">' . "\n".
            $this->_getSpace(13) . '<div className="text-sm text-gray-500">'. "\n" .
            $this->_getSpace(14) . '{ {{modelNameLowerCase}}.{{column}} }'. "\n" .
            $this->_getSpace(13) . '</div>'. "\n".
            $this->_getSpace(12) . '</td>' . "\n"
        );
    }

    /**
     * Make layout if not exists.
     *
     * @throws \Exception
     */
    protected function buildLayout(): void
    {
        if (!(view()->exists($this->layout))) {

            $this->info('Creating Layout ...');

            if ($this->layout == 'layouts.app') {
                $this->files->copy($this->getStub('default_front/layouts/app', false), $this->_getLayoutPath());
            } else {
                throw new \Exception("{$this->layout} layout not found!");
            }
        }
    }

    /**
     * Get the DB Table columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        if (empty($this->tableColumns)) {
            $this->tableColumns = DB::select('SHOW COLUMNS FROM ' . $this->table);
        }

        return $this->tableColumns;
    }

    /**
     * @return array
     */
    protected function getFilteredColumns()
    {
        $unwanted = $this->unwantedColumns;
        $columns = [];

        foreach ($this->getColumns() as $column) {
            $columns[] = $column->Field;
        }

        return array_filter($columns, function ($value) use ($unwanted) {
            return !in_array($value, $unwanted);
        });
    }

    /**
     * @return array
     */
    protected function getColumnsInfo()
    {
        $unwanted = $this->unwantedColumns;
        $columns = [];

        foreach ($this->getColumns() as $column) {
            $columns[$column->Field] = $column;
        }

        return array_filter($columns, function ($value) use ($unwanted) {
            return !in_array($value, $unwanted);
        });
    }

    /**
     * Make model attributes/replacements.
     *
     * @return array
     */
    protected function modelReplacements()
    {
        $properties = '*';
        $rulesArray = [];
        $softDeletesNamespace = $softDeletes = '';
        $is_id_string = false;

        foreach ($this->getColumns() as $value) {
            $properties .= "\n * @property $$value->Field";

            if ($value->Null == 'NO') {
                $rulesArray[$value->Field] = 'required';
            }

            if ($value->Field == 'id') {
                if ($this->isColumnString($value->Type)) {
                    $is_id_string = true;
                }
            }

            if ($value->Field == 'deleted_at') {
                $softDeletesNamespace = "use Illuminate\Database\Eloquent\SoftDeletes;\n";
                $softDeletes = "use SoftDeletes;\n";
            }
        }

        $rules = function () use ($rulesArray) {
            $rules = '';
            // Exclude the unwanted rulesArray
            $rulesArray = Arr::except($rulesArray, $this->unwantedColumns);
            // Make rulesArray
            foreach ($rulesArray as $col => $rule) {
                $rules .= "\n\t\t'{$col}' => '{$rule}',";
            }

            return $rules;
        };

        $fillable = function () {

            /** @var array $filterColumns Exclude the unwanted columns */
            $filterColumns = $this->getFilteredColumns();

            // Add quotes to the unwanted columns for fillable
            array_walk($filterColumns, function (&$value) {
                $value = "'" . $value . "'";
            });

            // CSV format
            return implode(',', $filterColumns);
        };

        $properties .= "\n *";

        list($relations, $properties) = (new ModelGenerator($this->table, $properties, $this->modelNamespace))->getEloquentRelations();

        $boot = ($is_id_string)? 'public static function boot()
        {
            parent::boot();
            static::creating(function($obj){
                $obj->id = RamseyUuid::uuid4()->toString();
            });
        }': '';

        return [
            '{{fillable}}' => $fillable(),
            '{{rules}}' => $rules(),
            '{{relations}}' => $relations,
            '{{properties}}' => $properties,
            '{{softDeletesNamespace}}' => $softDeletesNamespace,
            '{{softDeletes}}' => $softDeletes,
            '{{casts}}' => ($is_id_string)? "'id' => 'string'" : '',
            '{{incrementing}}' => ($is_id_string)? 'false' : 'true',
            '{{boot}}' => $boot,
            '{{uuid}}' => ($is_id_string)? 'use Ramsey\Uuid\Uuid as RamseyUuid;' : '',
        ];
    }

    /**
     * Make model attributes/replacements.
     *
     * @return array
     */
    protected function entityReplacements()
    {
        $attributes = "";

        foreach ($this->getColumns() as $value) {
            $attributes .= "\tprotected $$value->Field";

            if ($value->Default) {
                $attributes .= $this->transformDefault($value);
            }

            $attributes .= ";\n";
        }

        return [
            '{{entityAttributes}}' => $attributes
        ];
    }

    private function transformDefault($value)
    {
        $type = $value->Type;

        if($this->isColumnString($type)){
            return " = '".$value->Default."'";
        } else {
            return " = '".$value->Default."'";
        }
    }

    private function isColumnString($type)
    {
        return !( (strpos($type, 'int') !== false) ||
        (strpos($type, 'float') !== false) ||
        (strpos($type, 'decimal') !== false) ||
        (strpos($type, 'float') !== false) ||
        (strpos($type, 'numeric') !== false)
        );
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Build the options
     *
     * @return $this|array
     */
    protected function buildOptions()
    {
        $route = $this->option('route');

        if (!empty($route)) {
            $this->options['route'] = $route;
        }

        return $this;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the table'],
        ];
    }

    /**
     * Is Table exist in DB.
     *
     * @return mixed
     */
    protected function tableExists()
    {
        return Schema::hasTable($this->table);
    }
}
