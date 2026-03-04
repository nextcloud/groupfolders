<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Files\Mount;

use OC\Files\Filesystem;
use OC\Files\Storage\Storage;
use OC\Files\Storage\StorageFactory;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\Server;
use Psr\Log\LoggerInterface;

class MountPoint implements IMountPoint {
	/** @var IStorage|null $storage */
	protected $storage = null;
	/** @var class-string<IStorage> */
	protected string $class;
	protected ?string $storageId = null;
	protected ?int $numericStorageId = null;
	protected ?int $rootId = null;

	/**
	 * Configuration options for the storage backend
	 */
	protected array $arguments = [];
	protected string $mountPoint;

	/**
	 * Mount specific options
	 */
	protected array $mountOptions = [];
	protected string $mountProvider;

	/**
	 * @param IStorage|class-string<IStorage> $storage
	 * @param array $arguments (optional) configuration for the storage backend
	 * @param ?array $mountOptions mount specific options
	 * @param ?int $mountId
	 * @param ?string $mountProvider
	 * @throws \Exception
	 */
	public function __construct(string|IStorage $storage, string $mountpoint, ?array $arguments = null, ?IStorageFactory $loader = null, ?array $mountOptions = null, protected ?int $mountId = null, ?string $mountProvider = null)
 {
 }

	/**
	 * get complete path to the mount point, relative to data/
	 *
	 * @return string
	 */
	public function getMountPoint()
 {
 }

	/**
	 * Sets the mount point path, relative to data/
	 *
	 * @param string $mountPoint new mount point
	 */
	public function setMountPoint($mountPoint)
 {
 }

	/**
	 * @return IStorage|null
	 */
	public function getStorage()
 {
 }

	/**
	 * @return string|null
	 */
	public function getStorageId()
 {
 }

	/**
	 * @return int
	 */
	public function getNumericStorageId()
 {
 }

	/**
	 * @param string $path
	 * @return string
	 */
	public function getInternalPath($path)
 {
 }

	/**
	 * @param callable $wrapper
	 */
	public function wrapStorage($wrapper)
 {
 }

	/**
	 * Get a mount option
	 *
	 * @param string $name Name of the mount option to get
	 * @param mixed $default Default value for the mount option
	 * @return mixed
	 */
	public function getOption($name, $default)
 {
 }

	/**
	 * Get all options for the mount
	 *
	 * @return array
	 */
	public function getOptions()
 {
 }

	/**
	 * Get the file id of the root of the storage
	 *
	 * @return int
	 */
	public function getStorageRootId()
 {
 }

	public function getMountId()
 {
 }

	public function getMountType()
 {
 }

	public function getMountProvider(): string
 {
 }
}
