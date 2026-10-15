<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * First-run setup on Hostinger: seeds reference data and creates the owner account.
 * Non-interactive mode reads OWNER_NAME / OWNER_EMAIL / OWNER_PASSWORD from .env.
 */
final class InstallCommand extends Command
{
    protected $signature = 'woodex:install {--force : Run the reference data seeder again}';

    protected $description = 'Seed Woodex OS reference data and create the owner account.';

    public function handle(): int
    {
        $this->components->info('Woodex OS setup');

        $this->call('db:seed', ['--class' => ProductionSeeder::class, '--force' => true]);

        if (User::role('owner')->exists() && ! $this->option('force')) {
            $this->components->info('An owner account already exists. Nothing else to do.');

            return self::SUCCESS;
        }

        $name = (string) (config('woodex.owner.name') ?: ($this->input->isInteractive() ? text('Owner name', default: 'Imtiaz Ahmad', required: true) : ''));
        $email = Str::lower((string) (config('woodex.owner.email') ?: ($this->input->isInteractive() ? text('Owner email', required: true) : '')));
        $secret = (string) (config('woodex.owner.password') ?: ($this->input->isInteractive() ? password('Owner password (min 10 characters, letters and numbers)', required: true) : ''));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', Password::default()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $secret,
            'title' => 'Director',
            'is_active' => true,
        ]);
        $user->assignRole('owner');

        $this->components->info("Owner account created for {$email}. Turn on two-factor authentication after the first login.");

        return self::SUCCESS;
    }
}
