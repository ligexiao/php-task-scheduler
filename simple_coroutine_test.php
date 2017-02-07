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
			echo 'last yield value: ';
			return $this->coroutine->current();
		}else{
			echo 'next-send: '.$this->sendValue;
			$retval = $this->coroutine->send($this->sendValue);
			echo ', after-ret: '.$retval."\n";
			$this->sendValue=null;
			return $retval; 
		}
	}

	public function isFinished(){
		return !$this->coroutine->valid();
	}
}


function gen(){
	echo "\nstart gen:====== \n";
	
	yield 'first';
	
	$ret = (yield 'second');
	echo '[gen-'.$ret.'-gen]';
	
	yield 'third';

	echo "\nend gen======. \n";
}

$gen = gen(); // call rewind() in default -> the first line in gen()

function test_current(Generator $gen){
	var_dump($gen->current());// it will execute gen() function and return first yield value when call current() in the first time
	echo "another call: \n";
	var_dump($gen->current());// it just return the current value when call current() in the next time
}
function test_send(Generator $gen){
	$sendValue = $gen->send('not print');// start to excution from in the first yield and interrupt in the next yield
	var_dump($sendValue);
}

function test_task(Generator $gen){
	$cor1 = new Task(100, $gen);

	$cor1->setSendValue("not print1 ");
	var_dump($cor1->run());// start to execute gen() and return the current val in the first time

	$cor1->setSendValue("print2 ");
	var_dump($cor1->run());

	$cor1->setSendValue("the thrid send value ");
	var_dump($cor1->run());

	$cor1->setSendValue("the forth send value ");
	var_dump($cor1->run());
}

$gen = gen(); // call rewind() in default -> the first line in gen()

if(count($argv)>1){
	switch($argv[1]){
	case "current":
			test_current($gen);
			break;
	case "send":
			test_send($gen);
			break;
	case "task":
			test_task($gen);
			break;
	default:
			echo "not defined! \n";
			break;
	}
}

