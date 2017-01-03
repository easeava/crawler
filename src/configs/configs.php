<?php

$GLOBALS['config']['db'] = [
    'host'  => '127.0.0.1',
    'port'  => 3306,
    'user'  => 'root',
    'pass'  => '123456',
    'name'  => 'test',
];

$GLOBALS['config']['redis'] = [
    'host'      => '192.168.0.49',
    'port'      => 6379,
    'pass'      => '',
    'prefix'    => 'crawler',
    'timeout'   => 30,
];

$GLOBALS['config']['mimetype'] = array(
    'application/octet-stream'  => 'binary',
    //'text/xml'                  => 'xml',
    //'text/html'                 => 'html',
    //'text/htm'                  => 'htm',
    //'text/plain'                => 'txt',
    'image/png'                 => 'png',
    'image/jpeg'                => 'jpg',
    'image/gif'                 => 'gif',
    'image/tiff'                => 'tiff',
    'image/x-jpg'               => 'jpg',
    'image/x-icon'              => 'icon',
    'image/x-img'               => 'img',
    'application/pdf'           => 'pdf',
    'audio/mp3'                 => 'mp3',
    'video/avi'                 => 'avi',
    'video/mp4'                 => 'mp4',
    'application/x-msdownload'  => 'exe',
    'application/vnd.iphone'    => 'ipa',
    'application/x-bittorrent'  => 'torrent',
    'application/vnd.android.package-archive' => 'apk',
);