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

use Psr\Container\ContainerInterface;

/**
 * Class Container.
 */
final class Container implements ContainerInterface
{
    /**
     * When enabled, lazy binding allows you to pass classnames to your
     * register calls that will be resolved using reflection.
     */
    public const LAZY_BINDING = 1;
    /**
     * When enabled, extra lazy binding tries to resolve any class being fetched
     * from the container using reflection, even if it was not explicitly
     * registered using the register method.
     */
    public const EXTRA_LAZY_BINDING = 2;
    /**
     * When enabled, cache mode caches all the resolutions by default.
     */
    public const CACHE_MODE = 4;
    /**
     * When enabled, the container is registered in the container as a service
     * automatically under the PSR interface.
     */
    public const REGISTER_CONTAINER = 8;

    private int $flags;
    /**
     * @var array<string,ServiceDefinition>
     */
    private array $definitions;

    /**
     * Container constructor.
     */
    public function __construct(int $flags)
    {
        $this->flags = $flags;
        $this->definitions = [];
        if (($this->flags & self::REGISTER_CONTAINER) !== 0) {
            $this->register(ContainerInterface::class, $this);
        }
    }

    public static function boot(int $flags = 7): Container
    {
        return new self($flags);
    }

    /**
     * Creates a container from an array definition.
     *
     * You can use this to create service definitions "a la Pimple", just
     * passing an array of closures.
     *
     * @psalm-param array<string,callable(ContainerInterface): mixed> $factories
     */
    public static function fromArray(array $factories, int $flags = 7): Container
    {
        $container = new self($flags);
        foreach ($factories as $id => $factory) {
            $container->register($id, $factory);
        }

        return $container;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ContainerEntryNotFound
     * @throws ContainerError
     */
    public function get(string $id)
    {
        if ($this->hasDefinition($id)) {
            return $this->definitions[$id]->resolve($this);
        }
        if ($this->canReflect($id)) {
            return (ReflectionServiceFactory::forClass($id))($this);
        }

        throw new ContainerEntryNotFound($id);
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return $this->hasDefinition($id) || $this->canReflect($id);
    }

    /**
     * Registers a service.
     *
     * $abstract is usually the service name and $concrete the factory function.
     *
     * You can also pass an already built object to $concrete.
     *
     * If a class name is passed into $concrete and LAZY_BINDING is enabled, then
     * that class is created by using reflection and fetching arguments from
     * the container automatically.
     *
     * If nothing is passed into $concrete and a class name is passed into
     * $abstract, and LAZY_BINDING is enabled, then that class is resolved
     * using reflection.
     *
     * @psalm-param object|string|null|array|callable(ContainerInterface): mixed $concrete
     *
     * @param null|mixed $concrete
     */
    public function register(string $abstract, $concrete = null): Container
    {
        $cached = ($this->flags & self::CACHE_MODE) !== 0;
        $definition = $this->getOrCreateDefinition($abstract);
        if ($definition->hasFactory()) {
            throw new ContainerError(sprintf(
                'Service %s has already been registered',
                $abstract
            ));
        }
        $reflect = ($this->flags & self::LAZY_BINDING) !== 0;
        if ($reflect && is_string($concrete) && class_exists($concrete)) {
            $concrete = ReflectionServiceFactory::forClass($concrete);
        }
        if ($reflect && null === $concrete && class_exists($abstract)) {
            $concrete = ReflectionServiceFactory::forClass($abstract);
        }
        if (null === $concrete) {
            return $this;
        }
        if (!is_callable($concrete)) {
            $cached = false;
            $concrete = static fn () => $concrete;
        }
        $definition->setFactory($concrete);
        $definition->setCache($cached);

        return $this;
    }

    /**
     * Registers an inflector in the container.
     *
     * @psalm-param callable(mixed,ContainerInterface): void
     *
     * @return $this
     */
    public function inflect(string $id, callable $inflector): Container
    {
        $definition = $this->getOrCreateDefinition($id);
        $definition->addInflector($inflector);

        return $this;
    }

    /**
     * Registers a decorator in the container.
     *
     * Decorators must return an instance of the same type of the passed one.
     *
     * @psalm-param callable(mixed,ContainerInterface): mixed
     *
     * @return $this
     */
    public function decorate(string $id, callable $decorator): Container
    {
        $definition = $this->getOrCreateDefinition($id);
        $definition->addDecorator($decorator);

        return $this;
    }

    /**
     * @return $this
     */
    public function alias(string $id, string $alias): Container
    {
        $definition = $this->getOrCreateDefinition($id);
        $this->definitions[$alias] = $definition;

        return $this;
    }

    /**
     * Tags a group of services with another identifier.
     *
     * @param string ...$ids
     *
     * @return $this
     */
    public function tag(string $tag, string ...$ids): Container
    {
        $definition = $this->getOrCreateDefinition($tag);
        $definition->setFactory(static fn () => []);
        foreach ($ids as $id) {
            $definition->addDecorator(function (array $services, ContainerInterface $container) use ($id) {
                $services[] = $container->get($id);

                return $services;
            });
        }

        return $this;
    }

    /**
     * @param string ...$ids
     *
     * @return $this
     */
    public function refresh(string ...$ids): Container
    {
        foreach ($ids as $id) {
            $this->getOrCreateDefinition($id)->refresh();
        }

        return $this;
    }

    /**
     * @param callable(Container): void $provider
     */
    public function provide(callable $provider): Container
    {
        $provider($this);

        return $this;
    }

    private function getOrCreateDefinition(string $abstract): ServiceDefinition
    {
        $definition = $this->definitions[$abstract] ?? null;
        if (null !== $definition) {
            return $definition;
        }
        $definition = new ServiceDefinition(
            $abstract,
            ($this->flags & self::CACHE_MODE) !== 0,
        );
        $this->definitions[$abstract] = $definition;

        return $definition;
    }

    private function hasDefinition(string $id): bool
    {
        return array_key_exists($id, $this->definitions) && $this->definitions[$id]->hasFactory();
    }

    private function canReflect(string $id): bool
    {
        return ($this->flags & self::EXTRA_LAZY_BINDING) !== 0 && class_exists($id);
    }
}
