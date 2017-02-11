<?php
class Task{
	protected $taskId;
	protected $coroutine;

	protected $sendValue = null;
	protected $beforeFirstYieldFlag = true;

	public function __construct($taskId, Generator $coroutine){
		$this->taskId = $taskId;
		$this->coroutine = $coroutine;
	}

	public function getTaskId(){
		return $this->taskId;
	}

	public function setSendValue($sendValue){
		$this->sendValue = $sendValue;
	}

	public function run(){
		if($this->beforeFirstYieldFlag){// when the last yield has been excuted.   
			$this->beforeFirstYieldFlag = false;
			echo "last yield value \n ";
			$this->coroutine->current();
			echo "after current \n";
		}else{
			$retval = $this->coroutine->send($this->sendValue);// iterator go to the next yield
			$this->sendValue=null;
			return $retval; 
		}
	}

	public function isFinished(){
		return !$this->coroutine->valid();
	}
}

class Scheduler{
	protected $maxTaskId = 0;
	protected $taskMap = [];
	protected $taskQueue;

	public function __construct(){
		$this->taskQueue = new SplQueue();
	}

	public function newTask(Generator $coroutine){
		$tid = ++$this->maxTaskId;
		$task = new Task($tid, $coroutine);
		$this->taskMap[$tid] = $task;
		$this->schedule($task);
		return $tid;
	}

	public function schedule(Task $task){
		echo "schedule-enqueue...\n";
		$this->taskQueue->enqueue($task);
	}

	public function run(){
		while(!$this->taskQueue->isEmpty()){
			$task = $this->taskQueue->dequeue();
			$task->run();

			if($task->isFinished()){
				unset($this->taskMap[$task->getTaskId()]);
			}else{
				$this->schedule($task);// next yield expression enters to the queue
			}
		}
	}
}

function task1(){
	for($i = 1; $i <= 10; ++$i){
		echo "This is task 1 iteration {$i}.\n";
		yield;
	}
}

function task2(){
	for($i = 1; $i <= 5; ++$i){
		echo "This is task 2 iteration {$i}.\n";
		yield;
	}
}

$scheduler = new Scheduler();

$scheduler->newTask(task1());
$scheduler->newTask(task2());

echo "start run: \n";
$scheduler->run();
