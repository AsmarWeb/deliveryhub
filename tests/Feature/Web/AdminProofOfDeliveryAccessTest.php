<?php

namespace Tests\Feature\Web;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\ProofOfDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laratrust\Models\Role;
use Tests\TestCase;

class AdminProofOfDeliveryAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $merchant;
    private Delivery $delivery;
    private ProofOfDelivery $proof;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'merchant'] as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => ucfirst($name)]
            );
        }

        $this->admin = User::factory()->create();
        $this->admin->addRole('admin');

        $this->merchant = User::factory()->create();
        $this->merchant->addRole('merchant');

        $this->delivery = Delivery::factory()->create();

        $driver = Driver::create([
            'user_id' => User::factory()->create()->id,
            'phone' => '0501111111',
            'status' => 'active',
            'is_available' => false,
        ]);

        Storage::fake('local');

        $photo = "proof-of-deliveries/{$this->delivery->id}/photos/photo.jpg";
        $signature = "proof-of-deliveries/{$this->delivery->id}/signatures/signature.png";

        Storage::disk('local')->put($photo, 'photo content');
        Storage::disk('local')->put($signature, 'signature content');

        $this->proof = ProofOfDelivery::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $driver->id,
            'recipient_name' => 'Test Recipient',
            'photo_path' => $photo,
            'signature_path' => $signature,
            'confirmed_at' => now(),
        ]);
    }

    private function url(string $type): string
    {
        return route('admin.deliveries.proof.file', [
            'delivery' => $this->delivery,
            'type' => $type,
        ]);
    }


    public function test_admin_can_view_photo(): void
    {
        $response = $this->actingAs($this->admin)
            ->get($this->url('photo'));

        $response->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertNotNull($cacheControl);

        $directives = array_map(
            'trim',
            explode(',', $cacheControl)
        );

        $this->assertContains('private', $directives);
        $this->assertContains('no-store', $directives);
        $this->assertNotContains('public', $directives);
    }


    public function test_admin_can_view_signature(): void
    {
        $this->actingAs($this->admin)
            ->get($this->url('signature'))
            ->assertOk();
    }

    public function test_merchant_cannot_use_admin_file_route(): void
    {
        $this->actingAs($this->merchant)
            ->get($this->url('photo'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->url('photo'))
            ->assertRedirect(route('login'));
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::disk('local')->delete($this->proof->photo_path);

        $this->actingAs($this->admin)
            ->get($this->url('photo'))
            ->assertNotFound();
    }

    public function test_invalid_storage_path_returns_404(): void
    {
        $this->proof->update([
            'photo_path' => 'other-directory/private.jpg',
        ]);

        $this->actingAs($this->admin)
            ->get($this->url('photo'))
            ->assertNotFound();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->proof->update([
            'photo_path' => "proof-of-deliveries/{$this->delivery->id}/photos/../secret.jpg",
        ]);

        $this->actingAs($this->admin)
            ->get($this->url('photo'))
            ->assertNotFound();
    }

    public function test_missing_proof_returns_404(): void
    {
        $this->proof->delete();

        $this->actingAs($this->admin)
            ->get($this->url('photo'))
            ->assertNotFound();
    }

    public function test_invalid_file_type_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->get("/admin/deliveries/{$this->delivery->id}/proof/document")
            ->assertNotFound();
    }
}
