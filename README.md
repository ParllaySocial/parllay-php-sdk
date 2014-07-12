### Parllay SDK for PHP v1.0

This repository contains the open source PHP SDK that allows you to access Parllay Platform from your PHP app.

----

Usage
-----

The examples are a good place to start. The minimal you'll need to have is:
```php
require_once 'parllay.php';

$parllay = new Parllay(array(
  'appId' => 'YOUR_APP_ID',
  'secret' => 'YOUR_APP_SECRET'
));

// To make an API call

$result = $parllay->api("/businesses");
```

Docs
----
Visit docs [here](https://github.com/parllaysocial/parllay-php-sdk/wiki)

Request for testing
-------------------

Currently, The parllay app is only open to our partner, if you want to visit our platform data, please go to [Parllay](http://parllay.com) and leave your request.
