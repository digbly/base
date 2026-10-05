<?php

namespace Tests\Feature\Network;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Tests\TestCase;

class NetworkAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        Passport::actingAs($this->superAdmin);
    }

    protected function makeWebsite(array $attributes = []): Website
    {
        return Website::create(array_merge([
            'title' => 'Site '.uniqid(),
            'subdomain' => 'site-'.uniqid(),
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/network/websites')->assertUnauthorized();
        $this->getJson('/api/v1/network/users')->assertUnauthorized();
        $this->getJson('/api/v1/network/dashboard')->assertUnauthorized();
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/network/websites')->assertForbidden();
        $this->getJson('/api/v1/network/users')->assertForbidden();
        $this->getJson('/api/v1/network/roles')->assertForbidden();
        $this->getJson('/api/v1/network/dashboard')->assertForbidden();
    }

    public function test_dashboard_returns_network_overview(): void
    {
        $this->makeWebsite(['status' => WebsiteStatus::ACTIVE]);
        $this->makeWebsite(['status' => WebsiteStatus::ACTIVE]);
        $this->makeWebsite(['status' => WebsiteStatus::SUSPENDED]);

        $this->getJson('/api/v1/network/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.websites.total', 3)
            ->assertJsonPath('data.stats.websites.active', 2)
            ->assertJsonPath('data.stats.websites.inactive', 0)
            ->assertJsonPath('data.stats.websites.suspended', 1)
            ->assertJsonStructure([
                'data' => [
                    'stats' => ['websites', 'users'],
                    'recent_websites' => [['id', 'title', 'status']],
                    'recent_users' => [['id', 'name', 'email']],
                ],
            ]);
    }

    public function test_index_lists_every_website(): void
    {
        $this->makeWebsite(['title' => 'Alpha']);
        $this->makeWebsite(['title' => 'Beta']);

        $this->getJson('/api/v1/network/websites')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_websites(): void
    {
        $this->makeWebsite(['title' => 'Alpha', 'status' => WebsiteStatus::ACTIVE]);
        $this->makeWebsite(['title' => 'Beta', 'status' => WebsiteStatus::SUSPENDED]);

        $this->getJson('/api/v1/network/websites?q=Alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/network/websites?status=suspended')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Beta');
    }

    public function test_store_creates_website_for_any_owner(): void
    {
        $owner = User::factory()->create();

        $response = $this->postJson('/api/v1/network/websites', [
            'title' => 'New Site',
            'subdomain' => 'new-site',
            'domain' => 'new-site.com',
            'status' => WebsiteStatus::ACTIVE->value,
            'user_id' => $owner->id,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'New Site')
            ->assertJsonPath('data.owner.id', $owner->id);

        $this->assertDatabaseHas('website_user', [
            'website_id' => $response->json('data.id'),
            'user_id' => $owner->id,
        ]);
    }

    public function test_store_validates_website_input(): void
    {
        $this->postJson('/api/v1/network/websites', [
            'title' => '',
            'subdomain' => 'Invalid Subdomain',
            'status' => 'invalid',
            'user_id' => 'not-a-uuid',
        ])->assertJsonValidationErrors(['title', 'subdomain', 'status', 'user_id']);
    }

    public function test_update_and_delete_website(): void
    {
        $website = $this->makeWebsite(['title' => 'Old']);

        $this->putJson("/api/v1/network/websites/{$website->id}", [
            'title' => 'Updated',
            'subdomain' => $website->subdomain,
            'status' => WebsiteStatus::SUSPENDED->value,
            'user_id' => $website->user_id,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.status', 'suspended');

        $this->deleteJson("/api/v1/network/websites/{$website->id}")->assertOk();

        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
    }

    public function test_index_lists_every_user(): void
    {
        User::factory()->count(3)->create();

        $this->getJson('/api/v1/network/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'roles', 'is_super_admin', 'permissions']],
                'meta',
            ]);
    }

    public function test_store_creates_user(): void
    {
        $this->postJson('/api/v1/network/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_update_user(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/v1/network/users/{$user->id}", [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_delete_and_restore_user(): void
    {
        $user = User::factory()->create();

        $this->deleteJson("/api/v1/network/users/{$user->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->postJson("/api/v1/network/users/{$user->id}/restore")->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    public function test_roles_endpoint_lists_roles_for_super_admin(): void
    {
        Role::findOrCreate('editor', 'api');

        $this->getJson('/api/v1/network/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'editor']);
    }

    public function test_super_admin_cannot_delete_self(): void
    {
        $this->deleteJson("/api/v1/network/users/{$this->superAdmin->id}")
            ->assertStatus(422);
    }

    public function test_super_admin_cannot_revoke_own_super_admin_access(): void
    {
        $this->putJson("/api/v1/network/users/{$this->superAdmin->id}", [
            'name' => $this->superAdmin->name,
            'email' => $this->superAdmin->email,
            'is_super_admin' => false,
        ])->assertStatus(422);
    }

    public function test_can_reset_user_password(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/v1/network/users/{$user->id}/password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();
    }
}
