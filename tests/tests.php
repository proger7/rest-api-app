<?php

use PHPUnit\Framework\TestCase;

if (!file_exists(__DIR__.'/config.php')) {
	copy(__DIR__.'/config.php.dist',__DIR__.'/config.php');
}
require __DIR__.'/config.php';
require __DIR__.'/../api.php';

class API
{
	protected $test;
	protected $api;

	public function __construct($test)
	{
		$this->test = $test;
	}

	private function action($method,$url,$data='')
	{
		$url = parse_url($url);
		$query = isset($url['query'])?$url['query']:'';
		parse_str($query,$get);

		$data = 'data://text/plain;base64,'.base64_encode($data);

		switch(MySQL_CRUD_API_Config::$dbengine) {
			case 'mysql':	$class = 'MySQL_CRUD_API'; break;
			default:	die("DB engine not supported: $dbengine\n");
		}

		$this->api = new $class(array(
				'hostname'=>MySQL_CRUD_API_Config::$hostname,
				'username'=>MySQL_CRUD_API_Config::$username,
				'password'=>MySQL_CRUD_API_Config::$password,
				'database'=>MySQL_CRUD_API_Config::$database,
				'charset'=>'utf8',
				// for tests
				'method' =>$method,
				'request' =>$url['path'],
				'post'=>$data,
				'get' =>$get,
		));
		return $this;
	}

	public function get($url)
	{
		return $this->action('GET',$url);
	}

	public function post($url,$data)
	{
		return $this->action('POST',$url,$data);
	}

	public function put($url,$data)
	{
		return $this->action('PUT',$url,$data);
	}

	public function delete($url)
	{
		return $this->action('DELETE',$url);
	}

	public function expect($output,$error=false)
	{
		$exception = false;
		ob_start();
		try {
			$this->api->executeCommand();
		} catch (\Exception $e) {
			$exception = $e->getMessage();
		}
		$data = ob_get_contents();
		ob_end_clean();
		$this->test->assertEquals($error.$output, $exception.$data);
		return $this;
	}
}

class MySQL_CRUD_API_Test extends TestCase
{
	public static function setUpBeforeClass()
	{
		if (MySQL_CRUD_API_Config::$database=='test_database') {
			die("Configure database in 'config.php' before running tests.\n");
		}

		$dbengine = MySQL_CRUD_API_Config::$dbengine;
		$hostname = MySQL_CRUD_API_Config::$hostname;
		$username = MySQL_CRUD_API_Config::$username;
		$password = MySQL_CRUD_API_Config::$password;
		$database = MySQL_CRUD_API_Config::$database;

		$fixture = __DIR__.'/blog.'.$dbengine;

		if ($dbengine == 'mysql') {

			$link = mysqli_connect($hostname, $username, $password, $database);
			if (mysqli_connect_errno()) {
				die("Connect failed: ".mysqli_connect_error()."\n");
			}

			$i=0;
			if (mysqli_multi_query($link, file_get_contents($fixture))) {
				do { $i++; } while (mysqli_next_result($link));
			}
			if (mysqli_errno($link)) {
				die("Loading '$fixture' failed on statemement #$i with error:\n".mysqli_error($link)."\n");
			}

			mysqli_close($link);

		}
	}

	public function testListItems()
	{
		$test = new API($this);
		$test->get('/item');
		$test->expect('{"item":{"columns":["id","name","phone","key","created_at","updated_at"],"records":[["5","sdgsdgsdg","75667346","FGDG5123","2023-10-25 15:27:38","2023-10-25 15:27:38"],["6","hgkgh","3463463","GFGH331","2023-10-26 15:27:38","2023-10-26 15:27:38"]]}}');
	}

	public function testReadItem()
	{
		$test = new API($this);
		$test->get('/item/1');
		$test->expect('{"id":"1","name":"anouncement","phone":"371-555-777","key":"NUMPAD1","created_at":"2023-10-24 15:27:38","updated_at":"2023-10-24 15:27:38"}');
	}

	public function testAddItem()
	{
		$test = new API($this);
		$test->post('/item','{"name":"test name","phone":"371-555-889","key":"FHGH555","created_at":"2023-10-27 12:39:15","updated_at":"2023-10-27 12:39:15"}');
		$test->expect('3');
	}

	public function testEditItem()
	{
		$test = new API($this);
		$test->put('/item/3','{"name":"sdgsdgsdg","phone":"346734634","key":"FGDG (edited)"}');
		$test->expect('1');
		$test->get('/item/3');
		$test->expect('{"id":"3","name":"sdgsdgsdg","phone":"346734634","key":"FGDG (edited)"}');
	}

	public function testDeleteItem()
	{
		$test = new API($this);
		$test->delete('/item/4');
		$test->expect('1');
		$test->get('/item/4');
		$test->expect('','Not found (object)');
	}
}
