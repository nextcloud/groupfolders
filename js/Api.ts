import {OCSResult} from "NC";
import Thenable = JQuery.Thenable;

export interface Group {
	id: string;
	displayname: string;
}

export interface Folder {
	mount_point: string;
	quota: number;
	size: number;
	groups: { [group: string]: number };
}

export class Api {
	getUrl(endpoint: string): string {
		return OC.generateUrl(`apps/groupfolders/${endpoint}`);
	}

	listFolders(): Thenable<Folder[]> {
		return $.getJSON(this.getUrl('folders'))
			.then((data:OCSResult<Folder[]>) => data.ocs.data);
	}

	listGroups(): Thenable<Group[]> {
		return $.getJSON(OC.linkToOCS('cloud', 1) + 'groups/details')
			.then((data: OCSResult<{ groups: string[]; }>) => data.ocs.data.groups);
	}

	createFolder(mountPoint: string): Thenable<number> {
		return $.post(this.getUrl('folders'), {
			mountpoint: mountPoint
		}, null, 'json').then((data: OCSResult<{ id: number; }>) => data.ocs.data.id);
	}

	deleteFolder(id: number): Thenable<void> {
		return $.ajax({
			url: this.getUrl(`folders/${id}`),
			type: 'DELETE'
		});
	}

	addGroup(folderId: number, group: string): Thenable<void> {
		return $.post(this.getUrl(`folders/${folderId}/groups`), {
			group
		});
	}

	removeGroup(folderId: number, group: string): Thenable<void> {
		return $.ajax({
			url: this.getUrl(`folders/${folderId}/groups/${group}`),
			type: 'DELETE'
		});
	}

	setPermissions(folderId: number, group: string, permissions: number): Thenable<void> {
		return $.post(this.getUrl(`folders/${folderId}/groups/${group}`), {
			permissions
		});
	}

	setQuota(folderId: number, quota: number): Thenable<void> {
		return $.post(this.getUrl(`folders/${folderId}/quota`), {
			quota
		});
	}

	renameFolder(folderId: number, mountpoint: string): Thenable<void> {
		return $.post(this.getUrl(`folders/${folderId}/mountpoint`), {
			mountpoint
		});
	}
}
