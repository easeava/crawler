<?php 

class Test 
{

	public static $work_num = 10;
	public static $pid  = 0;
	public static $master = true;
	public static $process = false;
	public static $child_master = false;
	public static $child = false;

	public function __construct()
	{
		
	}

	public function run($worker = null) 
	{
		self::$pid = posix_getpid();
	    if (self::$master && !self::$process) {
			echo '主进程:'.self::$pid.PHP_EOL;
			self::$process = true;
			// 开启子进程master
			$process = new \swoole_process(function($worker) {
				self::$master = false;
				self::$child_master = true;
				$this->run();
			});

			$pid = $process->start();

			\swoole_process::signal(SIGCHLD, function($sig) use ($pid) {
			  	while($ret = swoole_process::wait(false)) {
			  		if ($ret['pid'] == $pid) {
			  			echo '子进程master: 退出'.PHP_EOL;
			  		}
			  		// var_dump($ret);
			      	echo "退出PID={$ret['pid']}\n";
			      	// $pid = $this->process();
			      	// echo "拉起PID={$pid}\n";	
			  	}
			});

			echo 'aaaa';
		}

		if (self::$child_master) {
	    	// 子进程构建
	    	for ($i=0; $i < self::$work_num; $i++) { 
				$this->process();
			}
		}

		if (self::$child) {
			while (true) {
			    echo '子进程while：'.self::$pid.PHP_EOL;
			    sleep(5);
			}
		}

	}


	public function process()
	{

		$process = new \swoole_process(function($work) {
			// 子进程中child_master标志取消
			self::$master = false;
			self::$child_master = false;
			// 子进程标志开启
			self::$child = true;
			$this->run();
		});
		// $process->name('child');
		$pid = $process->start();
		return $pid;
	}
}

$t = new Test();
$t->run();

// for ($i=0; $i < 10; $i++) { 
// 	$t = new Test();
// 	$t->run();
// }