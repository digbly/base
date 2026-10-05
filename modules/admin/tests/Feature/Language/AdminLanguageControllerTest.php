<?php

namespace Modules\Admin\Tests\Feature\Language;

use App\Models\Language;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;

class AdminLanguageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('permission:generate');
    }

    protected function base(): string
    {
        return '/api/v1/admin/languages';
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
            'code' => 'fr',
            'name' => 'French',
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

    public function test_store_creates_language(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), [
            'code' => 'fr',
            'name' => 'French',
        ])->assertCreated()
            ->assertJsonPath('data.code', 'fr')
            ->assertJsonPath('data.name', 'French')
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('languages', [
            'code' => 'fr',
        ]);
    }

    public function test_store_validates_payload(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), ['code' => '', 'name' => ''])
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), ['code' => 'en', 'name' => 'English US'])
            ->assertJsonValidationErrors('code');
    }

    public function test_store_rejects_code_not_present_in_locales(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), ['code' => 'xx-not-real', 'name' => 'Unknown'])
            ->assertJsonValidationErrors('code');
    }

    public function test_store_with_default_unsets_previous_default(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), [
            'code' => 'de',
            'name' => 'German',
            'is_default' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('languages', ['code' => 'de', 'is_default' => true]);
        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_default' => false]);
    }

    public function test_update_changes_language_and_sets_default(): void
    {
        Passport::actingAs($this->admin());
        $language = $this->makeLanguage(['code' => 'fr', 'name' => 'French']);

        $this->putJson($this->base()."/{$language->getKey()}", [
            'code' => 'fr',
            'name' => 'French (CA)',
            'is_default' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'French (CA)')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('languages', [
            'id' => $language->getKey(),
            'name' => 'French (CA)',
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
        $language = $this->makeLanguage(['code' => 'fr', 'is_default' => true]);

        $this->deleteJson($this->base()."/{$language->getKey()}")->assertStatus(422);

        $this->assertDatabaseHas('languages', ['id' => $language->getKey()]);
    }

    public function test_destroy_refuses_fallback_language(): void
    {
        Passport::actingAs($this->admin());

        Language::query()->where('code', 'en')->update(['is_default' => false]);
        $language = Language::query()->where('code', 'en')->firstOrFail();

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
