PHPG
====

A PostgreSQL database interface class written in PHP specifically designed to confront and resolve a majority of long-standing issues with PHP's native PostgreSQL driver.

Much of the underlying functionality utilizes PHP's native PostgreSQL driver to maintain performance and reliability.

<b>Features</b>
* Automatic detection and transformation of multiple PostgreSQL data-types (such as Arrays, Hstores, etc) to native PHP data structures.
* Transaction-style deletes, updates and inserts

<b>More on Automatic Detection & Transformation of PostgreSQL data-types</b>
* PostgreSQL Arrays (ANY data-type) to native PHP Arrays.
* PostgreSQL Hstores to native PHP Associative Arrays.
* PostgreSQL Geometric data-types (lseg, point, polygon, etc) to native PHP Arrays.
* PostgreSQL Dates / Timestamps to native PHP DateTime Objects.
* ... And many more!

<b>Requirements</b>

Need to perform more testing, and create solid minimal requirements. Currently developed and tested on PHP 5.4 and PostgreSQL 9.2.

Theoretical Requirements:
* PostgreSQL 6.5 or later. 8.0 or later for full PostgreSQL feature support.
* PHP 5.0 or later.

About The Author
================
<b>Written and maintained by:</b>
* Joshua D. Burns (jdburnz@gmail.com)

<b>Online Presence:</b>
* Programming BLOG: http://www.youlikeprogramming.com
* LinkedIn: http://www.linkedin.com/in/joshuadburns
* Stack Overflow: http://stackoverflow.com/users/253254/joshua-burns

<b>Background</b>
* Co-Founder and Director of Technology at Message In Action (http://www.messageinaction.com).
* Specialize in large-scale, high performance eCommerce and inventory management systems.

<b>Qualifications</b>
* 5+ years experience in Project Management
* 10+ years of programming experience in PHP, Python and Javascript.
* Intimate working knowledge of PostgreSQL, Microsoft SQL Server, MySQL and MongoDB database back-ends.

Quick Start Guide / Tutorial
============================

<b>Instantiating a connection to PostgreSQL</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class

// Pass a string of connection parameters as described here: http://php.net/manual/en/function.pg-connect.php
$params = "host=localhost port=5432 dbname=my_db user=postgres password=my_pass options='--client_encoding=UTF8'";
$phpg = PHPG($params);

// Pass an associative array of connection parameters:
$params = array(
  'host' => 'localhost',
  'port' => '5432',
  'dbname' => 'my_db',
  'user' => 'postgres',
  'password' => 'my_pass',
  'options' => "'--client_encoding=UTF8'"
);
$phpg = PHPG($params);
```

<b>Retrieve an existing PostgreSQL conection</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class

// Initial instantiation of connection, must pass connection parameters.
$params = "host=localhost port=5432 dbname=my_db user=postgres password=my_pass options='--client_encoding=UTF8'";
$phpg = PHPG($params);
$phpg->setAlias('My Connection');

/**
/* From Another Scope, where $phpg is not accessible...
**/

// Retrieve existing connection via it's alias, specified above:
$phpg = PHPG::connectionByAlias('My Connection');

// Retrieve existing connection by passing params as a connection string:
$params = "host=localhost port=5432 dbname=my_db user=postgres password=my_pass options='--client_encoding=UTF8'";
$phpg = PHPG::connectionByParams($params);

// Retrieve existing connection by passing params as an associative array:
$params = array(
  'host' => 'localhost',
  'port' => '5432',
  'dbname' => 'my_db',
  'user' => 'postgres',
  'password' => 'my_pass',
  'options' => "'--client_encoding=UTF8'"
);
$phpg = PHPG::connectionByParams($params);
```

<i>Note: The reasoning behind retrieving an existing connection via `connectionByParams()` rather than auto-detection of an existing connection when instantiating PHPG, is there may be fringe cases where two separate identical connections to the same data source may be desired.</i>

<b>Performing a query and iterating over the result set</b>
```php
<?php
// Perform the query
$phpg->execute('grab users', "SELECT first_name, last_name FROM users ORDER BY last_name, first_name");

// Iterating over the result set using `while`:
while($user = $phpg->iter('grab users')) {
  print $user['first_name'] . ' ' . $user['last_name'] . '<br />';
}

// Iterating over the result set using `foreach` (not yet implemented):
foreach($php->iter('grab users') as $index => $user) {
  print $user['first_name'] . ' ' . $user['last_name'] . '<br />';
}
```

<b>Performing a query and retrieving the number of rows returned in the result set</b>
```php
<?php
// Perform the query:
$phpg->execute('grab users', "SELECT first_name, last_name FROM users ORDER BY last_name, first_name");
// Retrieve the number of rows returned by the query:
$num_results = $phpg->rowcount('grab users');
// Print the number:
print 'Number of users found: ' . $num_results;
```

<b>Performing a query and grabbing a single result from the result set</b>
```php
<?php
// Perform the query
$phpg->execute('grab users', "SELECT first_name, last_name FROM users ORDER BY last_name, first_name");
// Grab the first row (advances the cursor from position 0 to position 1)
$user_1 = $phpg->fetchone('grab users');
// Grab the second row (advances the cursor from position 1 to position 2)
$user_2 = $phpg->fetchone('grab users');
```

<b>Committing one or more changes to the database</b>
```php
<?php
// Insert a record
$phpg->execute('insert user', "INSERT INTO users (first_name, last_name) VALUES ('John', 'Smith')");

// Update a record
$phpg->execute('update user', "UPDATE users SET first_name = 'Jane' WHERE last_name = 'Doe'");

// Commit all actions up to this point. This will commit both the INSERT and UPDATE.
$phpg->commit();
```

<i>Note: commit() will commit ALL inserts, updates and deletes on that database connection since the last commit(). rollback() or instantiation. Currently the ability to commit on an alias-by-alias basis is not supported.</i>

<b>Rolling back one or more changes to the database</b>
```php
<?php
// Insert a record
$phpg->execute('insert user', "INSERT INTO users (first_name, last_name) VALUES ('John', 'Smith')");

// Delete a record
$phpg->execute('delete user', "DELETE FROM users WHERE last_name = 'Doe'");

// Roll back all actions since the last commit() or rollback(), or instantiation. This will rollback both the insert and delete in this example.
$phpg->rollback();
```

<i>Note: rollback() will undo ALL inserts, updates and deletes on that database connection since the last commit(). rollback() or instantiation. Currently the ability to rollback on an alias-by-alias basis is not supported.</i>

<b>Performing an insert, update or delete, immediately discarding the result set</b>
```php
<?php
// Perform the query. Passing Null as the alias will cause the resulting resource to be immediately discarded.
$phpg->execute(Null, "DELETE FROM users WHERE last_name = 'Smith'");

```

<b>Performing an insert, update or delete, retrieving the number of affected rows</b>
```php
<?php
// Perform the query. Passing a string as the alias will cause the resulting resource to be stored, from which you can then access information such as iterating over the result set, and accessing rows returned (for SELECTs), and affected rows (for INSERTs, UPDATEs, DELETEs).
$phpg->execute('delete users', "DELETE FROM users WHERE last_name = 'Smith'");

// Retrieve the number of rows affected by this action:
$row_count = $phpg->rows_affected('delete users');

print 'Number of users deleted: ' . $row_count;
```

If anyone would like to request for features, please do not hesitate to e-mail me at: jdburnz@gmail.com
