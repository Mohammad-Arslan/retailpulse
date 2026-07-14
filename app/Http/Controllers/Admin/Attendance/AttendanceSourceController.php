<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSource;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AttendanceSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceSource::class);

        $filters = ListPagination::filters($request, ['search', 'status', 'driver', 'sort', 'direction']);
        $perPage = ListPagination::resolve($filters['per_page']);

        $query = AttendanceSource::query()
            ->with(['branch:id,name'])
            ->when($filters['search'] ?? null, function ($q, string $search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('driver', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['driver'] ?? null, fn ($q, string $driver) => $q->where('driver', $driver))
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc');

        $sources = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Attendance/Sources/Index', [
            'sources' => $sources->through(fn (AttendanceSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'driver' => $source->driver,
                'status' => $source->status,
                'branch' => $source->branch?->name,
            ]),
            'filters' => $filters,
            'drivers' => ['pos_pin', 'manual', 'biometric', 'mobile', 'import'],
        ]);
    }
}
