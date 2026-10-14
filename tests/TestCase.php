<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (\Spatie\Permission\Models\Role::count() === 0) {
                $this->seed(RolesAndPermissionsSeeder::class);
            }
        } catch (\Throwable $e) {
            // Silently continue if tables are not yet migrated
        }
    }
}
