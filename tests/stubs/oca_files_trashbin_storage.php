<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\Files_Trashbin;

use OC\Files\Filesystem;
use OC\Files\Storage\Wrapper\Wrapper;
use OCA\Files_Trashbin\Events\MoveToTrashEvent;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCP\App\IAppManager;
use OCP\Encryption\Exceptions\GenericEncryptionException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;

class Storage extends Wrapper {
	/**
	 * Storage constructor.
	 * @param array $parameters
	 */
	public function __construct($parameters, private ?ITrashManager $trashManager = null, private ?IUserManager $userManager = null, private ?LoggerInterface $logger = null, private ?IEventDispatcher $eventDispatcher = null, private ?IRootFolder $rootFolder = null, private ?IRequest $request = null)
    {
    }

	#[\Override]
    public function unlink(string $path): bool
    {
    }

	#[\Override]
    public function rmdir(string $path): bool
    {
    }

	/**
	 * check if it is a file located in data/user/files only files in the
	 * 'files' directory should be moved to the trash
	 */
	protected function shouldMoveToTrash(string $path): bool
    {
    }

	/**
	 * get move to trash event
	 *
	 * @param Node $node
	 * @return MoveToTrashEvent
	 */
	protected function createMoveToTrashEvent(Node $node): MoveToTrashEvent
    {
    }

	/**
	 * Setup the storage wrapper callback
	 */
	public static function setupStorage(): void
    {
    }

	public function getMountPoint()
    {
    }

	#[\Override]
    public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool
    {
    }

	protected function disableTrash(): void
    {
    }

	protected function enableTrash(): void
    {
    }
}
