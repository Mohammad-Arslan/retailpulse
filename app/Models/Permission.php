<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Spatie\Permission\Models\Permission as SpatiePermission;

#[Fillable(['name', 'display_name', 'guard_name', 'group', 'description'])]
class Permission extends SpatiePermission {}
