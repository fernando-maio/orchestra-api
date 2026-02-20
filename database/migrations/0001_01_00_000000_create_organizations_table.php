<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('cnpj', 18)->unique()->nullable();
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('logo_path')->nullable();

            // Address
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 10)->nullable();

            // Subscription
            $table->enum('subscription_status', ['trial', 'active', 'past_due', 'canceled'])->default('trial');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->string('subscription_plan')->default('basic');

            // Settings
            $table->boolean('white_label_enabled')->default(false);
            $table->json('settings')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
