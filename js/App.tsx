import * as React from 'react';
import {ChangeEvent, Component} from 'react';

import {Api, Folder, Group} from './Api';
import {FolderGroups} from './FolderGroups';
import {QuotaSelect} from './QuotaSelect';

import './App.css';
import {SubmitInput} from "./SubmitInput";
import {SortArrow} from "./SortArrow";
import FlipMove from "react-flip-move";

const defaultQuotaOptions = {
	'1 GB': 1073741274,
	'5 GB': 5368709120,
	'10 GB': 10737412742,
	'Unlimited': -3
};

export type SortKey = 'mount_point' | 'quota' | 'groups' | 'acl';

export interface AppState {
	folders: Folder[];
	groups: Group[],
	newMountPoint: string;
	editingGroup: number;
	editingMountPoint: number;
	renameMountPoint: string;
	filter: string;
	sort: SortKey;
	sortOrder: number;
}

export class App extends Component<{}, AppState> implements OC.Plugin<OC.Search.Core> {
	api = new Api();

	state: AppState = {
		folders: [],
		groups: [],
		newMountPoint: '',
		editingGroup: 0,
		editingMountPoint: 0,
		renameMountPoint: '',
		filter: '',
		sort: 'mount_point',
		sortOrder: 1
	};

	componentDidMount() {
		this.api.listFolders().then((folders) => {
			this.setState({folders});
		});
		this.api.listGroups().then((groups) => {
			this.setState({groups});
		});
		OC.Plugins.register('OCA.Search.Core', this);
	}

	createRow = () => {
		const mountPoint = this.state.newMountPoint;
		if (!mountPoint) {
			return;
		}
		this.setState({newMountPoint: ''});
		this.api.createFolder(mountPoint).then((id) => {
			const folders = this.state.folders;
			folders.push({
				mount_point: mountPoint,
				groups: {},
				quota: -3,
				size: 0,
				id,
				acl: false
			});
			this.setState({folders});
		});
	};

	attach = (search: OC.Search.Core) => {
		search.setFilter('settings', (query) => {
			this.setState({filter: query});
		});
	};

	deleteFolder(folder: Folder) {
		OC.dialogs.confirm(
			t('groupfolders', 'Are you sure you want to delete "{folderName}" and all files inside? This operation can not be undone', {folderName: folder.mount_point}),
			t('groupfolders', 'Delete "{folderName}"?', {folderName: folder.mount_point}),
			confirmed => {
				if (confirmed) {
					this.setState({folders: this.state.folders.filter(item => item.id !== folder.id)});
					this.api.deleteFolder(folder.id);
				}
			},
			true
		);
	};

	addGroup(folder: Folder, group: string) {
		const folders = this.state.folders;
		folder.groups[group] = OC.PERMISSION_ALL;
		this.setState({folders});
		this.api.addGroup(folder.id, group);
	}

	removeGroup(folder: Folder, group: string) {
		const folders = this.state.folders;
		delete folder.groups[group];
		this.setState({folders});
		this.api.removeGroup(folder.id, group);
	}

	setPermissions(folder: Folder, group: string, newPermissions: number) {
		const folders = this.state.folders;
		folder.groups[group] = newPermissions;
		this.setState({folders});
		this.api.setPermissions(folder.id, group, newPermissions);
	}

	setQuota(folder: Folder, quota: number) {
		const folders = this.state.folders;
		folder.quota = quota;
		this.setState({folders});
		this.api.setQuota(folder.id, quota);
	}

	renameFolder(folder: Folder, newName: string) {
		const folders = this.state.folders;
		folder.mount_point = newName;
		this.setState({folders, editingMountPoint: 0});
		this.api.renameFolder(folder.id, newName);
	}

	setAcl(folder: Folder, acl: boolean) {
		const folders = this.state.folders;
		folder.acl = acl;
		this.setState({folders});
		this.api.setACL(folder.id, acl);
	}

	onSortClick = (sort: SortKey) => {
		if (this.state.sort === sort) {
			this.setState({sortOrder: -this.state.sortOrder});
		} else {
			this.setState({sortOrder: 1, sort});
		}
	};

	static supportACL(): boolean {
		return parseInt(oc_config.version,10) >= 16;
	}

	render() {
		const rows = this.state.folders
			.filter(folder => {
				if (this.state.filter === '') {
					return true;
				}
				return folder.mount_point.toLowerCase().indexOf(this.state.filter.toLowerCase()) !== -1;
			})
			.sort((a, b) => {
				switch (this.state.sort) {
					case "mount_point":
						return a.mount_point.localeCompare(b.mount_point) * this.state.sortOrder;
					case "quota":
						if (a.quota < 0 && b.quota >= 0) {
							return this.state.sortOrder;
						}
						if (b.quota < 0 && a.quota >= 0) {
							return -this.state.sortOrder;
						}
						return (a.quota - b.quota) * this.state.sortOrder;
					case "groups":
						return (Object.keys(a.groups).length - Object.keys(b.groups).length) * this.state.sortOrder;
					case "acl":
						if (a.acl && !b.acl) {
							return this.state.sortOrder;
						}
						if (!a.acl && b.acl) {
							return -this.state.sortOrder;
						}
						return 0;
				}
			})
			.map(folder => {
				const id = folder.id;
				return <tr key={id}>
					<td className="mountpoint">
						{this.state.editingMountPoint === id ?
							<SubmitInput
								autoFocus={true}
								onSubmitValue={this.renameFolder.bind(this, folder)}
								onClick={event => {
									event.stopPropagation();
								}}
								initialValue={folder.mount_point}
							/> :
							<a
								className="action-rename"
								onClick={event => {
									event.stopPropagation();
									this.setState({editingMountPoint: id})
								}}
							>
								{folder.mount_point}
							</a>
						}
					</td>
					<td className="groups">
						<FolderGroups
							edit={this.state.editingGroup === id}
							showEdit={event => {
								event.stopPropagation();
								this.setState({editingGroup: id})
							}}
							groups={folder.groups}
							allGroups={this.state.groups}
							onAddGroup={this.addGroup.bind(this, folder)}
							removeGroup={this.removeGroup.bind(this, folder)}
							onSetPermissions={this.setPermissions.bind(this, folder)}
						/>
					</td>
					<td className="quota">
						<QuotaSelect options={defaultQuotaOptions}
									 value={folder.quota}
									 size={folder.size}
									 onChange={this.setQuota.bind(this, folder)}/>
					</td>
					<td className="acl">
						<input type="checkbox" checked={folder.acl} disabled={!App.supportACL()}
							   title={App.supportACL()?'':t('groupfolders', 'Advanced permissions are only supported with Nextcloud 16 and up')}
							   onChange={(event) => this.setAcl(folder, event.target.checked)}
						/>
					</td>
					<td className="remove">
						<a className="icon icon-delete icon-visible"
						   onClick={this.deleteFolder.bind(this, folder)}
						   title={t('groupfolders', 'Delete')}/>
					</td>
				</tr>
			});

		return <div id="groupfolders-react-root"
					onClick={() => {
						this.setState({editingGroup: 0, editingMountPoint: 0})
					}}>
			<table>
				<thead>
				<tr>
					<th onClick={() => this.onSortClick('mount_point')}>
						{t('groupfolders', 'Folder name')}
						<SortArrow name='mount_point' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th onClick={() => this.onSortClick('groups')}>
						{t('groupfolders', 'Groups')}
						<SortArrow name='groups' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th onClick={() => this.onSortClick('quota')}>
						{t('groupfolders', 'Quota')}
						<SortArrow name='quota' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th onClick={() => this.onSortClick('acl')}>
						{t('groupfolders', 'Advanced Permissions')}
						<SortArrow name='acl' value={this.state.sort}
								   direction={this.state.sortOrder}/>
					</th>
					<th/>
				</tr>
				</thead>
				<FlipMove typeName='tbody'>
					{rows}
					<tr>
						<td>
							<form action="#" onSubmit={this.createRow}>
								<input
									className="newgroup-name"
									value={this.state.newMountPoint}
									placeholder={t('groupfolders', 'Folder name')}
									onChange={(event) => {
										this.setState({newMountPoint: event.target.value})
									}}/>
								<input type="submit"
									   value={t('groupfolders', 'Create')}/>
							</form>
						</td>
						<td colSpan={3}/>
					</tr>
				</FlipMove>
			</table>
		</div>;
	}
}
