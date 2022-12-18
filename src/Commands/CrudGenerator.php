<?php

namespace Ibex\CrudGenerator\Commands;

use Illuminate\Support\Str;

/**
 * Class CrudGenerator.
 *
 * @author  Awais <asargodha@gmail.com>
 */
class CrudGenerator extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud
                            {name : Table name}
                            {--route= : Custom route name}
                            {--path= : For architecture mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create bootstrap or react CRUD operations';

    protected $architectureMode = 'default';

    /**
     * Execute the console command.
     *
     * @return bool|null
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    public function handle()
    {
        $this->correctPaths();

        $this->info('Running Crud Generator ...');

        $this->table = $this->getNameInput();

        // If table not exist in DB return
        if (!$this->tableExists()) {
            $this->error("`{$this->table}` table not exist");

            return false;
        }

        // Build the class name from table name
        $this->name = $this->_buildClassName();

        // Generate the crud
        $this->buildOptions()
            ->buildElements()
            ->buildWiring()
            ->buildViews();

        $this->info('Created Successfully.');

        return true;
    }

    protected function buildElements()
    {
        $build_mode = config('crud.architecture_mode', $this->architectureMode);

        switch ($build_mode) {
            case 'ddd':
                return $this
                ->buildController(false)
                ->buildModel(false)
                ->buildEntity()
                ->buildRepository()
                ->buildUseCases();
                break;
            case 'hexagonal':
                return $this
                ->buildController(false)
                ->buildModel(false)
                ->buildEntity()
                ->buildRepository()
                ->buildUseCases();
                break;
            default:
                return $this
                ->buildController()
                ->buildModel();
                break;
        }
    }

    /**
     * Build the Controller Class and save in app/Http/Controllers.
     *
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildController($orig = true)
    {
        $controllerPath = $this->_getControllerPath($this->name);
        $stub = ($orig)? 'Controller_ORIG' : 'Controller';

        if ($this->files->exists($controllerPath) && $this->ask('Already exist Controller. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Creating Controller ...');

        $replace = $this->buildReplacements();

        $controllerTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub($stub)
        );

        $this->write($controllerPath, $controllerTemplate);

        return $this;
    }

    protected function buildWiring()
    {
        $this->info('Creating Wirings ...');

        $this->info(' - Routes: web...');
        $content = file_get_contents("./routes/web.php");
        $new_content = $this->buildWebRoutes();
        $exist = strpos(str_replace(["\t", "\n", " "], "", $content), str_replace(["\t", "\n", " "], "", $new_content));

        if ($exist !== FALSE && $this->ask('Already exist this WEB route. Do you want to add new one (y/n)?', 'n') == 'n') {
            //
        } else {
            file_put_contents("./routes/web.php", $new_content, FILE_APPEND);
        }

        $this->info(' - Routes: api...');
        $content = file_get_contents("./routes/api.php");
        $new_content = $this->buildApiRoutes();
        $exist = strpos(str_replace(["\t", "\n", " "], "", $content), str_replace(["\t", "\n", " "], "", $new_content));

        if ($exist !== FALSE && $this->ask('Already exist this API route. Do you want to add new one (y/n)?', 'n') == 'n') {
            //
        } else {
            file_put_contents("./routes/api.php", $new_content, FILE_APPEND);
        }

        $this->info(' - Bindings...');
        $bindings = $this->buildBindings();

        $content = file_get_contents("./app/Providers/AppServiceProvider.php");
        preg_match('/function\sregister+\s*\((.*?)\)\s*\{(.*?)\}/s', $content, $matches);
        
        $new_content = str_replace($matches[0], str_replace($matches[2],  $matches[2]."\n\t\t".$bindings['controller_bind']."\n\t\t".$bindings['repository_bind']."\n\t", $matches[0]), $content);
        file_put_contents("./app/Providers/AppServiceProvider.php", $new_content);

        return $this;
        
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildModel($orig = true)
    {
        $modelPath = $this->_getModelPath($this->name);
        $stub = ($orig)? 'Model_ORIG' : 'Model';

        if ($this->files->exists($modelPath) && $this->ask('Already exist Model. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Creating Model ...');

        // Make the models attributes and replacement
        $replace = array_merge($this->buildReplacements(), $this->modelReplacements());

        $modelTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub($stub)
        );

        $this->write($modelPath, $modelTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildEntity()
    {
        $entityPath = $this->_getEntityPath($this->name);

        if ($this->files->exists($entityPath) && $this->ask('Already exist Entity. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Creating Entity ...');

        // Make the entitys attributes and replacement
        $replace = array_merge($this->buildReplacements(), $this->entityReplacements());

        $entityTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('Entity')
        );

        $this->write($entityPath, $entityTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildRepository()
    {
        $repositoryPath = $this->_getRepositoryPath($this->name);

        if ($this->files->exists($repositoryPath) && $this->ask('Already exist Repository. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('Creating Repository ...');

        // Make the models attributes and replacement
        $replace = $this->buildReplacements();

        $repositoryTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('Repository')
        );

        $this->write($repositoryPath, $repositoryTemplate);

        $repositoryInterfacePath = $this->_getRepositoryInterfacePath($this->name);

        if ($this->files->exists($repositoryInterfacePath) && $this->ask('Already exist RepositoryInterface. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('  - Interface ...');

        // Make the models attributes and replacement
        $replace = $this->buildReplacements();

        $repositoryInterfaceTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('RepositoryInterface')
        );

        $this->write($repositoryInterfacePath, $repositoryInterfaceTemplate);

        $repositoryExceptionPath = $this->_getRepositoryExceptionPath($this->name);

        if ($this->files->exists($repositoryExceptionPath) && $this->ask('Already exist RepositoryException. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        $this->info('  - Exception ...');

        // Make the models attributes and replacement
        $replace = $this->buildReplacements();

        $repositoryExceptionTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('ExceptionRepository')
        );

        $this->write($repositoryExceptionPath, $repositoryExceptionTemplate);

        return $this;
    }


    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     */
    protected function buildUseCases()
    {
        $this->info('Creating Usecase ...');

        $createUsecasePath = $this->_getUseCasePath('Create'.$this->name);

        if ($this->files->exists($createUsecasePath) && $this->ask('Already exist Create UseCase. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }
        $updateUsecasePath = $this->_getUseCasePath('Update'.$this->name);
        if ($this->files->exists($updateUsecasePath) && $this->ask('Already exist Update UseCase. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }
        $deleteUsecasePath = $this->_getUseCasePath('Delete'.$this->name);
        if ($this->files->exists($deleteUsecasePath) && $this->ask('Already exist UseCase. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }
        $getUsecasePath = $this->_getUseCasePath('Get'.$this->name);
        if ($this->files->exists($getUsecasePath) && $this->ask('Already exist Get UseCase. Do you want overwrite (y/n)?', 'y') == 'n') {
            return $this;
        }

        // Make the models attributes and replacement
        $replace = $this->buildReplacements();

        $this->info('  - Create Usecase ...');
        $createUsecaseTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('usecases/CreateUseCase')
        );
        $this->write($createUsecasePath, $createUsecaseTemplate);

        $this->info('  - Update Usecase ...');
        $updateUsecaseTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('usecases/UpdateUseCase')
        );
        $this->write($updateUsecasePath, $updateUsecaseTemplate);

        $this->info('  - Delete Usecase ...');
        $deleteUsecaseTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('usecases/DeleteUseCase')
        );
        $this->write($deleteUsecasePath, $deleteUsecaseTemplate);

        $this->info('  - Get Usecase ...');
        $getUsecaseTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub('usecases/GetUseCase')
        );
        $this->write($getUsecasePath, $getUsecaseTemplate);

        return $this;
    }

    /**
     * @return $this
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @throws \Exception
     */
    protected function buildViews()
    {
        $this->info('Creating Views ...');

        $front_builder = $this->front.'_front';
        return $this->$front_builder();
    }

    protected function react_front()
    {
        $tableHead = "\n";
        $tableBody = "\n";
        $form = "\n";
        $filter = "\n";
        $filterArray = "{\n";
        $definition = "{\n";
        $selectors = "{\n";

        $modelNamePluralLowerCase = Str::camel(Str::plural($this->name));
        $modelNamePluralUpperCase = ucfirst(Str::plural($this->name));
        $modelNameLowerCase =  Str::camel($this->name);

        $columns = $this->getFilteredColumns();
        $columnsInfo = $this->getColumnsInfo();

        foreach ($columns as $column) {
            $title = Str::title(str_replace('_', ' ', $column));
            $tableHead .= $this->getHeadReact($title);
            $tableBody .= $this->getBodyReact($column);
            $form .= $this->getFieldReact($title, $columnsInfo[$column]);
            $filter .= $this->getFilterReact(Str::title(Str::snake($title, ' ')), $column);
            $filterArray .= "\t\t\t\t".$column.": { name: '".$title."', code: '".$column."' },\n";
            $definition .= "\t\t\t\t".$column.": ".$this->getFieldAttributeReact($columnsInfo[$column]).",\n";
            if (str_starts_with($columnsInfo[$column]->Type, 'enum')) {
                $selectors .= "\t\t\t\t".$column.": ".$this->getListFromEnum($columnsInfo[$column]->Type).",\n";
            } 
        }
        $definition .= "\t\t\t},";
        $filterArray .= "\t\t\t}\n";
        $selectors .= "\t\t\t}";

        $replace = [
            '{{tableHead}}' => $tableHead,
            '{{tableBody}}' => $tableBody,
            '{{modelName}}' => $this->name,
            '{{modelNamePluralLowerCase}}' => $modelNamePluralLowerCase,
            '{{modelNamePluralUpperCase}}' => $modelNamePluralUpperCase,
            '{{modelNameLowerCase}}' => $modelNameLowerCase,
            '{{tableBody}}' => $tableBody,
            '{{form}}' => $form,
            '{{filter}}' => $filter,
            '{{definition}}' => $definition,
            '{{filterArray}}' => $filterArray,
            '{{selectors}}' => $selectors
        ];

        $pageTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub("react_front/pages/index")
        );
        $this->write($this->makeDirectory(resource_path('/js/Pages/'.$this->name.'/CRUD/index.jsx')), $pageTemplate);

        $manageTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub("react_front/pages/manage")
        );
        $this->write($this->makeDirectory(resource_path('/js/Pages/'.$this->name.'/CRUD/manage.jsx')), $manageTemplate);

        $serviceTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub("react_front/services/Service")
        );
        $this->write($this->makeDirectory(resource_path('/js/Services/'.$this->name.'Service.js')), $serviceTemplate);

        foreach (['List', 'Manage'] as $view) {
            $componentTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub("react_front/components/{$view}")
            );
            $this->write($this->makeDirectory(resource_path('/js/Components/'.$this->name.'/'.$modelNamePluralUpperCase.$view.'.jsx')), $componentTemplate);
        }

        $componentTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub("react_front/components/Paginator")
        );
        $this->write($this->makeDirectory(resource_path('/js/Components/Paginator.jsx')), $componentTemplate);

        $componentTemplate = str_replace(
            array_keys($replace), array_values($replace), $this->getStub("react_front/components/Loader")
        );
        $this->write($this->makeDirectory(resource_path('/js/Components/Loader.jsx')), $componentTemplate);

        return $this;
    }

    protected function default_front()
    {
        $tableHead = "\n";
        $tableBody = "\n";
        $viewRows = "\n";
        $form = "\n";

        foreach ($this->getFilteredColumns() as $column) {
            $title = Str::title(str_replace('_', ' ', $column));

            $tableHead .= $this->getHead($title);
            $tableBody .= $this->getBody($column);
            $viewRows .= $this->getField($title, $column, 'view-field');
            $form .= $this->getField($title, $column, 'form-field');
        }

        $replace = array_merge($this->buildReplacements(), [
            '{{tableHeader}}' => $tableHead,
            '{{tableBody}}' => $tableBody,
            '{{viewRows}}' => $viewRows,
            '{{form}}' => $form,
        ]);

        $this->buildLayout();

        foreach (['index', 'create', 'edit', 'form', 'show'] as $view) {
            $viewTemplate = str_replace(
                array_keys($replace), array_values($replace), $this->getStub("default_front/views/{$view}")
            );

            $this->write($this->_getViewPath($view), $viewTemplate);
        }

        return $this;
    }

    /**
     * Make the class name from table name.
     *
     * @return string
     */
    private function _buildClassName()
    {
        return Str::studly(Str::singular($this->table));
    }
}
