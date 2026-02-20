<?php

namespace Tests\Traits;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait TestHelpers
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createOrganization(array $attributes = []): Organization
    {
        return Organization::factory()->create($attributes);
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('super-admin');

        return $user;
    }

    protected function createOrgAdmin(?Organization $organization = null, array $attributes = []): User
    {
        $organization ??= $this->createOrganization();

        $user = User::factory()->forOrganization($organization)->create($attributes);
        $user->assignRole('admin');

        return $user;
    }

    protected function createOrgUser(?Organization $organization = null, string $role = 'organizer', array $attributes = []): User
    {
        $organization ??= $this->createOrganization();

        $user = User::factory()->forOrganization($organization)->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function actingAsSuperAdmin(?User $user = null): User
    {
        $user ??= $this->createSuperAdmin();
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function actingAsAdmin(?Organization $organization = null, ?User $user = null): User
    {
        $user ??= $this->createOrgAdmin($organization);
        $this->actingAs($user, 'sanctum');

        return $user;
    }

    protected function actingAsOrgUser(?Organization $organization = null, string $role = 'organizer'): User
    {
        $user = $this->createOrgUser($organization, $role);
        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
