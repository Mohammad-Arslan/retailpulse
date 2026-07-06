<?php

declare(strict_types=1);

namespace App\Services\Loyalty;

use App\DTOs\Loyalty\CreateLoyaltyCampaignData;
use App\DTOs\Loyalty\UpdateLoyaltyCampaignData;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\Concerns\AssertsLoyaltyProgramOwnership;
use Illuminate\Support\Facades\DB;

final class LoyaltyCampaignService
{
    use AssertsLoyaltyProgramOwnership;

    public function create(LoyaltyProgram $program, CreateLoyaltyCampaignData $data): LoyaltyCampaign
    {
        return DB::transaction(fn () => $program->campaigns()->create([
            'name' => $data->name,
            'description' => $data->description,
            'campaign_type' => $data->campaignType,
            'configuration_json' => $data->configurationJson ?? [],
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'status' => $data->status,
        ]));
    }

    public function update(
        LoyaltyProgram $program,
        LoyaltyCampaign $campaign,
        UpdateLoyaltyCampaignData $data,
    ): LoyaltyCampaign {
        $this->assertBelongsToProgram($campaign->program_id, $program);

        return DB::transaction(function () use ($campaign, $data) {
            $campaign->update([
                'name' => $data->name,
                'description' => $data->description,
                'campaign_type' => $data->campaignType,
                'configuration_json' => $data->configurationJson ?? [],
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'status' => $data->status,
            ]);

            return $campaign;
        });
    }

    public function delete(LoyaltyProgram $program, LoyaltyCampaign $campaign): void
    {
        $this->assertBelongsToProgram($campaign->program_id, $program);

        DB::transaction(fn () => $campaign->delete());
    }
}
