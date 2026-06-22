<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Resources\Procurement\SupplierResource;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Services\Procurement\SupplierService;
use App\Support\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SupplierApiController extends Controller
{
    public function __construct(
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly SupplierService $supplierService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        $filters = ListPagination::filters($request, ['search', 'is_active', 'sort', 'direction']);

        return SupplierResource::collection(
            $this->suppliers->paginate($filters, ListPagination::resolve($filters['per_page'] ?? 15)),
        );
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return new SupplierResource($this->suppliers->findById($supplier->id) ?? $supplier);
    }

    public function price(Supplier $supplier, ProductVariant $variant): JsonResponse
    {
        $this->authorize('view', $supplier);

        $qty = (float) request()->query('qty', 1);
        $price = $this->supplierService->resolvePrice($supplier->id, $variant->id, $qty);

        return response()->json(['data' => ['unit_price' => $price]]);
    }
}
