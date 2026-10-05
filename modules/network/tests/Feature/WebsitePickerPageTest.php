<?php

namespace Modules\Network\Tests\Feature;

use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Network\Tests\TestCase;
use Modules\Auth\Models\User;

class WebsitePickerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permission:generate');
    }

    protected function makeWebsite(User $owner, array $attributes = []): Website
    {
        $website = Website::create(array_merge([
            'title' => 'Site '.uniqid(),
            'subdomain' => 'site-'.uniqid(),
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => $owner->id,
        ], $attributes));

        $website->users()->attach($owner);

        return $website;
    }

    public function test_authenticated_user_can_view_the_picker(): void
    {
        $user = User::factory()->create();
        $this->makeWebsite($user);

        $this->actingAs($user, 'web')
            ->get('/admin/websites')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Network::websites/Picker', false)
                ->has('websites', 1)
                ->has('networkDomain')
                ->where('canCreate', false)
            );
    }

    public function test_super_admin_can_create_a_website_owned_by_self(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin, 'web')
            ->post('/admin/websites', [
                'title' => 'Mine',
                'subdomain' => 'mine',
                'status' => WebsiteStatus::ACTIVE->value,
            ])
            ->assertRedirect(route('admin.websites.index'));

        $website = Website::query()->where('subdomain', 'mine')->firstOrFail();
        $this->assertSame($admin->id, $website->user_id);
        $this->assertTrue($website->users()->whereKey($admin->id)->exists());
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin, 'web')
            ->post('/admin/websites', [])
            ->assertSessionHasErrors(['title', 'subdomain', 'status']);
    }

    public function test_store_rejects_duplicate_subdomain(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->makeWebsite($admin, ['subdomain' => 'taken']);

        $this->actingAs($admin, 'web')
            ->post('/admin/websites', [
                'title' => 'Another',
                'subdomain' => 'taken',
                'status' => WebsiteStatus::ACTIVE->value,
            ])
            ->assertSessionHasErrors('subdomain');
    }

    public function test_user_without_create_permission_cannot_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->post('/admin/websites', [
                'title' => 'Nope',
                'subdomain' => 'nope',
                'status' => WebsiteStatus::ACTIVE->value,
            ])
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/websites')->assertRedirect('/login');
    }
}
