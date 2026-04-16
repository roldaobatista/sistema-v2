<?php

return [
    /*
    |--------------------------------------------------------------------------
    | eSocial Environment
    |--------------------------------------------------------------------------
    |
    | 'production' for the live government endpoint,
    | 'restricted' for the testing/homologação endpoint.
    |
    */
    'environment' => env('ESOCIAL_ENVIRONMENT', 'restricted'),

    /*
    |--------------------------------------------------------------------------
    | eSocial Schema Version
    |--------------------------------------------------------------------------
    |
    | The eSocial schema version used for XML generation.
    |
    */
    'version' => env('ESOCIAL_VERSION', 'S-1.2'),
];
