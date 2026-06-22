<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Customer;

    public function findByPhone(string $phone): ?Customer;

    public function findByEmail(string $email): ?Customer;

    public function create(array $attributes): Customer;

    public function update(Customer $customer, array $attributes): Customer;
}
