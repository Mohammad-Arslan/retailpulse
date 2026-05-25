<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pos_pin_hash')->nullable()->after('password');
            $table->timestamp('pos_pin_updated_at')->nullable()->after('pos_pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pos_pin_hash', 'pos_pin_updated_at']);
        });
    }
};
