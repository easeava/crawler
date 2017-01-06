<?php 

require '../../src/main.php';

$configs = [
    'name' => 'u88',
    // 'daemonize' => true,
    'worker_num' => 10,
    'interval'  =>  10,
    'domains' => [
        'u88.cn',
        'www.u88.cn'
    ],
    'entry_urls' => [
        'http://www.u88.cn/'
    ],
    'content_url_regexes' => [
        "http://www.u88.cn/news/[a-z]+/[a-z]+/\d+\.html\.*",
    ],
    'max_try' => 5,
    // 'export' => [
    //     'type' => 'csv',
    //     'file' => __DIR__ . '/u88.csv',
    // ],
    'export' => [
        'type'  => 'sql',
        'file'  => __DIR__ . '/u88.sql',
        'table' => 'content',
    ],
    // 'export' => [
    //     'type' => 'db', 
    //     'table' => 'content',
    // ],
    'fields' => [
        [
            'name' => "article_content",
            'selector' => '/html/body/div[1]/div[2]/div[1]/div[1]/div[2]',
            'required' => true,
        ]
    ],
];

$craw = new crawler\core\Crawler($configs);
// $craw->html_extract_field = function($fieldname, $data, $page) {
//     if ($fieldname === 'depth') {
//         return $page['request']['depth'];
//     }

//     return $data;
// };
$craw->run();
