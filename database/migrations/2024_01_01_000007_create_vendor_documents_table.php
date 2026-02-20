<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vendor compliance documents
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');

            $table->enum('type', [
                'cnpj_card',           // Cartão CNPJ
                'alvara',              // Alvará de funcionamento
                'insurance',           // Seguro
                'negative_certificate', // Certidão negativa
                'contract',            // Contrato social
                'technical_cert',      // Certificação técnica
                'other'
            ]);

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->integer('file_size');

            // Validity
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Verification
            $table->enum('status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');

            $table->foreign('verified_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('type');
            $table->index('status');
            $table->index('expiry_date');
        });

        // Vendor ratings after events
        Schema::create('vendor_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('event_id');
            $table->uuid('proposal_id');
            $table->uuid('rated_by');

            // Ratings (1-5)
            $table->tinyInteger('punctuality_rating'); // Pontualidade
            $table->tinyInteger('quality_rating'); // Qualidade
            $table->tinyInteger('communication_rating'); // Cordialidade/Comunicação
            $table->tinyInteger('price_rating'); // Custo-benefício
            $table->decimal('overall_rating', 3, 2); // Média calculada

            $table->text('comment')->nullable();
            $table->boolean('would_hire_again')->default(true);

            $table->timestamps();

            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');

            $table->foreign('proposal_id')
                ->references('id')
                ->on('proposals')
                ->onDelete('cascade');

            $table->foreign('rated_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->unique(['vendor_id', 'event_id', 'proposal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_ratings');
        Schema::dropIfExists('vendor_documents');
    }
};
