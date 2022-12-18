<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stubs Path
    |--------------------------------------------------------------------------
    |
    | The stubs path directory to generate crud. You may configure your
    | stubs paths here, allowing you to customize the own stubs of the
    | model,controller or view. Or, you may simply stick with the CrudGenerator defaults!
    |
    | Example: 'stub_path' => resource_path('path/to/views/stubs/')
    | Default: "default"
    | Files:
    |       Controller.stub
    |       Model.stub
    |       views/
    |            create.stub
    |            edit.stub
    |            form.stub
    |            form-field.stub
    |            index.stub
    |            show.stub
    |            view-field.stub
    */

    'stub_path' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Architecture mode
    |--------------------------------------------------------------------------
    |
    | Select the mode in wich your project architecture will be diposed
    |
    | Default: "default"
    | Options:
    |       "default"
    |       "ddd"
    |       "hexagonal"
    */

    'architecture_mode' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Application Layout
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application layout. This value is used when creating
    | views for crud. Default will be the "layouts.app".
    |
    */

    'layout' => 'layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Front Framework
    |--------------------------------------------------------------------------
    |
    | You can choose wich framework will be use for the crud, default of Laravel or
    | React
    |
    | Default: "default"
    | Options:
    |       "default"
    |       "react"
    |
    */

    'front' => 'default',


    'model' => [
        'namespace' => 'App\Models',

        /*
         * Do not make these columns $fillable in Model or views
         */
        'unwantedColumns' => [
            'id',
            'password',
            'email_verified_at',
            'remember_token',
            'created_at',
            'updated_at',
            'deleted_at',
        ],
    ],

    'controller' => [
        'namespace' => 'App\Http\Controllers',
    ],

    'src' => [
        'namespace' => 'Src',
    ],

];
