<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\DeliveryStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, int $historyId): string
    {
        $user->notify(new DeliveryStatusNotification(
            1,
            'DH-TEST-001',
            'delivered',
            $historyId
        ));

        return $user->notifications()->latest()->firstOrFail()->id;
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertUnauthorized();

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertUnauthorized();

        $this->patchJson('/api/v1/notifications/fake-id/read')
            ->assertUnauthorized();

        $this->postJson('/api/v1/notifications/read-all')
            ->assertUnauthorized();
    }

    public function test_user_can_list_only_own_notifications(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownId = $this->notify($owner, 101);
        $otherId = $this->notify($other, 102);

        $this->actingAs($owner)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownId)
            ->assertDontSee($otherId);
    }

    public function test_unread_count_is_user_specific(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($owner, 201);
        $this->notify($owner, 202);
        $this->notify($other, 203);

        $this->actingAs($owner)
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 2);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $id = $this->notify($owner, 301);

        $this->actingAs($owner)
            ->patchJson("/api/v1/notifications/{$id}/read")
            ->assertOk();

        $this->assertNotNull(
            $owner->notifications()->findOrFail($id)->read_at
        );
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $id = $this->notify($other, 401);

        $this->actingAs($owner)
            ->patchJson("/api/v1/notifications/{$id}/read")
            ->assertNotFound();

        $this->assertNull(
            $other->notifications()->findOrFail($id)->read_at
        );
    }

    public function test_mark_all_read_affects_only_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->notify($owner, 501);
        $this->notify($owner, 502);
        $otherId = $this->notify($other, 503);

        $this->actingAs($owner)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(
            0,
            $owner->unreadNotifications()->count()
        );

        $this->assertNull(
            $other->notifications()->findOrFail($otherId)->read_at
        );
    }
}
