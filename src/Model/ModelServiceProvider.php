<?php

/**
 * @author Felix yelfivehuang@gmail.com
 */

namespace Overlord\Model;

use Illuminate\Support\ServiceProvider;
use Overlord\Model\Console\MakeModelCommand;

class ModelServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/overlord-model.php', 'overlord-model');
        // todo, check if the event is loaded it works
        $this->app->register(EventServiceProvider::class);
    }

    /**
     * @link https://laravel.com/docs/7.x/providers#the-boot-method
     */
    public function boot()
    {
        if (!$this->app->runningInConsole()) return;

        $this->commands(MakeModelCommand::class);
    }
}