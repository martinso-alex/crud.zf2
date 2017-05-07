<?php

date_default_timezone_set(@date_default_timezone_get());
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0); // as to be able to parse the real errors later on
set_time_limit(0);

if (PHP_SAPI !== 'cli') {
	$mtrig = new mtrig;
	$mtrig->handle_request();
}


class mtrig {
	
	protected $requests_todo = array(
			'func_error' => 0,
			'db_error' => 0,
			'slow_func' => 0,	
			'slow_req' => 0,	
			'mem' => 0,	
			'custom' => 0,		
			'php_warn' => 0,
			'java' => 0,	
			'php_error' => 0,		
	);
	
	public function __construct() {
		$this->init_request();
	}

	protected function init_request() {
		if ( isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === 0 ) {
			print '<pre>';
		}	

		$this->set_events_to_exec();
	}
	
	protected function set_events_to_exec() {			
		if (isset($_GET['all'])) { // gen all events
			foreach (array_keys($this->requests_todo) as $request_key) {
				$this->requests_todo[$request_key] = 1;
			}
		}
		else {
			foreach (array_keys($this->requests_todo) as $request_key) {
				if (isset($_GET[$request_key])) $this->requests_todo[$request_key] = 1;
			}
		}			
	}	
		
	public function handle_request() {	
		if ($this->requests_todo['func_error']) { // Function Error = func_error
			$event = new func_error_generator;
		}
		
		if ($this->requests_todo['db_error']) { // Database Error = db_error
			$event = new db_error_generator;
		}
				
		if ($this->requests_todo['slow_func']) { // Slow Function Execution = slow_func = slow_func_crit || slow_func_warn (will also generate 'Slow Request Execution')
			$event = new slow_func_generator;
		}		
		
		if ($this->requests_todo['slow_req']) { // Slow Request Execution = slow_req = slow_req_crit || slow_req_warn
			$event = new slow_req_generator;
		}	
		
		if ($this->requests_todo['mem']) { // High Request Memory Usage = mem (mem_crit, mem_warn)
			$event = new mem_generator;
		}		
		
		if ($this->requests_todo['custom']) { // // Custom Event = custom
			$event = new custom_generator;
		}		
		
		if ($this->requests_todo['php_warn']) { // PHP Error - Warning = php_warn
			$event = new php_warn_generator;
		}		
		
		if ($this->requests_todo['java']) { // Java Exception - Critical = java - will abort exec..
			$event = new java_generator;
		}					
		
		if ($this->requests_todo['php_error']) { // PHP Error - Critical = php_error - last one as script will terminate here
			$event = new php_error_generator;
		}		
			

		if ( !event_generator::was_event_triggered()) { // no event whatsoever
			print "unknown trigger, please specify another: " . print_r(array_merge(array('all'), array_keys($this->requests_todo)), true);
		}		
	}

	
}



abstract class event_generator {
	
	protected $buffer='';
	protected $mult=1; // multiplier - how many events generated per a single request - used for function error, custom event & php warning
	protected $bt_depth=1; // how deep would be the bt we genreate in events such as func_error
	protected $rand_agg = 1; // number that will be used to setting the aggregation key (would be randomized if hint was passed)
	protected $hint=false;
	protected $strmult=1; // how many times to multiple string in custom and function error events
	
	protected $ping_number = 3; // for slow exec
	protected $sleep; // for slow req
	protected $mem_limit = 8900000; // for mem related
	protected $custom_data='blabla'; // for custom events
	protected $func_error_var = 'varvar'; // for func_errors
	protected $error_var = 'varvar'; // for php_errors
	protected $slow_func_var = 'slowslow';
	protected $fatal_error_func = 'foo'; // for php_errors
	protected $java_method = 'xyz'; // for java events
	
	static protected $event_triggered_flag=false;
	
	static public function was_event_triggered() {
		return self::$event_triggered_flag === true;
	}
	
	protected function set_event_triggered() {
		self::$event_triggered_flag = true;
	}
	
	
	protected function set_hint() {	
		/// decding whether to generate a random agg hint - which will cause creation of a new issue
		if (!isset($_GET['hint'])) {
			return;
		}
		
		$this->hint = true;		
		$_GET['hint'] === '' ? $this->rand_agg = microtime(true) :  $this->rand_agg = $_GET['hint']; // use random value only if value was not passed
		
		$this->set_agg_hint();		
	}
	

	protected function set_agg_hint() {
		zend_monitor_set_aggregation_hint($this->rand_agg); // generating a random agg hint which ensures that we generate a new issue
	}

	protected function set_bt_depth() {
		if (isset($_GET["bt_depth"]) && is_numeric($_GET['bt_depth'])) {
			$this->bt_depth = $_GET["bt_depth"];		
		}
		elseif (isset($_GET["bt"]) && is_numeric($_GET['bt'])) { // more convinient to remember
			$this->bt_depth = $_GET["bt"];		
		}		
	}	
	

	protected function init_event_gen() {
		$this->set_event_options();		
		
		$this->set_hint();	
	}	
	
	
	protected function set_event_options() {
		$this->set_bt_depth();
		
		if (isset($_GET['mult'])) $this->mult = $_GET['mult'];

		if (isset($_GET['ping']) && is_numeric($_GET['ping'])) $this->ping_number = $_GET['ping'];
	
		if (isset($_GET['mem_limit'])) $this->mem_limit = $_GET['mem_limit'];
		
		if (isset($_GET['custom'])) $this->custom_data = $_GET['custom'];	

		if (isset($_GET['func_error']) && $_GET['func_error']) $this->func_error_var = $_GET['func_error'];
			
		if (isset($_GET['slow_func']) && $_GET['slow_func']) $this->slow_func_var = $_GET['slow_func'];
			
		if (isset($_GET['php_warn']) && $_GET['php_warn']) $this->error_var = $_GET['php_warn'];

		if (isset($_GET['php_error']) && $_GET['php_error']) $this->fatal_error_func = $_GET['php_warn'];		

		if (isset($_GET['java']) && is_string($_GET['java'])) $this->java_method = $_GET['java'];				
		
		$this->sleep = 1000 * 2100;
		if (isset($_GET['sleep'])) $this->sleep = 1000 * $_GET['sleep'];		

		if (isset($_GET['strmult']) && preg_match('/[0-9]*$/', $_GET['strmult'], $match)) $this->strmult = $match[0];
	}		
	
	public function __construct() {
		$this->init_event_gen();
		$this->set_event_triggered();
		$this->generate_event();
		$this->flush_buffer();
	}
	
	protected function flush_buffer() {
		print $this->buffer;
	}
	
	protected function is_win() {
		return isset($_SERVER['OS']) && $_SERVER['OS'] === 'Windows_NT';
	}	
	
	abstract protected function generate_event();	
	

	protected function bt_generator($i=0, $function='is_executable', $var='nosuchexec') {
		if ($i < $this->bt_depth) {
			$this->bt_generator(++$i, $function, $var);
		}
		else {
			$this->buffer .= "reached [$this->bt_depth] calls, generating a $function error \n";
			var_dump($function("$var"));
		}
	}			

	protected function trig_error() {
		$errors = array(E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE);
		$err = $errors[array_rand($errors)];
		trigger_error(str_repeat('nihil agere delectat', $this->strmult) . $this->rand_agg, $err);	
	}		
	
	protected function set_new_agg_key() {
		if ($this->hint) $this->rand_agg = microtime(true); // regenerating
	}
}



class func_error_generator extends event_generator {
	
	protected function generate_event() {
		$funcs = array ('file_get_contents', 'fopen');
		$func = $funcs[array_rand($funcs)];
		
		for ($i=0 ; $i < $this->mult ; $i++) { 
			$this->set_agg_hint();
			$this->bt_generator(0, $func, $this->func_error_var);
			$this->buffer .= "Generated a function_error with bt_depth of [$this->bt_depth] at func [$func] with var [$this->func_error_var] with [$this->rand_agg] as a hint\n";
			$this->set_new_agg_key();
		}	
	}	
}

class db_error_generator extends event_generator {

	protected function generate_event() {
		$tmp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sqlite.db';
		$sql_query = "SELECT * FROM nosuchtable";
		$dbHandle = new PDO("sqlite:{$tmp_file}");
		var_dump($dbHandle->exec($sql_query));

		$this->buffer .= "Generated a db_error event - using pdo::exec()\n";
	}
}

class slow_func_generator extends event_generator {
	
	protected function generate_event() {
		$slow_func_var = $this->slow_func_var;
		$$slow_func_var = array(); // so, if $this->slow_func_var = abc, then we now have an empty array named $abc
			
		$cmd = "ping -c $this->ping_number google.com";
		if ($this->is_win()) $cmd = "ping -n $this->ping_number google.com";		
	
		exec($cmd, $$slow_func_var);
		$this->buffer .= "Generated a slow_func with ping [$this->ping_number], and output: " . print_r($$slow_func_var, true);
	}	
}


class slow_req_generator extends event_generator {
	
	protected function generate_event() {		
		usleep($this->sleep);
		$this->buffer .= "Generated a slow_re event - slept for [$this->sleep] seconds\n";
	}	
}

class mem_generator extends event_generator {
	
	protected function generate_event() {
		$a = "content"; 
		while (true) {
			$a .= $a;
			$mem_used = memory_get_peak_usage();
			if ( $mem_used > $this->mem_limit) break;		
		}
		
		$this->buffer .=  "Generated a mem events with mem_limit [$this->mem_limit]\n";
	}	
}


class custom_generator extends event_generator {
	
	protected function generate_event() {
		$user_data = str_repeat("$this->custom_data: repeated [$this->strmult times]\n", $this->strmult) . "the end..";
			
		for ($i=0 ; $i < $this->mult ; $i++) { 
			$this->set_agg_hint();
			zend_monitor_custom_event("class_{$this->custom_data}", "text_desc_{$this->custom_data}", "agg_hint:{$this->rand_agg}, user_data_{$user_data}");
			$this->buffer .= "monitoring [class_{$this->custom_data}], [text_desc_{$this->custom_data}], [user_data_{$this->custom_data}] * [$this->strmult] with [$this->rand_agg] as a hint\n";	
			$this->set_new_agg_key();
		}	
	}	
}

class php_warn_generator extends event_generator {
	
	protected function generate_event() {
		for ($i=0 ; $i < $this->mult ; $i++) { 
			$this->set_agg_hint();
			if (rand(0,1)) { //0.5 to use bt_generator() and 0.5 to use trig_error()
				$this->bt_generator(0, 'file_get_contents', $this->error_var);			
				$this->buffer .= "Generated a PHP warning with bt_depth of [$this->bt_depth] at file_get_contents [$this->error_var] with [$this->rand_agg] as a hint\n";
			}
			else {
				$this->trig_error();
				$this->buffer .= "Generated a PHP warning with using trig_error()\n";
			}
			
			$this->set_new_agg_key();	
		}
	}	
}

		

class java_generator extends event_generator {
	
	protected function generate_event() {		
		if (isset($_GET["java"]) && in_array('java', get_declared_classes())) {
			print "Generating a Java error at java.util.Stack->$this->java_method()\n"; // no buffer as aborting
			$obj = new java("java.util.Stack");
			$method = $this->java_method;
			$obj->$method();
		}
	}	
}

class php_error_generator extends event_generator {
	
	protected function generate_event() {
		print "Generated a fatal PHP error $this->fatal_error_func() \n"; // cannot use the buffer as we're aborting
		$this->fatal_error_func();
	}	
}
