<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-11-24
 */

namespace Overlord\Model;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator as ValidatorBuilder;
use Illuminate\Validation\Validator;
use Overlord\Model\Events\ModelSaving;

/**
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $created_by
 * @property int $updated_by
 *
 * @method static $this firstOrCreate(array $attributes, array $values = []) @see \Illuminate\Database\Eloquent\Builder::firstOrCreate
 * @method static $this create(array $attributes = []) @see \Illuminate\Database\Eloquent\Builder::create()
 * @method static $this findOrFail(mixed|array|Arrayable $id, array $columns = ['*']) @see \Illuminate\Database\Eloquent\Builder::findOrFail()
 * @method static bool insert(array $values) @see \Illuminate\Database\Eloquent\Builder::insert()
 * @method static Builder groupBy(...$groups)
 */
abstract class OverlordModel extends Model
{

    /**
     *
     * After validating attributes, the validator will be saved,
     * by calling {@see getValidator()}, you can get the latest one.
     *
     * @var null|Validator
     */
    public ?Validator $validator;

    /**
     * Validation messages for attributes,
     * see {@link https://laravel.com/docs/8.x/validation#custom-error-messages Laravel document}
     * for more details
     *
     * @var array
     */
    protected array $messages = [];

    public function __construct(array $attributes = [])
    {
        $this->fillable($this->fillableFields());

        $this->dispatchesEvents = array_merge($this->dispatchesEvents, $this->events());

        parent::__construct($attributes);
    }

    /**
     * Register model events, the returned array will be merged to {@see $dispatchesEvents} as events.
     *
     * @return string[]
     */
    protected function events(): array
    {
        return [
            'saving' => ModelSaving::class,
        ];
    }

    /**
     * The returned array will be used as fillable fields.
     *
     * @return array
     */
    protected function fillableFields(): array
    {
        return array_keys($this->rules());
    }

    /**
     * Get label for this attribute, null if not found
     *
     * @param string|null $attribute The attribute to query for, `null` to query for labels for all attributes
     * @return array|string|null Label to display, `null` if not found
     */
    public static function getAttributeLabel(string $attribute = null)
    {
        // Cache the labels, the labels should not change on the same request.
        static $labels = null;
        if ($labels === null) $labels = (new static)->i18n();

        if ($attribute === null) {
            return $labels;
        } else {
            return $labels[$attribute] ?? null;
        }
    }

    /**
     * Similar to {@see __()} helper, except that this method translates given
     * `$attribute` by calling preset {@see i18n()}
     *
     * @param string $attribute
     * @return array|string
     * @see getAttributeLabel
     */
    public static function __(string $attribute)
    {
        return static::getAttributeLabel($attribute) ?? $attribute;
    }

    /**
     * An array of rule against the table fields, such as to validate the range and data type for fields.
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Returns an array that defines the i18n for the model attributes(table fields)
     * @return array
     */
    abstract protected function i18n(): array;

    public function validate(array $attributes = null): bool
    {
        if ($this->exists) {
            if (!is_array($attributes)) $attributes = $this->getDirty();
            $rules = array_intersect_key($this->rules(), $attributes);
        } else {
            $attributes = $this->attributes;
            $rules = $this->rules();
        }

        $this->validator = ValidatorBuilder::make($attributes, $rules, $this->getMessages());

        return $this->validator->passes();
    }

    public function messages(array $messages)
    {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }

    /**
     * Get defined messages for rules
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the latest validator after {@see validate()},
     * `null` will be returned if the previous method is not called.
     *
     * @return Validator|null
     */
    public function getValidator(): ?Validator
    {
        return $this->validator;
    }
}
