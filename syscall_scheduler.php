<?php
class Task{
	protected $taskId;
	protected $coroutine;

	protected $sendValue = null;
	protected $beforeFirstYieldFlag = true;

	/**
	 *	关键参数$coroutine: 运行任务的本质就是执行生成器函数 - send()
	 * */
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
		if($this->beforeFirstYieldFlag){// firstly run   
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

class Scheduler{
	protected $maxTaskId = 0;
	protected $taskMap = [];
	protected $taskQueue;

	public function __construct(){
		$this->taskQueue = new SplQueue();
	}

	public function newTask(Generator $coroutine){
		$tid = ++$this->maxTaskId;
		$task = new Task($tid, $coroutine);// call Task class instance
		$this->taskMap[$tid] = $task;
		$this->schedule($task);
		return $tid;
	}

	public function schedule(Task $task){
		echo "schedule task(id:{$task->getTaskId()}) enqueue...\n";
		$this->taskQueue->enqueue($task);
	}
	
	/*
	 * it traverses from the first yield to the last one of generator.
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
				$this->schedule($task);// next yield expression enters to the queue
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

/**
 *	A systemcall that just passes a value to the generator instance.
 * */
function sys_call_func(){
	echo "sys_call_func() start..\n";
	return new SystemCall(
		function(Task $task, Scheduler $scheduler){
			$task->setSendValue($task->getTaskId());// set value(taskid) to yield expresstion in the next send()
			$scheduler->schedule($task);// schedule task enqueue
		}
	);
}

/**
 * Generator Function
 * */
function generator_func($iterCnt){
	// here's the syscall and first to yeild since it will pass a global variable to generator function when call the next send() 
	$tid = (yield sys_call_func());
	for($i = 1; $i <= $iterCnt; ++$i){
		echo "This is task {$tid} iteration {$i}.\n";
		yield;
	}
}

$scheduler = new Scheduler();

$scheduler->newTask(generator_func(3));
$scheduler->newTask(generator_func(2));

echo "\nstart run: \n";
$scheduler->run();
