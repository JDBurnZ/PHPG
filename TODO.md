<b>Implement "cursors", replacing query aliases</b>
* Create PHPG_Cursor Class

Example:

```php
<?php
$phpg = new PHPG('My DB', $params);
$cursor = $phpg->cursor();
$cursor->execute("SELECT * FROM my_table");
while($row = $cursor->iter()) {
  // ...
}
```

<b>Migrate methods to PHPG_Cursor class</b>
* execute($query)
* rollback()
* commit()
* reset()
* seek($offset)
* fetchone($offset = Null)
* fetchall()
* fetchmany($limit, $offset = Null) <-- does not yet exist.
* iter()
* rowcount()
* rows_affected()

<b>Implement foreach-style iterations</b>

Example:

```php
<?php
$phpg = new PHPG('My DB', $params);
$cursor = $phpg->cursor();
$cursor->execute("SELECT * FROM my_table");
foreach($cursor as $offset => $row) {
  // ...
}
