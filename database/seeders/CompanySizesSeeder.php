<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySizesSeeder extends Seeder
{
    /**
     * Seed company sizes according to Colombian law
     * Based on Ley 590 de 2000 and Ley 905 de 2004
     */
    public function run(): void
    {
        $sizes = [
            [
                'code' => 'MICRO',
                'name' => 'Microempresa',
                'min_employees' => 1,
                'max_employees' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'SMALL',
                'name' => 'Pequeña Empresa',
                'min_employees' => 11,
                'max_employees' => 50,
                'is_active' => true,
            ],
            [
                'code' => 'MEDIUM',
                'name' => 'Mediana Empresa',
                'min_employees' => 51,
                'max_employees' => 200,
                'is_active' => true,
            ],
            [
                'code' => 'LARGE',
                'name' => 'Gran Empresa',
                'min_employees' => 201,
                'max_employees' => null,
                'is_active' => true,
            ],
        ];

        foreach ($sizes as $size) {
            DB::table('company_sizes')->insertOrIgnore([
                'code' => $size['code'],
                'name' => $size['name'],
                'min_employees' => $size['min_employees'],
                'max_employees' => $size['max_employees'],
                'is_active' => $size['is_active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Company sizes seeded successfully!');
    }
}
