<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests: full Laravel app + DB refresh between each test
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
// Unit tests: plain TestCase, no DB
pest()->extend(TestCase::class)
    ->in('Unit');
