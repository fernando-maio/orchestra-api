<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_request_id');
            $table->uuid('vendor_id');

            // Values
            $table->decimal('total_value', 15, 2);
            $table->decimal('labor_value', 15, 2)->nullable();
            $table->decimal('material_value', 15, 2)->nullable();
            $table->decimal('transport_value', 15, 2)->nullable();
            $table->decimal('taxes_value', 15, 2)->nullable();
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->string('currency', 3)->default('BRL');

            // Payment terms
            $table->string('payment_terms')->nullable();
            $table->integer('payment_days')->nullable(); // Days to pay after approval
            $table->text('payment_conditions')->nullable();

            // Delivery
            $table->date('delivery_date')->nullable();
            $table->integer('setup_hours')->nullable(); // Hours needed for setup
            $table->text('delivery_notes')->nullable();

            // Proposal details
            $table->text('description')->nullable();
            $table->json('items')->nullable(); // Itemized list
            $table->json('included_services')->nullable();
            $table->json('excluded_services')->nullable();
            $table->text('observations')->nullable();

            // Attachments
            $table->string('pdf_path')->nullable(); // Original PDF uploaded
            $table->json('ai_extracted_data')->nullable(); // Data extracted by AI from PDF

            // Price Lock (once accepted, this is the final price)
            $table->decimal('locked_value', 15, 2)->nullable();
            $table->timestamp('locked_at')->nullable();

            // Status
            $table->enum('status', ['draft', 'pending', 'under_review', 'approved', 'rejected', 'contracted'])->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            // Validity
            $table->date('valid_until')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('quote_request_id')
                ->references('id')
                ->on('quote_requests')
                ->onDelete('cascade');

            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('status');
            $table->index('total_value');
            $table->unique(['quote_request_id', 'vendor_id']);
        });

        // Proposal history for audit trail
        Schema::create('proposal_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('proposal_id');
            $table->uuid('user_id')->nullable();

            $table->string('action'); // created, updated, approved, rejected, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->foreign('proposal_id')
                ->references('id')
                ->on('proposals')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_histories');
        Schema::dropIfExists('proposals');
    }
};
