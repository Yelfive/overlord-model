<?php

/**
 * @author Felix yelfivehuang@gmail.com
 */

return [
    // todo, should check if the model in `artisan overlord:model model` starts with this namespace
    //  artisan overlord:model App\\User, those with back slashes should and starts with `App\\` should be created
    //  where namespace indicates: root/src/app/User.php
    'namespace' => 'App\Models',
    'base_model' => \Overlord\Model\OverlordModel::class,
    'dir' => 'app\Models',
    'prefer_array_rules' => true,
    'global_trans_keys' => ['id', 'created_at', 'updated_at', 'deleted_at'],
];