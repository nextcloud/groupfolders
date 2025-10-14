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
	 * @return T|mixed
	 * @psalm-template S as class-string<T>|string
	 * @psalm-param S $id
	 * @psalm-return (S is class-string<T> ? T : mixed)
	 */
	public function get(string $id): mixed
 {
 }

	public function has(string $id): bool
 {
 }

	public function resolve($name)
 {
 }

	public function query(string $name, bool $autoload = true)
 {
 }

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function registerParameter($name, $value)
 {
 }

	/**
	 * The given closure is call the first time the given service is queried.
	 * The closure has to return the instance for the given service.
	 * Created instance will be cached in case $shared is true.
	 *
	 * @param string $name name of the service to register another backend for
	 * @param Closure $closure the closure to be called on service creation
	 * @param bool $shared
	 */
	public function registerService($name, Closure $closure, $shared = true)
 {
 }

	/**
	 * Shortcut for returning a service from a service under a different key,
	 * e.g. to tell the container to return a class when queried for an
	 * interface
	 * @param string $alias the alias that should be registered
	 * @param string $target the target that should be resolved instead
	 */
	public function registerAlias($alias, $target): void
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
	public function offsetExists($id): bool
 {
 }

	/**
	 * @deprecated 20.0.0 use \Psr\Container\ContainerInterface::get
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
 public function offsetGet($id)
 {
 }

	/**
	 * @deprecated 20.0.0 use \OCP\IContainer::registerService
	 */
	public function offsetSet($offset, $value): void
 {
 }

	/**
	 * @deprecated 20.0.0
	 */
	public function offsetUnset($offset): void
 {
 }
}
