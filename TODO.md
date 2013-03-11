- Implement "cursors", replacing query aliases. Ex:
```php
<?php

$phpg = new PHPG('My DB', $params);
$cursor = $phpg->cursor();
$cursor->execute("SELECT * FROM my_table");
while($row = $cursor->iter();
