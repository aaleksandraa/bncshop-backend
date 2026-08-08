<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class GrantAdminAccessCommand extends Command
{
    protected $signature = 'bnc:grant-admin {email : Admin user email address} {--role=Super Admin : Role to assign}';

    protected $description = 'Assign Admin/Super Admin role to a Filament user created via make:filament-user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $roleName = (string) $this->option('role');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        if ($user->is_customer || $user->is_b2b_customer) {
            $this->error('User is flagged as customer/B2B customer and cannot access admin panel.');

            return self::FAILURE;
        }

        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null) {
            $this->warn("Role '{$roleName}' not found. Running RolesAndPermissionsSeeder...");
            $this->call('db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]);
            $role = Role::query()->where('name', $roleName)->first();
        }

        if ($role === null) {
            $this->error("Role '{$roleName}' still not found after seeding.");

            return self::FAILURE;
        }

        if (! $user->hasRole($roleName)) {
            $user->assignRole($roleName);
        }

        $user->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => false,
        ])->save();

        $loginPath = $roleName === 'B2B Admin' ? '/b2b-admin/login' : '/admin/login';

        $this->info("Granted '{$roleName}' to {$email}. Login at {$loginPath}");

        return self::SUCCESS;
    }
}
