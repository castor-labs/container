Castor Container
================

A simple yet powerful Dependency Injection Container

```
composer require castor/container
```

## Basic Usage

You can simply boot an instance of the container and everything comes ready to use.

You can register services factories (that are simply closures that create services)
using the `Castor\Container::register` method.

```php
<?php

$container = Castor\Container::boot();
$container->register('foo', fn() => new Foo());
$foo = $container->get('foo');
```

The booting defaults don't even need some registrations.

## Use Case Questions

### Can I fetch other services from the service factories?

Yes, you can. Every service factory closure takes an instance of 
`Psr\Container\ContainerInterface` as the only argument.

```php
<?php

use Psr\Container\ContainerInterface;

$container = Castor\Container::boot();
$container->register('foo', static function (ContainerInterface $container) {
    $bar = $container->get(Bar::class);
    return new Foo($bar);
});
$foo = $container->get('foo');
```

### Can I use service providers?

Yes, you can. A service provider in `castor/container` is simply a callable that
takes an instance of `Castor\Container` as the only argument.

```php
<?php

$container = Castor\Container::boot();
$container->provide(function (Castor\Container $container) {
    $container->register('foo', fn() => new Foo());
});
$foo = $container->get('foo');
```

It is recommended that these service providers are invokable classes, so you can
pass other dependencies to them like a typed configuration class. However, a
normal closure works exactly the same.

### Can I modify services?

Yes, you can. There are two ways of modifying services.

You can **inflect** a service fetched from the container. This means you can
change the state of that service without altering its reference.

Inflectors are registered as closures that take the inflected service and an
instance of `Psr\Container\ContainerInterface` as arguments.

```php
<?php

use Psr\Container\ContainerInterface;

$container = Castor\Container::boot();
$container->register('foo', fn() => new Foo());
$container->inflect('foo', function (Foo $foo, ContainerInterface $container) {
    $foo->setBar($container->get(Bar::class));
});
$foo = $container->get('foo');
```

You can also **decorate** services. This means you can modify a service and return
another reference as long as it complies with the [Liskov Substitution principle](https://en.wikipedia.org/wiki/Liskov_substitution_principle).

```php
<?php

use Psr\Container\ContainerInterface;

$container = Castor\Container::boot();
$container->register('foo', fn() => new Foo());
$container->inflect('foo', function (Foo $foo, ContainerInterface $container) {
    return new FooBar($foo, $container->get(Bar::class));
});
$foo = $container->get('foo'); // instance of FooBar
```

### What happens if I modify a service that has not been registered?

That is not a problem. You can register inflectors and decorators for services even
if they are not been registered then by another provider or the container itself.

If an actual factory for a service is not registered, the service technically does
not exist in the container even when there is a service definition containing
inflectors or decorators in it. 

It is important to note that inflectors and decorators are called in the order
in which they were registered in the container. So, if you have service
providers that need to register inflectors or decorators in a particular order,
is up to you to call them in the correct order. This is not so much a problem
with inflectors as it is with decorators, since you might want to build an
object tree in a specific order.

### Can I be lazy and use reflection?

Yes you can. The `Castor\Container::boot` method takes an integer as an argument.
This integer is a combination of some flags. All the available flags are public
constants in the `Castor\Container` class and they are properly documented. 

There are two container flags that handle reflection.

When passed, the `Castor\Container::LAZY_BINDING` flag (1) instructs the container
to resolve class names passed to the `Castor\Container::register` method using
reflection. You can do this just by passing a concrete implementation or by
binding an abstraction to a concrete implementation.

```php
<?php

$container = Castor\Container::boot();
// Foo will be instantiated using reflection when a service Foo is requested.
$container->register(Foo::class);
// Foo will be instantiated when a FooInterface service is requested.
$container->register(FooInterface::class, Foo::class);
```

The other flag that allows you to use reflection is the
`Castor\Container::EXTRA_LAZY_BINDING` (2). When passed, this flag instructs the
container to attempt to resolve any service requested that has a class name, even
when `Castor\Container::register` has not been explicitly called.

```php
<?php

$container = Castor\Container::boot();
// Foo will be automatically instantiated using reflection.
$container->get(Foo::class);
```

As with every service container that uses reflection, type information is required
in your constructors for the container to be able to figure out which services
can be injected. When the container cannot resolve these services, then a 
`ContainerError` exception will be thrown.

### Can I disable reflection?

Yes. You can simply instantiate the container with the `$flag` argument not
containing the reflection flags.

```php
<?php

$container = Castor\Container::boot(4); // This flag only enables caching.

```

### Can I alias services?

Yes, you can. Simply call the `Castor\Container::alias` method with the service
name, and the corresponding alias.

```php
<?php

$container = Castor\Container::boot();
$container->register(Foo::class);
$container->alias(Foo::class, 'foo');
```

### Can I specify tags for services?

Yes, you can. Simply call the `Castor\Container::tag` method with the tag
name, and the services you would like to tag.

```php
<?php

$container = Castor\Container::boot();
$container->register(Foo::class);
$container->tag('dummy_services', Foo::class, Bar::class);
```

Tags are fetched from the container as an array.

### Can I cache some services?

Yes, by default all services are cached. This means that once the factory is
called you will get the same instance (reference) every time you call the
`Castor\Container::get` method.

You can disable this behaviour by not passing the 
`Castor\Container::CACHE_MODE` (4) when creating the container.

At the moment, it is not possible to configure caching on a per-service
basis.