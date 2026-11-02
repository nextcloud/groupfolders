/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AclManage, Folder } from '../types/index.ts'

import { Permission } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { Api } from '../services/api.ts'
import { reportApiError } from '../services/apiError.ts'

/**
 * Group folders shown in the admin settings and the changes made to them.
 * Every action reports failures to the user; the folder only changes once the server accepted it.
 */
export const useFoldersStore = defineStore('folders', () => {
	const api = new Api()
	const folders = ref<Folder[]>([])
	const totalFolders = ref(0)

	/**
	 * Replace the loaded folders with the first folders in the given order.
	 *
	 * @param limit - Number of folders to load
	 * @param orderBy - Column to sort by
	 * @param order - Sort direction
	 * @return Whether the folders were loaded
	 */
	async function loadFolders(limit: number, orderBy: string, order: 'asc' | 'desc'): Promise<boolean> {
		try {
			folders.value = await api.listFolders(0, limit, orderBy, order)
			return true
		} catch (error) {
			reportApiError(error, 'Error while loading folders', t('groupfolders', 'Folders could not be loaded'))
			return false
		}
	}

	/**
	 * Append the next folders in the given order.
	 *
	 * @param limit - Number of folders to load
	 * @param orderBy - Column to sort by
	 * @param order - Sort direction
	 * @return Whether the folders were loaded
	 */
	async function loadMoreFolders(limit: number, orderBy: string, order: 'asc' | 'desc'): Promise<boolean> {
		try {
			const next = await api.listFolders(folders.value.length, limit, orderBy, order)
			folders.value = [...folders.value, ...next]
			return true
		} catch (error) {
			reportApiError(error, 'Error while loading folders', t('groupfolders', 'Folders could not be loaded'))
			return false
		}
	}

	/**
	 * Load the number of folders on the server.
	 */
	async function loadTotalFolders() {
		try {
			totalFolders.value = await api.countFolders()
		} catch (error) {
			reportApiError(error, 'Error while counting folders', t('groupfolders', 'Number of folders could not be loaded'))
		}
	}

	/**
	 * Create a group folder.
	 *
	 * @param mountPoint - Name of the new folder
	 * @param aclDefaultNoPermission - Whether advanced permissions deny access by default
	 * @return Whether the folder was created
	 */
	async function createFolder(mountPoint: string, aclDefaultNoPermission: boolean): Promise<boolean> {
		try {
			folders.value.push(await api.createFolder(mountPoint, aclDefaultNoPermission))
			totalFolders.value++
			return true
		} catch (error) {
			reportApiError(error, 'Error while creating new folder', t('groupfolders', 'Folder could not be created'))
			return false
		}
	}

	/**
	 * Delete a folder and all files inside.
	 *
	 * @param folder - Folder to delete
	 */
	async function deleteFolder(folder: Folder) {
		try {
			await api.deleteFolder(folder.id)
			folders.value = folders.value.filter((item) => item.id !== folder.id)
			totalFolders.value--
		} catch (error) {
			reportApiError(error, 'Error while deleting folder', t('groupfolders', 'Folder could not be deleted'))
		}
	}

	/**
	 * Rename a folder.
	 *
	 * @param folder - Folder to rename
	 * @param mountPoint - New name
	 */
	async function renameFolder(folder: Folder, mountPoint: string) {
		try {
			await api.renameFolder(folder.id, mountPoint)
			folder.mount_point = mountPoint
		} catch (error) {
			reportApiError(error, 'Error while renaming folder', t('groupfolders', 'Folder could not be renamed'))
		}
	}

	/**
	 * Grant a group or team access to a folder.
	 *
	 * @param folder - Folder to change
	 * @param group - Group or team id
	 */
	async function addGroup(folder: Folder, group: string) {
		try {
			await api.addGroup(folder.id, group)
			// The server sends an empty assignment as a JSON array, so build a new map
			const current: unknown = folder.groups
			folder.groups = {
				...(Array.isArray(current) ? {} : current as Folder['groups']),
				[group]: Permission.ALL,
			}
		} catch (error) {
			reportApiError(error, 'Error while adding group to folder', t('groupfolders', 'Access could not be granted'))
		}
	}

	/**
	 * Remove a group or team from a folder.
	 *
	 * @param folder - Folder to change
	 * @param group - Group or team id
	 */
	async function removeGroup(folder: Folder, group: string) {
		try {
			await api.removeGroup(folder.id, group)
			delete folder.groups[group]
		} catch (error) {
			reportApiError(error, 'Error while removing group from folder', t('groupfolders', 'Access could not be removed'))
		}
	}

	/**
	 * Store a new permission bitmask for one group or team.
	 *
	 * @param folder - Folder to change
	 * @param group - Group or team id
	 * @param permissions - Permission bitmask
	 */
	async function setPermissions(folder: Folder, group: string, permissions: number) {
		try {
			await api.setPermissions(folder.id, group, permissions)
			folder.groups[group] = permissions
		} catch (error) {
			reportApiError(error, 'Error while setting group permissions', t('groupfolders', 'Permissions could not be updated'))
		}
	}

	/**
	 * Update the folder quota.
	 *
	 * @param folder - Folder to change
	 * @param quota - Quota in bytes, or a negative preset
	 */
	async function setQuota(folder: Folder, quota: number) {
		try {
			await api.setQuota(folder.id, quota)
			folder.quota = quota
		} catch (error) {
			reportApiError(error, 'Error while setting folder quota', t('groupfolders', 'Quota could not be updated'))
		}
	}

	/**
	 * Enable or disable advanced permissions.
	 *
	 * @param folder - Folder to change
	 * @param acl - Whether advanced permissions are enabled
	 */
	async function setAcl(folder: Folder, acl: boolean) {
		try {
			await api.setACL(folder.id, acl)
			folder.acl = acl
		} catch (error) {
			reportApiError(error, 'Error while setting advanced permissions', t('groupfolders', 'Advanced permissions could not be updated'))
		}
	}

	/**
	 * Allow or deny a user, group, or team to manage advanced permissions.
	 *
	 * @param folder - Folder to change
	 * @param mapping - User, group, or team
	 * @param manageAcl - Whether management is allowed
	 */
	async function setManageAcl(folder: Folder, mapping: AclManage, manageAcl: boolean) {
		try {
			await api.setManageACL(folder.id, mapping.type, mapping.id, manageAcl)
		} catch (error) {
			reportApiError(error, 'Error while setting advanced permissions managers', t('groupfolders', 'Managers of advanced permissions could not be updated'))
			return
		}

		const others = folder.manage.filter((item) => item.type !== mapping.type || item.id !== mapping.id)
		folder.manage = manageAcl
			? [...others, { type: mapping.type, id: mapping.id, displayname: mapping.displayname }]
			: others
	}

	/**
	 * Find users, groups, and teams that could manage advanced permissions.
	 *
	 * @param folder - Folder to search for
	 * @param query - Search text
	 */
	async function searchManageAclMappings(folder: Folder, query: string): Promise<AclManage[]> {
		try {
			const { groups, users, circles } = await api.aclMappingSearch(folder.id, query)
			return [...groups, ...users, ...circles]
		} catch (error) {
			reportApiError(error, 'Error while searching advanced permissions managers', t('groupfolders', 'Users, groups, and teams could not be searched'))
			return []
		}
	}

	return {
		folders,
		totalFolders,
		loadFolders,
		loadMoreFolders,
		loadTotalFolders,
		createFolder,
		deleteFolder,
		renameFolder,
		addGroup,
		removeGroup,
		setPermissions,
		setQuota,
		setAcl,
		setManageAcl,
		searchManageAclMappings,
	}
})
