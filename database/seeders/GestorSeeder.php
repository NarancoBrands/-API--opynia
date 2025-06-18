<?php

namespace Database\Seeders;

use App\Models\Gestor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class GestorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Gestor::create([
            'nombre' => 'Gestor Principal',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->command->info('Usuario gestor creado exitosamente!');
    }
}
