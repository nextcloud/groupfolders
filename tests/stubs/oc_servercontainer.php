<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC;

use OC\AppFramework\App;
use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Utility\SimpleContainer;
use OCP\AppFramework\QueryException;
use function explode;
use function strtolower;

/**
 * Class ServerContainer
 *
 * @package OC
 */
class ServerContainer extends SimpleContainer {
	/** @var DIContainer[] */
	protected $appContainers;

	/** @var string[] */
	protected $hasNoAppContainer;

	/** @var string[] */
	protected $namespaces;

	/**
	 * ServerContainer constructor.
	 */
	public function __construct()
 {
 }

	/**
	 * @param string $appName
	 * @param string $appNamespace
	 */
	public function registerNamespace(string $appName, string $appNamespace): void
 {
 }

	/**
	 * @param string $appName
	 * @param DIContainer $container
	 */
	public function registerAppContainer(string $appName, DIContainer $container): void
 {
 }

	/**
	 * @param string $appName
	 * @return DIContainer
	 * @throws QueryException
	 */
	public function getRegisteredAppContainer(string $appName): DIContainer
 {
 }

	/**
	 * @param string $namespace
	 * @param string $sensitiveNamespace
	 * @return DIContainer
	 * @throws QueryException
	 */
	protected function getAppContainer(string $namespace, string $sensitiveNamespace): DIContainer
 {
 }

	public function has($id, bool $noRecursion = false): bool
 {
 }

	/**
	 * @template T
	 * @param class-string<T>|string $name
	 * @return T|mixed
	 * @psalm-template S as class-string<T>|string
	 * @psalm-param S $name
	 * @psalm-return (S is class-string<T> ? T : mixed)
	 * @throws QueryException
	 * @deprecated 20.0.0 use \Psr\Container\ContainerInterface::get
	 */
	public function query(string $name, bool $autoload = true)
 {
 }

	/**
	 * @internal
	 * @param string $id
	 * @return DIContainer|null
	 */
	public function getAppContainerForService(string $id): ?DIContainer
 {
 }
}
