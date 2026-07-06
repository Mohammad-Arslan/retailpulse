<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierAttachmentRequest;
use App\Models\Supplier;
use App\Models\SupplierAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SupplierAttachmentController extends Controller
{
    public function store(StoreSupplierAttachmentRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $file = $request->file('file');
        $path = $file->store("suppliers/{$supplier->id}", 'local');

        $supplier->attachments()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $request->validated('notes'),
            'uploaded_by' => $request->user()?->id,
        ]);

        return back()->with('success', __('Attachment uploaded.'));
    }

    public function destroy(Supplier $supplier, SupplierAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $supplier);

        if ($attachment->supplier_id !== $supplier->id) {
            abort(404);
        }

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', __('Attachment deleted.'));
    }

    public function download(Supplier $supplier, SupplierAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $supplier);

        if ($attachment->supplier_id !== $supplier->id) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    public function preview(Supplier $supplier, SupplierAttachment $attachment): Response
    {
        $this->authorize('view', $supplier);

        if ($attachment->supplier_id !== $supplier->id) {
            abort(404);
        }

        if (! str_starts_with((string) $attachment->mime_type, 'image/')) {
            abort(404);
        }

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->file_name,
            ['Content-Disposition' => 'inline; filename="'.$attachment->file_name.'"'],
        );
    }
}
