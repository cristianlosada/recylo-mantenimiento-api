<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contactTypes = [
            [
                'code' => 'EMAIL',
                'name' => 'Correo Electrónico',
                'validation_pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
            ],
            [
                'code' => 'PHONE_FIXED',
                'name' => 'Teléfono Fijo',
                'validation_pattern' => '^(\+57\s?)?[0-9]{7,8}$', // Formato colombiano
            ],
            [
                'code' => 'MOBILE',
                'name' => 'Teléfono Móvil/Celular',
                'validation_pattern' => '^(\+57\s?)?3[0-9]{9}$', // Formato colombiano
            ],
            [
                'code' => 'WHATSAPP',
                'name' => 'WhatsApp',
                'validation_pattern' => '^(\+57\s?)?3[0-9]{9}$', // Mismo formato móvil colombiano
            ],
            [
                'code' => 'FAX',
                'name' => 'Fax',
                'validation_pattern' => '^(\+57\s?)?[0-9]{7,8}$',
            ],
            [
                'code' => 'WEBSITE',
                'name' => 'Sitio Web',
                'validation_pattern' => '^https?:\/\/.+$',
            ],
            [
                'code' => 'LINKEDIN',
                'name' => 'LinkedIn',
                'validation_pattern' => '^https:\/\/(www\.)?linkedin\.com\/.+$',
            ],
            [
                'code' => 'FACEBOOK',
                'name' => 'Facebook',
                'validation_pattern' => '^https:\/\/(www\.)?facebook\.com\/.+$',
            ],
            [
                'code' => 'INSTAGRAM',
                'name' => 'Instagram',
                'validation_pattern' => '^@[a-zA-Z0-9._]{1,30}$',
            ],
            [
                'code' => 'EMERGENCY_CONTACT',
                'name' => 'Contacto de Emergencia',
                'validation_pattern' => '^(\+57\s?)?3[0-9]{9}$',
            ],
        ];

        foreach ($contactTypes as $type) {
            DB::table('contact_types')->insertOrIgnore([
                'code' => $type['code'],
                'name' => $type['name'],
                'validation_pattern' => $type['validation_pattern'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}