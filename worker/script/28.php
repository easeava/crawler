<?php 

// 抓取28新闻

require '../../src/main.php';
use crawler\core\Log;
$configs = [
    'name'          =>  '28',
    // 'daemonize' => true,
    'worker_num'    =>  10,
    'interval'      =>  10,
    'log_file'      =>  '28',
    'domains'       => [
        '28.com',
        'www.28.com',
        'news.28.com'
    ],
    'entry_urls'    =>  [
        'http://news.28.com/'
    ],
    'list_url_regexes'      =>  [],
    'content_url_regexes'   =>  [
        "http://news.28.com/cany/\w+/\d+\.html",
    ],
    'max_try'   => 5,
    // 'export' => [
    //     'type' => 'csv',
    //     'file' => __DIR__ . '/28.csv',
    // ],
    'export'    =>  [
        'type'  => 'sql',
        'file'  => __DIR__ . '/28.sql',
        'table' => 'content',
    ],
    // 'export' => [
    //     'type' => 'db', 
    //     'table' => 'content',
    // ],
    'fields'    => [
        [
            'name'      =>  "article_content",
            'selector'  =>  '/html/body/div[4]/div[1]/div[2]',
            'required'  =>  true,
        ]
    ],
];

$craw = new crawler\core\Crawler($configs);
  
$craw->html_download_page = function($page) {
    log::info('====回调:'.$page['url']);
    // return $data;
};

$craw->run();
