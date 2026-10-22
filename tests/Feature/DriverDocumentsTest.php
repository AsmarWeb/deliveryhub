<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DriverDocumentNotification;
use App\Services\Drivers\DriverDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32))]);
        Storage::fake('driver_documents');
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user = User::factory()->create();
        $user->addRole('admin');

        return $user;
    }

    private function upload(Driver $driver, string $type = 'emirates_id_front'): DriverDocument
    {
        $this->actingAs($driver->user)->post('/api/v1/driver/documents', [
            'type' => $type, 'file' => UploadedFile::fake()->image('secret-id.jpg'),
            'expires_on' => today()->addYear()->format('Y-m-d'),
        ])->assertCreated()->assertJsonCount(6, 'data');

        return DriverDocument::latest()->firstOrFail();
    }

    public function test_pending_driver_without_operational_role_can_upload_but_other_accounts_cannot(): void
    {
        $driver = Driver::factory()->create(['status' => 'pending']);
        $this->assertFalse($driver->user->hasRole('driver'));
        $this->upload($driver);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/driver/documents')->assertNotFound();
        $this->actingAs($driver->user)->getJson('/api/v1/driver/documents')->assertOk();
    }

    public function test_guest_cannot_read_or_upload(): void
    {
        $this->getJson('/api/v1/driver/documents')->assertUnauthorized();
        $this->postJson('/api/v1/driver/documents', [])->assertUnauthorized();
    }

    public function test_files_are_encrypted_private_and_only_owner_or_admin_can_read(): void
    {
        $driver = Driver::factory()->create();
        $row = $this->upload($driver);
        $stored = Storage::disk('driver_documents')->get($row->path);
        $plain = Crypt::decryptString($stored);
        $this->assertNotSame($plain, $stored);
        $this->assertStringNotContainsString('secret-id', $row->path);
        $this->get('/api/v1/driver/documents/'.$row->id.'/file')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = Driver::factory()->create();
        $this->actingAs($other->user)->get('/api/v1/driver/documents/'.$row->id.'/file')->assertNotFound();
        $this->actingAs($this->admin())->get('/driver/documents/'.$row->id.'/file')->assertOk();
        $list = $this->actingAs($driver->user)->getJson('/api/v1/driver/documents')->assertOk()->getContent();
        $this->assertStringNotContainsString($row->path, $list);
    }

    public function test_validation_rejects_wrong_types_expiry_large_and_executable_files(): void
    {
        $driver = Driver::factory()->create();
        $this->actingAs($driver->user);
        $this->postJson('/api/v1/driver/documents', ['type' => ['bad']])->assertUnprocessable();
        foreach ([
            ['type' => 'unknown', 'file' => UploadedFile::fake()->image('x.jpg')],
            ['type' => 'driving_license', 'file' => UploadedFile::fake()->image('x.jpg')],
            ['type' => 'driving_license', 'file' => UploadedFile::fake()->image('x.jpg'), 'expires_on' => today()->subDay()->format('Y-m-d')],
            ['type' => 'portrait', 'file' => UploadedFile::fake()->create('huge.jpg', 5121, 'image/jpeg')],
            ['type' => 'portrait', 'file' => UploadedFile::fake()->createWithContent('attack.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            ['type' => 'portrait', 'file' => UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo "attack";')],
        ] as $data) {
            $this->post('/api/v1/driver/documents', $data, ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('driver_documents', 0);
        $this->assertEmpty(Storage::disk('driver_documents')->allFiles());
    }

    public function test_reupload_supersedes_old_version_and_resets_approval_without_losing_history(): void
    {
        $driver = Driver::factory()->create();
        $row = $this->upload($driver);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/driver-documents/'.$row->id.'/review', ['status' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $row->fresh()->status);
        $replacement = $this->upload($driver);
        $this->assertNotNull($row->fresh()->superseded_at);
        $current = DriverDocument::whereNull('superseded_at')->sole();
        $this->assertSame('pending', $current->status);
        $this->assertDatabaseCount('driver_document_reviews', 1);
        $this->get('/api/v1/driver/documents/'.$row->id.'/file')->assertNotFound();
        $this->actingAs($admin)->post('/admin/driver-documents/'.$row->id.'/review', ['status' => 'approved'])->assertSessionHasErrors('document');
        $this->get('/driver/documents/'.$row->id.'/file')->assertOk();
    }

    public function test_review_permissions_reasons_notifications_and_expiry(): void
    {
        Notification::fake();
        $driver = Driver::factory()->create();
        $row = $this->upload($driver);
        $this->post('/admin/driver-documents/'.$row->id.'/review', ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->admin())->post('/admin/driver-documents/'.$row->id.'/review', ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $this->post('/admin/driver-documents/'.$row->id.'/review', ['status' => 'rejected', 'reason' => 'Please replace blurry photo'])->assertRedirect();
        $this->assertSame('Please replace blurry photo', $row->fresh()->rejection_reason);
        $this->assertStringNotContainsString('blurry', DB::table('driver_documents')->value('rejection_reason'));
        Notification::assertSentTo($driver->user, DriverDocumentNotification::class);
        $row->update(['expires_on' => today()->subDay()]);
        $this->post('/admin/driver-documents/'.$row->id.'/review', ['status' => 'approved'])->assertSessionHasErrors('document');
        $this->assertSame('expired', $row->fresh()->effectiveStatus());
    }

    public function test_reminders_are_once_per_version_and_expiry_is_independent_of_driver_activation(): void
    {
        Notification::fake();
        $driver = Driver::factory()->create(['status' => 'active']);
        $row = $this->upload($driver);
        $row->update(['expires_on' => today()->addDays(5)]);
        $service = app(DriverDocumentService::class);
        $this->assertSame(1, $service->reminders());
        $this->assertSame(0, $service->reminders());
        $this->travel(6)->days();
        $this->assertSame(1, $service->reminders());
        $this->assertSame(0, $service->reminders());
        Notification::assertSentToTimes($driver->user, DriverDocumentNotification::class, 2);
        $this->assertSame('active', $driver->fresh()->status);
    }

    public function test_dashboard_pages_use_sidebar_and_driver_profile_link(): void
    {
        $driver = Driver::factory()->create();
        $this->upload($driver);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/drivers')->assertOk()->assertSee(route('admin.drivers.show', $driver));
        foreach (['ar', 'en'] as $locale) {
            $this->withSession(['locale' => $locale])->get('/admin/drivers/'.$driver->id)->assertOk()->assertSee('id="dashboard-sidebar"', false);
            $this->actingAs($driver->user)->get('/driver/documents')->assertOk()->assertSee('multipart/form-data');
            $this->actingAs($admin);
        }
    }

    public function test_pdf_is_downloaded_with_safe_filename(): void
    {
        $driver = Driver::factory()->create();
        $file = UploadedFile::fake()->createWithContent('private-name.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->actingAs($driver->user)->post('/api/v1/driver/documents', ['type' => 'vehicle_insurance', 'file' => $file, 'expires_on' => today()->addYear()->format('Y-m-d')])->assertCreated();
        $row = DriverDocument::sole();
        $this->get('/api/v1/driver/documents/'.$row->id.'/file')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="vehicle_insurance.pdf"');
    }

    public function test_database_failure_keeps_previous_version_and_removes_new_file(): void
    {
        $driver = Driver::factory()->create();
        $row = $this->upload($driver);
        DriverDocument::creating(function () {
            throw new \RuntimeException('Simulated write failure');
        });
        try {
            app(DriverDocumentService::class)->upload($driver, 'emirates_id_front', UploadedFile::fake()->image('replacement.jpg'), today()->addYear()->format('Y-m-d'));
            $this->fail('Expected transaction failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated write failure', $error->getMessage());
        } finally {
            DriverDocument::flushEventListeners();
        }
        $this->assertNull($row->fresh()->superseded_at);
        $this->assertCount(1, Storage::disk('driver_documents')->allFiles());
    }

    public function test_document_upload_ignores_driver_status_and_storage_injection(): void
    {
        $driver = Driver::factory()->create(['status' => 'pending']);
        $other = Driver::factory()->create();
        $this->actingAs($driver->user)->post('/api/v1/driver/documents', [
            'type' => 'portrait', 'file' => UploadedFile::fake()->image('photo.png'), 'status' => 'approved',
            'driver_id' => $other->id, 'path' => '../public/leak.png',
        ])->assertCreated();
        $row = DriverDocument::sole();
        $this->assertSame($driver->id, $row->driver_id);
        $this->assertSame('pending', $row->status);
        $this->assertStringStartsWith($driver->id.'/', $row->path);
        $this->assertSame('pending', $driver->fresh()->status);
    }

    public function test_new_tables_can_roll_back_and_reapply(): void
    {
        $migration = require database_path('migrations/2026_10_22_230000_create_driver_documents.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('driver_documents'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('driver_document_reviews'));
    }

    public function test_duplicate_review_does_not_duplicate_audit_or_notification(): void
    {
        Notification::fake();
        $driver = Driver::factory()->create();
        $document = $this->upload($driver);
        $admin = $this->admin();
        $service = app(DriverDocumentService::class);
        $service->review($document, $admin, 'approved', 'Ignored approval note');
        $service->review($document, $admin, 'approved', 'Ignored approval note');
        $this->assertDatabaseCount('driver_document_reviews', 1);
        Notification::assertSentToTimes($driver->user, DriverDocumentNotification::class, 1);
    }
}
