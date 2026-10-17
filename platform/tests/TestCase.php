<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Feature tests run against seeded reference data (roles, stages, numbering, rate book, templates).
     *
     * @var bool
     */
    protected $seed = true;

    /**
     * @var class-string
     */
    protected $seeder = ProductionSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests never depend on built front-end assets.
        $this->withoutVite();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
