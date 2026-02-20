<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->nullable(); // Preferred vendors per organization
            $table->string('trade_name'); // Nome fantasia
            $table->string('legal_name')->nullable(); // Razão social
            $table->string('cnpj', 18)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('website')->nullable();

            // Address and Geolocation
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('service_radius_km')->default(50); // Raio de atendimento

            // Profile
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();

            // Capabilities
            $table->boolean('accepts_urgent')->default(false); // Selo de urgência
            $table->boolean('is_verified')->default(false);
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('total_ratings')->default(0);

            // ESG Tags
            $table->boolean('is_local_business')->default(false);
            $table->boolean('is_sustainable')->default(false);
            $table->boolean('is_minority_owned')->default(false);

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            // Indexes for geospatial queries
            $table->index(['latitude', 'longitude']);
            $table->index('service_radius_km');
        });

        // Pivot table: vendor_category
        Schema::create('category_vendor', function (Blueprint $table) {
            $table->uuid('category_id');
            $table->uuid('vendor_id');

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');

            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');

            $table->primary(['category_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_vendor');
        Schema::dropIfExists('vendors');
    }
};
