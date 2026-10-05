<?php

namespace Modules\Admin\Tests\Feature\Language;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Tests\TestCase;

class PublicLanguageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_languages_without_authentication(): void
    {
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
}
