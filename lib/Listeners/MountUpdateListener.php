<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Listeners;

use OC\Files\Cache\Cache;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\CircleMemberAddedEvent;
use OCA\Circles\Events\CircleMemberRemovedEvent;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\GroupFolders\AppInfo\Application;
use OCA\GroupFolders\Config\ConfigLexicon;
use OCA\GroupFolders\Event\GroupFolderCircleAddedEvent;
use OCA\GroupFolders\Event\GroupFolderCircleRemovedEvent;
use OCA\GroupFolders\Event\GroupFolderDeletedEvent;
use OCA\GroupFolders\Event\GroupFolderGroupAddedEvent;
use OCA\GroupFolders\Event\GroupFolderGroupRemovedEvent;
use OCA\GroupFolders\Folder\FolderDefinition;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Config\IUserConfig;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Cache\IFileAccess;
use OCP\Files\Config\IUserMountCache;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IUser;
use OCP\IUserManager;

/**
 * Listen to various events and update mounts for affected users
 *
 * @template-implements IEventListener<BeforeGroupDeletedEvent|CircleDestroyedEvent|CircleMemberAddedEvent|CircleMemberRemovedEvent|UserAddedEvent|UserRemovedEvent|GroupFolderCircleAddedEvent|GroupFolderGroupAddedEvent|GroupFolderCircleRemovedEvent|GroupFolderGroupRemovedEvent|GroupFolderDeletedEvent>
 */
readonly class MountUpdateListener implements IEventListener {
	public function __construct(
		private FolderManager $folderManager,
		private IUserManager $userManager,
		private IUserMountCache $userMountCache,
		private IFileAccess $fileAccess,
		private IUserConfig $userConfig,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if ($event instanceof GroupFolderDeletedEvent) {
			$this->userMountCache->removeMountsForRoot($event->getFolder()->rootId);
		} else {
			$users = $this->getAffectedUsers($event);
			if ($this->isMountRemove($event)) {
				// since the user might still have access to the groupfolder through a different route than the removed one
				// we just mark them as requiring a re-check
				foreach ($users as $user) {
					$this->userConfig->setValueBool($user->getUID(), Application::APP_ID, ConfigLexicon::USER_NEEDS_MOUNT_REFRESH, true);
				}
			} else {
				foreach ($this->getFolders($event) as $folder) {
					$cacheEntry = $this->fileAccess->getByFileIdInStorage($folder->rootId, $folder->storageId);
					if ($cacheEntry) {
						foreach ($users as $user) {
							$this->userMountCache->addMount($user, $this->getFullMountPoint($folder, $user), $cacheEntry, MountProvider::class);
						}
					}
				}
			}
		}
	}

	private function isMountRemove(
		BeforeGroupDeletedEvent|CircleDestroyedEvent|CircleMemberAddedEvent|CircleMemberRemovedEvent|UserAddedEvent|UserRemovedEvent|GroupFolderCircleAddedEvent|GroupFolderGroupAddedEvent|GroupFolderCircleRemovedEvent|GroupFolderGroupRemovedEvent $event,
	): bool {
		return $event instanceof BeforeGroupDeletedEvent
			|| $event instanceof CircleDestroyedEvent
			|| $event instanceof CircleMemberRemovedEvent
			|| $event instanceof UserRemovedEvent
			|| $event instanceof GroupFolderCircleRemovedEvent
			|| $event instanceof GroupFolderGroupRemovedEvent;
	}

	/**
	 * Return the *maximum* set of users that is affected by the event.
	 *
	 * @return \Iterator<IUser>
	 */
	private function getAffectedUsers(
		BeforeGroupDeletedEvent|CircleDestroyedEvent|CircleMemberAddedEvent|CircleMemberRemovedEvent|UserAddedEvent|UserRemovedEvent|GroupFolderCircleAddedEvent|GroupFolderGroupAddedEvent|GroupFolderCircleRemovedEvent|GroupFolderGroupRemovedEvent $event,
	): \Iterator {
		if ($event instanceof CircleDestroyedEvent) {
			yield from $this->getCircleUsers($event->getCircle());
		}
		if ($event instanceof BeforeGroupDeletedEvent) {
			yield from $event->getGroup()->getUsers();
		}

		if ($event instanceof CircleMemberAddedEvent || $event instanceof CircleMemberRemovedEvent) {
			yield from $this->getMemberUsers($event->getMember());
		}
		if ($event instanceof UserAddedEvent || $event instanceof UserRemovedEvent) {
			yield $event->getUser();
		}

		if ($event instanceof GroupFolderCircleAddedEvent || $event instanceof GroupFolderCircleRemovedEvent) {
			yield from $this->getCircleUsers($event->getCircle());
		}
		if ($event instanceof GroupFolderGroupAddedEvent || $event instanceof GroupFolderGroupRemovedEvent) {
			yield from $event->getGroup()->getUsers();
		}
	}

	/**
	 * @return \Iterator<FolderDefinition>
	 */
	private function getFolders(
		BeforeGroupDeletedEvent|CircleDestroyedEvent|CircleMemberAddedEvent|CircleMemberRemovedEvent|UserAddedEvent|UserRemovedEvent|GroupFolderCircleAddedEvent|GroupFolderGroupAddedEvent|GroupFolderCircleRemovedEvent|GroupFolderGroupRemovedEvent $event,
	): \Iterator {
		if (
			$event instanceof GroupFolderCircleAddedEvent
			|| $event instanceof GroupFolderCircleRemovedEvent
			|| $event instanceof GroupFolderGroupAddedEvent
			|| $event instanceof GroupFolderGroupRemovedEvent
		) {
			yield $event->getFolder();
		} elseif (
			$event instanceof BeforeGroupDeletedEvent
			|| $event instanceof UserAddedEvent
			|| $event instanceof UserRemovedEvent
		) {
			$group = $event->getGroup();
			yield from $this->folderManager->getFoldersForGroups([$group->getGID()]);
		} elseif (
			$event instanceof CircleDestroyedEvent
			|| $event instanceof CircleMemberAddedEvent
			|| $event instanceof CircleMemberRemovedEvent
		) {
			$circle = $event->getCircle();
			yield from $this->folderManager->getFoldersForCircle($circle->getSingleId());
		}
	}

	/**
	 * @return \Iterator<IUser>
	 */
	private function getCircleUsers(Circle $circle): \Iterator {
		foreach ($circle->getInheritedMembers() as $member) {
			if ($member->getUserType() === Member::TYPE_USER) {
				yield $this->userManager->getExistingUser($member->getUserId());
			}
		}
	}

	/**
	 * @return \Iterator<IUser>
	 */
	private function getMemberUsers(Member $member): \Iterator {
		foreach ($member->getMemberships() as $member) {
			if ($member->getUserType() === Member::TYPE_USER) {
				yield $this->userManager->getExistingUser($member->getUserId());
			}
		}
	}

	private function getFullMountPoint(FolderDefinition $folderDefinition, IUser $user): string {
		return '/' . $user->getUID() . '/files/' . ltrim($folderDefinition->mountPoint);
	}
}
