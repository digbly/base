<?php

namespace Modules\Admin\Tests\Feature\Setting;

use App\Facades\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Tests\TestCase;

class PublicSettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_public_settings_with_defaults(): void
    {
        $this->getJson('/api/v1/settings')
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

    public function test_it_returns_stored_values(): void
    {
        Setting::set('sitename', 'My Network');
        Setting::set('user_registration', false);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.sitename', 'My Network')
            ->assertJsonPath('data.user_registration', false);
    }

    public function test_it_hides_settings_without_public_api_flag(): void
    {
        Setting::make('internal_secret')->default('hidden')->disableShowApi()->add();

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonMissingPath('data.internal_secret');
    }
}
