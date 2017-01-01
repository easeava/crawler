<?php

define('SRC_PATH', realpath('.'));
define('LOG_PATH', SRC_PATH . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
require '../vendor/autoload.php';
require './configs/configs.php';

$configs = [
    'name'      => 'u88',
    'work_num'  => 10,
    'domains'   => [
        'u88.cn',
        'www.u88.cn',
        'canyin668.com',
        'www.canyin668.com'
    ],
    'entry_urls' => [
        'http://www.u88.cn/',
        'http://www.canyin668.com'
    ]
];

$craw = new \crawler\core\Produce($configs);
$craw->run();
