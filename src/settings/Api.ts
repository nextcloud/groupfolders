/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
// eslint-disable-next-line n/no-unpublished-import
import type { OCSResponse } from '@nextcloud/typings/lib/ocs'
import type { Folder, Group, Circle, User, AclManage } from '../types'

export class Api {

	getUrl(endpoint: string): string {
		return generateOcsUrl(`apps/groupfolders/${endpoint}`)
	}

	async listFolders(): Promise<Folder[]> {
		const response = await axios.get<OCSResponse<Folder[]>>(this.getUrl('folders'))
		return Object.keys(response.data.ocs.data).map(id => response.data.ocs.data[id])
	}

	// Returns all NC groups
	async listGroups(): Promise<Group[]> {
		const response = await axios.get<OCSResponse<Group[]>>(this.getUrl('delegation/groups'))
		return response.data.ocs.data
	}

	// Returns all visible NC circles
	async listCircles(): Promise<Circle[]> {
		const response = await axios.get<OCSResponse<Circle[]>>(this.getUrl('delegation/circles'))
		return response.data.ocs.data
	}

	// Returns all groups that have been granted delegated admin or subadmin rights on groupfolders
	async listDelegatedGroups(classname: string): Promise<Group[]> {
		const response = await axios.get<OCSResponse<Group[]>>(this.getUrl('/delegation/authorized-groups'), { params: { classname } })
		return response.data.ocs.data.filter(g => g.gid !== 'admin')
	}

	// Updates the list of groups that have been granted delegated admin or subadmin rights on groupfolders
	async updateDelegatedGroups(newGroups: Group[], classname: string): Promise<void> {
		await confirmPassword()

		await axios.post(generateUrl('/apps/settings/') + '/settings/authorizedgroups/saveSettings', {
			newGroups,
			class: classname,
		})
	}

	async createFolder(mountPoint: string): Promise<Folder> {
		await confirmPassword()

		const response = await axios.post<OCSResponse<Folder>>(this.getUrl('folders'), { mountpoint: mountPoint })
		return response.data.ocs.data
	}

	async deleteFolder(id: number): Promise<void> {
		await confirmPassword()

		await axios.delete(this.getUrl(`folders/${id}`))
	}

	async addGroup(folderId: number, group: string): Promise<void> {
		await confirmPassword()

		await axios.post(this.getUrl(`folders/${folderId}/groups`), { group })
	}

	async removeGroup(folderId: number, group: string): Promise<void> {
		await confirmPassword()

		await axios.delete(this.getUrl(`folders/${folderId}/groups/${group}`))
	}

	async setPermissions(folderId: number, group: string, permissions: number): Promise<void> {
		await confirmPassword()

		await axios.post(this.getUrl(`folders/${folderId}/groups/${group}`), { permissions })
	}

	async setManageACL(folderId: number, type: string, id: string, manageACL: boolean): Promise<void> {
		await confirmPassword()

		await axios.post(this.getUrl(`folders/${folderId}/manageACL`), {
			mappingType: type,
			mappingId: id,
			manageAcl: manageACL ? 1 : 0,
		})
	}

	async setQuota(folderId: number, quota: number): Promise<void> {
		await confirmPassword()

		await axios.post(this.getUrl(`folders/${folderId}/quota`), { quota })
	}

	async setACL(folderId: number, acl: boolean): Promise<void> {
		await confirmPassword()

		await axios.post(this.getUrl(`folders/${folderId}/acl`), { acl: acl ? 1 : 0 })
	}

	async renameFolder(folderId: number, mountpoint: string): Promise<void> {
		await confirmPassword()

		await axios.post(this.getUrl(`folders/${folderId}/mountpoint`), { mountpoint })
	}

	async aclMappingSearch(folderId: number, search: string): Promise<{
		groups: AclManage[],
		users: AclManage[]
	}> {
		const response = await axios.get<OCSResponse<{groups: Group[], users: User[]}>>(this.getUrl(`folders/${folderId}/search`), { params: { search } })
		return {
			groups: Object.values(response.data.ocs.data.groups).map((item) => {
				return {
					type: 'group',
					id: item.gid,
					displayName: item.displayName,
				}
			}),
			users: Object.values(response.data.ocs.data.users).map((item) => {
				return {
					type: 'user',
					id: item.uid,
					displayName: item.displayName,
				}
			}),
		}
	}

}
