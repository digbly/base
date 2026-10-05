<?php

namespace Modules\Admin\Tests\Feature\Language;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class PublicLanguageApiTest extends TestCase
{
    use RefreshDatabase;

    protected Website $website;

    protected function setUp(): void
    {
        parent::setUp();

        $this->website = Website::create([
            'title' => 'Test Site',
            'subdomain' => 'test-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);

        config(['app.website_id' => $this->website->id]);
    }

    public function test_it_lists_languages_without_authentication(): void
    {
        Language::create([
            'code' => 'en',
            'name' => 'English',
            'website_id' => $this->website->id,
            'is_default' => true,
        ]);
        Language::create([
            'code' => 'vi',
            'name' => 'Vietnamese',
            'website_id' => $this->website->id,
        ]);

        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'code', 'name', 'is_default'],
                ],
            ]);
    }

    public function test_it_only_lists_languages_of_current_website(): void
    {
        $other = Website::create([
            'title' => 'Other Site',
            'subdomain' => 'other-site',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);

        Language::create([
            'code' => 'en',
            'name' => 'English',
            'website_id' => $this->website->id,
        ]);
        Language::create([
            'code' => 'fr',
            'name' => 'French',
            'website_id' => $other->id,
        ]);

        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'en');
    }
}
