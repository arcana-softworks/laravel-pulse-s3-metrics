<?php

namespace Arcana\PulseS3Metrics\Tests;

use Arcana\PulseS3Metrics\Recorders\S3Metrics;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase, WithWorkbench;

    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        $this->usesTestingFeature(new WithMigration('laravel', 'queue'));

        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Arcana\\PulseS3Metrics\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    public function getEnvironmentSetUp($app)
    {
        // Load the testing .env file.
        $app->useEnvironmentPath(__DIR__.'/../workbench');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        config()->set('database.default', 'testing');

        config()->set('pulse.recorders', [
            S3Metrics::class => [
                'enabled' => config('pulse-s3-metrics.enabled'),
            ],
        ]);

        parent::getEnvironmentSetUp($app);
    }
}
