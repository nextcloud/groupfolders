import {OCSResult, AxiosOCSResult} from "NC";
import axios from '@nextcloud/axios'
import { generateUrl } from "@nextcloud/router";

export interface Group {
	gid: string;
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
		return OC.generateUrl(`apps/groupfolders/${endpoint}`);
	}

	async listFolders(): Promise<Folder[]> {
		return $.getJSON(this.getUrl('folders'))
			.then((data: OCSResult<Folder[]>) => Object.keys(data.ocs.data).map(id => data.ocs.data[id]));
	}

	// Returns all NC groups
	async listGroups(): Promise<Group[]> {
		return $.getJSON(this.getUrl('delegation/groups'))
			.then((data: OCSResult<Group[]>) => data.ocs.data)
	}

	// Returns all groups that have been granted delegated admin or subadmin rights on groupfolders
	async listDelegatedGroups(classname: string): Promise<Group[]> {
		return axios.get(this.getUrl('/delegation/authorized-groups'), { params: { classname } })
			.then((data: AxiosOCSResult<Group[]>) => {
				// The admin group is always there. We don't want the user to remove it
				const groups = data.data.ocs.data.filter(g => g.gid !== 'admin')
				return groups
			})
	}

	// Updates the list of groups that have been granted delegated admin or subadmin rights on groupfolders
	async updateDelegatedGroups(newGroups: Group[], classname: string): Promise<void> {
		return axios.post(generateUrl('/apps/settings/') + '/settings/authorizedgroups/saveSettings', {
			newGroups,
			class: classname,
		})
		.then((data) => data.data)
	}

	async createFolder(mountPoint: string): Promise<number> {
		return $.post(this.getUrl('folders'), {
			mountpoint: mountPoint
		}, null, 'json').then((data: OCSResult<{ id: number; }>) => data.ocs.data.id);
	}

	async deleteFolder(id: number): Promise<void> {
		return $.ajax({
			url: this.getUrl(`folders/${id}`),
			type: 'DELETE'
		});
	}

	async addGroup(folderId: number, group: string): Promise<void> {
		return $.post(this.getUrl(`folders/${folderId}/groups`), {
			group
		});
	}

	async removeGroup(folderId: number, group: string): Promise<void> {
		return $.ajax({
			url: this.getUrl(`folders/${folderId}/groups/${group}`),
			type: 'DELETE'
		});
	}

	async setPermissions(folderId: number, group: string, permissions: number): Promise<void> {
		return $.post(this.getUrl(`folders/${folderId}/groups/${group}`), {
			permissions
		});
	}

	async setManageACL(folderId: number, type: string, id: string, manageACL: boolean): Promise<void> {
		return $.post(this.getUrl(`folders/${folderId}/manageACL`), {
			mappingType: type,
			mappingId: id,
			manageAcl: manageACL ? 1 : 0
		});
	}

	async setQuota(folderId: number, quota: number): Promise<void> {
		return $.post(this.getUrl(`folders/${folderId}/quota`), {
			quota
		});
	}

	async setACL(folderId: number, acl: boolean): Promise<void> {
		return $.post(this.getUrl(`folders/${folderId}/acl`), {
			acl: acl ? 1 : 0
		});
	}

	async renameFolder(folderId: number, mountpoint: string): Promise<void> {
		return $.post(this.getUrl(`folders/${folderId}/mountpoint`), {
			mountpoint
		});
	}

	async aclMappingSearch(folderId: number, search: string): Promise<{groups: ManageRuleProps[], users: ManageRuleProps[]}> {
		return $.getJSON(this.getUrl(`folders/${folderId}/search?format=json&search=${search}`))
			.then((data: OCSResult<{ groups: OCSGroup[]; users: OCSUser[]; }>) => {
				return {
					groups: Object.values(data.ocs.data.groups).map((item) => {
						return {
							type: 'group',
							id: item.gid,
							displayname: item.displayname
						}
					}),
					users: Object.values(data.ocs.data.users).map((item) => {
						return {
							type: 'user',
							id: item.uid,
							displayname: item.displayname
						}
					})
				}
			});
	}
}
