<?php
abstract class Schedule{
    protected $_consumerList = array();
    protected $_msgqkey = null;

    protected $_consumerNum = 2;
    protected $_finishFlag = 'ALLDONE';

    public function __construct($cNum = 0){
        if ($cNum){
            $this->_consumerNum = $cNum;
        }
    }

    public function setConsumerNum($num = 0){
        if ($num){
            $this->_consumerNum = $num;
            return true;
        }

        return false;
    }

    public function setFinishFlag($flag = null){
        if ($flag){
            $this->_finishFlag = $flag;
            return true;
        }

        return false;
    }

    public function run(){
        $this->_consumerList = array();
        for($i=0; $i<$this->_consumerNum; $i++){
            $consumer = new swoole_process(function($worker){
                $this->_consumerFunc($worker);
            });

            if ($this->_msgqkey){
                $consumer->useQueue($this->_msgqkey);
            }
            else{
                $consumer->useQueue();
            }
            $pid = $consumer->start();

            $this->_consumerList[$pid] = $consumer;
        }

        $producer = new swoole_process(function($worker){
            //echo "i'm passer\n";
            exit(0);
        });

        if ($this->_msgqkey){
            $producer->useQueue($this->_msgqkey);
        }
        else{
            $producer->useQueue();
        }

        $pid = $producer->start();
        echo "begin:\n";
        echo sprintf("msgqkey:%s\n", $producer->msgQueueKey);

        $this->_producerFunc($producer);
    }

    protected function _producerFunc($worker){
        if ($this->_onlyConsume()){
            return;
        }

        foreach ($this->doProduce($worker) as $data){
            $worker->push($data);
        }

        //任务数据被取完
        while(true){
            $c = $worker->statQueue();
            $n = $c['queue_num'];
            if ($n === 0){
                break;
            }
        }

        //放入consumer进程程结束标识
        foreach($this->_consumerList as $pid => $w){
            $w->push($this->_finishFlag);
        }

        //确认结束
        while(true){
            $c = $worker->statQueue();
            $n = $c['queue_num'];
            if ($n === 0){
                break;
            }
        }

        $worker->freeQueue();
    }

    protected function _consumerFunc($worker){
        while(1){
            $data = $worker->pop();
            $pid = $worker->pid;
            if ($data == $this->_finishFlag){
                echo "consumer $pid exit\n";
                $worker->exit(0);
            }
            else{
                $this->doConsume($data, $worker);
            }
        }
    }

    protected function _onlyConsume(){
        return !! $this->_msgqkey;
    }

    abstract protected function doProduce($worker);

    abstract protected function doConsume($data, $worker);
}