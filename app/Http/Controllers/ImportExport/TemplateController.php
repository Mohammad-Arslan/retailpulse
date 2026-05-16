<?php

declare(strict_types=1);

namespace App\Http\Controllers\ImportExport;

use App\Http\Controllers\Controller;
use App\Services\ImportExport\ImportExportRegistry;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TemplateController extends Controller
{
    public function download(Request $request, string $entity): StreamedResponse
    {
        if (! in_array($entity, ImportExportRegistry::allEntities(), true)) {
            abort(404);
        }

        $handler = ImportExportRegistry::importHandler($entity);
        $headers = collect($handler->columns())->pluck('label', 'key')->keys()->all();
        $row = array_fill_keys($headers, '');

        return response()->streamDownload(function () use ($row, $headers) {
            $tmp = tempnam(sys_get_temp_dir(), 'template_').'.xlsx';
            (new FastExcel([array_combine($headers, array_fill(0, count($headers), ''))]))->export($tmp);
            readfile($tmp);
            @unlink($tmp);
        }, "{$entity}-import-template.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
