# Phalcon\Incubator\Session

Usage examples of the adapters available here:


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
