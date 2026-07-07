<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OrganizationEntity;
use Illuminate\Database\Seeder;

final class OrganizationEntitySeeder extends Seeder
{
    public function run(): void
    {
        OrganizationEntity::query()->firstOrCreate(
            ['legal_name' => config('app.name', 'RetailPulse')],
            [
                'tenant_id' => null,
                'tax_registration_no' => null,
                'functional_currency_code' => 'USD',
                'status' => 'active',
            ],
        );
    }
}
