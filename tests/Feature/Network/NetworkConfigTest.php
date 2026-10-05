<?php

namespace Tests\Feature\Network;

use Tests\TestCase;

class NetworkConfigTest extends TestCase
{
    public function test_it_returns_network_config(): void
    {
        config([
            'network.domain' => 'example.test',
            'network.subsite_domain' => 'subsites.example.test',
        ]);

        $this->getJson('/api/v1/network/config')
            ->assertOk()
            ->assertJsonPath('data.domain', 'example.test')
            ->assertJsonPath('data.subsite_domain', 'subsites.example.test');
    }
}