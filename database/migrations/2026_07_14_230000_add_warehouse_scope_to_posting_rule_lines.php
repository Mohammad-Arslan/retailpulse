<?php

declare(strict_types=1);

use App\Enums\PostingRuleEntrySide;
use App\Enums\PostingRuleWarehouseScope;
use App\Models\PostingRuleLine;
use App\Models\PostingRuleSet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posting_rule_lines', function (Blueprint $table) {
            $table->string('warehouse_scope', 16)->nullable()->after('account_mapping_key');
        });

        $ruleSet = PostingRuleSet::query()
            ->where('event_type', 'transfer.confirmed')
            ->where('code', 'transfer_confirmed_default')
            ->first();

        if ($ruleSet === null) {
            return;
        }

        PostingRuleLine::query()
            ->where('posting_rule_set_id', $ruleSet->id)
            ->where('sequence', 1)
            ->where('entry_side', PostingRuleEntrySide::Debit)
            ->update(['warehouse_scope' => PostingRuleWarehouseScope::Destination]);

        PostingRuleLine::query()
            ->where('posting_rule_set_id', $ruleSet->id)
            ->where('sequence', 2)
            ->where('entry_side', PostingRuleEntrySide::Credit)
            ->update(['warehouse_scope' => PostingRuleWarehouseScope::Source]);
    }

    public function down(): void
    {
        Schema::table('posting_rule_lines', function (Blueprint $table) {
            $table->dropColumn('warehouse_scope');
        });
    }
};
