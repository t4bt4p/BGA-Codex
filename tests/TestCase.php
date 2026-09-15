<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $databasePath = dirname(__DIR__).'/database/testing.sqlite';
        if (! is_file($databasePath)) {
            touch($databasePath);
        }

        parent::setUp();

        // Keep Storage::fake() away from Docker-owned project files.
        app()->useStoragePath(sys_get_temp_dir().'/bga-codex-testing');
    }
}
