<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the columns to fit ciphertext, then re-encrypt any plaintext rows.
     * Safely re-runnable: values that already decrypt successfully are left untouched.
     */
    public function up(): void
    {
        Schema::table('employee_bank_accounts', function (Blueprint $table): void {
            $table->text('account_number')->change();
            $table->text('iban')->nullable()->change();
        });

        foreach (DB::table('employee_bank_accounts')->select(['id', 'account_number', 'iban'])->get() as $row) {
            $updates = [];

            if ($row->account_number !== null && ! $this->isEncrypted($row->account_number)) {
                $updates['account_number'] = Crypt::encryptString($row->account_number);
            }

            if ($row->iban !== null && ! $this->isEncrypted($row->iban)) {
                $updates['iban'] = Crypt::encryptString($row->iban);
            }

            if ($updates !== []) {
                DB::table('employee_bank_accounts')->where('id', $row->id)->update($updates);
            }
        }
    }

    /**
     * Best-effort revert: decrypt back to plaintext, then shrink the columns.
     * Ciphertext longer than 64 chars will be truncated — expected data loss on rollback.
     */
    public function down(): void
    {
        foreach (DB::table('employee_bank_accounts')->select(['id', 'account_number', 'iban'])->get() as $row) {
            $updates = [];

            if ($row->account_number !== null && $this->isEncrypted($row->account_number)) {
                $updates['account_number'] = substr(Crypt::decryptString($row->account_number), 0, 64);
            }

            if ($row->iban !== null && $this->isEncrypted($row->iban)) {
                $updates['iban'] = substr(Crypt::decryptString($row->iban), 0, 64);
            }

            if ($updates !== []) {
                DB::table('employee_bank_accounts')->where('id', $row->id)->update($updates);
            }
        }

        Schema::table('employee_bank_accounts', function (Blueprint $table): void {
            $table->string('account_number', 64)->change();
            $table->string('iban', 64)->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
