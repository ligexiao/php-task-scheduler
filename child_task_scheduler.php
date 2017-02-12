<?php

/**
 *	A thin wapper around the coroutine function
 * */
class Task{
	protected $taskId;
	protected $coroutine;

	protected $sendValue = null;
	protected $beforeFirstYieldFlag = true;

	/**
	 *	key param: $coroutine, run task is essentially to execute generator function
	 * */
	public function __construct($taskId, Generator $coroutine){
		$this->taskId = $taskId;
		$this->coroutine = $coroutine;
	}

	public function getTaskId(){
		return $this->taskId;
	}

	/**
	 *	pass value to the next yield expression
	 * */
	public function setSendValue($sendValue){
		$this->sendValue = $sendValue;
	}

	public function run(){
		if($this->beforeFirstYieldFlag){// run in the first time on execution of task   
			$this->beforeFirstYieldFlag = false;
			echo "coroutine-yield current \n ";
			return $this->coroutine->current();
		}else{
			echo "sendValue: {$this->sendValue}\n";
			$retval = $this->coroutine->send($this->sendValue);// iterator go to the next yield
			$this->sendValue=null;
			return $retval; 
		}
	}

	public function isFinished(){
		return !$this->coroutine->valid();
	}
}


/**
 *	Scheduler, which manages multiple tasks into a queue, cycles through the tasks and run them
 * */
class Scheduler{
	protected $maxTaskId = 0;// assign the task id automaticaliy  
	protected $taskMap = [];
	protected $taskQueue;

	public function __construct(){
		$this->taskQueue = new SplQueue();
	}

	/**
	 *	schedule task into the queue
	 * */
	public function schedule(Task $task){
		echo "schedule task(id:{$task->getTaskId()}) enqueue...\n";
		$this->taskQueue->enqueue($task);
	}

	/**
	 *	Add generator function wapped in a task way into the queue.
	 *		1) assgin the task id in auto-increment
	 *		2) add task into the task quueue
	 * */
	public function addTask(Generator $coroutine){
		$tid = ++$this->maxTaskId;
		$task = new Task($tid, $coroutine);// call Task class instance
		$this->taskMap[$tid] = $task;
		$this->schedule($task);
		return $tid;
	}

	public function delTask($tid){
		if(!isset($this->taskMap[$tid])){
			return false;
		}
		unset($this->taskMap[$tid]);
		
		foreach($this->taskQueue as $i=>$task){
			if($task->getTaskId() == $tid){// if task to be deleted is still in the task queue.
				unset($this->taskQueue[$i]);	
				break;
			}
		}
		return true;
	}

	/*
	 * it traverses from the first yield to the last one of the generator function.
	 * iteration execution once and the task dequeue&enqueue one time until the iterator is finished. 
	 *
	 * */
	public function run(){
		while(!$this->taskQueue->isEmpty()){
			$task = $this->taskQueue->dequeue();
			echo "\ntask(id:{$task->getTaskId()}) dequeue...\n";
			$retval = $task->run();// return  the coroutine obj in the first time
			if($retval instanceof SystemCall){// when yield a SystemCall instance
				echo "SystemCall instance.\n";	
				$retval($task, $this);// set send value 
				echo "continue.\n";
				continue;
			}

			if($task->isFinished()){
				unset($this->taskMap[$task->getTaskId()]);
			}else{
				echo "task(id:{$task->getTaskId()}) enqueue...\n";
				$this->taskQueue->enqueue($task);
			}
		}
	}

}

/**
 *	General System Call Class
 * */
class SystemCall{
	protected $callback;

	public function __construct(Callable $callback){
		$this->callback = $callback;
	}

	public function __invoke(Task $task, Scheduler $scheduler){
		$callback = $this->callback;
		return $callback($task, $scheduler);
	}
}

// systemcall
function get_task_id(){
	echo "get_task_id() start..\n";
	return new SystemCall(
		function(Task $task, Scheduler $scheduler){
			$task->setSendValue($task->getTaskId());
			$scheduler->schedule($task);
		}
	);
}

/**
 *	A systemcall that just passes a value to the generator instance.
 * */
function add_task(Generator $coroutine){
	echo "add_task() start..\n";
	return new SystemCall(
		function(Task $task, Scheduler $scheduler) use ($coroutine){
			$childTid = $scheduler->addTask($coroutine);// schedule child task enqueue
			$task->setSendValue($childTid);// send value to the next dequeued task that is child task
			$scheduler->schedule($task);// schedule task enqueue
		}
	);
}

function del_task($tid){
	echo "del_task() start..\n";
	return new SystemCall(
		function(Task $task, Scheduler $scheduler) use ($tid){
			$task->setSendValue($scheduler->delTask($tid));
			$scheduler->schedule($task);// schedule task enqueue
		}
	);
}

/**
 * Generator Function
 * */
function child_task(){
	$tid = (yield get_task_id());
	while(true){
		echo "Child task {$tid} still alive!\n";
		yield;
	}
}

function task(){
	$tid = (yield get_task_id());
	$childTid = (yield add_task(child_task()));// call child task
	
	for($i = 1; $i <= 6; ++$i){
		echo "This is task {$tid} iteration {$i}.\n";
		yield;
			
		if($i==3){
			yield del_task($childTid);
		}
	}
}

$scheduler = new Scheduler();

$scheduler->addTask(task());

echo "\nstart run: \n";
$scheduler->run();
