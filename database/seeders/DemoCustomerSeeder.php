<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerWallet;
use Illuminate\Database\Seeder;

final class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'phone' => '+15550100001',
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'credit_limit' => 500.00,
                'wallet_balance' => 25.00,
            ],
            [
                'phone' => '+15550100002',
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'credit_limit' => 0,
                'wallet_balance' => 0,
            ],
            [
                'phone' => '+15550100003',
                'name' => 'Carol Williams',
                'email' => 'carol@example.com',
                'credit_limit' => 1000.00,
                'wallet_balance' => 150.00,
            ],
            [
                'phone' => '+15550100004',
                'name' => 'David Brown',
                'email' => 'david@example.com',
                'credit_limit' => 250.00,
                'wallet_balance' => 10.00,
            ],
            [
                'phone' => '+15550100005',
                'name' => 'Walk-in Guest',
                'email' => null,
                'credit_limit' => 0,
                'wallet_balance' => 0,
            ],
        ];

        foreach ($customers as $data) {
            $customer = Customer::query()->firstOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'credit_limit' => $data['credit_limit'],
                    'is_active' => true,
                ],
            );

            if ($data['wallet_balance'] > 0) {
                CustomerWallet::query()->firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['balance' => $data['wallet_balance']],
                );
            }
        }
    }
}
