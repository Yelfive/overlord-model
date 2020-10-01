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
 * @var \Overlord\Model\Support\ColumnSchema[] $columns
 * @var bool $useSoftDeletes
 */
?>
<?= '<?php' ?>


namespace <?= $namespace ?>;
<?php if ($useSoftDeletes): ?>

use <?= \Illuminate\Database\Eloquent\SoftDeletes::class ?>;

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
            '<?= $column->columnName ?>' => __('<?= \Illuminate\Support\Str::snake($model) ?>.<?= $column->columnName ?>'),
<?php endforeach; ?>
        ];
    }
}
