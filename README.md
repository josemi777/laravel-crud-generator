![Laravel Crud Generator](https://josemisr.com/images/Laravel%20CRUD%20ddd%20and%20react.png)


![Packagist](https://img.shields.io/badge/Packagist-v1.3.2-green.svg?style=flat-square)
![Licence](https://img.shields.io/badge/Licence-MIT-green.svg?style=flat-square)
![StyleCI](https://img.shields.io/badge/StyleCI-pass-green.svg?style=flat-square)


This Laravel Generator package provides and generate Controller, Model (with eloquent relations) and Views in **Bootstrap** or **React** and by **DDD** architecture, if you want, for your development of your applications with single command.

- Will create **Model** with Eloquent relations
- Will create **Controller** with all resources
- Will create **DDD/Hexagonal** Architecture
- Will create **UseCases**
- Will create **Repositories** with **Interface** and custom **Exception**
- Will create **Entities**
- Will create **views** in `Bootstrap` or `React`, yo can choose it in config file
- Will create **Bindings** Routes web and api, and autowiring on `AppProvider` file

## Requirements
    Laravel >= 8.0
    PHP >= 8.1

## Installation
1. Install
    ```
    composer require jmsr/crud-generator-laravel --dev
    ```
2. Publish the default package's config
    ```
    php artisan vendor:publish --tag=crud
    ```

## Usage

- Add `ITEMS_PER_PAGE = 10` to your .env file


- Use these commands

  ```
  php artisan make:crud {table_name}

  php artisan make:crud banks
  ```

- Add a route in `web.php`

  ```
  Route::resource('banks', 'BankController');
  ```
  **Route name in plural slug case.*

- Copy `BaseEntity.stub` file from `vendor/jmsr/src/stubs` to your `src` directory and rename it to `BaseEntity.php`
  


## Options

- ### Bootstrap

    In this case you don't need to change anything y confi file.

   - Custom Route

        ```
        php artisan make:crud {table_name} --route={route_name}
        ```

- ### React

    You need to change config file `architecture_mode` to *ddd* and `front` to *react*

   - Do everything under context

        ```
        php artisan make:crud {table_name} --path={context_name}
        ```
