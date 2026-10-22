# Driver documents and account navigation

This update adds private driver document onboarding to Laravel and the Expo app. Stripe Connect and automatic payouts are not included.

## Driver workflow

Open **My account → My documents**, or **Application status → My documents** before operational approval. Upload one file per slot:

- Profile photo (image only).
- Emirates ID front (expiry required) and back.
- Driving licence (expiry required).
- Vehicle registration (expiry required).
- Vehicle insurance (expiry required).

Supported files: actual JPEG, PNG, WebP or PDF, up to 5 MiB each. SVG, HEIC and executable content are not accepted. Expiry dates use YYYY-MM-DD and are evaluated by the backend in Asia/Dubai. The mobile file selector offers images/PDF; native camera capture requests permission. Use a normal JPG/PNG export when a phone offers an unsupported image format.

Uploads create a new pending version and supersede the previous version. The owner sees only current versions. Admins retain access to previous files and the review history. A file/expiry cannot be edited in place; upload a replacement so the review belongs to the actual reviewed document.

The app supports authenticated open/save using the operating system share sheet on native platforms, or a download on web. Native downloaded copies are placed in app cache and removed after the share action completes. Camera/file-provider cache and any copies explicitly saved by the user are subject to device storage behavior.

## Administration

Open **Manage drivers → Profile & documents**, or select the driver's name. Review each current document with Approve or Request update. Rejection requires a reason. Stale versions and expired documents cannot be approved. Re-uploading never carries over an old approval. Names, contact, vehicle, plate and licence details are shown alongside document cards.

Document review is independent of driver operational approval. It does not automatically activate, deactivate, remove roles, change availability or interrupt active assignments. Configure your operating policy before introducing document-based dispatch restrictions.

## Storage and notifications

A dedicated local disk stores encrypted bytes in `storage/app/driver-documents`, outside `public` and the normal served disk. Database reasons are encrypted. Original filenames are not stored. Authenticated endpoints allow the owner (current versions) or administrators (all versions) to read. No public/signed document URLs are generated. Responses use private/no-store and nosniff; PDFs download rather than rendering in the application origin.

Preserve APP_KEY and back up the database AND the private document directory securely. Previous versions are retained for review; this release does not automatically delete files. Include an intentional retention/deletion policy in production operations. This is Raslo's document review, not Stripe KYC or government verification.

The daily `drivers:document-reminders` task runs at 08:00 Asia/Dubai. It sends one in-account notification within 30 days of expiry, and one when expired, per current non-rejected version. Reviews also notify the driver. Notifications contain no images, document numbers, rejection reasons or private file URLs. This release uses database notifications, not email or new push delivery. Scheduler must run reliably; `php artisan drivers:document-reminders` can be used for manual testing.

## API

All endpoints require Sanctum authentication. Pending drivers are allowed, even without the operational driver role; ownership comes from their Driver profile. Merchants without a Driver profile get 404.

- GET `/api/v1/driver/documents`: all six slots and current review metadata.
- POST same path: multipart `type`, `file`, optional/required `expires_on` as above. 201 returns updated slots.
- GET `/api/v1/driver/documents/{uuid}/file`: authenticated binary.

Web equivalents are under `/driver/documents`. Admin profile is `/admin/drivers/{id}`; reviews POST `/admin/driver-documents/{uuid}/review`. Uploads are rate-limited. Upload retry does not have an idempotency key: after a network timeout, refresh the slots before uploading again to avoid creating another version.

## Mobile changes

Driver and merchant account screens now share the same emerald/ivory design and safe-area handling. Store integrations are inside **My account → Manage my business** rather than above the page header. Driver documents appear inside My profile. Language, notifications, driver availability, automatic offers, logout and existing billing links remain available. The driver's tab bar uses device bottom insets and explicit label line height.

Added SDK-compatible `expo-document-picker`, `expo-file-system`, `expo-sharing`; a new development build is required. Application identifiers, EAS project, notification configuration and stored login/language keys are unchanged.
