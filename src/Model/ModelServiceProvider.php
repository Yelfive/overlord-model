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
        $this->app->register(EventServiceProvider::class);
    }

    /**
     * @link https://laravel.com/docs/7.x/providers#the-boot-method
     */
    public function boot()
    {
        $this->commands(MakeModelCommand::class);
    }
}