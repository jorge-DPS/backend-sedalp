<?php

namespace Database\Seeders;

use App\Models\People\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Director', 'Responsable', 'Técnico', 'Administrativo', 'Comunicador'] as $name) {
            Position::withTrashed()->firstOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
