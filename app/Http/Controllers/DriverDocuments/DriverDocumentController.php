<?php

namespace App\Http\Controllers\DriverDocuments;

use App\Http\Controllers\Controller;
use App\Models\DriverDocument;
use App\Services\Drivers\DriverDocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverDocumentController extends Controller
{
    public function index(Request $request, DriverDocumentService $service)
    {
        $driver = $service->owned($request->user());
        $documents = $service->listing($driver);
        if ($request->is('api/*')) {
            return response()->json(['data' => $documents])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('driver-documents.index', compact('driver', 'documents'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, DriverDocumentService $service)
    {
        $driver = $service->owned($request->user());
        $type = $request->input('type');
        $expiryRequired = is_string($type) && (DriverDocumentService::TYPES[$type][2] ?? false);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DriverDocumentService::TYPES))],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120',
                function ($attribute, $value, $fail) use ($type) {
                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        return;
                    }
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($value->getPathname());
                    $allowed = $type === 'portrait' ? ['image/jpeg', 'image/png', 'image/webp'] : ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                    if (! in_array($mime, $allowed, true)) {
                        $fail('Choose a valid JPG, PNG, WebP or PDF document.');
                    }
                },
                Rule::when($request->input('type') === 'portrait', ['image'])],
            'expires_on' => [Rule::requiredIf($expiryRequired), 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::today('Asia/Dubai')->format('Y-m-d')],
        ]);
        $service->upload($driver, $data['type'], $request->file('file'), $data['expires_on'] ?? null);
        if ($request->is('api/*')) {
            return response()->json(['data' => $service->listing($driver)], 201)->header('Cache-Control', 'private, no-store');
        }

        return back()->with('success', app()->getLocale() === 'ar' ? 'تم رفع المستند للمراجعة.' : 'Document submitted for review.');
    }

    public function file(Request $request, DriverDocument $document, DriverDocumentService $service)
    {
        $admin = $request->user()->hasRole('admin');
        if (! $admin) {
            abort_unless($document->driver_id === $service->owned($request->user())->id && ! $document->superseded_at, 404);
        }
        $disk = Storage::disk('driver_documents');
        abort_unless($disk->exists($document->path), 404);
        $contents = Crypt::decryptString($disk->get($document->path));
        $extension = match ($document->mime) {
            'application/pdf' => 'pdf', 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
        };
        // Inline only safe raster images; PDF always downloads, never executes in our origin.
        $disposition = $document->mime === 'application/pdf' ? 'attachment' : 'inline';

        return response($contents)->withHeaders(['Content-Type' => $document->mime,
            'Content-Disposition' => $disposition.'; filename="'.$document->type.'.'.$extension.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
