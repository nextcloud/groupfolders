<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Grégory Brousse <pro@gregory-brousse.fr>
 *
 * @author Grégory Brousse <pro@gregory-brousse.fr>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\GroupFolders\Mount;

use OC\Files\Cache\Propagator;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ILogger;

class GroupFolderPropagator extends Propagator {
	private $logger;
	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 *
	 * @var int
	 */
	private $folderId;


	/**
	 * @param \OCA\GroupFolders\Mount\GroupFolderStorage $storage
	 */
	public function __construct(\OC\Files\Storage\Storage $storage, IDBConnection $connection, ILogger $logger) {
		parent::__construct($storage, $connection);
		$this->connection = $connection;
		$this->folderId = $storage->getFolderId();
		$this->logger = $logger;
	}

	protected function getParents($pathOrigin) {
		$groupFolderPath = $this->getGroupFolderMountPoint($this->folderId);
		if(!strstr($pathOrigin,$groupFolderPath)){
			$path=str_replace('//','/',$groupFolderPath.'/'.$pathOrigin);
		}else{
			$path = $pathOrigin;
		}
		$parents = parent::getParents($path);
		$parentsGroupFolders = $this->getGroupFolderParents();
		$fullParents = array_merge($parents,$parentsGroupFolders);
		$this->logger->debug('GroupFolders::Propagator',[
			'pathOrigin'=>$pathOrigin,
			'path'=>$path,
			'groupFolderPath'=>$groupFolderPath,
			'parents'=>$parents,
			'parentsGroupFolders'=>$parentsGroupFolders,
			'fullParents'=>$fullParents,
		]);
		return $fullParents;
	}

	protected function getGroupFolderParents(){
		// Get folder mountpoint
		$query = $this->connection->getQueryBuilder();
		$query->select('mount_point')
			->from('group_folders')
			->where($query->expr()->eq('folder_id', $query->createNamedParameter($this->folderId)));
		$mountPoint = $query->execute()->fetchOne();
		$parentsMountPoints = [];
		while($mountPoint != '.'){
			$parentMountPoint = dirname($mountPoint);
			if($parentMountPoint != '.'){
				$parentsMountPoints[]=$parentMountPoint;
			}
			$mountPoint = $parentMountPoint;
		}
		$query->select('folder_id')
			->from('group_folders')
			->where($query->expr()->in('mount_point',$query->createNamedParameter($parentsMountPoints, IQueryBuilder::PARAM_STR_ARRAY)));

		$parentsIds = $query->execute()->fetchAll();
		return array_map(function($folderId){
			return $this->getGroupFolderMountPoint($folderId['folder_id']);
		}
		,$parentsIds);
	}

	protected function getGroupFolderMountPoint($groupFolderId){
		return '__groupfolders/'.$groupFolderId;
	}

}
