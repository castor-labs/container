<?php

declare(strict_types=1);

/**
 * @project Castor Container
 * @link https://github.com/castor-labs/container
 * @package castor/container
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2021 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Class ReflectionFactoryTest.
 *
 * @internal
 * @coversNothing
 */
class ReflectionFactoryTest extends TestCase
{
    public function testItResolvesNoConstructor(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $factory = ReflectionServiceFactory::forClass(Foo::class);
        $instance = $factory($container);
        self::assertInstanceOf(Foo::class, $instance);
    }

    public function testItResolvesFromContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))
            ->method('has')
            ->withConsecutive([Foo::class], ['boo'], ['num'], ['check'])
            ->willReturnOnConsecutiveCalls(true, false, false, false)
        ;
        $container->expects(self::once())
            ->method('get')
            ->with(Foo::class)
            ->willReturn(new Foo())
        ;
        $factory = ReflectionServiceFactory::forClass(Bar::class);
        $instance = $factory($container);
        self::assertInstanceOf(Bar::class, $instance);
    }

    public function testItFailsWhenNotFoundInContainer(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('has')
            ->with(Foo::class)
            ->willReturn(true)
        ;
        $container->expects(self::once())
            ->method('get')
            ->with(Foo::class)
            ->willThrowException(ContainerEntryNotFound::forService(Foo::class))
        ;
        $factory = ReflectionServiceFactory::forClass(Bar::class);
        $this->expectException(ContainerError::class);
        $factory($container);
    }

    public function testItFailsWhenNoInfoProvided(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $factory = ReflectionServiceFactory::forClass(DefaultValue::class);
        $this->expectException(ContainerError::class);
        $factory($container);
    }

    public function testItIgnoresOptionalClosure(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('has')
            ->with('closure')
            ->willReturn(false)
        ;
        $factory = ReflectionServiceFactory::forClass(OptionalClosure::class);
        $instance = $factory($container);
        self::assertInstanceOf(OptionalClosure::class, $instance);
    }

    public function testItThrowsExceptionOnRequiredClosure(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('has')
            ->with('closure')
            ->willReturn(false)
        ;
        $factory = ReflectionServiceFactory::forClass(RequiredClosure::class);
        $this->expectException(ContainerError::class);
        $factory($container);
    }
}

class Foo
{
}

class Bar
{
    private Foo $foo;
    private ?string $boo;
    private ?int $num;
    private bool $check;
    private $meme;

    public function __construct(Foo $foo, ?string $boo, int $num = null, bool $check = true, ...$meme)
    {
        $this->foo = $foo;
        $this->boo = $boo;
        $this->num = $num;
        $this->check = $check;
        $this->meme = $meme;
    }
}

class DefaultValue
{
    private $hello;

    public function __construct($hello)
    {
        $this->hello = $hello;
    }
}

class OptionalClosure
{
    public function __construct(Closure $closure = null)
    {
    }
}

class RequiredClosure
{
    public function __construct(Closure $closure)
    {
    }
}
