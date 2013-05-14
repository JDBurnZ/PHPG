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
```

<b>Merge PHPG (Framework) and PHPG Utils (API) into single, multi-functional class</b>

<b>Create full working examples in separate files under "Examples" path.
