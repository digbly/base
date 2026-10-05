<?php

namespace Modules\Admin\Tests\Feature\Setting;

use App\Contracts\Setting as SettingContract;
use App\Models\Setting as SettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class AdminSettingControllerTest extends TestCase
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
        return "/api/v1/admin/websites/{$this->website->id}/settings";
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
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

    public function test_index_returns_defined_defaults(): void
    {
        Passport::actingAs($this->admin());

        $this->getJson($this->base())
            ->assertOk()
            ->assertJsonPath('data.user_registration', true)
            ->assertJsonPath('data.user_verification', false)
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'description',
                    'sitename',
                    'logo',
                    'favicon',
                    'banner',
                    'user_registration',
                    'user_verification',
                ],
            ]);
    }

    public function test_update_persists_translatable_and_scalar_values(): void
    {
        Passport::actingAs($this->admin());

        $this->putJson($this->base(), [
            'title' => ['en' => 'My network', 'vi' => 'Mạng của tôi'],
            'description' => ['en' => 'Best network'],
            'sitename' => 'Network',
            'user_registration' => false,
            'user_verification' => true,
        ])->assertOk()
            ->assertJsonPath('data.title.en', 'My network')
            ->assertJsonPath('data.sitename', 'Network')
            ->assertJsonPath('data.user_registration', false)
            ->assertJsonPath('data.user_verification', true);

        $this->assertDatabaseHas('settings', [
            'code' => 'sitename',
            'value' => 'Network',
        ]);

        $title = SettingModel::withoutGlobalScope('website_id')
            ->where('code', 'title')
            ->firstOrFail();

        $this->assertTrue($title->translatable);
        $this->assertDatabaseHas('setting_translations', [
            'setting_id' => $title->id,
            'locale' => 'en',
            'lang_value' => 'My network',
        ]);
        $this->assertDatabaseHas('setting_translations', [
            'setting_id' => $title->id,
            'locale' => 'vi',
            'lang_value' => 'Mạng của tôi',
        ]);
    }

    public function test_update_rejects_invalid_boolean(): void
    {
        Passport::actingAs($this->admin());

        $this->putJson($this->base(), [
            'user_registration' => 'not-a-boolean',
        ])->assertUnprocessable()->assertJsonValidationErrors(['user_registration']);
    }

    public function test_update_rejects_invalid_media_id(): void
    {
        Passport::actingAs($this->admin());

        $this->putJson($this->base(), [
            'logo' => 'not-a-uuid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['logo']);
    }

    public function test_update_only_changes_provided_settings(): void
    {
        Passport::actingAs($this->admin());

        $this->putJson($this->base(), ['sitename' => 'Only this'])->assertOk();
        $this->putJson($this->base(), ['user_verification' => true])->assertOk();

        $this->getJson($this->base())
            ->assertOk()
            ->assertJsonPath('data.sitename', 'Only this')
            ->assertJsonPath('data.user_verification', true);
    }

    public function test_update_does_not_leak_the_editing_locale(): void
    {
        Passport::actingAs($this->admin());
        app()->setLocale('en');

        $this->putJson($this->base(), [
            'title' => ['en' => 'English title', 'vi' => 'Tiêu đề'],
        ])->assertOk();

        $this->assertSame('English title', app(SettingContract::class)->get('title'));
    }
}
