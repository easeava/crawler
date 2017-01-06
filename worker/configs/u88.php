<?php 

return [
    'name' => '糗事百科',
    // 'daemonize' => true,
    'worker_num' => 10,
    'interval'  =>  10,
    'domains' => [
        'qiushibaike.com',
        'www.qiushibaike.com'
    ],
    'entry_urls' => [
        'http://www.qiushibaike.com/'
    ],
    'content_url_regexes' => [
        "http://www.qiushibaike.com/article/\d+",
    ],
    'max_try' => 5,
    //'export' => [
        //'type' => 'csv',
        //'file' => PATH_DATA.'/qiushibaike.csv',
    //],
    'export' => [
        'type'  => 'sql',
        'file'  => PATH_DATA.'/qiushibaike.sql',
        'table' => 'content',
    ],
    // 'export' => array(
    //     'type' => 'db', 
    //     'table' => 'content',
    // ],
    'fields' => [
        
        [
            'name' => "article_content",
            'selector' => '//*[@id="single-next-link"]/div',
            'required' => true,
        ],
        [
            'name' => "depth",
            'selector' => '',
            'required' => false,
        ]
    ],
];