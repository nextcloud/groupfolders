import * as React from 'react';
import {ChangeEvent, Component} from 'react';

import {Api, Folder, Group} from './Api';
import {FolderGroups} from './FolderGroups';
import {QuotaSelect} from './QuotaSelect';

import './App.css';
import {SubmitInput} from "./SubmitInput";

const defaultQuotaOptions = {
	'1 GB': 1073741274,
	'5 GB': 5368709120,
	'10 GB': 10737412742,
	'Unlimited': -3
};

export interface AppState {
	folders: Folder[];
	groups: Group[],
	newMountPoint: string;
	editingGroup: number;
	editingMountPoint: number;
	renameMountPoint: string;
	filter: string;
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
		filter: ''
	};

	componentDidMount() {
		this.api.listFolders().then((folders) => {
			this.setState({folders});
		});
		this.api.listGroups().then((groups) => {
			this.setState({groups});
		});
		// nc13
		OC.Plugins.register('OCA.Search', this);
		// nc14 and up
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
			folders[id] = {
				mount_point: mountPoint,
				groups: {},
				quota: -3,
				size: 0
			};
			this.setState({folders});
		});
	};

	attach = (search: OC.Search.Core) => {
		search.setFilter('settings', (query) => {
			this.setState({filter: query});
		});
	};

	deleteFolder(id: number) {
		const folderName = this.state.folders[id].mount_point;
		OC.dialogs.confirm(
			t('groupfolders', 'Are you sure you want to delete "{folderName}" and all files inside? This operation can not be undone', {folderName}),
			t('groupfolders', 'Delete "{folderName}"?', {folderName}),
			confirmed => {
				if (confirmed) {
					const folders = this.state.folders;
					delete folders[id];
					this.setState({folders});
					this.api.deleteFolder(id);
				}
			},
			true
		);
	};

	addGroup(folderId: number, group: string) {
		const folders = this.state.folders;
		folders[folderId].groups[group] = OC.PERMISSION_ALL;
		this.setState({folders});
		this.api.addGroup(folderId, group);
	}

	removeGroup(folderId: number, group: string) {
		const folders = this.state.folders;
		delete folders[folderId].groups[group];
		this.setState({folders});
		this.api.removeGroup(folderId, group);
	}

	setPermissions(folderId: number, group: string, newPermissions: number) {
		const folders = this.state.folders;
		folders[folderId].groups[group] = newPermissions;
		this.setState({folders});
		this.api.setPermissions(folderId, group, newPermissions);
	}

	setQuota(folderId: number, quota: number) {
		const folders = this.state.folders;
		folders[folderId].quota = quota;
		this.setState({folders});
		this.api.setQuota(folderId, quota);
	}

	renameFolder(folderId: number, newName: string) {
		const folders = this.state.folders;
		folders[folderId].mount_point = newName;
		// this.api.setQuota(folderId, quota);
		this.setState({folders, editingMountPoint: 0});
		this.api.renameFolder(folderId, newName);
	}

	render() {
		const rows = Object.keys(this.state.folders)
			.filter(key => {
				if (this.state.filter === '') {
					return true;
				}
				const id = parseInt(key, 10);
				const row = this.state.folders[id];
				return row.mount_point.toLowerCase().indexOf(this.state.filter.toLowerCase()) !== -1;
			})
			.map(key => {
				const id = parseInt(key, 10);
				const row = this.state.folders[id];
				return <tr key={id}>
					<td className="mountpoint">
						{this.state.editingMountPoint === id ?
							<SubmitInput
								autoFocus={true}
								onSubmitValue={this.renameFolder.bind(this, id)}
								onClick={event => {
									event.stopPropagation();
								}}
								initialValue={row.mount_point}
							/> :
							<a
								className="action-rename"
								onClick={event => {
									event.stopPropagation();
									this.setState({editingMountPoint: id})
								}}
							>
								{row.mount_point}
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
							groups={row.groups}
							allGroups={this.state.groups}
							onAddGroup={this.addGroup.bind(this, id)}
							removeGroup={this.removeGroup.bind(this, id)}
							onSetPermissions={this.setPermissions.bind(this, id)}
						/>
					</td>
					<td className="quota">
						<QuotaSelect options={defaultQuotaOptions}
									 value={row.quota}
									 size={row.size}
									 onChange={this.setQuota.bind(this, id)}/>
					</td>
					<td className="remove">
						<a className="icon icon-delete icon-visible"
						   onClick={this.deleteFolder.bind(this, id)}
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
					<th>
						{t('groupfolders', 'Folder name')}
					</th>
					<th>
						{t('groupfolders', 'Groups')}
					</th>
					<th>
						{t('groupfolders', 'Quota')}
					</th>
					<th/>
				</tr>
				</thead>
				<tbody>
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
				</tbody>
			</table>
		</div>;
	}
}
