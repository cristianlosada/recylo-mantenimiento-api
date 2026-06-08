<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener Colombia por código
        $colombia = DB::table('countries')->where('code', 'COL')->first();
        
        // Obtener el tipo de documento Cédula de Ciudadanía
        $documentType = DB::table('document_types')->where('code', 'CC')->first();
        
        // Crear empresa de prueba
        $company = Company::updateOrCreate(
            ['tax_id' => '901234567-1'],
            [
                'legal_name' => 'Empresa Demo RECYLO S.A.S.',
                'trade_name' => 'Empresa Demo RECYLO +',
                'country_id' => $colombia->id,
                'status' => 'active'
            ]
        );

        // Crear usuario Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@recylodemo.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Administrador',
                'password' => Hash::make('password123'),
                'document_type_id' => $documentType->id,
                'document_number' => '12345678',
                'status' => 'active',
                'email_verified_at' => now()
            ]
        );

        // Asociar usuarios con la empresa
        DB::table('user_companies')->insertOrIgnore([
            [
                'user_id' => $superAdmin->id,
                'company_id' => $company->id,
                'employee_code' => 'EMP001',
                'status' => 'active',
                'is_primary' => true,
                'hire_date' => now()->subYear(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Obtener roles
        $superAdminRole = Role::where('code', 'SUPER_ADMIN')->first();

        // Asignar roles a usuarios
        DB::table('user_roles')->insertOrIgnore([
            [
                'user_id' => $superAdmin->id,
                'role_id' => $superAdminRole->id,
                'company_id' => null, // Rol global
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        $this->command->info('✅ Datos de prueba creados exitosamente:');
        $this->command->info('🏢 Empresa: Empresa Demo RECYLO +');
        $this->command->info('👤 Super Admin: admin@recylodemo.com / password123');
    }
}
