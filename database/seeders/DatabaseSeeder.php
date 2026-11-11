<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Creates one demo user with a random password and prints it. Local use only.
     */
    public function run(): void
    {
        $password = Str::password(16, symbols: false);

        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => $password],
        );

        $this->command?->info("Demo user #{$user->id}: demo@example.com / {$password}");
    }
}
