<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
final class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'code' => 'SUP-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => Str::slug($name),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'currency_code' => 'USD',
            'is_active' => true,
        ];
    }
}
