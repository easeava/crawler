<?php 

namespace crawler\core;

use Goutte\Client;

class Crawler 
{

	const VERSION = 0.1;

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

    public static $serverid = 1;
    public static $multiserver = false;
    public static $terminate = false;

   	protected static $daemonize		=	false;
	protected static $worker_num 		= 	5;
	protected static $pid  			= 	0;
	protected static $master 		= 	true;
	protected static $process 		= 	false;
	protected static $child_master 	= 	false;
	protected static $child 		= 	false;
    protected static $queue_lists   =   [];
    protected static $configs       =   [];
    protected static $depth_num     =   0;
    protected static $crawlered_urls_num    =   0;
    protected static $crawler_urls_num      =   0;
 	public static $crawler_succ 	= 	0;
    public static $crawler_fail 	= 	0;
    protected static $child_pid		=	[];
    protected static $time_start 	=	0;
    protected static $task_id		=	0;
    protected static $task_pid;

    // 导出类型配置
    public static $export_type 		= 	'';
    public static $export_file 		= 	'';
    public static $export_conf 		= 	'';
    public static $export_table 	= 	'';
    public static $export_db_config = 	'';

    // 网页返回状态码
    public $request_status_code		=	null;
    public $html_download_page		= 	null;
    public $html_entry_page			=	null;
    public $html_list_page			=	null;
    public $html_content_page		=	null;
    public $html_download_attached_page	=	null;
    public $html_handle_img			=	null;
    public $html_extract_field		=	null;
    public $html_extract_page		=	null;


    // 运行面板参数长度
    public static $server_length 		= 	10;
    public static $worker_num_length 	= 	8;
    public static $taskid_length 		= 	8;
    public static $pid_length 			= 	8;
    public static $mem_length 			= 	8;
    public static $urls_length 			= 	15;
    public static $speed_length 		= 	6;

	public function __construct($configs) 
	{
		$configs['name']        =   $configs['name'] ?? 'crawler' ;

        $configs['name']        =   isset($configs['name'])        ? $configs['name']        : 'crawler';
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

        self::$daemonize      	=   isset($configs['daemonize']) ? $configs['daemonize'] ?? false : false;

        self::$worker_num     	=   $configs['worker_num'] ?? self::$worker_num;

        if (isset($GLOBALS['config']['redis']['prefix']))
            $GLOBALS['config']['redis']['prefix'] = $GLOBALS['config']['redis']['prefix'].'-'.md5($configs['name']);

        self::$configs      =   $configs;
        // $this->client       =   new Client();
        // self::$pid          =   posix_getpid();
        
        // csv、sql、db
        self::$export_type  = isset($configs['export']['type'])  ? $configs['export']['type']  : '';
        self::$export_file  = isset($configs['export']['file'])  ? $configs['export']['file']  : '';
        self::$export_table = isset($configs['export']['table']) ? $configs['export']['table'] : '';
        self::$export_db_config = isset($configs['export']['config']) ? $configs['export']['config'] : $GLOBALS['config']['db'];

        if (isset($configs['log_file'])) {
        	Log::$log_file = SRC_PATH . DIRECTORY_SEPARATOR . "logs/{$configs["log_file"]}.log";
        }
	}


	public function run()
	{
		// 爬虫开始时间
        self::$time_start = time();

        $this->check_export();

        $this->check_cache();

        // $this->init_redis();

        if (!empty(self::$configs['entry_urls'])) {
	        // 添加入口URL到队列
	        foreach (self::$configs['entry_urls'] as $url) {
	            // false 表示不允许重复
	            $this->set_entry_url($url, null, false);
	        }
        } else {
        	return false;
        }

        if (self::$daemonize) {
        	$this->daemonize();
        } else {
            $this->clear_echo();
        	$this->display_ui();
        }

		$this->start();
	}


	protected function start($worker = null)
	{
		// echo self::$task_id;
		// echo $this->get_pid();
		// self::$crawler_succ = 0;
  //       self::$crawler_fail = 0;

		while (true) {
			// var_dump(self::$master);
			$queue_lsize = $this->queue_lsize();
			if (self::$master && !self::$process && $queue_lsize > self::$worker_num * 2) {
				// echo 'with if master: '.$this->get_pid().PHP_EOL;
				self::$process = true;
				for ($i=0; $i < self::$worker_num; $i++) { 
					$this->process($i+1);
				}

				// $child_pid =& self::$child_pid;
				\swoole_process::signal(SIGCHLD, function($sig){
				  	while($ret = swoole_process::wait(false)) {
				      	echo "退出PID={$ret['pid']}\n";
				      	// if ($key = array_search($ret['pid'], $child_pid)) {
				      	// 	unset($child_pid[$key]);
	          //               $queue_lsize = $this->queue_lsize();
	          //               if ($queue_lsize > self::$worker_num * 2) {
	          //                   $child_pid[] = $pid = $this->process();
	          //                   echo "拉起".$pid.'\n';
	          //               }
	          //           }
				  	}
				});
			}
			// sleep(1);
			
			// if (self::$child && !$queue_lsize) 
			// 	$worker->exit(0);


			if ($queue_lsize) {
				$this->crawler_page();
			}

			$this->set_task_status();
			// sleep(1);
			
			if (!self::$daemonize) {
                $this->clear_echo();
                $this->display_ui();
			}
		}
	}


    public function init_redis()
    {
        // 添加当前服务器到服务器列表
        $this->add_server_list(self::$serverid, self::$worker_num);

        // 删除当前服务器的任务状态
        // 对于被强制退出的进程有用
        for ($i = 1; $i <= self::$worker_num; $i++) {
            $this->del_task_status(self::$serverid, $i);
        }
    }


	protected function process($taskid)
	{
		$process = new \swoole_process(function($work) use($taskid) {
			self::$master 	= 	false;
			self::$child 	=	true;
			self::$task_id	=	$taskid;
            // self::$task_pid =   $work->pid;
            self::$crawler_succ = 0;
            self::$crawler_fail = 0;
			$this->start($work);
		});

		$pid = $process->start();

		return $pid;
	}

	public function get_pid()
	{
		self::$pid = posix_getpid();
		return self::$pid;
	}



    protected function crawler_page()
    {
        $get_crawler_url_num = $this->get_crawler_url_num();
        Log::info("Find pages: {$get_crawler_url_num} ");

        $queue_lsize = $this->queue_lsize();
        Log::info("Waiting for crawler pages: {$queue_lsize} ");

        $get_crawlered_url_num = $this->get_crawlered_url_num();
        Log::info("crawlered pages: {$get_crawlered_url_num} ");

        // 先进先出
        $link = $this->queue_rpop();
        $link = $this->link_decompression($link);
        $url = $link['url'];
        // var_dump($link);

        $page_time_start = microtime(true);

        Requests::$input_encoding = null;
        $html = $this->request_url($url, $link);
        // echo $html;
         // 当前正在爬取的网页页面的对象
        $page = [
            'url'     => $url,
            'raw'     => $html,
            'request' => [
                'url'          => $url,
                'method'       => $link['method'],
                'headers'      => $link['headers'],
                'params'       => $link['params'],
                'context_data' => $link['context_data'],
                'try_num'      => $link['try_num'],
                'max_try'      => $link['max_try'],
                'depth'        => $link['depth'],
                'taskid'       => self::$task_id,
            ],
        ];
        unset($html);

        // =========================回调函数=========================
        
        // 在一个网页下载完成之后调用. 主要用来对下载的网页进行处理.
        // 比如下载了某个网页, 希望向网页的body中添加html标签
        if ($this->html_download_page) {
            $return = call_user_func($this->html_download_page, $page, $this);
            if (isset($return)) $page = $return;
        }

        // 是否从当前页面分析提取URL
        // 回调函数如果返回false表示不需要再从此网页中发现待爬url
        $is_find_url = true;
        if ($link['url_type'] == 'entry_page') {
            if ($this->html_entry_page) {
                $return = call_user_func($this->html_entry_page, $page, $page['raw'], $this);
                if (isset($return)) $is_find_url = $return;
            }
        } elseif ($link['url_type'] == 'list_page') {
            if ($this->html_list_page) {
                $return = call_user_func($this->html_list_page, $page, $page['raw'], $this);
                if (isset($return)) $is_find_url = $return;
            }
        } elseif ($link['url_type'] == 'content_page') {
            if ($this->html_content_page) {
                $return = call_user_func($this->html_content_page, $page, $page['raw'], $this);
                if (isset($return)) $is_find_url = $return;
            }
        }

        // 返回false表示不需要再从此网页中发现待爬url
        if ($is_find_url) {
            // 如果深度没有超过最大深度, 获取下一级URL
            if (self::$configs['max_depth'] == 0 || $link['depth'] < self::$configs['max_depth']) {
                // 分析提取HTML页面中的URL
                $this->get_urls($page['raw'], $url, $link['depth'] + 1);
            }
        }

        // 如果是内容页, 分析提取HTML页面中的字段
        // 列表页也可以提取数据的, source_type: urlcontext, 未实现
        if ($link['url_type'] == 'content_page') {
            $this->get_html_fields($page['raw'], $url, $page);
        }

        // 已爬取数量加1
        $this->incr_crawlered_url_num();

        // 带爬取数量减1
        $this->decr_crawler_url_num();

        // 如果深度没有超过最大深度, 获取下一级URL
        if (self::$configs['max_depth'] == 0 || $link['depth'] < self::$configs['max_depth']) 
            // 分析提取HTML页面中的URL
            $this->get_urls($page['raw'], $url, $link['depth'] + 1);

        // 如果当前深度大于缓存的, 更新缓存
        $this->incr_depth_num($link['depth']);

        // 处理页面耗时时间
        $time_run = round(microtime(true) - $page_time_start, 3);
        log::debug("Success process page {$url} in {$time_run} s");

        $spider_time_run = Util::time2second(intval(microtime(true) - self::$time_start));
        log::info("Spider running in {$spider_time_run}");

        // 爬虫爬取每个网页的时间间隔, 单位: 毫秒
        if (!isset(self::$configs['interval'])) 
            // 默认睡眠100毫秒, 太快了会被认为是ddos
            self::$configs['interval'] = 100;

        usleep(self::$configs['interval'] * 1000);
    }


    /**
     * 下载网页, 得到网页内容
     * 
     * @param mixed $url
     * @param mixed $link
     * @return void
     */
    public function request_url($url, $link = [])
    {
        $time_start = microtime(true);

        //$url = "http://www.qiushibaike.com/article/117568316";

        // 设置了编码就不要让requests去判断了
        if (isset(self::$configs['input_encoding'])) 
            Requests::$input_encoding = self::$configs['input_encoding'];

        // 得到的编码如果不是utf-8的要转成utf-8, 因为xpath只支持utf-8
        Requests::$output_encoding = 'utf-8';
        Requests::set_timeout(self::$configs['timeout']);
        Requests::set_useragent(self::$configs['user_agent']);
        if (self::$configs['user_agents']) 
            Requests::set_useragents(self::$configs['user_agents']);

        if (self::$configs['client_ip']) 
            Requests::set_client_ip(self::$configs['client_ip']);

        if (self::$configs['client_ips']) 
            Requests::set_client_ips(self::$configs['client_ips']);

        // 是否设置了代理
        if (!empty($link['proxy'])) {
            Requests::set_proxies(array('http'=>$link['proxy'], 'https'=>$link['proxy']));
            // 自动切换IP
            Requests::set_header('Proxy-Switch-Ip', 'yes');
        }

        // 如何设置了 HTTP Headers
        if (!empty($link['headers'])) {
            foreach ($link['headers'] as $k=>$v) {
                Requests::set_header($k, $v);
            }
        }

        $method = empty($link['method']) ? 'get' : strtolower($link['method']);
        $params = empty($link['params']) ? [] : $link['params'];
        $html = Requests::$method($url, $params);
        // 此url附加的数据不为空, 比如内容页需要列表页一些数据, 拼接到后面去
        if ($html && !empty($link['context_data'])) {
            $html .= $link['context_data'];
        }

        $http_code = Requests::$status_code;

        if ($this->request_status_code) {
            $return = call_user_func($this->request_status_code, $http_code, $url, $html, $this);
            if (isset($return)) 
                $html = $return;

            if (!$html) 
                return false;
        }

        if ($http_code != 200) {
            // 如果是301、302跳转, 抓取跳转后的网页内容
            if ($http_code == 301 || $http_code == 302) {
                $info = Requests::$info;
                if (isset($info['redirect_url'])) {
                    $url = $info['redirect_url'];
                    Requests::$input_encoding = null;
                    $html = $this->request_url($url, $link);
                    if ($html && !empty($link['context_data'])) {
                        $html .= $link['context_data'];
                    }
                } else {
                    return false;
                }
            } else {
                if ($http_code == 407) {
                    // 扔到队列头部去, 继续采集
                    $this->queue_rpush($link);
                    Log::error("Failed to download page {$url}");
                    self::$crawler_fail++;
                } elseif (in_array($http_code, array('0','502','503','429'))) {
                    // 采集次数加一
                    $link['try_num']++;
                    // 抓取次数 小于 允许抓取失败次数
                    if ( $link['try_num'] <= $link['max_try'] ) {
                        // 扔到队列头部去, 继续采集
                        $this->queue_rpush($link);
                    }
                    Log::error("Failed to download page {$url}, retry({$link['try_num']})");
                } else {
                    Log::error("Failed to download page {$url}");
                    self::$crawler_fail++;
                }

                Log::error("HTTP CODE: {$http_code}");
                return false;
            }
        }

        // 爬取页面耗时时间
        $time_run = round(microtime(true) - $time_start, 3);
        Log::debug("Success download page {$url} in {$time_run} s");
        self::$crawler_succ++;

        return $html;
    }

    /**
     * 分析提取HTML页面中的URL
     * 
     * @param mixed $html           HTML内容
     * @param mixed $crawler_url    抓取的URL, 用来拼凑完整页面的URL
     * @return void
     */
    public function get_urls($html, $crawler_url, $depth = 0) 
    { 
        $urls = selector::select($html, '//a/@href');             

        if (empty($urls)) {
            return false;
        }

        foreach ($urls as $key=>$url) {
            $urls[$key] = str_replace(array("\"", "'",'&amp;'), array("",'','&'), $url);
        }

        //--------------------------------------------------------------------------------
        // 过滤和拼凑URL
        //--------------------------------------------------------------------------------
        // 去除重复的RUL
        $urls = array_unique($urls);
        foreach ($urls as $k=>$url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            $val = $this->fill_url($url, $crawler_url);
            if ($val) {
                $urls[$k] = $val;
            } else {
                unset($urls[$k]);
            }
        }

        if (empty($urls)) {
            return false;
        }

        //--------------------------------------------------------------------------------
        // 把抓取到的URL放入队列
        //--------------------------------------------------------------------------------
        foreach ($urls as $url) {

            // 把当前页当做找到的url的Referer页
            $options = [
                'headers' => [
                    'Referer' => $crawler_url,
                ]
            ];

            $this->add_url($url, $options, $depth);
        }
    }



    /**
     * 分析提取HTML页面中的字段
     * 
     * @param mixed $html
     * @return void
     */
    public function get_html_fields($html, $url, $page) 
    {
        $fields = $this->get_fields(self::$configs['fields'], $html, $url, $page);

        if (!empty($fields)) {
            if ($this->html_extract_page) {
                $return = call_user_func($this->html_extract_page, $page, $fields);
                if (!isset($return)) {
                    log::warn("html_extract_page return value can't be empty");
                } elseif (!is_array($return)) {
                    log::warn("html_extract_page return value must be an array");
                } else {
                    $fields = $return;
                }
            }

            if (isset($fields) && is_array($fields)) {
                $fields_num = $this->incr_fields_num();
                if (self::$configs['max_fields'] != 0 && $fields_num > self::$configs['max_fields']) {
                    exit(0);
                }

                if (version_compare(PHP_VERSION,'5.4.0','<')) {
                    $fields_str = json_encode($fields);
                    $fields_str = preg_replace_callback( "#\\\u([0-9a-f]{4})#i", function($matchs) {
                        return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
                    }, $fields_str ); 
                } else {
                    $fields_str = json_encode($fields, JSON_UNESCAPED_UNICODE);
                }

                if (Util::is_win()) {
                    $fields_str = mb_convert_encoding($fields_str, 'gb2312', 'utf-8');
                }

                log::info("Result[{$fields_num}]: ".$fields_str);

                // 如果设置了导出选项
                if (!empty(self::$configs['export'])) {
                    self::$export_type = isset(self::$configs['export']['type']) ? self::$configs['export']['type'] : '';
                    if (self::$export_type == 'csv') {
                        Util::put_file(self::$export_file, Util::format_csv($fields)."\n", FILE_APPEND);
                    } elseif (self::$export_type == 'sql') {
                        $sql = Db::insert(self::$export_table, $fields, true);
                        Util::put_file(self::$export_file, $sql.";\n", FILE_APPEND);
                    } elseif (self::$export_type == 'db') {
                        Db::insert(self::$export_table, $fields);
                    }
                }
            }
        }
    }

    /**
     * 根据配置提取HTML代码块中的字段
     * 
     * @param mixed $confs
     * @param mixed $html
     * @param mixed $page
     * @return void
     */
    public function get_fields($confs, $html, $url, $page) 
    {
        $fields = [];
        foreach ($confs as $conf) {
            // 当前field抽取到的内容是否是有多项
            $repeated = isset($conf['repeated']) && $conf['repeated'] ? true : false;
            // 当前field抽取到的内容是否必须有值
            $required = isset($conf['required']) && $conf['required'] ? true : false;

            if (empty($conf['name'])) {
                log::error("The field name is null, please check your \"fields\" and add the name of the field\n");
                exit;
            }

            $values = [];
            // 如果定义抽取规则
            if (!empty($conf['selector'])) {
                // 如果这个field是上一个field的附带连接
                if (isset($conf['source_type']) && $conf['source_type']=='attached_url') {
                    // 取出上个field的内容作为连接, 内容分页是不进队列直接下载网页的
                    if (!empty($fields[$conf['attached_url']])) {
                        $crawler_url = $this->fill_url($fields[$conf['attached_url']], $url);
                        //log::debug("Find attached content page: {$crawler_url}");
                        $link['url'] = $crawler_url;
                        $link = $this->link_uncompress($link);
                        requests::$input_encoding = null;
                        $html = $this->request_url($crawler_url, $link);
                        // 在一个attached_url对应的网页下载完成之后调用. 主要用来对下载的网页进行处理.
                        if ($this->html_download_attached_page) {
                            $return = call_user_func($this->html_download_attached_page, $html, $this);
                            if (isset($return)) {
                                $html = $return;
                            }
                        }

                        // 请求获取完分页数据后把连接删除了 
                        unset($fields[$conf['attached_url']]);
                    }
                }

                // 没有设置抽取规则的类型 或者 设置为 xpath
                if (!isset($conf['selector_type']) || $conf['selector_type']=='xpath') 
                    $values = $this->get_fields_xpath($html, $conf['selector'], $conf['name']);
                elseif ($conf['selector_type']=='css') 
                    $values = $this->get_fields_css($html, $conf['selector'], $conf['name']);
                elseif ($conf['selector_type']=='regex') 
                    $values = $this->get_fields_regex($html, $conf['selector'], $conf['name']);
                
                // field不为空而且存在子配置
                if (!empty($values) && !empty($conf['children'])) {
                    $child_values = [];
                    // 父项抽取到的html作为子项的提取内容
                    foreach ($values as $html) {
                        // 递归调用本方法, 所以多少子项目都支持
                        $child_value = $this->get_fields($conf['children'], $html, $url, $page);
                        if (!empty($child_value)) {
                            $child_values[] = $child_value;
                        }
                    }
                    // 有子项就存子项的数组, 没有就存HTML代码块
                    if (!empty($child_values)) {
                        $values = $child_values;
                    }
                }
            }

            if (empty($values)) {
                // 如果值为空而且值设置为必须项, 跳出foreach循环
                if ($required) {
                    // 清空整个 fields
                    // $fields[$conf['name']] = [];
                    break;
                }
                // 避免内容分页时attached_url拼接时候string + array了
                $fields[$conf['name']] = '';
                //$fields[$conf['name']] = [];
            } else {
                if (is_array($values)) {
                    if ($repeated) {
                        $fields[$conf['name']] = $values;
                    } else {
                        $fields[$conf['name']] = $values[0];
                    }
                } else {
                    $fields[$conf['name']] = $values;
                }
            }
        }

        if (!empty($fields)) {
            foreach ($fields as $fieldname => $data) {
                $pattern = "/<img.*src=[\"']{0,1}(.*)[\"']{0,1}[> \r\n\t]{1,}/isU";
                /*$pattern = "/<img.*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.jpeg|\.png]))[\'|\"].*?[\/]?>/i"; */
                // 在抽取到field内容之后调用, 对其中包含的img标签进行回调处理
                if ($this->html_handle_img && preg_match($pattern, $data)) {
                    $return = call_user_func($this->html_handle_img, $fieldname, $data);
                    if (!isset($return)) {
                        log::warn("html_handle_img return value can't be empty\n");
                    } else {
                        // 有数据才会执行 html_handle_img 方法, 所以这里不要被替换没了
                        $data = $return;
                    }
                }

                // 当一个field的内容被抽取到后进行的回调, 在此回调中可以对网页中抽取的内容作进一步处理
                if ($this->html_extract_field) {
                    $return = call_user_func($this->html_extract_field, $fieldname, $data, $page);
                    if (!isset($return)) {
                        log::warn("html_extract_field return value can't be empty\n");
                    } else {
                        // 有数据才会执行 html_extract_field 方法, 所以这里不要被替换没了
                        $fields[$fieldname] = $return;
                    }
                }
            }
        }

        return $fields;
    }



    /**
     * 获得完整的连接地址
     *
     * @param mixed $url            要检查的URL
     * @param mixed $crawler_url    从那个URL页面得到上面的URL
     * @return void
     */
    public function fill_url($url, $crawler_url)
    {
        $url = trim($url);
        $crawler_url = trim($crawler_url);

        // 排除JavaScript的连接
        //if (strpos($url, "javascript:") !== false)
        if( preg_match("@^(javascript:|#|'|\")@i", $url) || $url == '')
            return false;

        // 排除没有被解析成功的语言标签
        if(substr($url, 0, 3) == '<%=')
            return false;

        $parse_url = @parse_url($crawler_url);
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
        if (isset($parse_url['host'])) {
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
        $link['url_type']   =   'entry_page';
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
                Log::debug(self::$pid."Find entry page: {$url}");
            elseif ($link['url_type'] == 'list_page')
                Log::debug(self::$pid."Find list page: {$url}");
            elseif ($link['url_type'] == 'content_page')
                Log::debug(self::$pid."Find content page: {$url}");
        }

        return $status;
    }


    public function add_url($url, $options = [], $depth = 0)
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
                Log::debug(self::$pid."Find entry page: {$url}");
            } elseif ($link['url_type'] == 'list_page') {
                Log::debug(self::$pid."Find list page: {$url}");
            } elseif ($link['url_type'] == 'content_page') {
                Log::debug(self::$pid."Find content page: {$url}");
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
     * @param bool $allow_repeat
     * @return bool
     */
    public function queue_lpush($link = [], $allow_repeat = false)
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
            if (!$exists || $allow_repeat) {
                // 待爬取网页记录数加一
                Queue::incr("crawler_urls_num");
                // 先标记为待爬取网页
                Queue::set($key, time());
                // 入队列
                $link = json_encode($link);
                Queue::lpush("crawler_queue", $link);
                $status = true;
            } else {
            	// echo $url.PHP_EOL;
            }

            // 解锁
            Queue::unlock($lock);
        }

        return $status;
    }

    /**
     * 队列右侧插入  先进先出规则
     * @param array $link
     * @param bool $allow_repeat
     * @return bool
     */
    public function queue_rpush($link = [], $allow_repeat = false)
    {
        if (empty($link) || empty($link['url']))
            return false;

        $url    =   $link['url'];

        $status =   false;
        $key    =   "crawler_urls-".md5($url);
        $lock   =   "lock-".$key;
        // 加锁: 一个进程一个进程轮流处理
        if (Queue::lock($lock)) {
            $exists = Queue::exists($key);
            // 不存在或者当然URL可重复入
            if (!$exists || $allow_repeat) {
                // 待爬取网页记录数加一
                Queue::incr("crawler_urls_num");
                // 先标记为待爬取网页
                Queue::set($key, time());
                // 入队列
                $link = json_encode($link);
                Queue::rpush("crawler_queue", $link);
                $status = true;
            } else {
            	// echo $url.PHP_EOL;
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

    protected function decr_crawler_url_num()
    {
    	$lock = "lock-crawler_urls_num";
        // 锁2秒
        if (Queue::lock($lock, time(), 2)) {
            Queue::decr('crawler_urls_num');

            Queue::unlock($lock);
        }
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


        /**
     * 采用xpath分析提取字段
     * 
     * @param mixed $html
     * @param mixed $selector
     * @return void
     */
    public function get_fields_xpath($html, $selector, $fieldname) 
    {
        $result = selector::select($html, $selector);

        if (selector::$error) 
            log::error("Field(\"{$fieldname}\") ".selector::$error."\n");

        return $result;
    }

    /**
     * 采用正则分析提取字段
     * 
     * @param mixed $html
     * @param mixed $selector
     * @return void
     */
    public function get_fields_regex($html, $selector, $fieldname) 
    {
        $result = selector::select($html, $selector, 'regex');
        if (selector::$error) 
        {
            log::error("Field(\"{$fieldname}\") ".selector::$error."\n");
        }
        return $result;
    }

    /**
     * 采用CSS选择器提取字段
     * 
     * @param mixed $html
     * @param mixed $selector
     * @param mixed $fieldname
     * @return void
     */
    public function get_fields_css($html, $selector, $fieldname) 
    {
        $result = selector::select($html, $selector, 'css');
        if (selector::$error) 
        {
            log::error("Field(\"{$fieldname}\") ".selector::$error."\n");
        }
        return $result;
    }

    /**
     * 提取到的field数目加一
     * 
     * @return void
     */
    public function incr_fields_num()
    {
        $fields_num = Queue::incr("fields_num"); 
        
        return $fields_num;
    }

    /**
     * 提取到的field数目
     * 
     * @return void
     */
    public function get_fields_num()
    {
        $fields_num = Queue::get("fields_num"); 
        return $fields_num ? $fields_num : 0;
    }


    /**
     * 验证导出
     * 
     * @return void
     */
    public function check_export()
    {
        // 如果设置了导出选项
        if (!empty(self::$configs['export'])) {
            if (self::$export_type == 'csv') {
                if (empty(self::$export_file)) {
                    log::error("Export data into CSV files need to Set the file path.");
                    exit;
                }
            } elseif (self::$export_type == 'sql') {
                if (empty(self::$export_file)) {
                    log::error("Export data into SQL files need to Set the file path.");
                    exit;
                }
            } elseif (self::$export_type == 'db') {
                if (!function_exists('mysqli_connect')) {
                    log::error("Export data to a database need Mysql support, Error: Unable to load mysqli extension.");
                    exit;
                }

                if (empty(self::$export_db_config)) {
                    log::error("Export data to a database need Mysql support, Error: You not set a config array for connect.\nPlease check the configuration file config/inc_config.php");
                    exit;
                }

                $config = self::$export_db_config;
                @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
                if(mysqli_connect_errno()) {
                    log::error("Export data to a database need Mysql support, Error: ".mysqli_connect_error()." \nPlease check the configuration file config/inc_config.php");
                    exit;
                }

                Db::_init_mysql(self::$export_db_config);

                if (!Db::table_exists(self::$export_table)) {
                    log::error("Table ".self::$export_table." does not exist");
                    exit;
                }
            }
        }
    }

    public function check_cache()
    {

        //if (Queue::exists("crawler_queue")) 
        $keys = Queue::keys("*"); 
        $count = count($keys);
        $count;
        if ($count != 0) {
            // After this operation, 4,318 kB of additional disk space will be used.
            // Do you want to continue? [Y/n] 
            //$msg = "发现Redis中有采集数据, 是否继续执行, 不继续则清空Redis数据重新采集\n";
            $msg = "Found that the data of Redis, no continue will empty Redis data start again\n";
            $msg .= "Do you want to continue? [Y/n]";
            fwrite(STDOUT, $msg);
            $arg = strtolower(trim(fgets(STDIN)));
            $arg = empty($arg) || !in_array($arg, array('y','n')) ? 'y' : $arg;
            if ($arg == 'n') {
                foreach ($keys as $key) {
                    $key = str_replace($GLOBALS['config']['redis']['prefix'].":", "", $key);
                    Queue::del($key);
                }
            }
        }
    }


    /**
     * Run as deamon mode.
     *
     * @throws Exception
     */
    protected static function daemonize()
    {
        if (!self::$daemonize) {
            return;
        }

        // fork前一定要关闭redis
        Queue::close();

        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }

        if (-1 === posix_setsid()) {
            throw new Exception("setsid fail");
        }

        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }


    /**
     * 设置任务状态, 主进程和子进程每成功采集一个页面后调用
     */
    public function set_task_status()
    {
        // 每采集成功一个页面, 生成当前进程状态到文件, 供主进程使用
        $mem = round(memory_get_usage(true)/(1024*1024),2);
        $use_time = microtime(true) - self::$time_start; 
        $speed = round((self::$crawler_succ + self::$crawler_fail) / $use_time, 2);
        $status = [
            'id' => self::$task_id,
            'pid' => $this->get_pid(),
            'mem' => $mem,
            'crawler_succ' => self::$crawler_succ,
            'crawler_fail' => self::$crawler_fail,
            'speed' => $speed,
        ];

        $task_status = json_encode($status);

        $key = "server-".self::$serverid."-task_status-".self::$task_id;
        Queue::set($key, $task_status); 
    }


	/**
     * 删除任务状态
     * 
     * @return void
     */
    public function del_task_status($serverid, $taskid)
    {
        $key = "server-{$serverid}-task_status-{$taskid}";
        Queue::del($key); 
    }

    /**
     * 获得任务状态, 主进程才会调用
     * 
     * @return void
     */
    public function get_task_status($serverid, $taskid)
    {
        $key = "server-{$serverid}-task_status-{$taskid}";
        $task_status = Queue::get($key);
        return $task_status;
    }

    /**
     * 获得任务状态, 主进程才会调用
     * 
     * @return void
     */
    public function get_task_status_list($serverid = 1, $tasknum)
    {
        $task_status = [];
        for ($i = 1; $i <= $tasknum; $i++) {
            $key = "server-{$serverid}-task_status-".$i;
            $task_status[] = Queue::get($key);
        }
        
        return $task_status;
    }

    /**
     * 添加当前服务器信息到服务器列表
     * 
     * @return void
     */
    public function add_server_list($serverid, $tasknum)
    {
        // 更新服务器列表
        $server_list_json = Queue::get("server_list");
        $server_list = [];
        if (!$server_list_json) {
            $server_list[$serverid] = [
                'serverid' => $serverid,
                'tasknum' => $tasknum,
                'time' => time(),
            ];
        } else {
            $server_list = json_decode($server_list_json, true);
            $server_list[$serverid] = [
                'serverid' => $serverid,
                'tasknum' => $tasknum,
                'time' => time(),
            ];
            ksort($server_list);
        }

        Queue::set("server_list", json_encode($server_list));
    }

    /**
     * 从服务器列表中删除当前服务器信息
     * 
     * @return void
     */
    public function del_server_list($serverid)
    {
        $server_list_json = Queue::get("server_list");
        $server_list = [];
        if ($server_list_json) {
            $server_list = json_decode($server_list_json, true);
            if (isset($server_list[$serverid])) 
                unset($server_list[$serverid]);

            // 删除完当前的任务列表如果还存在，就更新一下Redis
            if (!empty($server_list)) {
                ksort($server_list);
                Queue::set("server_list", json_encode($server_list));
            }
        }
    }


    /**
     * 清空shell输出内容
     * 
     * @return void
     */
    public function clear_echo()
    {
        $arr = array(27, 91, 72, 27, 91, 50, 74);
        foreach ($arr as $a) 
        {
            print chr($a);
        }
        //array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
    }

    /**
     * 替换shell输出内容
     * 
     * @param mixed $message
     * @param mixed $force_clear_lines
     * @return void
     */
    public function replace_echo($message, $force_clear_lines = NULL) 
    {
        static $last_lines = 0;

        if(!is_null($force_clear_lines)) 
        {
            $last_lines = $force_clear_lines;
        }

        // 获取终端宽度
        $toss = $status = null;
        $term_width = exec('tput cols', $toss, $status);
        if($status || empty($term_width)) 
        {
            $term_width = 64; // Arbitrary fall-back term width.
        }

        $line_count = 0;
        foreach(explode("\n", $message) as $line) 
        {
            $line_count += count(str_split($line, $term_width));
        }

        // Erasure MAGIC: Clear as many lines as the last output had.
        for($i = 0; $i < $last_lines; $i++) 
        {
            // Return to the beginning of the line
            echo "\r";
            // Erase to the end of the line
            echo "\033[K";
            // Move cursor Up a line
            echo "\033[1A";
            // Return to the beginning of the line
            echo "\r";
            // Erase to the end of the line
            echo "\033[K";
            // Return to the beginning of the line
            echo "\r";
            // Can be consolodated into
            // echo "\r\033[K\033[1A\r\033[K\r";
        }

        $last_lines = $line_count;

        echo $message."\n";
    }

    /**
     * 展示启动界面, Windows 不会到这里来
     * @return void
     */
    public function display_ui()
    {
        $loadavg = sys_getloadavg();
        foreach ($loadavg as $k=>$v) 
            $loadavg[$k] = round($v, 2);

        $display_str = "\033[1A\n\033[K-----------------------------\033[47;30m crawler \033[0m-----------------------------\n\033[0m";
        $run_time_str = util::time2second(time()-self::$time_start, false);
        $display_str .= 'CRAWLER version:' . self::VERSION . "          PHP version:" . PHP_VERSION . "\n";
        $display_str .= 'start time:'. date('Y-m-d H:i:s', self::$time_start).'   run ' . $run_time_str . " \n";

        $display_str .= 'spider name: ' . self::$configs['name'] . "\n";
        if (self::$multiserver) 
            $display_str .= 'server id: ' . self::$serverid."\n";

        $display_str .= 'worker number: ' . self::$worker_num . "\n";
        $display_str .= 'load average: ' . implode(", ", $loadavg) . "\n";

        $display_str .= $this->display_task_ui();

        // if (self::$multiserver) 
        //     $display_str .= $this->display_server_ui();

        $display_str .= $this->display_crawler_ui();

        // 清屏
        //$this->clear_echo();
        // 返回到第一行,第一列
        //echo "\033[0;0H";
        $display_str .= "---------------------------------------------------------------------\n";
        $display_str .= "Press Ctrl-C to quit. Start success.";
        // if (self::$terminate) 
        //     $display_str .= "\n\033[33mWait for the process exits...\033[0m";

        //echo $display_str;
        $this->replace_echo($display_str);
    }

    public function display_task_ui()
    {
        $display_str = "-------------------------------\033[47;30m TASKS \033[0m-------------------------------\n";

        $display_str .= "\033[47;30mtaskid\033[0m". str_pad('', self::$taskid_length+2-strlen('taskid')). 
            "\033[47;30mtaskpid\033[0m". str_pad('', self::$pid_length+2-strlen('taskpid')). 
            "\033[47;30mmemory\033[0m". str_pad('', self::$mem_length+2-strlen('memory')). 
            "\033[47;30mcrawler succ\033[0m". str_pad('', self::$urls_length-strlen('crawler succ')). 
            "\033[47;30mcrawler fail\033[0m". str_pad('', self::$urls_length-strlen('crawler fail')). 
            "\033[47;30mspeed\033[0m". str_pad('', self::$speed_length+2-strlen('speed')). 
            "\n";

        // "\033[32;40m [OK] \033[0m"
        $task_status = $this->get_task_status_list(self::$serverid, self::$worker_num);
        foreach ($task_status as $json) {
            $task = json_decode($json, true);
            if (empty($task)) 
                continue;

            $display_str .= str_pad($task['id'], self::$taskid_length+2).
                str_pad($task['pid'], self::$pid_length+2).
                str_pad($task['mem']."MB", self::$mem_length+2). 
                str_pad($task['crawler_succ'], self::$urls_length). 
                str_pad($task['crawler_fail'], self::$urls_length). 
                str_pad($task['speed']."/s", self::$speed_length+2). 
                "\n";
        }
        //echo "\033[9;0H";
        return $display_str;
    }

    public function display_server_ui()
    {
        $display_str = "-------------------------------\033[47;30m SERVER \033[0m------------------------------\n";

        $display_str .= "\033[47;30mserver\033[0m". str_pad('', self::$server_length+2-strlen('serverid')). 
            "\033[47;30mtasknum\033[0m". str_pad('', self::$worker_num_length+2-strlen('tasknum')). 
            "\033[47;30mmem\033[0m". str_pad('', self::$mem_length+2-strlen('mem')). 
            "\033[47;30mcrawler succ\033[0m". str_pad('', self::$urls_length-strlen('crawler succ')). 
            "\033[47;30mcrawler fail\033[0m". str_pad('', self::$urls_length-strlen('crawler fail')). 
            "\033[47;30mspeed\033[0m". str_pad('', self::$speed_length+2-strlen('speed')). 
            "\n";

        $server_list_json = Queue::get("server_list");
        $server_list = json_decode($server_list_json, true);
        foreach ($server_list as $server) {
            $serverid = $server['serverid'];
            $tasknum = $server['tasknum'];
            $mem = 0;
            $speed = 0;
            $crawler_succ = $crawler_fail = 0;
            $task_status = $this->get_task_status_list($serverid, $tasknum);
            foreach ($task_status as $json) {
                $task = json_decode($json, true);
                if (empty($task)) 
                    continue;

                $mem += $task['mem'];
                $speed += $task['speed'];
                $crawler_fail += $task['crawler_fail'];
                $crawler_succ += $task['crawler_succ'];
            }

            $display_str .= str_pad($serverid, self::$server_length).
                str_pad($tasknum, self::$worker_num_length+2). 
                str_pad($mem."MB", self::$mem_length+2). 
                str_pad($crawler_succ, self::$urls_length). 
                str_pad($crawler_fail, self::$urls_length). 
                str_pad($speed."/s", self::$speed_length+2). 
                "\n";
        }
        return $display_str;
    }

    public function display_crawler_ui()
    {
        $display_str = "---------------------------\033[47;30m crawler STATUS \033[0m--------------------------\n";

        $display_str .= "\033[47;30mfind pages\033[0m". str_pad('', 16-strlen('find pages')). 
            "\033[47;30mqueue\033[0m". str_pad('', 14-strlen('queue')). 
            "\033[47;30mcrawlered\033[0m". str_pad('', 15-strlen('crawlered')). 
            "\033[47;30mfields\033[0m". str_pad('', 15-strlen('fields')). 
            "\033[47;30mdepth\033[0m". str_pad('', 12-strlen('depth')). 
            "\n";

        $crawler   = $this->get_crawler_url_num();
        $crawlered = $this->get_crawlered_url_num();
        $queue     = $this->queue_lsize();
        $fields    = $this->get_fields_num();
        $depth     = $this->get_depth_num();
        $display_str .= str_pad($crawler, 16);
        $display_str .= str_pad($queue, 14);
        $display_str .= str_pad($crawlered, 15);
        $display_str .= str_pad($fields, 15);
        $display_str .= str_pad($depth, 12);
        $display_str .= "\n";
        return $display_str;
    }


}
