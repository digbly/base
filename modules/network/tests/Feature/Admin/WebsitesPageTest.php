<?php

namespace Modules\Network\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Modules\Network\Tests\TestCase;

class WebsitesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('permission:generate');
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
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

    public function test_super_admin_can_view_websites(): void
    {
        $this->makeWebsite();

        $this->actingAs($this->admin(), 'web')
            ->get('/network/websites')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Network::websites/Index', false)
                ->has('websites.data')
                ->has('owners')
                ->has('networkDomain')
            );
    }

    public function test_super_admin_can_create_website(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($this->admin(), 'web')
            ->post('/network/websites', [
                'title' => 'Created Site',
                'subdomain' => 'created-site',
                'status' => WebsiteStatus::ACTIVE->value,
                'user_id' => $owner->id,
            ])
            ->assertRedirect();

        $website = Website::query()->where('subdomain', 'created-site')->firstOrFail();
        $this->assertSame($owner->id, $website->user_id);
        $this->assertTrue($website->users()->whereKey($owner->id)->exists());
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/network/websites', [])
            ->assertSessionHasErrors(['title', 'subdomain', 'status', 'user_id']);
    }

    public function test_store_rejects_duplicate_subdomain(): void
    {
        $this->makeWebsite(['subdomain' => 'taken']);

        $this->actingAs($this->admin(), 'web')
            ->post('/network/websites', [
                'title' => 'Another',
                'subdomain' => 'taken',
                'status' => WebsiteStatus::ACTIVE->value,
                'user_id' => User::factory()->create()->id,
            ])
            ->assertSessionHasErrors('subdomain');
    }

    public function test_super_admin_can_update_website(): void
    {
        $website = $this->makeWebsite(['title' => 'Old']);

        $this->actingAs($this->admin(), 'web')
            ->put('/network/websites/'.$website->id, [
                'title' => 'New',
                'subdomain' => $website->subdomain,
                'status' => WebsiteStatus::SUSPENDED->value,
                'user_id' => $website->user_id,
            ])
            ->assertRedirect();

        $this->assertSame('New', $website->fresh()->title);
        $this->assertSame(WebsiteStatus::SUSPENDED, $website->fresh()->status);
    }

    public function test_super_admin_can_delete_website(): void
    {
        $website = $this->makeWebsite();

        $this->actingAs($this->admin(), 'web')
            ->delete('/network/websites/'.$website->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
    }

    public function test_non_super_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get('/network/websites')
            ->assertForbidden();
    }
}
