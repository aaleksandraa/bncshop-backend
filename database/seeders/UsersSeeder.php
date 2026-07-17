<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use RuntimeException;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            $this->command?->warn('UsersSeeder preskočen u produkciji. Koristite: php artisan make:filament-user');

            return;
        }

        $this->seedAdminUser();
        $this->seedSellerUser();
    }

    private function seedAdminUser(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@bncshop.test');
        $password = $this->resolvePassword('ADMIN_PASSWORD', 'Admin123!');
        $role = Role::findOrCreate('Super Admin');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('ADMIN_NAME', 'BNC Admin'),
                'password' => $password,
                'phone' => null,
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => false,
        ])->save();

        $user->syncRoles([$role->name]);

        $this->command?->info("Admin korisnik kreiran: {$email}");
    }

    private function seedSellerUser(): void
    {
        $email = (string) env('SELLER_EMAIL', 'prodavac@bncshop.test');
        $password = $this->resolvePassword('SELLER_PASSWORD', 'Prodavac123!');
        $role = Role::findOrCreate('Prodavac');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('SELLER_NAME', 'BNC Prodavac'),
                'password' => $password,
                'phone' => null,
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => false,
        ])->save();

        $user->syncRoles([$role->name]);

        $this->command?->info("Prodavač kreiran: {$email}");
    }

    private function resolvePassword(string $envKey, string $localDefault): string
    {
        $password = env($envKey);

        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (App::environment('local', 'testing')) {
            return $localDefault;
        }

        throw new RuntimeException("Postavite {$envKey} prije pokretanja seedera.");
    }
}
