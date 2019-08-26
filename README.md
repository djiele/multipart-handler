# multipart-handler
Parse HTTP multipart/form-data request body. Useful for HTTP PATCH method since PHP doesn't natively handle it yet.

Simple usage:


```php
require_once __DIR__'./vendor/autoload.php';
use djiele\http\MultipartHandler;
$mh = new MultipartHandler();
$mh->populateGlobals();
echo var_export($_POST, true), PHP_EOL;
echo var_export($_FILES, true), PHP_EOL;
```

Et voilà!

