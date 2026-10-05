<?php

namespace Modules\Admin\Tests\Feature\Page;

use App\Models\Pages\Page;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Enums\PagePermission;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');

        $this->website = Website::create([
            'title' => 'Test Site',
            'subdomain' => 'test-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);

        Passport::actingAs($this->adminUser());
    }

    protected function pagesUrl(string $suffix = ''): string
    {
        return "/api/v1/admin/websites/{$this->website->id}/pages{$suffix}";
    }

    protected function adminUser(): User
    {
        $role = Role::findOrCreate('admin', 'api');
        $role->syncPermissions(PagePermission::values());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->pagesUrl())->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->pagesUrl())->assertForbidden();
    }

    public function test_store_creates_page_with_translation(): void
    {
        $response = $this->postJson($this->pagesUrl(), [
            'title' => 'About us',
            'slug' => 'about-us',
            'content' => '<p>Hello</p>',
            'status' => 'published',
            'template' => 'landing',
            'locale' => 'en',
        ])->assertCreated();

        $response->assertJsonPath('data.title', 'About us')
            ->assertJsonPath('data.slug', 'about-us')
            ->assertJsonPath('data.template', 'landing');

        $this->assertDatabaseHas('pages', [
            'template' => 'landing',
            'website_id' => $this->website->id,
        ]);

        $page = Page::query()->firstOrFail();
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'locale' => 'en',
            'slug' => 'about-us',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson($this->pagesUrl(), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'slug']);
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        $this->postJson($this->pagesUrl(), [
            'title' => 'About',
            'slug' => 'about',
        ])->assertCreated();

        $this->postJson($this->pagesUrl(), [
            'title' => 'About duplicate',
            'slug' => 'about',
        ])->assertUnprocessable()->assertJsonValidationErrors(['slug']);
    }

    public function test_update_updates_page_and_translation(): void
    {
        $page = Page::create(['status' => 'published', 'website_id' => $this->website->id]);
        $page->translateOrNew('en')->title = 'Old title';
        $page->translateOrNew('en')->slug = 'old-title';
        $page->save();

        $this->putJson($this->pagesUrl("/{$page->id}"), [
            'title' => 'New title',
            'slug' => 'new-title',
            'status' => 'draft',
            'locale' => 'en',
        ])->assertOk()->assertJsonPath('data.title', 'New title');

        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'slug' => 'new-title',
        ]);

        $this->assertSame('draft', $page->fresh()->status->value);
    }

    public function test_update_without_template_preserves_existing_template(): void
    {
        $page = Page::create(['status' => 'published', 'template' => 'landing', 'website_id' => $this->website->id]);
        $page->translateOrNew('en')->title = 'Landing';
        $page->translateOrNew('en')->slug = 'landing';
        $page->save();

        $this->putJson($this->pagesUrl("/{$page->id}"), [
            'title' => 'Landing updated',
            'slug' => 'landing',
            'locale' => 'en',
        ])->assertOk();

        $this->assertSame('landing', $page->fresh()->template);
    }

    public function test_destroy_deletes_page(): void
    {
        $page = Page::create(['status' => 'published', 'website_id' => $this->website->id]);
        $page->translateOrNew('en')->title = 'Delete me';
        $page->translateOrNew('en')->slug = 'delete-me';
        $page->save();

        $this->deleteJson($this->pagesUrl("/{$page->id}"))->assertOk();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }
}
