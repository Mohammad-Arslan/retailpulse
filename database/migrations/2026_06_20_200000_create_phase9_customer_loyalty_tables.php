<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_groups')) {
            Schema::create('customer_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('price_list_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loyalty_tiers')) {
            Schema::create('loyalty_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('points_multiplier', 5, 2)->default(1);
                $table->unsignedInteger('min_points')->default(0);
                $table->boolean('auto_upgrade')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'loyalty_tier_id')) {
                $table->foreignId('loyalty_tier_id')->nullable()->after('is_active')->constrained('loyalty_tiers')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'customer_group_id')) {
                $table->foreignId('customer_group_id')->nullable()->after('loyalty_tier_id')->constrained('customer_groups')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'credit_limit')) {
                $table->decimal('credit_limit', 12, 2)->nullable()->after('customer_group_id');
            }
            if (! Schema::hasColumn('customers', 'preferred_payment_method')) {
                $table->string('preferred_payment_method', 32)->nullable()->after('credit_limit');
            }
            if (! Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('preferred_payment_method');
            }
        });

        if (! Schema::hasTable('loyalty_points')) {
            Schema::create('loyalty_points', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->integer('points');
                $table->string('type', 16);
                $table->string('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('customer_wallets')) {
            Schema::create('customer_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
                $table->decimal('balance', 12, 2)->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_wallet_transactions')) {
            Schema::create('customer_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('type', 16);
                $table->string('reason', 32);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('meta')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (! Schema::hasTable('store_credits')) {
            Schema::create('store_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->decimal('balance', 12, 2)->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->foreignId('source_sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('store_credit_transactions')) {
            Schema::create('store_credit_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_credit_id')->constrained('store_credits')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('type', 16);
                $table->string('reason', 32);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('customer_ar_ledger')) {
            Schema::create('customer_ar_ledger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->string('entry_type', 32);
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['customer_id', 'branch_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ar_aging_snapshots')) {
            Schema::create('ar_aging_snapshots', function (Blueprint $table) {
                $table->id();
                $table->date('snapshot_date');
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->decimal('current', 12, 2)->default(0);
                $table->decimal('bucket_30', 12, 2)->default(0);
                $table->decimal('bucket_60', 12, 2)->default(0);
                $table->decimal('bucket_90', 12, 2)->default(0);
                $table->decimal('bucket_over_90', 12, 2)->default(0);
                $table->decimal('total_outstanding', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['snapshot_date', 'customer_id', 'branch_id']);
            });
        }

        if (! Schema::hasTable('customer_reminder_logs')) {
            Schema::create('customer_reminder_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('channel', 16);
                $table->string('bucket', 16);
                $table->decimal('amount_due', 12, 2)->default(0);
                $table->string('status', 16)->default('sent');
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('customer_write_offs')) {
            Schema::create('customer_write_offs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('reason_code', 64);
                $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('approved_at');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_write_offs');
        Schema::dropIfExists('customer_reminder_logs');
        Schema::dropIfExists('ar_aging_snapshots');
        Schema::dropIfExists('customer_ar_ledger');
        Schema::dropIfExists('store_credit_transactions');
        Schema::dropIfExists('store_credits');
        Schema::dropIfExists('customer_wallet_transactions');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('loyalty_points');

        if (Schema::hasColumn('customers', 'loyalty_tier_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropForeign(['loyalty_tier_id']);
                $table->dropForeign(['customer_group_id']);
                $table->dropColumn([
                    'loyalty_tier_id',
                    'customer_group_id',
                    'credit_limit',
                    'preferred_payment_method',
                    'notes',
                ]);
            });
        }

        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('customer_groups');
    }
};
