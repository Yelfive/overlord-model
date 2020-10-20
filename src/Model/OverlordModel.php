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
 *
 * @method static $this firstOrCreate(array $attributes, array $values = []) @see \Illuminate\Database\Eloquent\Builder::firstOrCreate
 * @method static $this create(array $attributes = []) @see \Illuminate\Database\Eloquent\Builder::create()
 * @method static bool insert(array $values) @see \Illuminate\Database\Eloquent\Builder::insert()
 */
abstract class OverlordModel extends Model
{
    public function __construct(array $attributes = [])
    {
        $this->fillable($this->initFillable());
        parent::__construct($attributes);
    }

    protected function initFillable()
    {
        return array_keys($this->rules());
    }

    /**
     * Get label for this attribute, null if not found
     *
     * @param string|null $attribute The attribute to query for, `null` to query for labels for all attributes
     * @return array|string|null Label to display, `null` if not found
     */
    public function getAttributeLabel(string $attribute = null)
    {
        // Cache the labels, the labels should not change on the same request.
        static $labels = null;
        if ($labels === null) $labels = $this->i18n();

        if ($attribute === null) {
            return $this->i18n();
        } else {
            return $labels[$attribute] ?? null;
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