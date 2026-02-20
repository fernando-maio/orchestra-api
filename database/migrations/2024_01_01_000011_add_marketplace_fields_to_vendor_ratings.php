<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_ratings', function (Blueprint $table) {
            // Add organization_id
            $table->uuid('organization_id')->nullable()->after('rated_by');

            // Contract value for weighted ratings
            $table->decimal('contract_value', 15, 2)->default(0)->after('would_hire_again');

            // Is public flag
            $table->boolean('is_public')->default(true)->after('comment');

            // Dispute handling
            $table->enum('status', ['active', 'disputed', 'removed'])->default('active')->after('contract_value');
            $table->text('dispute_reason')->nullable()->after('status');
            $table->uuid('dispute_resolved_by')->nullable()->after('dispute_reason');
            $table->timestamp('disputed_at')->nullable()->after('dispute_resolved_by');
            $table->timestamp('resolved_at')->nullable()->after('disputed_at');
            $table->text('resolution_notes')->nullable()->after('resolved_at');

            // Foreign keys
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('set null');

            $table->foreign('dispute_resolved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Index
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_ratings', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['dispute_resolved_by']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'organization_id',
                'contract_value',
                'is_public',
                'status',
                'dispute_reason',
                'dispute_resolved_by',
                'disputed_at',
                'resolved_at',
                'resolution_notes',
            ]);
        });
    }
};
