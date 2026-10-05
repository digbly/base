<?php

namespace Tests\Feature\Translation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTranslationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_every_namespace_without_authentication(): void
    {
        $this->getJson('/api/v1/translations/en')
            ->assertOk()
            ->assertJsonPath('common.layout.brand', 'SiteStore')
            ->assertJsonPath('common.topbar.openMenu', 'Open navigation menu')
            ->assertJsonPath('admin.nav.dashboard', 'Dashboard')
            ->assertJsonPath('admin.users.title', 'Users')
            ->assertJsonPath('admin.media.title', 'Media Library')
            ->assertJsonPath('auth.login.title', 'Welcome back')
            ->assertJsonPath('blog.posts.title', 'Blog Posts')
            ->assertJsonPath('blog.nav.blogPosts', 'Blog Posts');
    }

    public function test_it_returns_vietnamese_translations(): void
    {
        $this->getJson('/api/v1/translations/vi')
            ->assertOk()
            ->assertJsonPath('auth.login.title', 'Chào mừng trở lại')
            ->assertJsonPath('blog.posts.title', 'Bài viết');
    }

    public function test_it_returns_not_found_for_an_unknown_locale(): void
    {
        $this->getJson('/api/v1/translations/xx')->assertNotFound();
    }
}
