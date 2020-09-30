<?php

/**
 * @author Felix yelfivehuang@gmail.com
 */

namespace Overlord\Model;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Overlord\Model\Events\ModelSaving;
use Overlord\Model\Listeners\CheckRules;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ModelSaving::class => [
            CheckRules::class,
        ],
    ];
}