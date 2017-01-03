<?php
namespace crawler\core;

use Goutte\Client;

class Produce
{

    /**
     * 爬虫爬取每个网页的时间间隔,0表示不延时, 单位: 毫秒
     */
    const INTERVAL = 0;

    /**
     * 爬虫爬取每个网页的超时时间, 单位: 秒
     */
    const TIMEOUT = 5;

    /**
     * 爬取失败次数, 不想失败重新爬取则设置为0
     */
    const MAX_TRY = 0;

    /**
     * 爬虫爬取网页所使用的浏览器类型: pc、ios、android
     * 默认类型是PC
     */
    const AGENT_PC      =   "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36";
    const AGENT_IOS     =   "Mozilla/5.0 (iPhone; CPU iPhone OS 9_3_3 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13G34 Safari/601.1";
    const AGENT_ANDROID =   "Mozilla/5.0 (Linux; U; Android 6.0.1;zh_cn; Le X820 Build/FEXCNFN5801507014S) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 Chrome/49.0.0.0 Mobile Safari/537.36 EUI Browser/5.8.015S";

    protected static $master        =   true;
    protected static $process       =   false;
    protected static $work_num      =   1;
    protected static $queue_lists   =   [];
    protected static $configs       =   [];
    protected static $depth_num     =   0;
    protected static $crawlered_urls_num    =   0;
    protected static $crawler_urls_num      =   0;


    public function __construct($configs = [])
    {
        $configs['name']        =   $configs['name'] ?? 'crawler' ;

        $configs['name']        =   isset($configs['name'])        ? $configs['name']        : 'phpspider';
        $configs['proxy']       =   isset($configs['proxy'])       ? $configs['proxy']       : '';
        $configs['user_agent']  =   isset($configs['user_agent'])  ? $configs['user_agent']  : self::AGENT_PC;
        $configs['user_agents'] =   isset($configs['user_agents']) ? $configs['user_agents'] : null;
        $configs['client_ip']   =   isset($configs['client_ip'])   ? $configs['client_ip']   : null;
        $configs['client_ips']  =   isset($configs['client_ips'])  ? $configs['client_ips']  : null;
        $configs['interval']    =   isset($configs['interval'])    ? $configs['interval']    : self::INTERVAL;
        $configs['timeout']     =   isset($configs['timeout'])     ? $configs['timeout']     : self::TIMEOUT;
        $configs['max_try']     =   isset($configs['max_try'])     ? $configs['max_try']     : self::MAX_TRY;
        $configs['max_depth']   =   isset($configs['max_depth'])   ? $configs['max_depth']   : 0;
        $configs['max_fields']  =   isset($configs['max_fields'])  ? $configs['max_fields']  : 0;
        $configs['export']      =   isset($configs['export'])      ? $configs['export']      : [];

        self::$work_num     =   $configs['work_num'] ?? self::$work_num;

        if (isset($GLOBALS['config']['redis']['prefix']))
            $GLOBALS['config']['redis']['prefix'] = $GLOBALS['config']['redis']['prefix'].'-'.md5($configs['name']);

        self::$configs      =   $configs;
    }

    public function run()
    {
        // 添加入口URL到队列
        foreach (self::$configs['entry_urls'] as $url)
        {
            // false 表示不允许重复
            $this->set_entry_url($url, null, false);
        }

        $this->crawler();
    }


    protected function crawler()
    {
        while ($queue_lsize = $this->queue_lsize()) {
            if (self::$master) {
                // 如果开启多进程
                if (self::$work_num > 1 && !self::$process) {
                    // 如果队列里的任务两倍于进程数时, 生成子进程
                    if ($queue_lsize > self::$work_num * 2) {
                        $this->child_process();
                    }
                }
                Log::debug('=========master size: '.$queue_lsize);

                // 爬取页面
                $this->crawler_page();
            } else {
                if ($queue_lsize > self::$work_num * 2) {
                    $this->crawler_page();
                } else {
                    sleep(1);
                }
            }
        }
    }


    protected function crawler_page()
    {
        $get_crawler_url_num = $this->get_crawler_url_num();
        Log::info("Find pages: {$get_crawler_url_num} ");

        $queue_lsize = $this->queue_lsize();
        Log::info("Waiting for collect pages: {$queue_lsize} ");

        $get_crawlered_url_num = $this->get_crawlered_url_num();
        Log::info("Collected pages: {$get_crawlered_url_num} ");

        // 先进先出
        $link = $this->queue_rpop();
        $link = $this->link_decompression($link);
        $url = $link['url'];

        $this->incr_crawlered_url_num();
//        var_dump(self::$master);
        if (self::$master) {

            Log::debug('======Master: '.$url);
        $client = new Client();
        $crawler = $client->request($link['method'], $url);
        $data = [];
        $crawler->filterXPath('//a/@href')->each(function ($node) use (&$data) {
            $data[] = $node->text();
        });

        foreach ($data as $key => $url) {
            $urls[$key] = str_replace(array("\"", "'", '&amp;'), array("", '', '&'), $url);
        }

        $urls = array_unique($data);

        foreach ($urls as $k => $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            $val = $this->fill_url($url, $link['url']);
            if ($val) {
                $urls[$k] = $val;
            } else {
                unset($urls[$k]);
            }
        }

        if (empty($urls)) {
            return false;
        }
//        var_dump($urls);
        //--------------------------------------------------------------------------------
        // 把抓取到的URL放入队列
        //--------------------------------------------------------------------------------
        foreach ($urls as $url) {

            // 把当前页当做找到的url的Referer页
            $options = array(
                'headers' => array(
                    'Referer' => $link['url'],
                )
            );
            $this->add_url($url, $options, $link['depth']);
        }
        }
    }


    public function set_child()
    {
        self::$master   =   false;
        self::$process  =   true;
    }

    protected function child_process()
    {
        $configs = self::$configs;
        for ($i = 0; $i < self::$work_num; $i++) {
            $process = new \swoole_process(function($worker) use($configs) {
                $child = new Produce($configs);
                $child->set_child();
                $child->run();
            });
            $process->start();
        }

        \swoole_process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  \swoole_process::wait(false)) {
                echo "PID={$ret['pid']}结束\n";
            }
        });
    }


    /**
     * 获得完整的连接地址
     *
     * @param mixed $url            要检查的URL
     * @param mixed $collect_url    从那个URL页面得到上面的URL
     * @return void
     */
    public function fill_url($url, $collect_url)
    {
        $url = trim($url);
        $collect_url = trim($collect_url);

        // 排除JavaScript的连接
        //if (strpos($url, "javascript:") !== false)
        if( preg_match("@^(javascript:|#|'|\")@i", $url) || $url == '')
            return false;

        // 排除没有被解析成功的语言标签
        if(substr($url, 0, 3) == '<%=')
            return false;

        $parse_url = @parse_url($collect_url);
        if (empty($parse_url['scheme']) || empty($parse_url['host']))
            return false;

        // 过滤mailto、tel、sms、wechat、sinaweibo、weixin等协议
        if (!in_array($parse_url['scheme'], array("http", "https")))
            return false;

        $scheme = $parse_url['scheme'];
        $domain = $parse_url['host'];
        $path = empty($parse_url['path']) ? '' : $parse_url['path'];
        $base_url_path = $domain.$path;
        $base_url_path = preg_replace("/\/([^\/]*)\.(.*)$/","/",$base_url_path);
        $base_url_path = preg_replace("/\/$/",'',$base_url_path);

        $i = $path_step = 0;
        $dstr = $pstr = '';
        $pos = strpos($url,'#');
        if($pos > 0) {
            // 去掉#和后面的字符串
            $url = substr($url, 0, $pos);
        }

        // 京东变态的都是 //www.jd.com/111.html
        if(substr($url, 0, 2) == '//') {
            $url = str_replace("//", "", $url);
        } elseif($url[0] == '/') {
            // /1234.html
            $url = $domain.$url;
        } elseif ($url[0] == '.') {
            // ./1234.html、../1234.html 这种类型的
            if(!isset($url[2])) {
                return false;
            } else {
                $urls = explode('/',$url);
                foreach($urls as $u) {
                    if( $u == '..' ) {
                        $path_step++;
                    } else if($i < count($urls)-1) {
                        // 遇到 ., 不知道为什么不直接写$u == '.', 貌似一样的
                        //$dstr .= $urls[$i].'/';
                    } else {
                        $dstr .= $urls[$i];
                    }

                    $i++;
                }

                $urls = explode('/',$base_url_path);
                if(count($urls) <= $path_step) {
                    return false;
                } else {
                    $pstr = '';
                    for($i=0;$i<count($urls)-$path_step;$i++){ $pstr .= $urls[$i].'/'; }
                    $url = $pstr.$dstr;
                }
            }
        } else {
            if( strtolower(substr($url, 0, 7))=='http://' ) {
                $url = preg_replace('#^http://#i','',$url);
                $scheme = "http";
            } else if( strtolower(substr($url, 0, 8))=='https://' ) {
                $url = preg_replace('#^https://#i','',$url);
                $scheme = "https";
            } else {
                $url = $base_url_path.'/'.$url;
            }
        }
        // 两个 / 或以上的替换成一个 /
        $url = preg_replace('@/{1,}@i', '/', $url);
        $url = $scheme.'://'.$url;
        //echo $url;exit("\n");

        $parse_url = @parse_url($url);
        $domain = empty($parse_url['host']) ? $domain : $parse_url['host'];
        // 如果host不为空, 判断是不是要爬取的域名
        if (!empty($parse_url['host'])) {
            //排除非域名下的url以提高爬取速度
            if (!in_array($parse_url['host'], self::$configs['domains'])) {
                return false;
            }
        }

        return $url;
    }

    public function set_entry_url($url, $option = [], $allow_repeat = false)
    {
        $status =   false;

        $link               =   $option;
        $link['url']        =   $url;
        $link['url_type']   =   'entry_url';
        $link               =   $this->link_decompression($link);

        if ($this->is_list_page($url)) {
            $link['url_type'] = 'list_page';
            $status = $this->queue_lpush($link, $allow_repeat);
        } elseif ($this->is_content_page($url)) {
            $link['url_type'] = 'content_page';
            $status = $this->queue_lpush($link, $allow_repeat);
        } else {
            $status = $this->queue_lpush($link, $allow_repeat);
        }

        if ($status) {
            if ($link['url_type'] == 'entry_page')
                Log::debug("Find scan page: {$url}");
            elseif ($link['url_type'] == 'list_page')
                Log::debug("Find list page: {$url}");
            elseif ($link['url_type'] == 'content_page')
                Log::debug("Find content page: {$url}");
        }

        return $status;
    }


    public function add_url($url, $options = array(), $depth = 0)
    {
        // 投递状态
        $status = false;

        $link = $options;
        $link['url'] = $url;
        $link['depth'] = $depth;
        $link = $this->link_decompression($link);

        if ($this->is_list_page($url)) {
            $link['url_type'] = 'list_page';
            $status = $this->queue_lpush($link);
        }

        if ($this->is_content_page($url)) {
            $link['url_type'] = 'content_page';
            $status = $this->queue_lpush($link);
        }

        if ($status) {
            if ($link['url_type'] == 'entry_page') {
                Log::debug("Find scan page: {$url}");
            } elseif ($link['url_type'] == 'list_page') {
                Log::debug("Find list page: {$url}");
            } elseif ($link['url_type'] == 'content_page') {
                Log::debug("Find content page: {$url}");
            }
        }

        return $status;
    }


    /**
     * 是否入口页面
     *
     * @param mixed $url
     * @return void
     */
    public function is_entry_page($url)
    {
        $parse_url = parse_url($url);
        if (empty($parse_url['host']) || !in_array($parse_url['host'], self::$configs['domains']))
            return false;
        return true;
    }

    /**
     * 是否列表页面
     *
     * @param mixed $url
     * @return void
     */
    public function is_list_page($url)
    {
        $result = false;
        if (!empty(self::$configs['list_url_regexes'])) {
            foreach (self::$configs['list_url_regexes'] as $regex) {
                if (preg_match("#{$regex}#i", $url)) {
                    $result = true;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * 是否内容页面
     *
     * @param mixed $url
     * @return void
     */
    public function is_content_page($url)
    {
        $result = false;
        if (!empty(self::$configs['content_url_regexes'])) {
            foreach (self::$configs['content_url_regexes'] as $regex) {
                if (preg_match("#{$regex}#i", $url)) {
                    $result = true;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * 链接对象压缩
     * @param $link
     * @return mixed
     */
    public function link_compress($link)
    {
        if (empty($link['url_type']))
            unset($link['url_type']);

        if (empty($link['method']) || strtolower($link['method']) == 'get')
            unset($link['method']);

        if (empty($link['headers']))
            unset($link['headers']);

        if (empty($link['params']))
            unset($link['params']);

        if (empty($link['context_data']))
            unset($link['context_data']);

        if (empty($link['proxy']))
            unset($link['proxy']);

        if (empty($link['try_num']))
            unset($link['try_num']);

        if (empty($link['max_try']))
            unset($link['max_try']);

        if (empty($link['depth']))
            unset($link['depth']);
        //$json = json_encode($link);
        //$json = gzdeflate($json);
        return $link;
    }


    /**
     * 连接对象解压缩
     * @param $link
     * @return array
     */
    public function link_decompression($link)
    {
        $link = [
            'url'          => isset($link['url'])          ? $link['url']          : '',
            'url_type'     => isset($link['url_type'])     ? $link['url_type']     : '',
            'method'       => isset($link['method'])       ? $link['method']       : 'get',
            'headers'      => isset($link['headers'])      ? $link['headers']      : [],
            'params'       => isset($link['params'])       ? $link['params']       : [],
            'context_data' => isset($link['context_data']) ? $link['context_data'] : '',
            'proxy'        => isset($link['proxy'])        ? $link['proxy']        : self::$configs['proxy'],
            'try_num'      => isset($link['try_num'])      ? $link['try_num']      : 0,
            'max_try'      => isset($link['max_try'])      ? $link['max_try']      : self::$configs['max_try'],
            'depth'        => isset($link['depth'])        ? $link['depth']        : 0,
        ];

        return $link;
    }


    /**
     * 队列左侧插入
     * @param array $link
     * @param bool $allowed_repeat
     * @return bool
     */
    public function queue_lpush($link = array(), $allowed_repeat = false)
    {
        if (empty($link) || empty($link['url']))
            return false;

        $url    =   $link['url'];
        $link   =   $this->link_compress($link);

        $status =   false;
        $key    =   "crawler_urls-".md5($url);
        $lock   =   "lock-".$key;
        // 加锁: 一个进程一个进程轮流处理

        if (Queue::lock($lock)) {
            $exists = Queue::exists($key);
            // 不存在或者当然URL可重复入
            if (!$exists || $allowed_repeat) {
                // 待爬取网页记录数加一
                Queue::incr("crawler_urls_num");
                // 先标记为待爬取网页
                Queue::set($key, time());
                // 入队列
                $link = json_encode($link);
                Queue::lpush("crawler_queue", $link);
                $status = true;
            }
            // 解锁
            Queue::unlock($lock);
        }

        return $status;
    }

    /**
     * 队列右侧插入  先进先出规则
     * @param array $link
     * @param bool $allowed_repeat
     * @return bool
     */
    public function queue_rpush($link = array(), $allowed_repeat = false)
    {
        if (empty($link) || empty($link['url']))
            return false;

        $url    =   $link['url'];

        $status =   false;
        $key    =   "crawler_urls-".md5($url);
        $lock   =   "lock-".$key;
        // 加锁: 一个进程一个进程轮流处理
        if (Queue::lock($lock))
        {
            $exists = Queue::exists($key);
            // 不存在或者当然URL可重复入
            if (!$exists || $allowed_repeat)
            {
                // 待爬取网页记录数加一
                Queue::incr("crawler_urls_num");
                // 先标记为待爬取网页
                Queue::set($key, time());
                // 入队列
                $link = json_encode($link);
                Queue::rpush("crawler_queue", $link);
                $status = true;
            }
            // 解锁
            Queue::unlock($lock);
        }

        return $status;
    }

    /**
     * 左侧取出  后进先出
     * @return mixed
     */
    public function queue_lpop()
    {
        $link = Queue::lpop("crawler_queue");
        $link = json_decode($link, true);
        return $link;
    }

    /**
     * 从右侧取出
     * @return mixed|void
     */
    public function queue_rpop()
    {
        $link = Queue::rpop("crawler_queue");
        $link = json_decode($link, true);
        return $link;
    }


    /**
     * 获取队列长度
     */
    public function queue_lsize()
    {
        $lsize = Queue::lsize("crawler_queue");

        return $lsize;
    }


    /**
     * 采集深度加一
     *
     * @return void
     */
    public function incr_depth_num($depth)
    {
        $lock = "lock-depth_num";
        // 锁2秒
        if (Queue::lock($lock, time(), 2)) {
            if (Queue::get("depth_num") < $depth) 
                Queue::set("depth_num", $depth);

            Queue::unlock($lock);
        }
    }

    /**
     * 获得采集深度
     *
     * @return void
     */
    public function get_depth_num()
    {
        $depth_num = Queue::get("depth_num");
        return $depth_num ? $depth_num : 0;
    }


    /**
     * 获取等待爬取页面数量
     *
     * @param mixed $url
     * @return void
     */
    public function get_crawler_url_num()
    {
        $count = Queue::get("crawler_urls_num");

        return $count;
    }

    /**
     * 获取已经爬取页面数量
     *
     * @param mixed $url
     * @return void
     */
    public function get_crawlered_url_num()
    {
        $count = Queue::get("crawlered_urls_num");

        return $count;
    }


    /**
     * 已采集页面数量加一
     * @param $url
     */
    public function incr_crawlered_url_num()
    {
        Queue::incr("crawlered_urls_num");
    }


}