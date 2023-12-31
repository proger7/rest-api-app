<?php

class MySQL_CRUD_API extends REST_CRUD_API {

	protected $queries = array(
		'reflect_table'=>'SELECT "TABLE_NAME" FROM "INFORMATION_SCHEMA"."TABLES" WHERE "TABLE_NAME" LIKE ? AND "TABLE_SCHEMA" = ?',
		'reflect_pk'=>'SELECT "COLUMN_NAME" FROM "INFORMATION_SCHEMA"."COLUMNS" WHERE "COLUMN_KEY" = \'PRI\' AND "TABLE_NAME" = ? AND "TABLE_SCHEMA" = ?',
		'reflect_belongs_to'=>'SELECT
				"TABLE_NAME","COLUMN_NAME",
				"REFERENCED_TABLE_NAME","REFERENCED_COLUMN_NAME"
			FROM
				"INFORMATION_SCHEMA"."KEY_COLUMN_USAGE"
			WHERE
				"TABLE_NAME" = ? AND
				"REFERENCED_TABLE_NAME" IN ? AND
				"TABLE_SCHEMA" = ? AND
				"REFERENCED_TABLE_SCHEMA" = ?',
		'reflect_has_many'=>'SELECT
				"TABLE_NAME","COLUMN_NAME",
				"REFERENCED_TABLE_NAME","REFERENCED_COLUMN_NAME"
			FROM
				"INFORMATION_SCHEMA"."KEY_COLUMN_USAGE"
			WHERE
				"TABLE_NAME" IN ? AND
				"REFERENCED_TABLE_NAME" = ? AND
				"TABLE_SCHEMA" = ? AND
				"REFERENCED_TABLE_SCHEMA" = ?',
		'reflect_habtm'=>'SELECT
				k1."TABLE_NAME", k1."COLUMN_NAME",
				k1."REFERENCED_TABLE_NAME", k1."REFERENCED_COLUMN_NAME",
				k2."TABLE_NAME", k2."COLUMN_NAME",
				k2."REFERENCED_TABLE_NAME", k2."REFERENCED_COLUMN_NAME"
			FROM
				"INFORMATION_SCHEMA"."KEY_COLUMN_USAGE" k1, "INFORMATION_SCHEMA"."KEY_COLUMN_USAGE" k2
			WHERE
				k1."TABLE_SCHEMA" = ? AND
				k2."TABLE_SCHEMA" = ? AND
				k1."REFERENCED_TABLE_SCHEMA" = ? AND
				k2."REFERENCED_TABLE_SCHEMA" = ? AND
				k1."TABLE_NAME" = k2."TABLE_NAME" AND
				k1."REFERENCED_TABLE_NAME" = ? AND
				k2."REFERENCED_TABLE_NAME" IN ?'
	);

	protected function connectDatabase($hostname,$username,$password,$database,$port,$socket,$charset) {
		$db = mysqli_connect($hostname,$username,$password,$database,$port,$socket);
		if (mysqli_connect_errno()) {
			throw new \Exception('Connect failed. '.mysqli_connect_error());
		}
		if (!mysqli_set_charset($db,$charset)) {
			throw new \Exception('Error setting charset. '.mysqli_error($db));
		}
		if (!mysqli_query($db,'SET SESSION sql_mode = \'ANSI_QUOTES\';')) {
			throw new \Exception('Error setting ANSI quotes. '.mysqli_error($db));
		}
		return $db;
	}

	protected function query($db,$sql,$params) {
		$sql = preg_replace_callback('/\!|\?/', function ($matches) use (&$db,&$params) {
			$param = array_shift($params);
			if ($matches[0]=='!') return preg_replace('/[^a-zA-Z0-9\-_=<>]/','',$param);
			if (is_array($param)) return '('.implode(',',array_map(function($v) use (&$db) {
				return "'".mysqli_real_escape_string($db,$v)."'";
			},$param)).')';
			if (is_object($param) && $param->type=='base64') {
				return "x'".bin2hex(base64_decode($param->data))."'";
			}
			if ($param===null) return 'NULL';
			return "'".mysqli_real_escape_string($db,$param)."'";
		}, $sql);
		//echo "\n$sql\n";
		return mysqli_query($db,$sql);
	}

	protected function fetch_assoc($result) {
		return mysqli_fetch_assoc($result);
	}

	protected function fetch_row($result) {
		return mysqli_fetch_row($result);
	}

	protected function insert_id($db,$result) {
		return mysqli_insert_id($db);
	}

	protected function affected_rows($db,$result) {
		return mysqli_affected_rows($db);
	}

	protected function close($result) {
		return mysqli_free_result($result);
	}

	protected function fetch_fields($result) {
		return mysqli_fetch_fields($result);
	}

	protected function add_limit_to_sql($sql,$limit,$offset) {
		return "$sql LIMIT $limit OFFSET $offset";
	}

	protected function likeEscape($string) {
		return addcslashes($string,'%_');
	}

	protected function is_binary_type($field) {
		//echo "$field->name: $field->type ($field->flags)\n";
		return (($field->flags & 128) && ($field->type==252));
	}

}


class REST_CRUD_API {

	protected $config;

	/* Need to do little changes to Logging Actions */
	// protected function _log_request($authorized = FALSE)
	// {
	//     // Insert the request into the log table
	//     $is_inserted = $this->rest->db->insert(
	//         $this->config->item('rest_logs_table'), [
	//             'uri' => $this->uri->uri_string(),
	//             'method' => $this->request->method,
	//             'params' => $this->_args ? ($this->config->item('rest_logs_json_params') === TRUE ? json_encode($this->_args) : serialize($this->_args)) : NULL,
	//             'api_key' => isset($this->rest->key) ? $this->rest->key : '',
	//             'ip_address' => $this->input->ip_address(),
	//             'time' => time(),
	//             'authorized' => $authorized
	//         ]);

	//     //variable for saving value
	//     $uri        = $this->uri->uri_string();
	//     $method     = $this->request->method;
	//     $params     = $this->_args ? ($this->config->item('rest_logs_json_params') === TRUE ? json_encode($this->_args) : serialize($this->_args)) : NULL;
	//     $api_key    = isset($this->rest->key) ? $this->rest->key : '';
	//     $ip_address = $this->input->ip_address();
	//     $time = time();

	//     //write into file
	// 	$message="\n\n".str_repeat("=", 100);
	// 	$message .= "uri =>".$uri."\n";
	// 	$message .= "method=>".$method."\n";
	// 	$message .= "params =>".$params."\n";
	// 	$message .= "api_key=>".$api_key."\n";
	// 	$message .= "ip_address =>".$ip_address."\n";
	// 	$message .= "time=>".$time."\n";

	// 	$filepath="application/logs/log-".date('Y-m-d').'.txt';
	// 	$fp = fopen($filepath, "a")
	// 	fwrite($fp, $message);
	// 	fclose($fp);


	//     // Get the last insert id to update at a later stage of the request
	//     $this->_insert_id = $this->rest->db->insert_id();       

	//     return $is_inserted;
	// }

	protected function mapMethodToAction($method,$key) {
		switch ($method) {
			case 'GET': return $key?'read':'list';
			case 'PUT': return 'update';
			case 'POST': return 'create';
			case 'DELETE': return 'delete';
			default: $this->exitWith404('method');
		}
	}

	protected function parseRequestParameter(&$request,$characters,$default) {
		if (!count($request)) return $default;
		$value = array_shift($request);
		return $characters?preg_replace("/[^$characters]/",'',$value):$value;
	}

	protected function parseGetParameter($get,$name,$characters,$default) {
		$value = isset($get[$name])?$get[$name]:$default;
		return $characters?preg_replace("/[^$characters]/",'',$value):$value;
	}

	protected function parseGetParameterArray($get,$name,$characters,$default) {
		$values = isset($get[$name])?$get[$name]:$default;
		if (!is_array($values)) $values = array($values);
		if ($characters) {
			foreach ($values as &$value) {
				$value = preg_replace("/[^$characters]/",'',$value);
			}
		}
		return $values;
	}

	protected function applyPermissions($database, $tables, $action, $permissions, $multidb) {
		if (in_array(strtolower($database), array('information_schema','mysql','sys'))) return array();
		$results = array();
		$permissions = array_change_key_case($permissions,CASE_LOWER);
		foreach ($tables as $table) {
			$result = false;
			$options = $multidb?array("*.*","$database.*","$database.$table"):array("*","$table");
			$options = array_map('strtolower', $options);
			foreach ($options as $option) {
				if (isset($permissions[$option])) {
					$result = strpos($permissions[$option],$action[0])!==false;
				}
			}
			if ($result) $results[] = $table;
		}
		return $results;
	}

	protected function processTableParameter($database,$table,$db) {
		$tablelist = explode(',',$table);
		$tables = array();
		foreach ($tablelist as $table) {
			$table = str_replace('*','%',$table);
			if ($result = $this->query($db,$this->queries['reflect_table'],array($table,$database))) {
				while ($row = $this->fetch_row($result)) $tables[] = $row[0];
				$this->close($result);
			}
		}
		return $tables;
	}

	protected function findSinglePrimaryKey($table,$database,$db) {
		$keys = array();
		if ($result = $this->query($db,$this->queries['reflect_pk'],array($table[0],$database))) {
			while ($row = $this->fetch_row($result)) $keys[] = $row[0];
			$this->close($result);
		}
		return count($keys)==1?$keys[0]:false;
	}

	protected function exitWith404($type) {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			header('Content-Type:',true,404);
			die("Not found ($type)");
		} else {
			throw new \Exception("Not found ($type)");
		}
	}

	protected function exitWith422($object) {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			header('Content-Type:',true,422);
			die('Unprocessable Entity');
		} else {
			throw new \Exception(json_encode($object));
		}
	}

	protected function startOutput($callback) {
		if (isset($_SERVER['REQUEST_METHOD'])) {
			header('Access-Control-Allow-Origin: *');
			if ($callback) {
				header('Content-Type: application/javascript');
				echo $callback.'(';
			} else {
				header('Content-Type: application/json');
			}
		}
	}

	protected function endOutput($callback) {
		if ($callback) {
			echo ');';
		}
	}

	protected function processKeyParameter($key,$table,$database,$db) {
		if ($key) {
			$key = array($key,$this->findSinglePrimaryKey($table,$database,$db));
			if ($key[1]===false) $this->exitWith404('1pk');
		}
		return $key;
	}

	protected function processOrderParameter($order) {
		if ($order) {
			$order = explode(',',$order,2);
			if (count($order)<2) $order[1]='ASC';
			$order[1] = strtoupper($order[1])=='DESC'?'DESC':'ASC';
		}
		return $order;
	}

	protected function processFilterParameter($filter,$db) {
		if ($filter) {
			$filter = explode(',',$filter,3);
			if (count($filter)==3) {
				$match = $filter[1];
				$filter[1] = 'LIKE';
				if ($match=='cs') $filter[2] = '%'.$this->likeEscape($filter[2]).'%';
				if ($match=='sw') $filter[2] = $this->likeEscape($filter[2]).'%';
				if ($match=='ew') $filter[2] = '%'.$this->likeEscape($filter[2]);
				if ($match=='eq') $filter[1] = '=';
				if ($match=='ne') $filter[1] = '<>';
				if ($match=='lt') $filter[1] = '<';
				if ($match=='le') $filter[1] = '<=';
				if ($match=='ge') $filter[1] = '>=';
				if ($match=='gt') $filter[1] = '>';
				if ($match=='in') {
					$filter[1] = 'IN';
					$filter[2] = explode(',',$filter[2]);

				}
			} else {
				$filter = false;
			}
		}
		return $filter;
	}

	protected function processPageParameter($page) {
		if ($page) {
			$page = explode(',',$page,2);
			if (count($page)<2) $page[1]=20;
			$page[0] = ($page[0]-1)*$page[1];
		}
		return $page;
	}

	protected function retrieveObject($key,$table,$db) {
		if (!$key) return false;
		if ($result = $this->query($db,'SELECT * FROM "!" WHERE "!" = ?',array($table[0],$key[1],$key[0]))) {
			$object = $this->fetch_assoc($result);
			foreach ($this->fetch_fields($result) as $field) {
				if ($this->is_binary_type($field) && $object[$field->name]) {
					$object[$field->name] = base64_encode($object[$field->name]);
				}
			}
			$this->close($result);
		}
		return $object;
	}

	protected function createObject($input,$table,$db) {
		if (!$input) return false;
		$keys = implode('","',str_split(str_repeat('!', count($input))));
		$values = implode(',',str_split(str_repeat('?', count($input))));
		$params = array_merge(array_keys((array)$input),array_values((array)$input));
		array_unshift($params, $table[0]);
		$result = $this->query($db,'INSERT INTO "!" ("'.$keys.'") VALUES ('.$values.')',$params);
		return $this->insert_id($db,$result);
	}

	protected function updateObject($key,$input,$table,$db) {
		if (!$input) return false;
		$params = array();
		$sql = 'UPDATE "!" SET ';
		$params[] = $table[0];
		foreach (array_keys($input) as $i=>$k) {
			if ($i) $sql .= ',';
			$v = $input[$k];
			$sql .= '"!"=?';
			$params[] = $k;
			$params[] = $v;
		}
		$sql .= ' WHERE "!"=?';
		$params[] = $key[1];
		$params[] = $key[0];
		$result = $this->query($db,$sql,$params);
		return $this->affected_rows($db, $result);
	}

	protected function deleteObject($key,$table,$db) {
		$result = $this->query($db,'DELETE FROM "!" WHERE "!" = ?',array($table[0],$key[1],$key[0]));
		return $this->affected_rows($db, $result);
	}

	protected function findRelations($tables,$database,$db) {
		$collect = array();
		$select = array();
		if (count($tables)>1) {
			$table0 = array_shift($tables);

			$result = $this->query($db,$this->queries['reflect_belongs_to'],array($table0,$tables,$database,$database));
			while ($row = $this->fetch_row($result)) {
				$collect[$row[0]][$row[1]]=array();
				$select[$row[2]][$row[3]]=array($row[0],$row[1]);
			}
			$result = $this->query($db,$this->queries['reflect_has_many'],array($tables,$table0,$database,$database));
			while ($row = $this->fetch_row($result)) {
				$collect[$row[2]][$row[3]]=array();
				$select[$row[0]][$row[1]]=array($row[2],$row[3]);
			}
			$result = $this->query($db,$this->queries['reflect_habtm'],array($database,$database,$database,$database,$table0,$tables));
			while ($row = $this->fetch_row($result)) {
				$collect[$row[2]][$row[3]]=array();
				$select[$row[0]][$row[1]]=array($row[2],$row[3]);
				$collect[$row[4]][$row[5]]=array();
				$select[$row[6]][$row[7]]=array($row[4],$row[5]);
			}
		}
		return array($collect,$select);
	}

	protected function retrieveInput($post) {
		$input = array();
		$data = trim(file_get_contents($post));
		if (strlen($data)>0) {
			if ($data[0]=='{') {
				$input = (array)json_decode($data);
			} else {
				parse_str($data, $input);
				foreach ($input as $key => $value) {
					if (substr($key,-9)=='__is_null') {
						$input[substr($key,0,-9)] = null;
						unset($input[$key]);
					}
				}
			}
		}
		return $input;
	}

	protected function convertBinary($input,$tables,$db) {
		$result = $this->query($db,'SELECT * FROM "!" WHERE 1=2;',array($tables[0]));
		foreach ($this->fetch_fields($result) as $field) {
			$key = $field->name;
			if (isset($input[$key]) && $input[$key] && $this->is_binary_type($field)) {
				$data = $input[$key];
				$data = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
				$input[$key] = (object)array('type'=>'base64','data'=>$data);
			}
		}
		return $input;
	}

	protected function getParameters($config) {

		if(isset($_GET['token'])):

			$con=mysqli_connect('localhost','root','Messi101010!','rest-api-app');
			$token = mysqli_real_escape_string($con,$_GET['token']);
			$checkTokenRes = mysqli_query($con,"SELECT * FROM api_token WHERE token='$token'");
			if(mysqli_num_rows($checkTokenRes) > 0) {
		        $checkTokenRow=mysqli_fetch_assoc($checkTokenRes);
		        if($checkTokenRow['status'] == 1){
						extract($config);
						$table     = $this->parseRequestParameter($request, 'a-zA-Z0-9\-_*,', false);
						$key       = $this->parseRequestParameter($request, 'a-zA-Z0-9\-,', false); // auto-increment or uuid
						$action    = $this->mapMethodToAction($method,$key);
						$callback  = $this->parseGetParameter($get, 'callback', 'a-zA-Z0-9\-_', false);
						$page      = $this->parseGetParameter($get, 'page', '0-9,', false);
						$filters   = $this->parseGetParameterArray($get, 'filter', false, false);
						$satisfy   = $this->parseGetParameter($get, 'satisfy', 'a-z', 'all');
						$columns   = $this->parseGetParameter($get, 'columns', 'a-zA-Z0-9\-_,', false);
						$order     = $this->parseGetParameter($get, 'order', 'a-zA-Z0-9\-_*,', false);
						$transform = $this->parseGetParameter($get, 'transform', '1', false);

						$table    = $this->processTableParameter($database,$table,$db);
						$key      = $this->processKeyParameter($key,$table,$database,$db);
						foreach ($filters as &$filter) $filter = $this->processFilterParameter($filter,$db);
						if ($columns) $columns = explode(',',$columns);
						$page     = $this->processPageParameter($page);
						$order    = $this->processOrderParameter($order);

						$table  = $this->applyPermissions($database,$table,$action,$permissions,$multidb);
						if (empty($table)) $this->exitWith404('entity');

						$object = $this->retrieveObject($key,$table,$db);
						$input = $this->retrieveInput($post);

						// $logs = $this->_log_request();

						// Validation for `Item` table
						if( $_SERVER['REQUEST_METHOD'] !== 'DELETE' ) {

								$id = $object['id'];
								$name = $object['name'];
								$phone = $object['phone'];
								$key = $object['key'];

						
								if( isset($id) ) {
									if ( !is_numeric($id) || $id <= 0  || $id > 9223372036854775807 ) {
										throw new Exception('ID error');
									}
								} elseif( isset($name) ) {
									if (strlen($name) < 1 || strlen($name) > 255) {
										throw new Exception('Name error');
									}
								} elseif( isset($phone) ) {
									if( !is_numeric($phone) || strlen($phone) > 15 ) {
										throw new Exception('Phone error');
									}
								} elseif( isset($key) ) {
									if (strlen($key) < 1 || strlen($key) > 25) {
										throw new Exception('Key error');
									}
								}
						} 


						if (!empty($input)) $input = $this->convertBinary($input,$table,$db);

						list($collect,$select) = $this->findRelations($table,$database,$db);

						return compact('action','database','table','key','callback','page','filters','satisfy','columns','order','transform','db','object','input','collect','select');
		        } else {
		            $status='true';
		            $data="API token deactivated";
		            $code='3';
		        }
			} else {
		        $status='true';
		        $data="Please provide valid API token";
		        $code='2';
		    }

		endif;
	}

	protected function listCommand($parameters) {
		extract($parameters);
		$this->startOutput($callback);
		echo '{';
		$tables = $table;
		$table = array_shift($tables);
		// first table
		$count = false;
		echo '"'.$table.'":{';
		if (is_array($order) && is_array($page)) {
			$params = array();
			$sql = 'SELECT COUNT(*) FROM "!"';
			$params[] = $table;
			foreach ($filters as $i=>$filter) {
				if (is_array($filter)) {
					$sql .= $i==0?' WHERE ':($satisfy=='all'?' AND ':' OR ');
					$sql .= '"!" ! ?';
					$params[] = $filter[0];
					$params[] = $filter[1];
					$params[] = $filter[2];
				}
			}
			if ($result = $this->query($db,$sql,$params)) {
				while ($pages = $this->fetch_row($result)) {
					$count = $pages[0];
				}
			}
		}
		$params = array();
		$sql = 'SELECT ';
		if (is_array($columns)) {
			$sql .= '"'.implode('","',$columns).'"';
		} else {
			$sql .= '*';
		}
		$sql .= ' FROM "!"';
		$params[] = $table;
		foreach ($filters as $i=>$filter) {
			if (is_array($filter)) {
				$sql .= $i==0?' WHERE ':($satisfy=='all'?' AND ':' OR ');
				$sql .= '"!" ! ?';
				$params[] = $filter[0];
				$params[] = $filter[1];
				$params[] = $filter[2];
			}
		}
		if (is_array($order)) {
			$sql .= ' ORDER BY "!" !';
			$params[] = $order[0];
			$params[] = $order[1];
		}
		if (is_array($order) && is_array($page)) {
			$sql = $this->add_limit_to_sql($sql,$page[1],$page[0]);
		}
		if ($result = $this->query($db,$sql,$params)) {
			echo '"columns":';
			$fields = array();
			$base64 = array();
			foreach ($this->fetch_fields($result) as $field) {
				$base64[] = $this->is_binary_type($field);
				$fields[] = $field->name;
			}
			echo json_encode($fields);
			$fields = array_flip($fields);
			echo ',"records":[';
			$first_row = true;
			while ($row = $this->fetch_row($result)) {
				if ($first_row) $first_row = false;
				else echo ',';
				if (isset($collect[$table])) {
					foreach (array_keys($collect[$table]) as $field) {
						$collect[$table][$field][] = $row[$fields[$field]];
					}
				}
				foreach ($base64 as $k=>$v) {
					if ($v && $row[$k]) {
						$row[$k] = base64_encode($row[$k]);
					}
				}
				echo json_encode($row);
			}
			$this->close($result);
			echo ']';
			if ($count) echo ',';
		}
		if ($count) echo '"results":'.$count;
		echo '}';
		// prepare for other tables
		foreach (array_keys($collect) as $t) {
			if ($t!=$table && !in_array($t,$tables)) {
				array_unshift($tables,$t);
			}
		}

		// other tables
		foreach ($tables as $t=>$table) {
			echo ',';
			echo '"'.$table.'":{';
			$params = array();
			$sql = 'SELECT * FROM "!"';
			$params[] = $table;
			if (isset($select[$table])) {
				$first_row = true;
				echo '"relations":{';
				foreach ($select[$table] as $field => $path) {
					$values = $collect[$path[0]][$path[1]];
					$sql .= $first_row?' WHERE ':' OR ';
					$sql .= '"!" IN ?';
					$params[] = $field;
					$params[] = $values;
					if ($first_row) $first_row = false;
					else echo ',';
					echo '"'.$field.'":"'.implode('.',$path).'"';
				}
				echo '}';
			}
			if ($result = $this->query($db,$sql,$params)) {
				if (isset($select[$table])) echo ',';
				echo '"columns":';
				$fields = array();
				$base64 = array();
				foreach ($this->fetch_fields($result) as $field) {
					$base64[] = $this->is_binary_type($field);
					$fields[] = $field->name;
				}
				echo json_encode($fields);
				$fields = array_flip($fields);
				echo ',"records":[';
				$first_row = true;
				while ($row = $this->fetch_row($result)) {
					if ($first_row) $first_row = false;
					else echo ',';
					if (isset($collect[$table])) {
						foreach (array_keys($collect[$table]) as $field) {
							$collect[$table][$field][]=$row[$fields[$field]];
						}
					}
					foreach ($base64 as $k=>$v) {
						if ($v && $row[$k]) {
							$row[$k] = base64_encode($row[$k]);
						}
					}
					echo json_encode($row);
				}
				$this->close($result);
				echo ']';
			}
			echo '}';
		}
		echo '}';
		$this->endOutput($callback);
	}

	protected function readCommand($parameters) {
		extract($parameters);
		if (!$object) $this->exitWith404('object');
		$this->startOutput($callback);
		echo json_encode($object);
		$this->endOutput($callback);
	}

	protected function createCommand($parameters) {
		extract($parameters);
		if (!$input) $this->exitWith404('input');
		$this->startOutput($callback);
		echo json_encode($this->createObject($input,$table,$db));
		$this->endOutput($callback);
	}

	protected function updateCommand($parameters) {
		extract($parameters);
		if (!$input) $this->exitWith404('subject');
		$this->startOutput($callback);
		echo json_encode($this->updateObject($key,$input,$table,$db));
		$this->endOutput($callback);
	}

	protected function deleteCommand($parameters) {
		extract($parameters);
		$this->startOutput($callback);
		echo json_encode($this->deleteObject($key,$table,$db));
		$this->endOutput($callback);
	}

	protected function listCommandTransform($parameters) {
		if ($parameters['transform']) {
			ob_start();
		}
		$this->listCommand($parameters);
		if ($parameters['transform']) {
			$content = ob_get_contents();
			ob_end_clean();
			$data = json_decode($content,true);
			echo json_encode(self::mysql_crud_api_transform($data));
		}
	}

	public function __construct($config) {
		extract($config);

		$hostname = isset($hostname)?$hostname:null;
		$username = isset($username)?$username:'root';
		$password = isset($password)?$password:null;
		$database = isset($database)?$database:false;
		$port = isset($port)?$port:null;
		$socket = isset($socket)?$socket:null;
		$charset = isset($charset)?$charset:'utf8';

		$permissions = isset($permissions)?$permissions:array('*'=>'crudl');

		$db = isset($db)?$db:null;
		$method = isset($method)?$method:$_SERVER['REQUEST_METHOD'];
		$request = isset($request)?$request:(isset($_SERVER['PATH_INFO'])?$_SERVER['PATH_INFO']:'');
		$get = isset($get)?$get:$_GET;
		$post = isset($post)?$post:'php://input';

		$request = explode('/', trim($request,'/'));

		$multidb   = !$database;
		if ($multidb) {
			$database  = $this->parseRequestParameter($request, 'a-zA-Z0-9\-_,', false);
		}
		if (!$db) {
			$db = $this->connectDatabase($hostname,$username,$password,$database,$port,$socket,$charset);
		}

		$this->config = compact('method', 'request', 'get', 'post', 'multidb', 'database', 'permissions', 'db');
	}

	public static function mysql_crud_api_transform(&$tables) {
		$get_objects = function (&$tables,$table_name,$where_index=false,$match_value=false) use (&$get_objects) {
			$objects = array();
			foreach ($tables[$table_name]['records'] as $record) {
				if ($where_index===false || $record[$where_index]==$match_value) {
					$object = array();
					foreach ($tables[$table_name]['columns'] as $index=>$column) {
						$object[$column] = $record[$index];
						foreach ($tables as $relation=>$reltable) {
							if (isset($reltable['relations'])) {
								foreach ($reltable['relations'] as $key=>$target) {
									if ($target == "$table_name.$column") {
										$column_indices = array_flip($reltable['columns']);
										$object[$relation] = $get_objects($tables,$relation,$column_indices[$key],$record[$index]);
									}
								}
							}
						}
					}
					$objects[] = $object;
				}
			}

			return $objects;
		};
		$tree = array();
		foreach ($tables as $name=>$table) {
			if (!isset($table['relations'])) {
				$tree[$name] = $get_objects($tables,$name);
				if (isset($table['results'])) {
					$tree['_results'] = $table['results'];
				}
			}
		}

		return $tree;
	}

	public function executeCommand() {
		$parameters = $this->getParameters($this->config);
		switch($parameters['action']){
			case 'list': $this->listCommandTransform($parameters); break;
			case 'read': $this->readCommand($parameters); break;
			case 'create': $this->createCommand($parameters); break;
			case 'update': $this->updateCommand($parameters); break;
			case 'delete': $this->deleteCommand($parameters); break;
		}
	}

}

// uncomment the lines below when running in stand-alone mode:

header('Access-Control-Allow-Origin: *');

$api = new MySQL_CRUD_API(array(
	'hostname'=>'localhost',
	'username'=>'<USERNAME>',
	'password'=>'<PASSWORD>',
	'database'=>'rest-api-app',
	'charset'=>'utf8'
));
$api->executeCommand();