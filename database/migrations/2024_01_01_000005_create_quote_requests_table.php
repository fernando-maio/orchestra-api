<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('category_id');
            $table->uuid('created_by');

            $table->string('title');
            $table->text('technical_description'); // What the organizer needs
            $table->text('specifications')->nullable(); // Detailed specs

            // Budget range
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();

            // Deadlines
            $table->datetime('response_deadline')->nullable(); // When vendors must respond
            $table->datetime('delivery_deadline')->nullable(); // When service must be delivered

            // Status
            $table->enum('status', ['draft', 'open', 'in_review', 'closed', 'canceled'])->default('draft');

            // Settings
            $table->integer('max_proposals')->nullable(); // Limit number of proposals
            $table->boolean('is_urgent')->default(false);
            $table->json('requirements')->nullable(); // Additional requirements

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('status');
            $table->index('response_deadline');
        });

        // Pivot: vendors invited to this quote request
        Schema::create('quote_request_vendor', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_request_id');
            $table->uuid('vendor_id');

            // Magic Link for vendor access
            $table->string('token', 64)->unique();
            $table->timestamp('token_expires_at')->nullable();

            // Tracking
            $table->enum('status', ['pending', 'sent', 'viewed', 'responded', 'expired'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->integer('send_attempts')->default(0);
            $table->text('send_error')->nullable();

            $table->timestamps();

            $table->foreign('quote_request_id')
                ->references('id')
                ->on('quote_requests')
                ->onDelete('cascade');

            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');

            $table->unique(['quote_request_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_request_vendor');
        Schema::dropIfExists('quote_requests');
    }
};
