<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCA\GroupFolders\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\GroupFolders\Folder\FolderManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class CircleDestroyedEventListener implements IEventListener {

	public function __construct(
		private FolderManager $folderManager,
	) {
	}


	public function handle(Event $event): void {
		if (!$event instanceof CircleDestroyedEvent) {
			return;
		}

		$circle = $event->getCircle();
		$this->folderManager->deleteCircle($circle->getSingleId());
	}
}
