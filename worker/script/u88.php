<?php 

// 抓取u88新闻

require '../../src/main.php';

$configs = [
    'name'          =>  'u88',
    // 'daemonize' => true,
    'worker_num'    =>  10,
    'interval'      =>  10,
    'log_file'      =>  'u88',
    'domains'       => [
        'u88.cn',
        'www.u88.cn'
    ],
    'entry_urls'    =>  [
        'http://www.u88.cn/'
    ],
    'content_url_regexes'   =>  [
        "http://www.u88.cn/news/[a-z]+/[a-z]+/\d+\.html\.*",
    ],
    'max_try'   => 5,
    // 'export' => [
    //     'type' => 'csv',
    //     'file' => __DIR__ . '/u88.csv',
    // ],
    'export'    =>  [
        'type'  => 'sql',
        'file'  => __DIR__ . '/u88.sql',
        'table' => 'content',
    ],
    // 'export' => [
    //     'type' => 'db', 
    //     'table' => 'content',
    // ],
    'fields'    => [
        [
            'name'      =>  "article_content",
            'selector'  =>  '/html/body/div[1]/div[2]/div[1]/div[1]/div[2]',
            'required'  =>  true,
        ]
    ],
];

$craw = new crawler\core\Crawler($configs);

// 支持的回调
// public $request_status_code     =   null;
// public $html_download_page      =   null;
// public $html_entry_page         =   null;
// public $html_list_page          =   null;
// public $html_content_page       =   null;
// public $html_download_attached_page =   null;
// public $html_handle_img         =   null;
// public $html_extract_field      =   null;
// public $html_extract_page       =   null;
    
// $craw->html_extract_field = function($fieldname, $data, $page) {
//     if ($fieldname === 'depth') {
//         return $page['request']['depth'];
//     }

//     return $data;
// };

$craw->run();
