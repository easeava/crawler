<?php

$scan_url = 'http://www.u88.cn';

require './vendor/autoload.php';

if (!$scan_url)
    exit('请指定入口');

$process = new swoole_process(function($worker) use($scan_url) {
//    var_dump($worker->freeQueue());
    $client = new Goutte\Client();
    $crawler = $client->request('GET', $scan_url);

    $data = $crawler->filterXPath('//a/@href')->each(function ($node) use($worker) {
        if ($node->text()) {
            $worker->push($node->text());
        }
    });
    $worker->freeQueue();
}, false);
$process->useQueue();
$pid = $process->start();

for ($i=0;$i<10;$i++) {
    $cust = new swoole_process(function($wo) {
        while (1) {
            $data = $wo->pop();
            $pid = $wo->pid . PHP_EOL;
            $url = parse_url($data);
            if (isset($url['host']) && !in_array($url['host'], ['www.u88.cn', 'u88.cn'])) {
                break;
            } elseif (!isset($url['host'])) {
                $url = 'http://www.u88.cn/'.trim($data, '/');
            }
            $html = file_get_contents($url);
            file_put_contents(md5($url).'.html', my_encoding($html, 'utf-8'));
        }
    }, false);
    $cust->useQueue();
    $cust->start();
}

swoole_process::signal(SIGCHLD, function($sig) {
    while($ret =  swoole_process::wait(false)) {
    }
});


function my_encoding( $data, $to )
{
    $encode_arr = array('UTF-8','ASCII','GBK','GB2312','BIG5','JIS','eucjp-win','sjis-win','EUC-JP');
    $encoded = mb_detect_encoding($data, $encode_arr);
    $data = mb_convert_encoding($data,$to,$encoded);
    return $data;
}