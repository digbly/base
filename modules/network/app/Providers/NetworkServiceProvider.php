<?php

namespace Modules\Network\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Network\Contracts\Network as NetworkContract;
use Modules\Network\Support\NetworkRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class NetworkServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Network';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'network';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Register the module services and the network context binding.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(NetworkContract::class, function ($app) {
            return new NetworkRepository($app, $app['request']);
        });
    }

    /**
     * Bootstrap module services and resolve the current website context.
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->make(NetworkContract::class)->init();
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
