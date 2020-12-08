<?php

/*
 * psr2
 */

namespace Scout\Xunsearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Console\FlushCommand;
use Laravel\Scout\Console\ImportCommand;
use Laravel\Scout\EngineManager;
use Scout\Xunsearch\Engines\XunsearchEngine;

class XunsearchServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function register()
    {
        //$this->app->configure('scout');
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('scout.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('scout');
        }
        $this->app->singleton(EngineManager::class, function ($app) {
            return (new EngineManager($app))->extend('xunsearch', function ($app) {
                return new XunsearchEngine(new XunsearchClient(
                            $app['config']['scout.xunsearch.index'],
                            $app['config']['scout.xunsearch.search'],
                            ['schema' => $app['config']['scout.xunsearch.schema']]
                        ));
            });
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                    ImportCommand::class,
                    FlushCommand::class,
            ]);
        }
        
        $this->app->bind(XunsearchClient::class, function ($app) {
            return new XunsearchClient(
                $app['config']['scout.xunsearch.index'],
                $app['config']['scout.xunsearch.search'],
                ['schema' => $app['config']['scout.xunsearch.schema']]
             );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [EngineManager::class];
    }
}
