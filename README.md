# Async & Await for PHP

[![Build Status](https://travis-ci.org/logicalsteps/async.svg?branch=master)](https://travis-ci.org/logicalsteps/async)
[![Code Coverage](https://scrutinizer-ci.com/g/logicalsteps/async/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/logicalsteps/async/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/logicalsteps/async/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/logicalsteps/async/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/logicalsteps/async/v/stable.svg)](https://packagist.org/packages/logicalsteps/async)
[![Total Downloads](https://poser.pugx.org/logicalsteps/async/downloads.svg)](https://packagist.org/packages/logicalsteps/async)
[![License](https://poser.pugx.org/logicalsteps/async/license.svg)](https://packagist.org/packages/logicalsteps/async)

Simplify your asynchronous code and make it readable like synchronous code. It works similar to Async and await in other 
languages such as JavaScript and C#

It can be used standalone with callback functions. It also works with the promise interface of the following frameworks
 - [ReactPHP](https://github.com/reactphp/promise)
 - [Amp](https://amphp.org/amp/promises/)
 - [Guzzle](https://github.com/guzzle/promises)
 - [Httplug](https://github.com/php-http/promise)
 

It can do cooperative multitasking with the event loop interfaces of the following frameworks
 - [ReactPHP](https://github.com/reactphp/event-loop)
 - [Amp](https://amphp.org/amp/event-loop/)

## Installation

Async can be installed with [Composer](http://getcomposer.org)
by adding it as a dependency to your project's composer.json file. It can be done using the following command.

```bash
composer require logicalsteps/async
```

Please refer to [Composer's documentation](https://github.com/composer/composer/blob/master/doc/00-intro.md#introduction)
for more detailed installation and usage instructions.

## Usage

Consider the following example
 
```php
<?php
require __DIR__ . '/../vendor/autoload.php';
use Web3\Web3; //installed with `composer require sc0vu/web3.php` on the commandline

function balance($accountNumber)
{
    $web3 = new Web3('http://localhost:8545');
    $eth = $web3->eth;
    $eth->accounts(function ($error, $result) use ($eth, $accountNumber) {
        if ($error) {
            return;
        }
        $accounts = $result;
        $eth->getBalance($accounts[$accountNumber], function ($error, $result) {
            if ($error) {
                return;
            }
            var_export((int)$result->value);
        });
    });
}

balance(0);
```

If it is all synchronous, our function will simply be

```php
function balance($accountNumber)
{
    $web3 = new Web3('http://localhost:8545');
    $eth = $web3->eth;
    $accounts = $eth->accounts();
    $balance = $eth->getBalance($accounts[$accountNumber]);
    return (int)$balance->value;
}

var_export(balance(0));

```

With Async library it can be written as the following

```php
use LogicalSteps\Async\Async;
use Web3\Web3;

function balance($accountNumber)
{

    $web3 = new Web3('http://localhost:8545');
    $eth = $web3->eth;
    $accounts = yield [$eth, 'accounts'];
    $balance = yield [$eth, 'getBalance', $accounts[$accountNumber]];
    $value = (int)$balance->value;
    return $value;
}

$async = new Async();
$async->await(balance(0))->then('var_export');
```

Now the code is clean and looks like synchronous, but runs asynchronously for better performance getting us 
best of both worlds :) 

For more examples and integration with the frameworks take a look at [examples folder](examples)