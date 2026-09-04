<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Organizations
            'organizations.view',
            'organizations.create',
            'organizations.update',
            'organizations.delete',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Events
            'events.view',
            'events.create',
            'events.update',
            'events.delete',

            // Vendors
            'vendors.view',
            'vendors.create',
            'vendors.update',
            'vendors.delete',
            'vendors.verify',
            'vendors.approve',

            // Categories
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',

            // Quote Requests
            'quote-requests.view',
            'quote-requests.create',
            'quote-requests.update',
            'quote-requests.delete',
            'quote-requests.send',

            // Proposals
            'proposals.view',
            'proposals.create',
            'proposals.update',
            'proposals.delete',
            'proposals.approve',
            'proposals.reject',

            // Reports
            'reports.view',
            'reports.export',

            // Settings
            'settings.view',
            'settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo([
            'users.view', 'users.create', 'users.update',
            'events.view', 'events.create', 'events.update', 'events.delete',
            'vendors.view', 'vendors.create', 'vendors.update',
            'categories.view',
            'quote-requests.view', 'quote-requests.create', 'quote-requests.update', 'quote-requests.delete', 'quote-requests.send',
            'proposals.view', 'proposals.approve', 'proposals.reject',
            'reports.view', 'reports.export',
            'settings.view', 'settings.update',
        ]);

        $organizer = Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web']);
        $organizer->givePermissionTo([
            'events.view', 'events.create', 'events.update',
            'vendors.view',
            'categories.view',
            'quote-requests.view', 'quote-requests.create', 'quote-requests.update', 'quote-requests.send',
            'proposals.view', 'proposals.approve', 'proposals.reject',
            'reports.view',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo([
            'events.view',
            'vendors.view',
            'categories.view',
            'quote-requests.view',
            'proposals.view',
            'reports.view',
        ]);
    }
}
