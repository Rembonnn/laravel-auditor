<?php

return [


    /*
    |--------------------------------------------------------------------------
    | Performance Metrics
    |--------------------------------------------------------------------------
    | Set to true to enable performance metrics collection,
    | or false to disable it. This allows you to control
    | whether metrics are recorded for monitoring purposes.
    */
    'performance_metrics' => env(key: 'AUDITOR_ENABLE_PERFORMANCE', default: true),

    /*
    |--------------------------------------------------------------------------
    | Auditor Views
    |--------------------------------------------------------------------------
    | Set to true to enable auditor views for monitoring,
    | or false to disable them. This controls the visibility
    | of auditor-related data in the application.
    */
    'views' => env(key: 'AUDITOR_ENABLE_VIEWS', default: true),

    /*
    |--------------------------------------------------------------------------
    | User Model Class Naming
    |--------------------------------------------------------------------------
    | Specify the naming convention for user model classes used in the project.
    | This setting allows customization of model names to match user
    | preferences and project requirements.
    */
    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Owner ID Configuration
    |--------------------------------------------------------------------------
    | Define the owner ID for resources related to the user.
    | This setting allows you to specify the user ID that
    | acts as the owner for managing project resources.
    */
    'user_owner_key' => 'id',
];
