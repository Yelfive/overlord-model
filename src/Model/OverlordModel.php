<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-11-24
 */

namespace Overlord\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $created_by
 * @property int $updated_by
 */
abstract class OverlordModel extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->fillable($this->initFillable());
    }

    protected function initFillable()
    {
        return array_keys($this->rules());
    }

    public function getAttributeLabel(string $attribute = null)
    {
        static $labels = null;
        if ($labels === null) $labels = $this->i18n();

        if ($attribute === null) {
            return $this->i18n();
        } else {
            return $labels[$attribute] ?? $attribute;
        }
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
}