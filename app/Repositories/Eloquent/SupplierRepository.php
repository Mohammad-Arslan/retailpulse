<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SupplierRepository implements SupplierRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Supplier::query()->withCount('purchaseOrders');

        $sort = $filters['sort'] ?? 'name';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'code', 'created_at', 'is_active', 'balance'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction);

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): ?Supplier
    {
        return Supplier::query()
            ->with(['contacts', 'addresses', 'priceLists.items'])
            ->find($id);
    }

    public function findByCode(string $code): ?Supplier
    {
        return Supplier::query()->where('code', $code)->first();
    }

    public function create(array $attributes): Supplier
    {
        return Supplier::query()->create($attributes);
    }

    public function update(Supplier $supplier, array $attributes): Supplier
    {
        $supplier->update($attributes);

        return $supplier->fresh(['contacts', 'addresses']) ?? $supplier;
    }

    public function nextCode(): string
    {
        $format = (string) SystemSetting::get('procurement', 'supplier_code_format', 'SUP-{seq:6}');
        $latest = Supplier::query()
            ->where('code', 'like', 'SUP-%')
            ->orderByDesc('id')
            ->value('code');

        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        if (preg_match('/\{seq:(\d+)\}/', $format, $padMatch)) {
            $pad = (int) $padMatch[1];

            return preg_replace('/\{seq:\d+\}/', str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT), $format) ?? 'SUP-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        }

        return 'SUP-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
