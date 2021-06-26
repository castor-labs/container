Castor Container
================

A simple yet powerful Dependency Injection Container

```
composer require castor/container
```

## Basic Usage

```php
<?php

$container = Castor\Container::boot();
$container->register('foo', fn() => new Foo());
$foo = $container->get('foo');
```