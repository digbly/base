<?php

namespace Modules\Admin\Tests\Feature\Media;

use App\Models\MediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;

class AdminMediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->artisan('permission:generate');
    }

    protected function base(): string
    {
        return '/api/v1/admin/media';
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

    public function test_upload_stores_media_and_generates_thumbnail(): void
    {
        Passport::actingAs($this->admin());

        $response = $this->postJson($this->base(), [
            'files' => [UploadedFile::fake()->image('photo.jpg', 800, 600)],
            'alt' => 'A photo',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'data' => [
                ['id', 'title', 'alt', 'url', 'thumb_url', 'mime_type', 'size', 'is_image'],
            ],
        ]);

        $this->assertDatabaseCount('media_items', 1);
        $this->assertDatabaseCount('media', 1);

        $item = MediaItem::query()->firstOrFail();
        $media = $item->getFirstMedia();

        $this->assertNotNull($media);
        $this->assertSame('A photo', $item->alt);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertTrue($media->hasGeneratedConversion('thumb'));

        Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
        $this->assertMatchesRegularExpression(
            '#^\d{4}/\d{2}/\d{2}/#',
            $media->getPathRelativeToRoot()
        );
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), [
            'files' => [UploadedFile::fake()->create('malware.exe', 10)],
        ])->assertUnprocessable()->assertJsonValidationErrors(['files.0']);
    }

    public function test_upload_rejects_svg_to_prevent_stored_xss(): void
    {
        Passport::actingAs($this->admin());

        $this->postJson($this->base(), [
            'files' => [UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml')],
        ])->assertUnprocessable()->assertJsonValidationErrors(['files.0']);
    }

    public function test_index_returns_paginated_media(): void
    {
        Passport::actingAs($this->admin());
        MediaItem::factory()->count(2)->create();

        $this->getJson($this->base())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'url', 'is_image']],
                'links',
                'meta',
            ]);
    }

    public function test_update_changes_metadata(): void
    {
        Passport::actingAs($this->admin());
        $item = MediaItem::factory()->create();

        $this->putJson($this->base()."/{$item->id}", [
            'title' => 'Renamed',
            'alt' => 'Alt text',
            'caption' => 'A caption',
            'description' => 'A description',
        ])->assertOk()->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.alt', 'Alt text');

        $this->assertDatabaseHas('media_items', [
            'id' => $item->id,
            'title' => 'Renamed',
        ]);
    }

    public function test_destroy_deletes_media_item(): void
    {
        Passport::actingAs($this->admin());
        $item = MediaItem::factory()->create();

        $this->deleteJson($this->base()."/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('media_items', ['id' => $item->id]);
    }
}
