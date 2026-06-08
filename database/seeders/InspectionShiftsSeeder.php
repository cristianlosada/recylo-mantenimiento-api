<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InspectionShiftsSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            ['name' => 'Turno A — Mañana',   'start_time' => '06:00', 'end_time' => '14:00', 'is_active' => true],
            ['name' => 'Turno B — Tarde',    'start_time' => '14:00', 'end_time' => '22:00', 'is_active' => true],
            ['name' => 'Turno C — Noche',    'start_time' => '22:00', 'end_time' => '06:00', 'is_active' => true],
            ['name' => 'Turno Diurno',       'start_time' => '07:00', 'end_time' => '17:00', 'is_active' => true],
            ['name' => 'Turno Administrativo','start_time' => '08:00', 'end_time' => '18:00', 'is_active' => true],
        ];

        foreach ($shifts as $shift) {
            DB::table('inspection_shifts')->updateOrInsert(
                ['name' => $shift['name']],
                [...$shift, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->command->info('5 turnos de inspección creados (globales)');
    }
}
