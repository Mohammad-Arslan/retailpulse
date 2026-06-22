<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SwitchLocaleRequest;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;

final class LocaleController extends Controller
{
    public function __construct(
        private readonly LocaleService $locales,
    ) {}

    public function update(SwitchLocaleRequest $request): RedirectResponse
    {
        $this->locales->switchLocale($request, (string) $request->validated('locale'));

        return back();
    }
}
