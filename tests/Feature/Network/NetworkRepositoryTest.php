<?php

namespace Tests\Feature\Network;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;
use Modules\Network\Support\NetworkRepository;
use ReflectionProperty;
use Tests\TestCase;

class NetworkRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the HTTP branch of NetworkRepository::init(); under PHPUnit the
        // application otherwise reports itself as running in the console.
        $property = new ReflectionProperty($this->app, 'isRunningInConsole');
        $property->setValue($this->app, false);
    }

    protected function makeWebsite(): Website
    {
        return Website::create([
            'title' => 'Main Site',
            'subdomain' => 'main',
            'status' => WebsiteStatus::ACTIVE,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    protected function repositoryForHost(string $host): NetworkRepository
    {
        return new NetworkRepository(
            $this->app,
            Request::create('/', 'GET', [], [], [], ['HTTP_HOST' => $host])
        );
    }

    public function test_loopback_host_resolves_the_main_website(): void
    {
        $main = $this->makeWebsite();
        config([
            'network.domain' => 'localhost',
            'network.main_website_id' => $main->id,
        ]);

        $repository = $this->repositoryForHost('127.0.0.1');
        $repository->init();

        $this->assertNotNull($repository->website());
        $this->assertSame($main->id, $repository->website()->id);
    }

    public function test_loopback_host_is_not_the_main_website_for_a_public_domain(): void
    {
        $main = $this->makeWebsite();
        config([
            'network.domain' => 'example.test',
            'network.main_website_id' => $main->id,
        ]);

        $repository = $this->repositoryForHost('127.0.0.1');
        $repository->init();

        $this->assertNull($repository->website());
    }
}
