# MySQL-CRUD-API

Simple PHP script that adds a very basic API to a MySQL InnoDB database

## Requirements

  - PHP 5.3 or higher with MySQLi or SQLSRV enabled

## Installation

This is a single file application! Upload "api.php" somewhere and enjoy!


## Configuration

Edit the following lines in the bottom of the file "api.php":

```
$api = new MySQL_CRUD_API(array(
	'username'=>'xxx',
	'password'=>'xxx',
	'database'=>'xxx'
));
$api->executeCommand();
```

These are all the configuration options and their default values:

```
$api = new MySQL_CRUD_API(array(
	'username=>'root'
	'password=>null,
	'database=>false,
	'permissions'=>array('*'=>'crudl'),
// for connectivity (defaults to localhost):
	'hostname'=>null,
	'port=>null,
	'socket=>null,
	'charset=>'utf8',
// dependencies (added for unit testing):
	'db'=>null,
	'method'=>$_SERVER['REQUEST_METHOD'],
	'request'=>$_SERVER['PATH_INFO'],
	'get'=>$_GET,
	'post'=>'php://input',
));
$api->executeCommand();
```

For the alternative MsSQL_CRUD_API class the following mapping applies:

 - username = UID
 - password = PWD
 - database = Database
 - hostname = Server
 - port = (Server),port
 - socket = (not supported)
 - charset = CharacterSet

The other variables are not MySQL or MsSQL server specific.


## Token
Add token at the end of url. For example, http://<DOMAIN>/api.php/item?token=test777

## Usage
You can do all CRUD (Create, Read, Update, Delete) operations and one extra List operation. Here is how:

### List
http://<DOMAIN>/api.php/item?token=test777

### Create
You can easily add a record using the POST method 
(x-www-form-urlencoded, see rfc1738). The call returns the "last insert id".
POST http://<DOMAIN>/api.php/item?token=test777
id=1&name=Internet

### Read
GET http://<DOMAIN>/api.php/item/1?token=test777

### Update
Editing a record is done with the PUT method. The call returns the rows affected.
PUT http://<DOMAIN>/api.php/item/4?token=test777
id=4&name=Internet+networking


### Delete
The DELETE verb is used to delete a record. The call returns the rows affected.
DELETE http://<DOMAIN>/api.php/item/4?token=test777

## Tests

There are PHPUnit tests in the file 'tests.php'. You need to configure your test database connection in this file. After that run:

```
$ wget https://phar.phpunit.de/phpunit.phar
$ php phpunit.phar tests/tests.php
PHPUnit 4.7.3 by Sebastian Bergmann and contributors.
$
```

NB: You MUST use an empty database as a desctructive database fixture ('blog.mysql') is loaded.
