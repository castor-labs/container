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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

/**
 * Class ReflectionServiceFactory is an callable class that instantiates a
 * service class using reflection.
 */
class ReflectionServiceFactory
{
    private string $class;

    /**
     * ReflectionServiceFactory constructor.
     */
    private function __construct(string $class)
    {
        $this->class = $class;
    }

    /**
     * @return mixed
     */
    public function __invoke(ContainerInterface $container)
    {
        try {
            $class = new ReflectionClass($this->class);
        } catch (ReflectionException $e) {
            throw new ContainerError(sprintf('Could not reflect class %s', $this->class), 0, $e);
        }
        $constructor = $class->getConstructor();
        if (null === $constructor) {
            return new $this->class();
        }
        $args = [];
        $params = $constructor->getParameters();
        foreach ($params as $param) {
            if ($param->isVariadic()) {
                continue;
            }
            $args[] = $this->resolveParameter($container, $param);
        }

        try {
            return $class->newInstance(...$args);
        } catch (ReflectionException $e) {
            throw new ContainerError(sprintf('Error while instantiating class %s', $this->class), 0, $e);
        }
    }

    /**
     * @psalm-param class-string $class
     */
    public static function forClass(string $class): ReflectionServiceFactory
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf(
                'Argument 1 $class passed to %s must be an existing class',
                __METHOD__
            ));
        }

        return new self($class);
    }

    /**
     * @return mixed
     * @psalm-suppress UndefinedMethod
     * @psalm-suppress InvalidCatch
     */
    protected function resolveParameter(ContainerInterface $container, ReflectionParameter $param)
    {
        $type = $param->getType();
        $e = null;
        if (null !== $type && !$type->isBuiltin() && $container->has($type->getName())) {
            try {
                return $container->get($type->getName());
            } catch (NotFoundExceptionInterface $e) {
                // Continue if not found
            } catch (ContainerExceptionInterface $e) {
                throw new ContainerError(sprintf(
                    'Underlying container could not resolve service of type %s',
                    $type->getName()
                ), 0, $e);
            }
        }
        if ($param->isDefaultValueAvailable()) {
            try {
                return $param->getDefaultValue();
            } catch (ReflectionException $e) {
            }
        }
        if ($param->isOptional()) {
            return null;
        }
        if (null !== $type && $type->allowsNull()) {
            return null;
        }

        $error = sprintf(
            'Could not resolve argument #%s ($%s) of method %s::%s.',
            $param->getPosition(),
            $param->getName(),
            $param->getDeclaringClass()->getName(),
            $param->getDeclaringFunction()->getName()
        );

        if (null === $type) {
            throw new ContainerError($error.' Try type-hinting arguments to help reflection resolution or register a proper factory in the container.');
        }

        throw new ContainerError(sprintf(
            '%s Cannot find a service of id "%s" in the service container. Maybe you forgot to register it in the container?',
            $error,
            $param->getType()
        ), 0, $e);
    }
}
