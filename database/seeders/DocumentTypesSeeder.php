<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colombiaId = DB::table('countries')->where('code', 'COL')->value('id');

        $documentTypes = [
            // Documentos colombianos
            [
                'country_id' => $colombiaId,
                'code' => 'CC',
                'name' => 'Cédula de Ciudadanía',
                'validation_pattern' => '^[0-9]{6,10}$',
                'max_length' => 10,
            ],
            [
                'country_id' => $colombiaId,
                'code' => 'CE',
                'name' => 'Cédula de Extranjería',
                'validation_pattern' => '^[0-9]{6,12}$',
                'max_length' => 12,
            ],
            [
                'country_id' => $colombiaId,
                'code' => 'TI',
                'name' => 'Tarjeta de Identidad',
                'validation_pattern' => '^[0-9]{6,11}$',
                'max_length' => 11,
            ],
            [
                'country_id' => $colombiaId,
                'code' => 'RC',
                'name' => 'Registro Civil',
                'validation_pattern' => '^[0-9]{10,11}$',
                'max_length' => 11,
            ],
            [
                'country_id' => $colombiaId,
                'code' => 'PA',
                'name' => 'Pasaporte',
                'validation_pattern' => '^[A-Z]{2}[0-9]{6,8}$',
                'max_length' => 10,
            ],
            [
                'country_id' => $colombiaId,
                'code' => 'NIT',
                'name' => 'NIT (Número de Identificación Tributaria)',
                'validation_pattern' => '^[0-9]{8,10}-[0-9]$',
                'max_length' => 12,
            ],
            // Documentos genéricos para otros países
            [
                'country_id' => null,
                'code' => 'DNI',
                'name' => 'Documento Nacional de Identidad',
                'validation_pattern' => '^[0-9A-Z]{5,15}$',
                'max_length' => 15,
            ],
            [
                'country_id' => null,
                'code' => 'PASSPORT',
                'name' => 'Pasaporte Internacional',
                'validation_pattern' => '^[A-Z0-9]{6,12}$',
                'max_length' => 12,
            ],
            [
                'country_id' => null,
                'code' => 'ID_CARD',
                'name' => 'Tarjeta de Identidad',
                'validation_pattern' => '^[0-9A-Z]{5,20}$',
                'max_length' => 20,
            ],
        ];

        foreach ($documentTypes as $type) {
            DB::table('document_types')->insertOrIgnore([
                'country_id' => $type['country_id'],
                'code' => $type['code'],
                'name' => $type['name'],
                'validation_pattern' => $type['validation_pattern'],
                'max_length' => $type['max_length'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}