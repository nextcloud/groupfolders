import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
// eslint-disable-next-line n/no-unpublished-import
import type { OCSResponse } from '@nextcloud/typings/lib/ocs'

export interface Group {
	gid: string;
	displayName: string;
}

export interface Circle {
	singleId: string;
	displayName: string;
}

export interface OCSUser {
	uid: string;
	displayname: string;
}

export interface OCSGroup {
	gid: string;
	displayname: string;
}

export interface OSCCircle {
	sid: string;
	displayname: string;
}

export interface ManageRuleProps {
	type: string;
	id: string;
	displayname: string;
}

export interface Folder {
	id: number;
	mount_point: string;
	quota: number;
	size: number;
	groups: { [group: string]: number };
	acl: boolean;
	manage: ManageRuleProps[];
}

export class Api {

	getUrl(endpoint: string): string {
		return OC.generateUrl(`apps/groupfolders/${endpoint}`)
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

	async createFolder(mountPoint: string): Promise<number> {
		await confirmPassword()

		const response = await axios.post<OCSResponse<number>>(this.getUrl('folders'), { mountpoint: mountPoint })
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
		groups: ManageRuleProps[],
		users: ManageRuleProps[],
		circles: ManageRuleProps[],
	}> {
		const response = await axios.get<OCSResponse<{groups: OCSGroup[], users: OCSUser[], circles: OSCCircle[]}>>(this.getUrl(`folders/${folderId}/search`), { params: { search } })
		return {
			groups: Object.values(response.data.ocs.data.groups).map((item) => {
				return {
					type: 'group',
					id: item.gid,
					displayname: item.displayname,
				}
			}),
			users: Object.values(response.data.ocs.data.users).map((item) => {
				return {
					type: 'user',
					id: item.uid,
					displayname: item.displayname,
				}
			}),
			circles: Object.values(response.data.ocs.data.circles).map((item) => {
				return {
					type: 'circle',
					id: item.sid,
					displayname: item.displayname,
				}
			}),
		}
	}

}
