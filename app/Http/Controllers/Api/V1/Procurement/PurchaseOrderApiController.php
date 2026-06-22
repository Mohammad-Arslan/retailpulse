<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Services\Procurement\ProcurementConfigService;
use App\Support\BranchContext;
use App\Support\ListPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PurchaseOrderApiController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly ProcurementConfigService $config,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'supplier_id', 'sort', 'direction']);
        $branchId = app(BranchContext::class)->branchId;

        if ($branchId !== null) {
            $filters['branch_id'] = $branchId;
        }

        return PurchaseOrderResource::collection(
            $this->orders->paginate($filters, ListPagination::resolve($filters['per_page'] ?? 15)),
        );
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorize('view', $purchaseOrder);

        return new PurchaseOrderResource(
            $this->orders->findByIdWithRelations($purchaseOrder->id) ?? $purchaseOrder,
        );
    }

    public function config(): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $branchId = app(BranchContext::class)->branchId;

        return response()->json([
            'data' => $this->config->resolve($branchId),
        ]);
    }

    public function searchVariants(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $term = (string) $request->query('q', '');

        return response()->json(
            $this->products->searchVariants($term),
        );
    }
}
