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
 * Class ServiceDefinition.
 */
class ServiceDefinition
{
    private string $id;

    /**
     * @psalm-var null|callable(ContainerInterface): mixed
     */
    private $factory;
    private bool $cache;

    /**
     * @var mixed
     */
    private $instance;

    /**
     * @psalm-var array<int,callable(mixed, ContainerInterface): void>
     */
    private array $inflectors;

    /**
     * @psalm-var array<int,callable(mixed, ContainerInterface): mixed>
     */
    private array $decorators;

    /**
     * ServiceDefinition constructor.
     */
    public function __construct(string $id, bool $cache)
    {
        $this->id = $id;
        $this->cache = $cache;
        $this->inflectors = [];
        $this->decorators = [];
    }

    /**
     * @psalm-param callable(ContainerInterface): mixed $factory
     */
    public function setFactory(callable $factory): void
    {
        $this->factory = $factory;
    }

    public function setCache(bool $cache): void
    {
        $this->cache = $cache;
    }

    public function hasFactory(): bool
    {
        return null !== $this->factory;
    }

    /**
     * @return mixed
     */
    public function resolve(ContainerInterface $container)
    {
        if (null !== $this->instance) {
            return $this->instance;
        }
        if (null === $this->factory) {
            throw ContainerEntryNotFound::forService($this->id);
        }
        $instance = ($this->factory)($container);

        foreach ($this->inflectors as $inflector) {
            $inflector($instance, $container);
        }

        foreach ($this->decorators as $decorator) {
            $instance = $decorator($instance, $container);
        }

        if (true === $this->cache) {
            $this->instance = $instance;
        }

        return $instance;
    }

    public function refresh(): void
    {
        $this->instance = null;
    }

    /**
     * @psalm-param callable(mixed, ContainerInterface): void $inflector
     */
    public function addInflector(callable $inflector): void
    {
        $this->inflectors[] = $inflector;
    }

    /**
     * @psalm-param callable(mixed, ContainerInterface): mixed $decorator
     */
    public function addDecorator(callable $decorator): void
    {
        $this->decorators[] = $decorator;
    }
}
