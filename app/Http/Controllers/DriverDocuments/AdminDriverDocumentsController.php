<?php

namespace App\Http\Controllers\DriverDocuments;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Services\Drivers\DriverDocumentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDriverDocumentsController extends Controller
{
    public function show(Request $request, Driver $driver, DriverDocumentService $service)
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $driver->load('user');
        $documents = $service->listing($driver);
        $history = DriverDocument::where('driver_id', $driver->id)->with('reviews.reviewer')->latest()->paginate(20);

        return response()->view('driver-documents.admin', compact('driver', 'documents', 'history'))->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, DriverDocument $document, DriverDocumentService $service)
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000']]);
        $service->review($document, $request->user(), $data['status'], $data['reason'] ?? null);

        return redirect()->route('admin.drivers.show', $document->driver_id)->with('success', app()->getLocale() === 'ar' ? 'تم حفظ المراجعة.' : 'Review saved.');
    }
}
