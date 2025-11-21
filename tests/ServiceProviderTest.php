<?php

namespace ArieTimmerman\Laravel\SCIMServer\Tests;

use ArieTimmerman\Laravel\SCIMServer\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase as BaseTestCase;

class ServiceProviderTest extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function testMigrationsCanBePublished()
    {
        // Test that the vendor:publish command can find the migration tag
        $result = Artisan::call('vendor:publish', [
            '--tag' => 'laravel-scim-migrations',
            '--provider' => ServiceProvider::class,
            '--dry-run' => true,
        ]);

        // The command should succeed (return 0)
        $this->assertEquals(0, $result, 'Migrations should be publishable via vendor:publish command');
    }

    public function testConfigCanBePublished()
    {
        // Test that the vendor:publish command can find the config tag
        $result = Artisan::call('vendor:publish', [
            '--tag' => 'laravel-scim',
            '--provider' => ServiceProvider::class,
            '--dry-run' => true,
        ]);

        // The command should succeed (return 0)
        $this->assertEquals(0, $result, 'Config should be publishable via vendor:publish command');
    }

    public function testMigrationFileExistsInPackage()
    {
        // Verify the migration file exists in the package
        $migrationPath = realpath(__DIR__ . '/../database/migrations/2021_01_01_000003_add_scim_fields_to_users_table.php');
        
        $this->assertFileExists($migrationPath, 'Migration file should exist in the package');
    }
}
