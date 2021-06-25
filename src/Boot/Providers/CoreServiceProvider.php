<?php

namespace Core\Boot\Providers;

use Core\Dev\Commands\MakeClassCommand;
use Core\Dev\Commands\MakeTraitCommand;
use Core\Dev\Commands\RunSchemaCommand;
use Core\Dev\Commands\RunSeedersCommand;
use Core\Dev\Commands\RunInstallCommand;
use Core\Dev\Commands\MakeRepoCommand;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * The available commands
     *
     * @var array
     */
    protected $commands = [
        RunInstallCommand::class,
        MakeTraitCommand::class,
        MakeClassCommand::class,
        MakeRepoCommand::class,
        RunSchemaCommand::class,
        RunSeedersCommand::class,
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();

        $this->mergeConfigFrom(
            __DIR__.'/../../Conf/core.php', 'core'
        );

    }

    public function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands( $this->commands );
        }
    }


    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([
            __DIR__.'/../../../lib/stubs' => base_path('stubs/custom'),
        ], 'stubs');

        $this->publishes([
            __DIR__.'/../../Conf/core.php' => config_path('core.php'),
        ], 'config');
    }
}
