<?php

namespace App\Services\Drivers;

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\User;
use App\Notifications\DriverDocumentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DriverDocumentService
{
    public const TYPES = [
        'portrait' => ['الصورة الشخصية', 'Profile photo', false],
        'emirates_id_front' => ['الهوية الإماراتية · الوجه الأمامي', 'Emirates ID · front', true],
        'emirates_id_back' => ['الهوية الإماراتية · الوجه الخلفي', 'Emirates ID · back', false],
        'driving_license' => ['رخصة القيادة', 'Driving licence', true],
        'vehicle_registration' => ['ملكية المركبة', 'Vehicle registration', true],
        'vehicle_insurance' => ['تأمين المركبة', 'Vehicle insurance', true],
    ];

    public function owned(User $user): Driver
    {
        return Driver::where('user_id', $user->id)->firstOrFail();
    }

    public function listing(Driver $driver): array
    {
        $rows = DriverDocument::where('driver_id', $driver->id)->whereNull('superseded_at')->get()->keyBy('type');

        return collect(self::TYPES)->map(function ($labels, $type) use ($rows) {
            $row = $rows->get($type);

            return ['type' => $type, 'label_ar' => $labels[0], 'label_en' => $labels[1], 'expiry_required' => $labels[2],
                'id' => $row?->id, 'status' => $row?->effectiveStatus() ?? 'missing',
                'expires_on' => $row?->expires_on?->format('Y-m-d'), 'mime' => $row?->mime,
                'size' => $row?->size, 'rejection_reason' => $row?->rejection_reason,
                'uploaded_at' => $row?->created_at?->toIso8601String()];
        })->values()->all();
    }

    public function upload(Driver $driver, string $type, UploadedFile $file, ?string $expires): DriverDocument
    {
        $path = $driver->id.'/'.Str::uuid().'.enc';
        // A dedicated private disk, authenticated encryption and random names.
        Storage::disk('driver_documents')->put($path, Crypt::encryptString($file->getContent()));
        try {
            return DB::transaction(function () use ($driver, $type, $file, $expires, $path) {
                Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
                DriverDocument::where('driver_id', $driver->id)->where('type', $type)->whereNull('superseded_at')->update(['superseded_at' => now()]);

                return DriverDocument::create(['driver_id' => $driver->id, 'type' => $type, 'path' => $path,
                    'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()), 'size' => $file->getSize(), 'expires_on' => $expires, 'status' => 'pending']);
            });
        } catch (\Throwable $error) {
            Storage::disk('driver_documents')->delete($path);
            throw $error;
        }
    }

    public function review(DriverDocument $document, User $admin, string $status, ?string $reason): void
    {
        $reason = $status === 'rejected' ? $reason : null;
        DB::transaction(function () use ($document, $admin, $status, $reason) {
            Driver::whereKey($document->driver_id)->lockForUpdate()->firstOrFail();
            $row = DriverDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($row->superseded_at) {
                throw ValidationException::withMessages(['document' => 'A newer version was uploaded. Reload the driver profile.']);
            }
            if ($status === 'approved' && $row->effectiveStatus() === 'expired') {
                throw ValidationException::withMessages(['document' => 'Expired documents cannot be approved.']);
            }
            if ($row->status === $status && $row->rejection_reason === $reason) {
                return;
            }
            $row->update(['status' => $status, 'rejection_reason' => $status === 'rejected' ? $reason : null]);
            $row->reviews()->create(['reviewer_id' => $admin->id, 'status' => $status, 'reason' => $status === 'rejected' ? $reason : null]);
            $row->driver->user->notify(new DriverDocumentNotification($row->id, $status));
        });
    }

    public function reminders(): int
    {
        $count = 0;
        DriverDocument::whereNull('superseded_at')->where('status', '!=', 'rejected')->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', CarbonImmutable::today('Asia/Dubai')->addDays(30))->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$count) {
                        Driver::whereKey($row->driver_id)->lockForUpdate()->firstOrFail();
                        $row = DriverDocument::whereKey($row->id)->lockForUpdate()->firstOrFail();
                        if ($row->superseded_at || $row->status === 'rejected') {
                            return;
                        }
                        $expired = $row->effectiveStatus() === 'expired';
                        $field = $expired ? 'expired_notified_at' : 'reminded_at';
                        if ($row->{$field}) {
                            return;
                        }
                        $row->driver->user->notify(new DriverDocumentNotification($row->id, $expired ? 'expired' : 'expiring'));
                        $row->update([$field => now()]);
                        $count++;
                    });
                }
            });

        return $count;
    }
}
