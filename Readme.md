# LinkedIn API client in PHP

[![CI Status](https://github.com/elninotech/Linkedin-API-Client/workflows/CI/badge.svg)](https://github.com/elninotech/Linkedin-API-Client/actions)
[![codecov](https://codecov.io/github/elninotech/LinkedIn-API-client/graph/badge.svg?token=MN6BEDGUY2)](https://codecov.io/github/elninotech/LinkedIn-API-client)
[![Latest Stable Version](https://poser.pugx.org/elninotech/linkedin-api-client/v)](https://packagist.org/packages/elninotech/linkedin-api-client)
[![Total Downloads](https://poser.pugx.org/elninotech/linkedin-api-client/downloads)](https://packagist.org/packages/elninotech/linkedin-api-client/stats)


A PHP library to handle authentication and communication with LinkedIn API. The library/SDK helps you to get an access
token and when authenticated it helps you to send API requests. You will not get *everything* for free though... You
have to read the [LinkedIn documentation][api-doc-core] to understand how you should query the API. 

To get an overview what this library actually is doing for you. Take a look at the authentication page from
the [API docs][api-doc-authentication].

## Features

Here is a list of features that might convince you to choose this LinkedIn client over some of our competitors'.

* Flexible and easy to extend
* Developed with modern PHP standards
* Not developed for a specific framework. 
* Handles the authentication process
* Respects the CSRF protection

## Installation

**TL;DR**
```bash
composer require php-http/curl-client guzzlehttp/psr7 php-http/message elninotech/linkedin-api-client
```

This library does not have a dependency on Guzzle or any other library that sends HTTP requests. We use the awesome 
HTTPlug to achieve the decoupling. We want you to choose what library to use for sending HTTP requests. Consult this list 
of packages that support [php-http/client-implementation](https://packagist.org/providers/php-http/client-implementation) 
find clients to use. For more information about virtual packages please refer to 
[HTTPlug](http://docs.php-http.org/en/latest/httplug/users.html). Example:

```bash
composer require php-http/guzzle7-adapter
```

You do also need to install a PSR-7 implementation and a factory to create PSR-7 messages (PSR-17 whenever that is 
released). You could use Guzzles PSR-7 implementation and factories from php-http:

```bash
composer require guzzlehttp/psr7 php-http/message 
```

Now you may install the library by running the following:

```bash
composer require elninotech/linkedin-api-client
```

If you are updating from a previous version, make sure to read [the upgrade documentation](Upgrade.md).

### Finding the HTTP client (optional) 

The LinkedIn client needs to know what library you are using to send HTTP messages. You could provide an instance of 
HttpClient and MessageFactory or you could fall back to auto discovery. Below is an example of where you provide a Guzzle7
instance.

```php
$linkedIn=new Elnino\LinkedIn\LinkedIn('app_id', 'app_secret');
$linkedIn->setHttpClient(new \Http\Adapter\Guzzle7\Client());
$linkedIn->setHttpMessageFactory(new Http\Message\MessageFactory\GuzzleMessageFactory());

```

## Usage

In order to use this API client (or any other LinkedIn clients), you have to [register your application][register-app]
with LinkedIn to receive an API key. Once you've registered your LinkedIn app, you will be provided with
an *API Key* and *Secret Key*.

### LinkedIn login

This example below is showing how to login with LinkedIn.

```php
<?php

/**
 * This demonstrates how to authenticate with LinkedIn and send api requests
 */

/*
 * First you need to make sure you've used composers auto load. You have is probably 
 * already done this before. You usually don't bother..
 */
//require_once "vendor/autoload.php";

$linkedIn=new Elnino\LinkedIn\LinkedIn('client_id', 'client_secret');

if ($linkedIn->isAuthenticated()) {
    //we know that the user is authenticated now. Start query the API
    $user=$linkedIn->get('/v2/me/?projection=(id,firstName,lastName)');
    echo "Welcome ".$user['firstName'];

    exit();
} elseif ($linkedIn->hasError()) {
    echo "User canceled the login.";
    exit();
}

//if not authenticated
$url = $linkedIn->getLoginUrl();
echo "<a href='$url'>Login with LinkedIn</a>";

```

### How to post on LinkedIn wall

The example below shows how you can post on a users wall. The access token is fetched from the database. 

```php
$linkedIn=new Elnino\LinkedIn\LinkedIn('app_id', 'app_secret');
$linkedIn->setAccessToken('access_token_from_db');

$options = array('json'=>
    array(
        'commentary' => 'Im testing the El Niño LinkedIn client! https://github.com/elninotech/Linkedin-API-client',
        'visibility' => array(
            'code' => 'anyone'
        )
    )
);

$result = $linkedIn->post('rest/posts', $options);

var_dump($result);

// Prints: 
// array (size=2)
//   'updateKey' => string 'UPDATE-01234567-0123456789012345678' (length=35)
//   'updateUrl' => string 'https://www.linkedin.com/updates?discuss=&scope=01234567&stype=M&topic=0123456789012345678&type=U&a=mVKU' (length=104)

```

## Configuration

### The api options

The third parameter of `LinkedIn::api` is an array with options. Below is a table of array keys that you may use. 

| Option name | Description
| ----------- | -----------
| body | The body of a HTTP request. Put your json string here. 
| headers | This is HTTP headers to the request
| json | This is an array with json data that will be encoded to a json string. 
| response_data_type | To override the response format for one request 
| query | This is an array with query parameters

### Understanding response data type

The data type returned from `LinkedIn::api` can be configured. You may use the third constructor argument, the
`LinkedIn::setResponseDataType` or as an option for `LinkedIn::api`

```php
// By constructor argument
$linkedIn=new Elnino\LinkedIn\LinkedIn('app_id', 'app_secret', 'array');

// By setter
$linkedIn->setResponseDataType('string');

// Set format for just one request
$linkedIn->get('/v2/me/?projection=(id,firstName,lastName)', array('response_data_type'=>'psr7'));

```

Below is a table that specifies what the possible return data types are when you call `LinkedIn::api`.

| Type | Description
| ------ | ------------
| array | An assosiative array. This can only be used with the `json` format.
| psr7 | A PSR7 response.
| stream | A file stream.
| string | A plain old string.


### Use different Session classes

You might want to use another storage than the default `SessionStorage`. If you are using Laravel
you are more likely to inject the `IlluminateSessionStorage`.  
```php
$linkedIn=new Elnino\LinkedIn\LinkedIn('app_id', 'app_secret');
$linkedIn->setStorage(new IlluminateSessionStorage());
```

You can inject any class implementing `DataStorageInterface`. You can also inject different `UrlGenerator` classes.

### Using different scopes

If you want to define special scopes when you authenticate the user you should specify them when you are generating the 
login url. If you don't specify scopes LinkedIn will use the default scopes that you have configured for the app.  

```php
$scope = 'r_fullprofile,r_emailaddress,w_share';
//or 
$scope = array('rw_groups', 'r_contactinfo', 'r_fullprofile', 'w_messages');

$url = $linkedIn->getLoginUrl(array('scope'=>$scope));
echo "<a href='$url'>Login with LinkedIn</a>";
```

### Special thanks

This repo was originally created by [Thomas Nyholm from Happyr][forked-from].

[register-app]: https://www.linkedin.com/developers/apps
[api-doc-authentication]: https://learn.microsoft.com/en-us/linkedin/shared/authentication/authentication
[api-doc-core]: https://learn.microsoft.com/en-us/linkedin/shared/api-guide/concepts
[forked-from]: https://github.com/Happyr/LinkedIn-API-client
