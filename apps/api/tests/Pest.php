<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/Unit/Packages/Execution/helpers.php';
require_once __DIR__.'/Support/BuildRunnerTestHelpers.php';

pest()
    ->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
