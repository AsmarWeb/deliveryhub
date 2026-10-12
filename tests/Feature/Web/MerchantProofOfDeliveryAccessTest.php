<?php

namespace Tests\Feature\Web;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Merchant;
use App\Models\ProofOfDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laratrust\Models\Role;
use Tests\TestCase;

class MerchantProofOfDeliveryAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $merchantUser;
    private User $otherMerchantUser;
    private User $driverUser;
    private Delivery $delivery;
    private ProofOfDelivery $proof;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['merchant', 'driver'] as $role) {
            Role::firstOrCreate(
                ['name' => $role],
                ['display_name' => ucfirst($role)]
            );
        }

        $this->merchantUser = User::factory()->create();
        $this->merchantUser->addRole('merchant');

        $this->otherMerchantUser = User::factory()->create();
        $this->otherMerchantUser->addRole('merchant');

        $this->driverUser = User::factory()->create();
        $this->driverUser->addRole('driver');

        $merchant = Merchant::factory()->create([
            'user_id' => $this->merchantUser->id,
        ]);

        Merchant::factory()->create([
            'user_id' => $this->otherMerchantUser->id,
        ]);

        $this->delivery = Delivery::factory()->create([
            'merchant_id' => $merchant->id,
        ]);

        $driver = Driver::factory()->create([
            'user_id' => $this->driverUser->id,
        ]);

        Storage::fake('local');

        $photoPath = "proof-of-deliveries/{$this->delivery->id}/photos/photo.jpg";
        $signaturePath = "proof-of-deliveries/{$this->delivery->id}/signatures/signature.png";

        Storage::disk('local')->put($photoPath, 'test photo');
        Storage::disk('local')->put($signaturePath, 'test signature');

        $this->proof = ProofOfDelivery::create([
            'delivery_id' => $this->delivery->id,
            'driver_id' => $driver->id,
            'recipient_name' => 'Test Recipient',
            'photo_path' => $photoPath,
            'signature_path' => $signaturePath,
            'confirmed_at' => now(),
        ]);
    }

    private function fileUrl(string $type): string
    {
        return route('merchant.deliveries.proof.file', [
            'delivery' => $this->delivery,
            'type' => $type,
        ]);
    }

    public function test_merchant_can_view_own_photo(): void
    {
        $this->actingAs($this->merchantUser)
            ->get($this->fileUrl('photo'))
            ->assertOk();
    }

    public function test_merchant_can_view_own_signature(): void
    {
        $this->actingAs($this->merchantUser)
            ->get($this->fileUrl('signature'))
            ->assertOk();
    }

    public function test_merchant_can_see_proof_details_on_delivery_page(): void
    {
        $this->actingAs($this->merchantUser)
            ->get(route('merchant.deliveries.show', $this->delivery))
            ->assertOk()
            ->assertSee('Test Recipient')
            ->assertSee($this->fileUrl('photo'), false)
            ->assertSee($this->fileUrl('signature'), false);
    }

    public function test_other_merchant_cannot_view_photo(): void
    {
        $this->actingAs($this->otherMerchantUser)
            ->get($this->fileUrl('photo'))
            ->assertNotFound();
    }

    public function test_other_merchant_cannot_view_signature(): void
    {
        $this->actingAs($this->otherMerchantUser)
            ->get($this->fileUrl('signature'))
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->fileUrl('photo'))
            ->assertRedirect(route('login'));
    }

    public function test_driver_cannot_access_merchant_file_route(): void
    {
        $this->actingAs($this->driverUser)
            ->get($this->fileUrl('photo'))
            ->assertForbidden();
    }

    public function test_missing_photo_returns_not_found(): void
    {
        Storage::disk('local')->delete($this->proof->photo_path);

        $this->actingAs($this->merchantUser)
            ->get($this->fileUrl('photo'))
            ->assertNotFound();
    }

    public function test_unexpected_storage_path_is_rejected(): void
    {
        $unexpectedPath = 'other-files/private.jpg';

        Storage::disk('local')->put(
            $unexpectedPath,
            'private content'
        );

        $this->proof->update([
            'photo_path' => $unexpectedPath,
        ]);

        $this->actingAs($this->merchantUser)
            ->get($this->fileUrl('photo'))
            ->assertNotFound();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->proof->update([
            'photo_path' => "proof-of-deliveries/{$this->delivery->id}/photos/../private.jpg",
        ]);

        $this->actingAs($this->merchantUser)
            ->get($this->fileUrl('photo'))
            ->assertNotFound();
    }

    public function test_missing_proof_returns_not_found(): void
    {
        $this->proof->delete();

        $this->actingAs($this->merchantUser)
            ->get($this->fileUrl('photo'))
            ->assertNotFound();
    }
}
