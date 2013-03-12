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
