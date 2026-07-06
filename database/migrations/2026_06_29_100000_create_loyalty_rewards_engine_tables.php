<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loyalty_programs')) {
            Schema::create('loyalty_programs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('scope_type', 32)->default('global');
                $table->string('earn_scope', 16)->default('global');
                $table->string('redeem_scope', 16)->default('global');
                $table->boolean('allow_cross_branch_earn')->default(true);
                $table->boolean('allow_cross_branch_redeem')->default(true);
                $table->string('status', 16)->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'starts_at', 'ends_at']);
            });
        }

        if (! Schema::hasTable('loyalty_program_branches')) {
            Schema::create('loyalty_program_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->unique(['program_id', 'branch_id']);
            });
        }

        if (! Schema::hasTable('loyalty_rules')) {
            Schema::create('loyalty_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('rule_type', 32);
                $table->unsignedSmallInteger('priority')->default(100);
                $table->json('conditions_json')->nullable();
                $table->json('reward_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->timestamps();
                $table->index(['program_id', 'rule_type', 'is_active', 'priority']);
            });
        }

        if (! Schema::hasTable('loyalty_program_tiers')) {
            Schema::create('loyalty_program_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedSmallInteger('tier_level')->default(1);
                $table->string('qualification_type', 32);
                $table->decimal('qualification_value', 14, 2)->default(0);
                $table->decimal('multiplier', 5, 2)->default(1);
                $table->json('benefits_json')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();
                $table->unique(['program_id', 'tier_level']);
            });
        }

        if (! Schema::hasTable('customer_loyalty_wallets')) {
            Schema::create('customer_loyalty_wallets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('tier_id')->nullable()->constrained('loyalty_program_tiers')->nullOnDelete();
                $table->integer('available_points')->default(0);
                $table->integer('pending_points')->default(0);
                $table->integer('redeemed_points')->default(0);
                $table->integer('expired_points')->default(0);
                $table->integer('lifetime_earned_points')->default(0);
                $table->timestamps();
                $table->unique(['customer_id', 'program_id', 'branch_id'], 'clw_customer_program_branch_unique');
                $table->index(['program_id', 'available_points'], 'clw_program_available_index');
            });
        }

        if (! Schema::hasTable('customer_loyalty_transactions')) {
            Schema::create('customer_loyalty_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->foreignId('wallet_id')->constrained('customer_loyalty_wallets')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('transaction_type', 32);
                $table->integer('points');
                $table->integer('balance_before')->default(0);
                $table->integer('balance_after')->default(0);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reason')->nullable();
                $table->string('status', 32)->default('completed');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['reference_type', 'reference_id'], 'clt_reference_index');
                $table->index(['customer_id', 'program_id', 'created_at'], 'clt_customer_program_created_index');
                $table->index(['status', 'transaction_type'], 'clt_status_type_index');
            });
        }

        if (! Schema::hasTable('customer_loyalty_events')) {
            Schema::create('customer_loyalty_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->string('event_type', 32);
                $table->integer('points')->default(0);
                $table->integer('before_balance')->default(0);
                $table->integer('after_balance')->default(0);
                $table->string('description')->nullable();
                $table->json('metadata_json')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['customer_id', 'program_id', 'created_at'], 'cle_customer_program_created_index');
            });
        }

        if (! Schema::hasTable('loyalty_approval_policies')) {
            Schema::create('loyalty_approval_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->string('action_type', 32);
                $table->string('threshold_type', 16)->default('points');
                $table->decimal('threshold_value', 14, 2)->default(0);
                $table->string('approval_mode', 16)->default('pin');
                $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['program_id', 'action_type', 'is_active'], 'lap_program_action_active_index');
            });
        }

        if (! Schema::hasTable('loyalty_expiry_rules')) {
            Schema::create('loyalty_expiry_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->string('expiry_type', 32)->default('never');
                $table->unsignedInteger('value')->nullable();
                $table->unsignedInteger('grace_period_days')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loyalty_campaigns')) {
            Schema::create('loyalty_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('loyalty_programs')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('campaign_type', 32);
                $table->json('configuration_json')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('status', 16)->default('draft');
                $table->timestamps();
                $table->index(['program_id', 'status', 'starts_at', 'ends_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_campaigns');
        Schema::dropIfExists('loyalty_expiry_rules');
        Schema::dropIfExists('loyalty_approval_policies');
        Schema::dropIfExists('customer_loyalty_events');
        Schema::dropIfExists('customer_loyalty_transactions');
        Schema::dropIfExists('customer_loyalty_wallets');
        Schema::dropIfExists('loyalty_program_tiers');
        Schema::dropIfExists('loyalty_rules');
        Schema::dropIfExists('loyalty_program_branches');
        Schema::dropIfExists('loyalty_programs');
    }
};
