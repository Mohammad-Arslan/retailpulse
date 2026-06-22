<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Supplier;
use App\Models\SupplierPriceList;
use App\Models\SupplierPriceListItem;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupplierService
{
    public function __construct(
        private readonly SupplierRepositoryInterface $suppliers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $contacts
     * @param  list<array<string, mixed>>  $addresses
     */
    public function create(array $data, array $contacts = [], array $addresses = [], ?int $userId = null): Supplier
    {
        $this->assertUnique($data['code'] ?? null, $data['email'] ?? null, $data['tax_registration_no'] ?? null);

        return DB::transaction(function () use ($data, $contacts, $addresses, $userId) {
            $supplier = $this->suppliers->create([
                'tenant_id' => $data['tenant_id'] ?? null,
                'code' => $data['code'] ?? $this->suppliers->nextCode(),
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tax_registration_no' => $data['tax_registration_no'] ?? null,
                'payment_terms_days' => $data['payment_terms_days'] ?? null,
                'credit_terms_days' => $data['credit_terms_days'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncContacts($supplier, $contacts, $userId);
            $this->syncAddresses($supplier, $addresses, $userId);

            return $this->suppliers->findById($supplier->id) ?? $supplier;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $contacts
     * @param  list<array<string, mixed>>|null  $addresses
     */
    public function update(
        Supplier $supplier,
        array $data,
        ?int $userId = null,
        ?array $contacts = null,
        ?array $addresses = null,
    ): Supplier {
        $this->assertUnique(
            $data['code'] ?? $supplier->code,
            $data['email'] ?? $supplier->email,
            $data['tax_registration_no'] ?? $supplier->tax_registration_no,
            $supplier->id,
        );

        $supplier = $this->suppliers->update($supplier, array_merge($data, ['updated_by' => $userId]));

        if ($contacts !== null) {
            $supplier->contacts()->delete();
            $this->syncContacts($supplier, $contacts, $userId);
        }

        if ($addresses !== null) {
            $supplier->addresses()->delete();
            $this->syncAddresses($supplier, $addresses, $userId);
        }

        return $this->suppliers->findById($supplier->id) ?? $supplier;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByCode(array $attributes): Supplier
    {
        $code = (string) ($attributes['code'] ?? '');

        if ($code === '') {
            return $this->create($attributes);
        }

        $existing = $this->suppliers->findByCode($code);

        if ($existing !== null) {
            return $this->update($existing, $attributes);
        }

        return $this->create($attributes);
    }

    public function resolvePrice(int $supplierId, int $variantId, float $qty = 1): ?float
    {
        $priceList = SupplierPriceList::query()
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($priceList === null) {
            return null;
        }

        $item = SupplierPriceListItem::query()
            ->where('price_list_id', $priceList->id)
            ->where('product_variant_id', $variantId)
            ->where('min_qty', '<=', $qty)
            ->first();

        return $item !== null ? (float) $item->unit_price : null;
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     */
    private function syncContacts(Supplier $supplier, array $contacts, ?int $userId): void
    {
        foreach ($contacts as $contact) {
            $supplier->contacts()->create(array_merge($contact, [
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     */
    private function syncAddresses(Supplier $supplier, array $addresses, ?int $userId): void
    {
        foreach ($addresses as $address) {
            $supplier->addresses()->create(array_merge($address, [
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        }
    }

    private function assertUnique(
        ?string $code,
        ?string $email,
        ?string $taxRegistrationNo,
        ?int $ignoreId = null,
    ): void {
        if ($code !== null && $code !== '') {
            $query = Supplier::query()->where('code', $code);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages(['code' => __('Supplier code already exists.')]);
            }
        }

        if ($email !== null && $email !== '') {
            $query = Supplier::query()->where('email', $email);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages(['email' => __('Supplier email already exists.')]);
            }
        }

        if ($taxRegistrationNo !== null && $taxRegistrationNo !== '') {
            $query = Supplier::query()->where('tax_registration_no', $taxRegistrationNo);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->exists()) {
                throw ValidationException::withMessages(['tax_registration_no' => __('Tax registration number already exists.')]);
            }
        }
    }
}
