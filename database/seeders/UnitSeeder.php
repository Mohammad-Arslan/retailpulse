<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

final class UnitSeeder extends Seeder
{
    /**
     * @var list<array{name: string, abbreviation: string}>
     */
    private const UNITS = [
        ['name' => 'Each', 'abbreviation' => 'ea'],
        ['name' => 'Piece', 'abbreviation' => 'pc'],
        ['name' => 'Kilogram', 'abbreviation' => 'kg'],
        ['name' => 'Liter', 'abbreviation' => 'L'],
        ['name' => 'Box', 'abbreviation' => 'box'],
        ['name' => 'Pack', 'abbreviation' => 'pk'],
    ];

    public function run(): void
    {
        foreach (self::UNITS as $unit) {
            Unit::query()->firstOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                [
                    'name' => $unit['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
