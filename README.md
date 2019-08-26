# multipart-handler
Parse HTTP multipart/form-data request body. Useful for HTTP PATCH method since PHP doesn't natively handle it yet.

Simple usage:

`
require_once __DIR__'./vendor/autoload.php';

use djiele\http\MultipartHandler;

$mh = new MultipartHandler();

$mh->populateGlobals();

echo var_export($_POST), PHP_EOL;

echo var_export($_FILES), PHP_EOL;
`

Et voilà!

