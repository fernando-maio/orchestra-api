<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Category
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Repositories\CategoryRepository;
use App\Services\CategoryService;

// Vendor
use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Services\VendorServiceInterface;
use App\Repositories\VendorRepository;
use App\Services\VendorService;

// Event
use App\Contracts\Repositories\EventRepositoryInterface;
use App\Contracts\Services\EventServiceInterface;
use App\Repositories\EventRepository;
use App\Services\EventService;

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

        // Services
        CategoryServiceInterface::class => CategoryService::class,
        VendorServiceInterface::class => VendorService::class,
        EventServiceInterface::class => EventService::class,
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
