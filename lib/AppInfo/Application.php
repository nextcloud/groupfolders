<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\GroupFolders\AppInfo;

use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\Files\NotFoundException;

class Application extends App {

	public function __construct(array $urlParams = []) {
		parent::__construct('groupfolders', $urlParams);

		$container = $this->getContainer();
		$container->registerService(FolderManager::class, function (IAppContainer $c) {
			return new FolderManager($c->getServer()->getDatabaseConnection());
		});

		$container->registerService(MountProvider::class, function (IAppContainer $c) {
			$rootProvider = function () use ($c) {
				try {
					return $c->getServer()->getRootFolder()->get('__groupfolders');
				} catch (NotFoundException $e) {
					return $c->getServer()->getRootFolder()->newFolder('__groupfolders');
				}
			};

			return new MountProvider(
				$c->getServer()->getGroupManager(),
				$c->query(FolderManager::class),
				$rootProvider
			);
		});
	}

	public function register() {
		$container = $this->getContainer();

		$container->getServer()->getMountProviderCollection()->registerProvider($container->query(MountProvider::class));
	}
}

