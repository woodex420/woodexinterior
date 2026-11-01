<?php

namespace Tests;

use App\Models\User;
use App\Support\Install\Installer;
use App\Support\Install\SiteState;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Filesystem\Filesystem;

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

    private ?string $installedSiteFixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Ordinary feature tests model an installed site, without borrowing a developer/hosting .env.
        // Use an owned temporary layout so even a local developer .env is never borrowed. Installer tests
        // can (and do) replace this binding with their own deliberately unconfigured site.
        $this->installedSiteFixture = sys_get_temp_dir().'/woodex-feature-state-'.bin2hex(random_bytes(8));
        $site = new SiteState($this->installedSiteFixture.'/domains/feature.test/public_html/platform', $this->installedSiteFixture);
        $directory = $site->externalDirectory();
        (new Filesystem)->makeDirectory($directory, 0700, recursive: true);
        file_put_contents($directory.'/.env', "# Synthetic installed-state marker only; QA runtime comes from process environment.\n");
        chmod($directory.'/.env', 0600);
        $this->app->instance(SiteState::class, $site);
        $this->app->forgetInstance(Installer::class);

        // Feature tests never depend on built front-end assets.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->installedSiteFixture !== null) {
                (new Filesystem)->deleteDirectory($this->installedSiteFixture);
                $this->installedSiteFixture = null;
            }
        }
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
