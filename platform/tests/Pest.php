<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot Laravel with a fresh, seeded SQLite database (phpunit.xml).
| Unit tests are plain PHP (Money, Phone, QuoteCalculator…) and run without the framework.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
