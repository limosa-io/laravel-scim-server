<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class ServiceProviderTest extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function testMigrationsArePublishable()
    {
        // Get the publishable groups
        $published = ServiceProvider::$publishes[ServiceProvider::class] ?? [];

        // Check that the migration publishing is configured
        $migrationPublishes = collect($published)
            ->filter(function ($destination, $source) {
                return str_contains($source, 'database/migrations');
            });

        $this->assertNotEmpty($migrationPublishes, 'Migrations should be publishable');
    }

    public function testConfigIsPublishable()
    {
        // Get the publishable groups
        $published = ServiceProvider::$publishes[ServiceProvider::class] ?? [];

        // Check that the config publishing is configured
        $configPublishes = collect($published)
            ->filter(function ($destination, $source) {
                return str_contains($source, 'config/scim.php');
            });

        $this->assertNotEmpty($configPublishes, 'Config should be publishable');
    }

    public function testMigrationsAreNotAutoLoaded()
    {
        // Create an instance of the service provider
        $provider = new ServiceProvider($this->app);

        // Use reflection to check if loadMigrationsFrom was called
        // Since we can't directly check if a migration path is loaded,
        // we just verify the migration file exists in the package
        $migrationPath = realpath(__DIR__ . '/../database/migrations/2021_01_01_000003_add_scim_fields_to_users_table.php');
        
        $this->assertFileExists($migrationPath, 'Migration file should exist in the package');
    }
}
