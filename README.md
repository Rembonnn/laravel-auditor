# Laravel Auditor
The Laravel Auditing package is a comprehensive auditing solution for Laravel applications. It provides a way to track changes made to your models, enabling you to maintain a detailed record of data modifications

## What is Laravel Auditor
The Laravel Auditing package is designed to seamlessly integrate with your Laravel application, offering a robust and flexible solution for auditing your Eloquent models. It records every change made to your models, storing the old and new values of attributes, the user responsible for the changes, and timestamps. This allows you to maintain a detailed audit trail and ensures data integrity and accountability.

The package is highly customizable, enabling you to define which models and attributes should be audited, specify the storage location for audit logs, and configure how audits are queried and displayed. Additionally, it supports broadcasting audit events, which can be useful for triggering real-time notifications or other actions in response to data changes.

By using Laravel Auditing, you can enhance the transparency and reliability of your application, making it easier to debug issues, understand user actions, and maintain compliance with regulatory requirements.

## Key Features
- Keeping track of user actions
- Keeping track of what the user sees
- Keeping track of system changes
- Keeping track of databases 

## When to use Laravel Auditor ?
- Keeping track of user actions (responsibility)
- Third party integrations (request and response)
- Discover malicious activities in your applications
- Debugging purposes

## How to Install

### Step 1: Install the Package via Composer
Run the following command in your terminal to install the package:

```sh
composer require rembon/laravel-auditor
```

### Step 2: Register the Service Provider
Once the package is successfully installed, you need to register the service provider and publish the assets. Add the service provider to the providers array in `config/app.php`:

```php
'providers' => [
    /*
    * Laravel Framework Service Providers...
    */
    ...

    /*
    * Package Service Providers...
    */
    \Rembon\LaravelAuditor\LaravelAuditorServiceProvider::class,

    /*
    * Application Service Providers...
    */
    ...
],
```

Use these library on top of your Event Service Provider Files `app/Providers/EventServiceProvider.php`:
```php
<?php

...
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Rembon\LaravelAuditor\Listeners\AuthorizeMail;
use Rembon\LaravelAuditor\Listeners\AuthorizeNotification;
```

Then replace these code into `app/Providers/EventServiceProvider.php`
```php
protected $listen = [
    ...
    MessageSent::class => [
        AuthorizeMail::class,
    ],
    NotificationSent::class => [
        AuthorizeNotification::class,
    ],
];
```

### Step 3: Publish the Configuration Files
Run the following Artisan commands to publish the package configuration:

```sh
php artisan vendor:publish --tag=config
php artisan vendor:publish --tag=migration
php artisan vendor:publish --tag=public
php artisan vendor:publish --tag=view
```

### Step 4: Running the Migration Files
```sh
php artisan migrate --path=database/migrations/2050_06_14_042948_create_audits_table.php
```
and
```sh
php artisan migrate --path=database/migrations/2050_07_13_093233_create_performances_table.php
```

### Step 5: `Optional` Commands
Lastly, run the following optional commands:

```sh
php artisan composer:dump-autoload
```

## How to Use ?
Simple, Just put our Auditable Traits into your models

```php
<?php

use Rembon\LaravelAuditor\Traits\Auditable;

class User extends Authenticatable
{
    use ..., Auditable;
    
    ...
}

```

## Credits
- [Rembon Karya Digital](https://github.com/rembonnn)
- [DayCod](https://github.com/dayCod)
- [Ade Yusuf](https://github.com/adeyusuf211)
- [See All Contributors](https://github.com/rembonnn/laravel-auditor/contributors)
