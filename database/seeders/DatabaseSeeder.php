<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'giovanny.ramirez.24e@utzmg.edu.mx',
        ], [
            'name' => 'Giovanny Ramirez',
            'email_verified_at' => now(),
            'password' => Hash::make('Password1234'),
            'role' => 'resident',
            'departamento' => 101,
        ]);
    }
}
