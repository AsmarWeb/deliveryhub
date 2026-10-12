<?php

namespace Tests\Feature\Web;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverProofOfDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(
            ['name' => 'driver'],
            [
                'display_name' => 'Driver',
                'description' => 'Delivery driver',
            ]
        );

        $this->user = User::factory()->create();
        $this->user->addRole('driver');

        $this->driver = Driver::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'is_available' => false,
        ]);

        Storage::fake('local');
    }

    private function assignment(
        DeliveryStatus $status = DeliveryStatus::InTransit,
        ?Driver $driver = null
    ): DeliveryAssignment {
        $delivery = Delivery::factory()->create([
            'status' => $status,
        ]);

        return DeliveryAssignment::create([
            'delivery_id' => $delivery->id,
            'driver_id' => ($driver ?? $this->driver)->id,
            'status' => 'accepted',
            'assigned_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    public function test_driver_can_view_proof_form(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->user)
            ->get(route('driver.assignments.proof.create', $assignment))
            ->assertOk()
            ->assertViewIs('driver.assignments.proof');
    }

    public function test_driver_can_submit_proof_of_delivery(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
                'notes' => 'Delivered successfully',
            ])
            ->assertRedirect(
                route('driver.assignments.show', $assignment)
            )
            ->assertSessionHas('success');

        $this->assertDatabaseHas('proof_of_deliveries', [
            'delivery_id' => $assignment->delivery_id,
            'driver_id' => $this->driver->id,
            'recipient_name' => 'Ahmed Ali',
        ]);

        $this->assertDatabaseHas('deliveries', [
            'id' => $assignment->delivery_id,
            'status' => DeliveryStatus::Delivered->value,
        ]);

        $this->assertNotNull(
            $assignment->delivery->fresh()->delivered_at
        );
    }

    public function test_driver_can_upload_photo_and_signature(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
                'photo' => new UploadedFile(
                    base_path('tests/Fixtures/delivery.jpg'),
                    'delivery.jpg',
                    'image/jpeg',
                    null,
                    true
                ),
                'signature' => new UploadedFile(
                    base_path('tests/Fixtures/signature.png'),
                    'signature.png',
                    'image/png',
                    null,
                    true
                ),
            ])
            ->assertRedirect();

        $proof = \App\Models\ProofOfDelivery::query()
            ->where('delivery_id', $assignment->delivery_id)
            ->firstOrFail();

        $this->assertNotNull($proof->photo_path);
        $this->assertNotNull($proof->signature_path);

        Storage::disk('local')->assertExists($proof->photo_path);
        Storage::disk('local')->assertExists($proof->signature_path);
    }

    public function test_recipient_name_is_required(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => '',
            ])
            ->assertSessionHasErrors('recipient_name');

        $this->assertDatabaseMissing('proof_of_deliveries', [
            'delivery_id' => $assignment->delivery_id,
        ]);
    }

    public function test_driver_cannot_submit_proof_twice(): void
    {
        $assignment = $this->assignment();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('proof_of_deliveries', 1);
    }

    public function test_driver_cannot_submit_proof_before_transit(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Pickup);

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_driver_cannot_submit_another_drivers_proof(): void
    {
        $otherDriver = Driver::factory()->create();

        $assignment = $this->assignment(
            DeliveryStatus::InTransit,
            $otherDriver
        );

        $this->actingAs($this->user)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
            ])
            ->assertNotFound();
    }

    public function test_guest_cannot_access_proof_form(): void
    {
        $assignment = $this->assignment();

        $this->get(route('driver.assignments.proof.create', $assignment))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_driver_role_is_forbidden(): void
    {
        $assignment = $this->assignment();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->post(route('driver.assignments.proof.store', $assignment), [
                'recipient_name' => 'Ahmed Ali',
            ])
            ->assertForbidden();
    }

    public function test_driver_cannot_open_proof_form_before_transit(): void
    {
        $assignment = $this->assignment(DeliveryStatus::Pickup);

        $this->actingAs($this->user)
            ->get(route('driver.assignments.proof.create', $assignment))
            ->assertNotFound();
    }
}
