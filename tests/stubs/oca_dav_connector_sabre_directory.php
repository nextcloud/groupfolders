<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\Connector\Sabre;

use OC\Files\Mount\MoveableMount;
use OC\Files\View;
use OCA\DAV\AppInfo\Application;
use OCA\DAV\Connector\Sabre\Exception\FileLocked;
use OCA\DAV\Connector\Sabre\Exception\Forbidden;
use OCA\DAV\Connector\Sabre\Exception\InvalidPath;
use OCA\DAV\Storage\PublicShareWrapper;
use OCP\App\IAppManager;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\ForbiddenException;
use OCP\Files\InvalidPathException;
use OCP\Files\Mount\IMountManager;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\StorageNotAvailableException;
use OCP\IL10N;
use OCP\IRequest;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Server;
use OCP\Share\IManager as IShareManager;
use Psr\Log\LoggerInterface;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Locked;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAV\IFile;
use Sabre\DAV\INode;

class Directory extends Node implements \Sabre\DAV\ICollection, \Sabre\DAV\IQuota, \Sabre\DAV\IMoveTarget, \Sabre\DAV\ICopyTarget {
	/**
	 * Sets up the node, expects a full path name
	 */
	public function __construct(View $view, FileInfo $info, private ?CachingTree $tree = null, ?IShareManager $shareManager = null)
 {
 }

	/**
	 * Creates a new file in the directory
	 *
	 * Data will either be supplied as a stream resource, or in certain cases
	 * as a string. Keep in mind that you may have to support either.
	 *
	 * After successful creation of the file, you may choose to return the ETag
	 * of the new file here.
	 *
	 * The returned ETag must be surrounded by double-quotes (The quotes should
	 * be part of the actual string).
	 *
	 * If you cannot accurately determine the ETag, you should not return it.
	 * If you don't store the file exactly as-is (you're transforming it
	 * somehow) you should also not return an ETag.
	 *
	 * This means that if a subsequent GET to this new file does not exactly
	 * return the same contents of what was submitted here, you are strongly
	 * recommended to omit the ETag.
	 *
	 * @param string $name Name of the file
	 * @param resource|string $data Initial payload
	 * @return null|string
	 * @throws Exception\EntityTooLarge
	 * @throws Exception\UnsupportedMediaType
	 * @throws FileLocked
	 * @throws InvalidPath
	 * @throws \Sabre\DAV\Exception
	 * @throws \Sabre\DAV\Exception\BadRequest
	 * @throws \Sabre\DAV\Exception\Forbidden
	 * @throws \Sabre\DAV\Exception\ServiceUnavailable
	 */
	public function createFile($name, $data = null)
 {
 }

	/**
	 * Creates a new subdirectory
	 *
	 * @param string $name
	 * @throws FileLocked
	 * @throws InvalidPath
	 * @throws \Sabre\DAV\Exception\Forbidden
	 * @throws \Sabre\DAV\Exception\ServiceUnavailable
	 */
	public function createDirectory($name)
 {
 }

	/**
	 * Returns a specific child node, referenced by its name
	 *
	 * @param string $name
	 * @param FileInfo $info
	 * @return \Sabre\DAV\INode
	 * @throws InvalidPath
	 * @throws \Sabre\DAV\Exception\NotFound
	 * @throws \Sabre\DAV\Exception\ServiceUnavailable
	 */
	public function getChild($name, $info = null, ?IRequest $request = null, ?IL10N $l10n = null)
 {
 }

	/**
	 * Returns an array with all the child nodes
	 *
	 * @return \Sabre\DAV\INode[]
	 * @throws \Sabre\DAV\Exception\Locked
	 * @throws Forbidden
	 */
	public function getChildren()
 {
 }

	/**
	 * Checks if a child exists.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function childExists($name)
 {
 }

	/**
	 * Deletes all files in this directory, and then itself
	 *
	 * @return void
	 * @throws FileLocked
	 * @throws \Sabre\DAV\Exception\Forbidden
	 */
	public function delete()
 {
 }

	/**
	 * Returns available diskspace information
	 *
	 * @return array
	 */
	public function getQuotaInfo()
 {
 }

	/**
	 * Moves a node into this collection.
	 *
	 * It is up to the implementors to:
	 *   1. Create the new resource.
	 *   2. Remove the old resource.
	 *   3. Transfer any properties or other data.
	 *
	 * Generally you should make very sure that your collection can easily move
	 * the move.
	 *
	 * If you don't, just return false, which will trigger sabre/dav to handle
	 * the move itself. If you return true from this function, the assumption
	 * is that the move was successful.
	 *
	 * @param string $targetName New local file/collection name.
	 * @param string $fullSourcePath Full path to source node
	 * @param INode $sourceNode Source node itself
	 * @return bool
	 * @throws BadRequest
	 * @throws ServiceUnavailable
	 * @throws Forbidden
	 * @throws FileLocked
	 * @throws \Sabre\DAV\Exception\Forbidden
	 */
	public function moveInto($targetName, $fullSourcePath, INode $sourceNode)
 {
 }


	public function copyInto($targetName, $sourcePath, INode $sourceNode)
 {
 }

	public function getNode(): Folder
 {
 }
}
