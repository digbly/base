<?php

namespace Modules\Admin\Tests\Feature\Language;

use App\Models\Language;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class AdminLanguageControllerTest extends TestCase
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

        config(['app.website_id' => $this->website->id]);
    }

    protected function base(): string
    {
        return "/api/v1/admin/websites/{$this->website->id}/languages";
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    protected function editor(): User
    {
        $role = Role::query()->create([
            'name' => 'language-editor',
            'guard_name' => 'api',
            'website_id' => $this->website->id,
        ]);

        $role->syncPermissions([
            'languages.view',
            'languages.create',
            'languages.update',
            'languages.delete',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function makeLanguage(array $attributes = []): Language
    {
        return Language::create(array_merge([
            'code' => 'en',
            'name' => 'English',
            'website_id' => $this->website->id,
        ], $attributes));
    }

    public function test_index_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson($this->base())->assertUnauthorized();
    }

    public function test_index_forbids_user_without_permission(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson($this->base())->assertForbidden();
    }

    public function test_index_only_returns_languages_of_current_website(): void
    {
        Passport::actingAs($this->admin());

        $this->makeLanguage(['code' => 'en', 'name' => 'English']);

        $other = Website::create([
            'title' => 'Other Site',
            'subdomain' => 'other-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);
        $this->makeLanguage(['code' => 'fr', 'name' => 'French', 'website_id' => $other->id]);

        $this->getJson($this->base())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'en');
    }

    public function test_store_creates_language(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), [
            'code' => 'vi',
            'name' => 'Vietnamese',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'vi')
            ->assertJsonPath('data.name', 'Vietnamese')
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('languages', [
            'code' => 'vi',
            'website_id' => $this->website->id,
        ]);
    }

    public function test_store_validates_payload(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), ['code' => '', 'name' => ''])
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_store_rejects_duplicate_code_within_website(): void
    {
        Passport::actingAs($this->admin());
        $this->makeLanguage(['code' => 'en']);

        $this->postJson($this->base(), ['code' => 'en', 'name' => 'English US'])
            ->assertJsonValidationErrors('code');
    }

    public function test_store_rejects_code_not_present_in_locales(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), ['code' => 'xx-not-real', 'name' => 'Unknown'])
            ->assertJsonValidationErrors('code');
    }

    public function test_store_allows_same_code_on_another_website(): void
    {
        Passport::actingAs($this->admin());

        $other = Website::create([
            'title' => 'Other Site',
            'subdomain' => 'other-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);
        $this->makeLanguage(['code' => 'en', 'website_id' => $other->id]);

        $this->postJson($this->base(), ['code' => 'en', 'name' => 'English'])
            ->assertCreated();
    }

    public function test_store_with_default_unsets_previous_default(): void
    {
        Passport::actingAs($this->admin());
        $this->makeLanguage(['code' => 'en', 'is_default' => true]);

        $this->postJson($this->base(), [
            'code' => 'vi',
            'name' => 'Vietnamese',
            'is_default' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('languages', ['code' => 'vi', 'is_default' => true]);
        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_default' => false]);
    }

    public function test_update_changes_language_and_sets_default(): void
    {
        Passport::actingAs($this->admin());
        $language = $this->makeLanguage(['code' => 'en', 'name' => 'English']);

        $this->putJson($this->base()."/{$language->getKey()}", [
            'code' => 'en',
            'name' => 'English (US)',
            'is_default' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'English (US)')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('languages', [
            'id' => $language->getKey(),
            'name' => 'English (US)',
            'is_default' => true,
        ]);
    }

    public function test_destroy_deletes_language(): void
    {
        Passport::actingAs($this->admin());
        $language = $this->makeLanguage(['code' => 'fr', 'name' => 'French']);

        $this->deleteJson($this->base()."/{$language->getKey()}")->assertOk();

        $this->assertDatabaseMissing('languages', ['id' => $language->getKey()]);
    }

    public function test_destroy_refuses_default_language(): void
    {
        Passport::actingAs($this->admin());
        $language = $this->makeLanguage(['code' => 'vi', 'is_default' => true]);

        $this->deleteJson($this->base()."/{$language->getKey()}")->assertStatus(422);

        $this->assertDatabaseHas('languages', ['id' => $language->getKey()]);
    }

    public function test_destroy_refuses_fallback_language(): void
    {
        Passport::actingAs($this->admin());
        $language = $this->makeLanguage(['code' => 'en']);

        $this->deleteJson($this->base()."/{$language->getKey()}")->assertStatus(422);

        $this->assertDatabaseHas('languages', ['id' => $language->getKey()]);
    }

    public function test_editor_with_permission_can_create_language(): void
    {
        Passport::actingAs($this->editor());

        $this->postJson($this->base(), ['code' => 'de', 'name' => 'German'])
            ->assertCreated();
    }
}
