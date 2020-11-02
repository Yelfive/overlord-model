<?php

namespace Overlord\Model\Listeners;

use Overlord\Model\Events\ModelSaving;
use Illuminate\Validation\ValidationException;
use Overlord\Model\OverlordModel;

class ValidateModelByRules
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
        $model = $event->model;
        if ($model instanceof OverlordModel) {
            // Exception will be thrown if fails, for programmatically handling the event,
            // you can catch this validation exception, after model saved and access the validator
            // by calling `$exception->validator`
            if (!$model->validate()) throw new ValidationException($model->getValidator());
        }
        return true;
    }
}
