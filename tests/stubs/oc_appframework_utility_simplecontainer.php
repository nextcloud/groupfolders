<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\AppFramework\Utility;

use ArrayAccess;
use Closure;
use OCP\AppFramework\QueryException;
use OCP\IContainer;
use Pimple\Container;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use function class_exists;

/**
 * SimpleContainer is a simple implementation of a container on basis of Pimple
 */
class SimpleContainer implements ArrayAccess, ContainerInterface, IContainer {
	public static bool $useLazyObjects = false;

	public function __construct()
    {
    }

	/**
	 * @template T
	 * @param class-string<T>|string $id
	 * @return ($id is class-string<T> ? T : mixed)
	 */
	#[\Override]
    public function get(string $id): mixed
    {
    }

	#[\Override]
    public function has(string $id): bool
    {
    }

	/**
	 * @inheritDoc
	 * @param list<class-string> $chain
	 */
	#[\Override]
    public function resolve(string $name, array $chain = []): mixed
    {
    }

	/**
	 * @inheritDoc
	 * @param list<class-string> $chain
	 */
	#[\Override]
    public function query(string $name, bool $autoload = true, array $chain = []): mixed
    {
    }

	#[\Override]
    public function registerParameter(string $name, mixed $value): void
    {
    }

	#[\Override]
    public function registerService(string $name, Closure $closure, bool $shared = true): void
    {
    }

	/**
	 * Shortcut for returning a service from a service under a different key,
	 * e.g. to tell the container to return a class when queried for an
	 * interface
	 * @param string $alias the alias that should be registered
	 * @param string $target the target that should be resolved instead
	 */
	#[\Override]
    public function registerAlias(string $alias, string $target): void
    {
    }

	protected function registerDeprecatedAlias(string $alias, string $target): void
    {
    }

	/**
	 * @param string $name
	 * @return string
	 */
	protected function sanitizeName($name)
    {
    }

	/**
	 * @deprecated 20.0.0 use \Psr\Container\ContainerInterface::has
	 */
	#[\Override]
    public function offsetExists($id): bool
    {
    }

	/**
	 * @deprecated 20.0.0 use \Psr\Container\ContainerInterface::get
	 * @return mixed
	 */
	#[\Override]
    #[\ReturnTypeWillChange]
    public function offsetGet($id)
    {
    }

	/**
	 * @deprecated 20.0.0 use \OCP\IContainer::registerService
	 */
	#[\Override]
    public function offsetSet($offset, $value): void
    {
    }

	/**
	 * @deprecated 20.0.0
	 */
	#[\Override]
    public function offsetUnset($offset): void
    {
    }
}
