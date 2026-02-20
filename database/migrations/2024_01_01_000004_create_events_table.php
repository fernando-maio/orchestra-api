<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('created_by'); // User who created

            $table->string('name');
            $table->text('description')->nullable();
            $table->text('briefing')->nullable(); // AI can suggest categories based on this

            // Dates
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Location with Geolocation
            $table->string('venue_name')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('state', 2);
            $table->string('zip_code', 10)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Budget
            $table->decimal('estimated_budget', 15, 2)->nullable();
            $table->decimal('actual_budget', 15, 2)->nullable();
            $table->string('currency', 3)->default('BRL');

            // Expected attendees
            $table->integer('expected_attendees')->nullable();

            // Status
            $table->enum('status', ['draft', 'active', 'completed', 'canceled'])->default('draft');

            // Settings
            $table->json('required_categories')->nullable(); // Categories needed for this event
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Indexes
            $table->index(['latitude', 'longitude']);
            $table->index('status');
            $table->index('start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
