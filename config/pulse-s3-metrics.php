<?php

return [

    'enabled' => env('PULSE_S3_METRICS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | AWS Config for Pulse S3 Metrics
    |--------------------------------------------------------------------------
    |
    | You may set the credentials used for connecting to AWS CloudWatch;
    | your existing AWS credentials will be used from your .env file.
    | You may need to override these if you are using a different
    | AWS account (with different permissions) for CloudWatch.
    |
    */

    'key' => env('AWS_ACCESS_KEY_ID'),

    'secret' => env('AWS_SECRET_ACCESS_KEY'),

    'region' => env('AWS_DEFAULT_REGION'),

    'bucket' => env('AWS_BUCKET'),

    // Must be one of the following: StandardStorage | IntelligentTieringFAStorage | IntelligentTieringIAStorage | OneZoneIAStorage | ReducedRedundancyStorage | StandardIAStorage
    'class' => env('AWS_STORAGE_CLASS', 'StandardStorage'),

];
