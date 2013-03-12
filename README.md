PHPG
====

A PostgreSQL database interface class written in PHP specifically designed to confront and resolve a majority of long-standing issues with PHP's native PostgreSQL driver.

Much of the underlying functionality utilizes PHP's native PostgreSQL driver to maintain performance and reliability.

<b>Features</b>
* Automatic detection and transformation of most PostgreSQL data-types to native PHP data structures. Includes Integers, Floats, Booleans, NULLs, Arrays, Hstores, Geometrical Types, and more!
* Transaction-style database cursors, with commit and rollback functionality.
* Superglobal database connections: Retrieve existing database connections from any scope.

<b>More on Automatic Detection & Transformation of PostgreSQL Data-Types</b>
* PostgreSQL Arrays (any data-type) to PHP Arrays.
* PostgreSQL Hstores to PHP Associative Arrays.
* PostgreSQL Geometric Data-Types (box, point, polygon, lseg, etc) to native PHP Associative Arrays.
* PostgreSQL Dates / Timestamps to native PHP DateTime Objects (including automatic detection of Time Zones).
* ... And much more!

<b>Requirements</b>
* PHP: 5.0 or later
* PostgreSQL: 8.0 or later
* PostgreSQL Contrib Modules (Optional) : hstore, PostGIS (PostgreSQL 8.x, built into 9.x)

About The Author
================
<b>Written and maintained by:</b>
* Joshua D. Burns
* <jdburnz@gmail.com>, <josh@messageinaction.com>
* http://www.messageinaction.com

<b>Online Presence:</b>
* Programming BLOG: http://www.youlikeprogramming.com
* LinkedIn: http://www.linkedin.com/in/joshuadburns
* Stack Overflow: http://stackoverflow.com/users/253254/joshua-burns

<b>Background</b>
* Co-Founder and Director of Technology at Message In Action (http://www.messageinaction.com).
* Specializes in large-scale, high performance eCommerce and inventory management systems.
* Entrepreneur, Strategist, Political Enthusiast and Project Manager.

<b>Qualifications</b>
* 5+ years experience in Project Management
* 15+ years of programming experience including PHP, Python and Javascript.
* Intimate working knowledge of PostgreSQL, Microsoft SQL Server, MySQL and MongoDB database back-ends.

Quick Start Guide / Tutorial
============================

<b>Instantiate a new PostgreSQL connection</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class

// Pass a string of connection parameters as described in here: http://php.net/manual/en/function.pg-connect.php
$params = "host=localhost port=5432 dbname=my_db user=postgres password=my_pass options='--client_encoding=UTF8'";
$phpg = new PHPG('My DB', $params);

// Pass an associative array of connection parameters:
$params = array(
  'host' => 'localhost',
  'port' => '5432',
  'dbname' => 'my_db',
  'user' => 'postgres',
  'password' => 'my_pass',
  'options' => "'--client_encoding=UTF8'"
);
$phpg = new PHPG('My DB', $params);
```

<b>Retrieve an existing PostgreSQL conection (from any scope)</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class

// Initial instantiation of connection:
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params);

/**
/* From Another Scope, where $phpg database connection is not accessible...
**/

// Retrieve existing connection via it's connection alias:
$phpg = new PHPG('My DB');
```

<b>Create a new Cursor</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection

// Create a cursor from which we can execute queries:
$cursor = $phpg->cursor();
```

<b>Perform a query and iterate over the result set</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$cursor = $phpg->cursor(); // Create a cursor

// Perform the query
$cursor->execute("SELECT first_name, last_name FROM users ORDER BY last_name, first_name");

// Iterate over the result set using `while` syntax:
while($user = $cursor->iter()) {
  // do something
}

// Iterate over the result set using `foreach` syntax (not yet implemented):
foreach($cursor as $offset => $user) {
  // do something
}
```

<b>Perform a query, and retrieve the number of rows returned (used for SELECT and RETURNING statements)</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$cursor = $phpg->cursor(); // Create a cursor

// Perform the query:
$cursor->execute("SELECT first_name, last_name FROM users ORDER BY last_name, first_name");

// Retrieve the number of rows returned by the query:
$num_results = $cursor->rows_returned();
```

<b>Perform a query, and retrieve the number of rows affected (used for INSERT, UPDATE and DELETE statements)</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$cursor = $phpg->cursor(); // Create a cursor

// Perform the query:
$cursor->execute("DELETE FROM users WHERE last_name = 'Doe'");

// Retrieve the number of rows affected by the query:
$num_results = $cursor->rows_affected();
```

<b>Perform a query, and retrieve a single row from the result set</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$cursor = $phpg->cursor(); // Create a cursor

// Perform the query
$cursor->execute("SELECT first_name, last_name FROM users ORDER BY last_name, first_name");

// Grab a row (returns row 0, and advances the cursor from row 0 to row 1)
$user_1 = $cursor->fetchone();

// Grab another row (returns row 1, and advances the cursor from row 1 to row 2)
$user_2 = $cursor->fetchone();
```

<b>Commit one or more changes</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$first_cursor = $phpg->cursor(); // Create a cursor
$second_cursor = $phpg->cursor(); // Create another cursor

// Insert a couple records
$first_cursor->execute("INSERT INTO users (first_name, last_name) VALUES ('John', 'Smith')");
$second_cursor->execute("INSERT INTO users (first_name, last_name) VALUES ('Janet', 'Johnson')");

// Update a record
$first_cursor->execute("UPDATE users SET first_name = 'Jane' WHERE last_name = 'Doe'");

// Commit all actions, across all the database connection's cursors, up to this point.
$phpg->commit();
```

<i>Note: commit() will commit *all* INSERT, UPDATE, DELETE, ALTER, CREATE, DROP, etc actions made across all of the database connection's cursors since the last rollback() or commit() was performed.</i>

<b>Rollback one or more changes</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$first_cursor = $phpg->cursor(); // Create a cursor
$second_cursor = $phpg->cursor(); // Create another cursor

// Insert a couple records
$first_cursor->execute("INSERT INTO users (first_name, last_name) VALUES ('John', 'Smith')");
$second_cursor->execute("INSERT INTO users (first_name, last_name) VALUES ('Janet', 'Johnson')");

// Update a record
$first_cursor->execute("UPDATE users SET first_name = 'Jane' WHERE last_name = 'Doe'");

// Roll back all actions, across all the database connection's cursors, up to this point.
$phpg->rollback();
```

<i>Note: rollback() will rollback *all* INSERT, UPDATE, DELETE, ALTER, CREATE, DROP, etc actions made across all of the database connection's cursors since the last rollback() or commit() was performed</i>

<b>Reset the cursor's pointer to the beginning of the result set</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$cursor = $phpg->cursor(); // Create a cursor

// Perform a query
$cursor->execute("SELECT * FROM users");

$user1 = $cursor->fetchone(); // Grab the first row
$user2 = $cursor->fetchone(); // Grab the second row

// Reset the cursor, setting it's internal pointer back to row zero
$cursor->seek(1);

$user3 = $cursor->fetchone(); // Grab first row again
$user4 = $cursor->fetchone(); // Grab second row again
```

<b>Set the cursor's pointer to a specific offset</b>
```php
<?php
require('phpg.php'); // Contains PHPG Class
$params = "host=localhost dbname=my_db user=postgres password=my_pass";
$phpg = new PHPG('My DB', $params); // Instantiate a PostgreSQL connection
$cursor = $phpg->cursor(); // Create a cursor

// Perform a query
$cursor->execute("SELECT * FROM users");

$user1 = $cursor->fetchone(); // Grab the first row
$user2 = $cursor->fetchone(); // Grab the second row
$user3 = $cursor->fetchone(); // Grab the third row

// Set the cursor's pointer back to row 2 (offsets start at zero, so 0 = first row, 1 = second row etc)
$cursor->seek(1);

$user4 = $cursor->fetchone(); // Grab second row again
$user5 = $cursor->fetchone(); // Grab third row again
```
