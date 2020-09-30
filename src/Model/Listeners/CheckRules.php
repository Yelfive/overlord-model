<?php

namespace Overlord\Model\Listeners;

use Overlord\Model\Events\ModelSaving;
use Illuminate\Validation\ValidationException;

class CheckRules
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param ModelSaving $event
     * @return bool
     * @throws ValidationException
     */
    public function handle(ModelSaving $event): bool
    {
        if (!$event->model->validate()) throw new ValidationException($event->model->validator);
        return true;
    }
}
