<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('employees', 'title')) {
                $table->string('title', 32)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('employees', 'middle_name')) {
                $table->string('middle_name', 120)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('employees', 'preferred_name')) {
                $table->string('preferred_name', 120)->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('employees', 'gender')) {
                $table->string('gender', 24)->nullable()->after('preferred_name');
            }
            if (! Schema::hasColumn('employees', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('employees', 'marital_status')) {
                $table->string('marital_status', 32)->nullable()->after('date_of_birth');
            }
            if (! Schema::hasColumn('employees', 'nationality')) {
                $table->string('nationality', 64)->nullable()->after('marital_status');
            }
            if (! Schema::hasColumn('employees', 'national_id_encrypted')) {
                $table->text('national_id_encrypted')->nullable()->after('nationality');
            }
            if (! Schema::hasColumn('employees', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->after('confirmation_date');
            }
            if (! Schema::hasColumn('employees', 'joined_as')) {
                $table->string('joined_as', 120)->nullable()->after('employment_type');
            }
        });

        if (! Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
                $table->string('address_line1')->nullable();
                $table->string('address_line2')->nullable();
                $table->string('city', 120)->nullable();
                $table->string('state', 120)->nullable();
                $table->string('postal_code', 32)->nullable();
                $table->string('country', 120)->nullable();
                $table->string('emergency_contact_name', 120)->nullable();
                $table->string('emergency_contact_phone', 64)->nullable();
                $table->string('emergency_contact_relation', 64)->nullable();
                $table->unsignedSmallInteger('attendance_grace_minutes')->default(0);
                $table->boolean('overtime_eligible')->default(true);
                $table->json('attendance_prefs')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_dependents')) {
            Schema::create('employee_dependents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('name');
                $table->string('relation', 64);
                $table->date('date_of_birth')->nullable();
                $table->string('gender', 24)->nullable();
                $table->string('national_id', 64)->nullable();
                $table->string('phone', 64)->nullable();
                $table->boolean('is_emergency_contact')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['employee_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('employee_medical_profiles')) {
            Schema::create('employee_medical_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
                $table->string('blood_group', 16)->nullable();
                $table->text('allergies')->nullable();
                $table->text('conditions')->nullable();
                $table->string('insurance_provider', 120)->nullable();
                $table->string('insurance_policy_no', 120)->nullable();
                $table->text('emergency_notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_bank_accounts')) {
            Schema::create('employee_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('label', 120)->nullable();
                $table->string('bank_name', 120);
                $table->string('account_number', 64);
                $table->string('iban', 64)->nullable();
                $table->string('currency_code', 3)->nullable();
                $table->string('payment_method', 32)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index(['employee_id', 'is_primary']);
            });
        }

        if (! Schema::hasTable('employee_attachments')) {
            Schema::create('employee_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('document_type', 64)->default('other');
                $table->string('original_name');
                $table->string('disk', 32)->default('local');
                $table->string('path');
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'document_type']);
            });
        }

        if (! Schema::hasTable('employee_branch_assignments')) {
            Schema::create('employee_branch_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->boolean('is_primary')->default(false);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->unique(['employee_id', 'branch_id', 'effective_from'], 'employee_branch_unique');
            });
        }

        if (! Schema::hasTable('employee_shift_preferences')) {
            Schema::create('employee_shift_preferences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
                $table->string('shift_label', 120)->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->json('rest_days')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shift_preferences');
        Schema::dropIfExists('employee_branch_assignments');
        Schema::dropIfExists('employee_attachments');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_medical_profiles');
        Schema::dropIfExists('employee_dependents');
        Schema::dropIfExists('employee_profiles');

        Schema::table('employees', function (Blueprint $table): void {
            foreach ([
                'title',
                'middle_name',
                'preferred_name',
                'gender',
                'date_of_birth',
                'marital_status',
                'nationality',
                'national_id_encrypted',
                'contract_end_date',
                'joined_as',
            ] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
