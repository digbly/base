<?php

namespace Modules\Network\Support;

use App\Contracts\Setting as SettingContract;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Request;
use Modules\Network\Contracts\Network as NetworkContract;
use Modules\Network\Enums\WebsiteStatus;
use Modules\Network\Models\Website;

class NetworkRepository implements NetworkContract
{
    protected ?Website $website = null;

    protected ConfigRepository $config;

    protected ConnectionResolverInterface $db;

    public function __construct(protected Application $app, protected Request $request)
    {
        $this->config = $app->make(ConfigRepository::class);
        $this->db = $app->make(ConnectionResolverInterface::class);
    }

    protected function setting(): SettingContract
    {
        return $this->app->make(SettingContract::class);
    }

    public function init(null|string|Website $website = null): void
    {
        if ($website) {
            if (is_string($website)) {
                $this->website = Website::query()->findOrFail($website);
            } else {
                $this->website = $website;
            }

            $this->setup();

            return;
        }

        if ($this->app->runningInConsole()) {
            try {
                $this->website = Website::query()->find(config('network.main_website_id'));
                $this->setup();
            } catch (\Throwable) {
                // The websites table might not exist yet while running in console.
            }

            return;
        }

        if ($this->isMainDomain()) {
            $this->website = Website::query()->find(config('network.main_website_id'));
            $this->setup();

            return;
        }

        $this->website = Website::query()
            ->whereHost($this->request->getHost())
            ->where('status', WebsiteStatus::ACTIVE)
            ->first();

        $this->setup();
    }

    /**
     * Determine whether the current request targets the main website.
     *
     * Beside an exact match with the configured domain, loopback aliases are
     * accepted while the main domain itself is a loopback address. The admin
     * SPA usually reaches the API through a local proxy (e.g. Vite targeting
     * 127.0.0.1) even though the main domain is configured as "localhost"; the
     * two must resolve to the same website or dynamic modules would not be
     * activated.
     */
    protected function isMainDomain(): bool
    {
        $host = $this->request->getHost();
        $domain = config('network.domain');

        if ($host === $domain) {
            return true;
        }

        return $this->isLoopback($domain) && $this->isLoopback($host);
    }

    protected function isLoopback(?string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public function setup(): void
    {
        if (! $this->website) {
            return;
        }

        config(['app.website_id' => $this->website->id]);

        $this->setupDatabase();

        try {
            $locale = $this->setting()->get('language', config('app.locale'));
        } catch (\Throwable) {
            $locale = config('app.locale');
        }

        config(['app.locale' => $locale]);
        config(['translatable.locale' => $locale]);
        config(['translatable.fallback_locale' => $locale]);
        config(['cache.prefix' => 'network_'.str_replace('-', '', $this->website->id).'_cache']);
    }

    protected function setupDatabase(): void
    {
        if (is_null($this->website->database)) {
            return;
        }

        if (config('database.connections.subsite') === null) {
            return;
        }

        $this->config->set(
            'database.connections.subsite.database',
            $this->website->database
        );

        $this->config->set('database.default', 'subsite');

        $this->db->purge('subsite');
    }

    public function currentIsMainSite(): bool
    {
        return $this->website?->isMainWebsite() ?? false;
    }

    public function website(): ?Website
    {
        return $this->website;
    }
}
