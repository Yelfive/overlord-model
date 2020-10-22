<?php

/**
 * @author Felix yelfivehuang@gmail.com
 */

return [
    // todo, should check if the model in `artisan overlord:model model` starts with this namespace
    'namespace' => 'App\Models',
    'base_model' => \Overlord\Model\OverlordModel::class,
    'dir' => 'app\Models',
    'prefer_array_rules' => true,
    'global_trans_keys' => ['id', 'created_at', 'updated_at', 'deleted_at'],
];