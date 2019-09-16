# multipart-handler
This class became necessary during the development of a REST API. This class parses HTTP multipart/form-data request bodies and try to populate `$_FILES` and `$_POST` as PHP would do. The idea was to be able to handle very big files with no memory overflow.  The `php://input` stream is read in chunks of 8192 bytes that are written directly to disk, so very small memory footprint.

At this time there is no check on '`post_max_size`', '`upload_max_filesize`', '`max_file_uploads`' and '`max_input_vars`'. Maybe in a future release this checks will be done to be compliant with server configuration.

##### Installation

You can install the package via composer:

```
composer require djiele/multipart dev-master
```

##### Simple usage


```php
require_once __DIR__'./vendor/autoload.php';
use Djiele\Http\MultipartHandler;
$mh = new MultipartHandler();
$mh->populateGlobals();
echo var_export($_POST, true), PHP_EOL;
echo var_export($_FILES, true), PHP_EOL;
```

##### Using the package with frameworks

just instanciate the class at the very beginning of the framework boot.  You have to make sure that the class object will be destroyed at the very end of script or application. Otherwise the uploaded files will be deleted  by the destructor before your script can process them. For frameworks like Laravel or Symfony i usually instanciate the handler as a public member of 'app' and 'kernel' respectively.

<u>Sample code for Laravel</u>

in public/index.php, just after the app is created you can add this little code:


```php

|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

### multipartHandler call if request is PUT, PATCH, OR DELETE
if (
	isset($_SERVER['REQUEST_METHOD']) 
	&& in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH', 'DELETE'])
) {
    $app->multipartHandler = new Djiele\Http\MultipartHandler();
    $app->multipartHandler->populateGlobals();
}
###
```

At this step the `$_POST` and `$_FILES` super globals are a populated as would PHP with regular POST method. The framework can then populate the Request->files collection and gives you the ability to use UploadedFile objects as you would do normally. Note that you can not use the 'move' method since it make use of the 'move_uploaded_file' native function of PHP which checks that files where uploaded during POST request. You can to use  UploadedFile::store() or UploadedFile::storeAs() and then delete the temporary file.

```<?php

$request->file('id')->storeAs('somedir', $uploadedFile->getClientOriginalName());
unlink($uploadedFile->getPathName());
```

<u>Sample code for symfony</u>

in the file public/index.php just after the kernel is created add the same little code:

```php
use Symfony\Component\Debug\Debug;
use Symfony\Component\HttpFoundation\Request;
use Djiele\Http\MultipartHandler;

require dirname(__DIR__).'/config/bootstrap.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

### multipartHandler call if request is PUT, PATCH, OR DELETE
if (
    isset($_SERVER['REQUEST_METHOD']) 
    && in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH', 'DELETE'])
) {
    $kernel->multipartHandler = new MultipartHandler();
    $kernel->multipartHandler->populateGlobals();
}
###

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
```

Same remarks and notices as Laravel


Et voilà!

