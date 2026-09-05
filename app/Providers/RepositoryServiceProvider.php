<?php

namespace App\Providers;

use App\Contracts\Repositories\CategoryRepositoryInterface;
// Category
use App\Contracts\Repositories\DashboardRepositoryInterface;
use App\Contracts\Repositories\EventRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Contracts\Services\DashboardServiceInterface;
use App\Contracts\Services\EventServiceInterface;
// Vendor
use App\Contracts\Services\UserServiceInterface;
use App\Contracts\Services\VendorServiceInterface;
use App\Repositories\CategoryRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\EventRepository;
use App\Repositories\UserRepository;
use App\Repositories\VendorRepository;
// Event
use App\Services\CategoryService;
use App\Services\DashboardService;
use App\Services\EventService;
use App\Services\UserService;
use App\Services\VendorService;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Mapeamento de interfaces para implementações
     */
    public array $bindings = [
        // Repositories
        CategoryRepositoryInterface::class => CategoryRepository::class,
        VendorRepositoryInterface::class => VendorRepository::class,
        EventRepositoryInterface::class => EventRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        DashboardRepositoryInterface::class => DashboardRepository::class,

        // Services
        CategoryServiceInterface::class => CategoryService::class,
        VendorServiceInterface::class => VendorService::class,
        EventServiceInterface::class => EventService::class,
        UserServiceInterface::class => UserService::class,
        DashboardServiceInterface::class => DashboardService::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        foreach ($this->bindings as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
