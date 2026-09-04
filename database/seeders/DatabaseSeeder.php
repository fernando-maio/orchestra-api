<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Run seeders
        $this->call([
            RolesAndPermissionsSeeder::class,
            CategoriesSeeder::class,
        ]);

        // Create Super Admin (without organization)
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@orchestra.local'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'is_active' => true,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // Create demo organization
        $demoOrg = Organization::firstOrCreate(
            ['email' => 'demo@orchestra.local'],
            [
                'name' => 'Empresa Demo',
                'legal_name' => 'Empresa Demo Ltda',
                'cnpj' => '00.000.000/0001-00',
                'phone' => '(11) 99999-9999',
                'subscription_status' => 'active',
                'subscription_plan' => 'professional',
                'subscription_ends_at' => now()->addYear(),
            ]
        );

        // Create demo admin user
        $demoAdmin = User::firstOrCreate(
            ['email' => 'demo@orchestra.local'],
            [
                'organization_id' => $demoOrg->id,
                'name' => 'Usuário Demo',
                'password' => 'password',
                'is_active' => true,
            ]
        );
        $demoAdmin->assignRole('admin');

        // Usuarios para testar os demais perfis. Todos na mesma organizacao do
        // demo, para que a diferenca observada seja de permissao e nao de
        // multi-tenancy.
        $demoOrganizer = User::firstOrCreate(
            ['email' => 'organizer@orchestra.local'],
            [
                'organization_id' => $demoOrg->id,
                'name' => 'Organizador Demo',
                'password' => 'password',
                'is_active' => true,
            ]
        );
        $demoOrganizer->assignRole('organizer');

        $demoViewer = User::firstOrCreate(
            ['email' => 'viewer@orchestra.local'],
            [
                'organization_id' => $demoOrg->id,
                'name' => 'Visualizador Demo',
                'password' => 'password',
                'is_active' => true,
            ]
        );
        $demoViewer->assignRole('viewer');

        // Seed vendors and events (depends on organization)
        $this->call([
            VendorsSeeder::class,
            EventsSeeder::class,
        ]);
    }
}
