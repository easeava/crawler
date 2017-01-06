<?php

define('SRC_PATH', __DIR__);
define('DS', DIRECTORY_SEPARATOR);
define('LOG_PATH', SRC_PATH . DS . 'logs' . DS);
require SRC_PATH . DS . '../vendor/autoload.php';
require SRC_PATH . DS . 'configs/init.php';
require SRC_PATH . DS . 'configs/configs.php';

// echo SRC;
// exit;
// $configs = [
//     'name' => '糗事百科',
//     // 'daemonize' => true,
//     //'log_show' => true,
//     'worker_num' => 10,
//     'interval'  =>  10,
//     //'save_running_state' => true,
//     'domains' => [
//         'qiushibaike.com',
//         'www.qiushibaike.com'
//     ],
//     'entry_urls' => [
//         'http://www.qiushibaike.com/'
//     ],
//     'content_url_regexes' => [
//         "http://www.qiushibaike.com/article/\d+",
//     ],
//     'max_try' => 5,
//     //'export' => [
//         //'type' => 'csv',
//         //'file' => PATH_DATA.'/qiushibaike.csv',
//     //],
//     // 'export' => [
//     //     'type'  => 'sql',
//     //     'file'  => PATH_DATA.'/qiushibaike.sql',
//     //     'table' => 'content',
//     // ],
//     'export' => [
//         'type' => 'db', 
//         'table' => 'content',
//     ],
//     'fields' => [
        
//         [
//             'name' => "article_content",
//             'selector' => '//*[@id="single-next-link"]/div',
//             'required' => true,
//         ]
//     ],
// ];


// $craw = new \crawler\core\Crawler($configs);
// // $craw->html_extract_field = function($fieldname, $data, $page) {
// //     if ($fieldname === 'depth') {
// //         return $page['request']['depth'];
// //     }

// //     return $data;
// // };
// $craw->run();
