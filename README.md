# Phalcon\Incubator\Session

Usage examples of the adapters available here:

## Aerospike

This adapter uses an Aerospike Database to store session data.

To use this adapter on your machine, you need at least:

- [Aerospike Server][1] >= 3.5.3
- [Aerospike PHP Extension][2]

Usage:

```php
use Phalcon\Incubator\Session\Adapter\Aerospike as SessionHandler;

$di->set('session', function () {
    $session = new SessionHandler([
        'hosts' => [
            [
                'addr' => '127.0.0.1',
                'port' => 3000,
            ]
        ],
        'persistent' => true,
        'namespace'  => 'test',
        'prefix'     => 'session_',
        'lifetime'   => 8600,
        'uniqueId'   => '3Hf90KdjQ18',
        'options'    => [
            \Aerospike::OPT_CONNECT_TIMEOUT => 1250,
            \Aerospike::OPT_WRITE_TIMEOUT   => 1500,
        ],
    ]);

    $session->start();

    return $session;
});
```


## Database

This adapter uses a database backend to store session data:

```php
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Incubator\Session\Adapter\Database;

$di->set('session', function () {
    // Create a connection
    $connection = new Mysql([
        'host'     => 'localhost',
        'username' => 'root',
        'password' => 'secret',
        'dbname'   => 'test',
    ]);

    $session = new Database([
        'db'    => $connection,
        'table' => 'session_data',
    ]);

    $session->start();

    return $session;
});

```

This adapter uses the following table to store the data:

```sql
 CREATE TABLE `session_data` (
  `session_id` VARCHAR(35) NOT NULL,
  `data` text NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`session_id`)
);
```

## Mongo

Install PHP MongoDB Extension via pecl:

```bash
pecl install mongodb
```

After install, add the following line to your `php.ini` file:

```
extension=mongodb.so
```

This adapter uses a Mongo database backend to store session data:

```php
use Phalcon\Incubator\Session\Adapter\Mongo as MongoSession;

$di->set('session', function () {
    // Create a connection to mongo
    $mongo = new \MongoDB\Client(
        'mongodb+srv://<username>:<password>@<cluster-address>/test?retryWrites=true&w=majority'
    );

    // Passing a collection to the adapter
    $session = new MongoSession([
        'collection' => $mongo->test->session_data,
    ]);
    $session->start();

    return $session;
});
```
