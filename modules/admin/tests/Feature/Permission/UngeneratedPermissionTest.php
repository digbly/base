<?php

namespace Modules\Admin\Tests\Feature\Permission;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Modules\Admin\Tests\TestCase;
use Modules\Auth\Models\User;

class UngeneratedPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_with_ungenerated_permission_is_forbidden_not_an_error(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/menus')->assertForbidden();
    }
}
