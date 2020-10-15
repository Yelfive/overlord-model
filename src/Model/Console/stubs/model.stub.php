<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2020-10-02
 */

/**
 * @var string $namespace
 * @var string $model
 * @var string $useHeader
 * @var string $useBody
 * @var ColumnSchema[] $columns
 * @var bool $useSoftDeletes
 */

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Overlord\Model\Support\ColumnSchema;

// @formatter:off
?>
<?= '<?php' ?>


namespace <?= $namespace ?>;
<?php if ($useSoftDeletes): ?>

use <?= SoftDeletes::class ?>;

<?php else: ?>

<?php endif ?>
class <?= $model ?> extends Contracts\<?= $model ?>Contract
{
<?php if ($useSoftDeletes): ?>

    use SoftDeletes;

<?php endif ?>
    protected function i18n(): array
    {
        return [
<?php foreach($columns as $column): ?>
            '<?= $column->columnName ?>' => __('<?= Str::snake($model, '-') ?>.<?= $column->columnName ?>'),
<?php endforeach; ?>
        ];
    }
}
