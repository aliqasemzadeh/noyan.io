<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $compiledPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'views';

        File::ensureDirectoryExists($compiledPath);

        putenv('VIEW_COMPILED_PATH='.$compiledPath);
        $_ENV['VIEW_COMPILED_PATH'] = $compiledPath;
        $_SERVER['VIEW_COMPILED_PATH'] = $compiledPath;

        parent::setUp();
    }
}
