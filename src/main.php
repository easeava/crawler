<?php

define('SRC_PATH', realpath('.'));
define('LOG_PATH', SRC_PATH . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
require '../vendor/autoload.php';
require './configs/configs.php';

$configs = [
    'name'      => 'u88a',
    'work_num'  => 20,
    'max_depth' =>  1,
    'log_file'  =>  'u88',
    'dbname'    =>  'test',
    'domains'   => [
        'u88.cn',
        'www.u88.cn',
    ],
    'entry_urls' => [
        'http://www.u88.cn/',
    ],
    'list_url_regexes' => [
        "http://www.u88.cn/\.*",
    ],
    'content_url_regexes' => [

    ],
    'fields'    =>  [
        [
            'selector'  =>  '',
            'name'      =>  []
        ],
        [
            'selector'  =>  '',
            ''
        ]
    ]
];

$craw = new \crawler\core\Crawler($configs);
// $craw->download_html = function($aa) {
//     return 1;
// } 
$craw->run();
