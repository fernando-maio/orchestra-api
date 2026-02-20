<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Approval workflow
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'suspended'])
                ->default('pending')
                ->after('is_active');
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->uuid('approved_by')->nullable()->after('rejection_reason');
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Source of registration
            $table->enum('source', ['self_register', 'import', 'invite', 'admin'])
                ->default('admin')
                ->after('approved_at');

            // Invite token for vendor invitations
            $table->string('invite_token', 64)->nullable()->unique()->after('source');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->uuid('invited_by')->nullable()->after('invite_expires_at');

            // Subscription/Sponsorship tier
            $table->enum('subscription_tier', ['free', 'featured', 'premium'])
                ->default('free')
                ->after('invited_by');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_tier');

            // Contact person for the vendor
            $table->string('contact_name')->nullable()->after('subscription_expires_at');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 20)->nullable()->after('contact_email');

            // Foreign keys
            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Indexes for filtering
            $table->index('approval_status');
            $table->index('subscription_tier');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['invited_by']);
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['subscription_tier']);
            $table->dropIndex(['source']);

            $table->dropColumn([
                'approval_status',
                'rejection_reason',
                'approved_by',
                'approved_at',
                'source',
                'invite_token',
                'invite_expires_at',
                'invited_by',
                'subscription_tier',
                'subscription_expires_at',
                'contact_name',
                'contact_email',
                'contact_phone',
            ]);
        });
    }
};
