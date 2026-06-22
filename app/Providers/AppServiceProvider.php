<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Procurement\DropShipGrnConfirmed;
use App\Events\Procurement\GoodsReceived;
use App\Events\Procurement\SupplierInvoiceMatched;
use App\Listeners\Procurement\LogDropShipGrnConfirmed;
use App\Listeners\Procurement\LogGoodsReceived;
use App\Listeners\Procurement\LogSupplierInvoiceMatched;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\GoodsReceivingNote;
use App\Models\LandedCostEntry;
use App\Models\Permission;
use App\Models\PoMatchResult;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\SupplierAttachment;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierPriceList;
use App\Models\SupplierPriceListItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\AuditObserver;
use App\Repositories\Contracts\BinLocationRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\BrandRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CountSessionRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\IdentifierSequenceRepositoryInterface;
use App\Repositories\Contracts\ImageRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\PosCartRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WarehouseRepositoryInterface;
use App\Repositories\Eloquent\BinLocationRepository;
use App\Repositories\Eloquent\BranchRepository;
use App\Repositories\Eloquent\BrandRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\CountSessionRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\IdentifierSequenceRepository;
use App\Repositories\Eloquent\ImageRepository;
use App\Repositories\Eloquent\InventoryRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\PosCartRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\PurchaseOrderRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\StockMovementRepository;
use App\Repositories\Eloquent\StockTransferRepository;
use App\Repositories\Eloquent\SupplierRepository;
use App\Repositories\Eloquent\SystemSettingRepository;
use App\Repositories\Eloquent\UnitRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WarehouseRepository;
use App\Services\Procurement\Contracts\ProcurementPostingHook;
use App\Services\Procurement\NullProcurementPostingHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PosCartRepositoryInterface::class, PosCartRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(BranchRepositoryInterface::class, BranchRepository::class);
        $this->app->bind(WarehouseRepositoryInterface::class, WarehouseRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(BrandRepositoryInterface::class, BrandRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(UnitRepositoryInterface::class, UnitRepository::class);
        $this->app->bind(SupplierRepositoryInterface::class, SupplierRepository::class);
        $this->app->bind(PurchaseOrderRepositoryInterface::class, PurchaseOrderRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(ImageRepositoryInterface::class, ImageRepository::class);
        $this->app->bind(IdentifierSequenceRepositoryInterface::class, IdentifierSequenceRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
        $this->app->bind(BinLocationRepositoryInterface::class, BinLocationRepository::class);
        $this->app->bind(CountSessionRepositoryInterface::class, CountSessionRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class, StockMovementRepository::class);
        $this->app->bind(StockTransferRepositoryInterface::class, StockTransferRepository::class);
        $this->app->bind(SystemSettingRepositoryInterface::class, SystemSettingRepository::class);
        $this->app->singleton(ProcurementPostingHook::class, NullProcurementPostingHook::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        User::observe(AuditObserver::class);
        Role::observe(AuditObserver::class);
        Permission::observe(AuditObserver::class);
        Branch::observe(AuditObserver::class);
        Warehouse::observe(AuditObserver::class);
        Category::observe(AuditObserver::class);
        Brand::observe(AuditObserver::class);
        Unit::observe(AuditObserver::class);
        Product::observe(AuditObserver::class);
        Customer::observe(AuditObserver::class);
        Supplier::observe(AuditObserver::class);
        PurchaseOrder::observe(AuditObserver::class);
        GoodsReceivingNote::observe(AuditObserver::class);
        SupplierInvoice::observe(AuditObserver::class);
        SupplierPayment::observe(AuditObserver::class);
        PurchaseReturn::observe(AuditObserver::class);
        SupplierPriceList::observe(AuditObserver::class);
        SupplierPriceListItem::observe(AuditObserver::class);
        SupplierAttachment::observe(AuditObserver::class);
        LandedCostEntry::observe(AuditObserver::class);
        PoMatchResult::observe(AuditObserver::class);
        DebitNote::observe(AuditObserver::class);
        Sale::observe(AuditObserver::class);
        SalePayment::observe(AuditObserver::class);
        SaleInvoice::observe(AuditObserver::class);

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(strtolower($email).'|'.$request->ip());
        });

        Event::listen(DropShipGrnConfirmed::class, LogDropShipGrnConfirmed::class);
        Event::listen(GoodsReceived::class, LogGoodsReceived::class);
        Event::listen(SupplierInvoiceMatched::class, LogSupplierInvoiceMatched::class);
    }
}
