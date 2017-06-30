import {Component} from 'react';

import {Api} from './Api';
import {FolderGroups} from './FolderGroups';
import {QuotaSelect} from './QuotaSelect';

import './App.css';

const defaultQuotaOptions = {
	'1 GB': 1073741274,
	'5 GB': 5368709120,
	'10 GB': 10737412742,
	'Unlimited': -3
};

export class App extends Component {
	state = {
		folders: [],
		groups: [],
		newMountPoint: '',
		editing: 0
	};

	constructor (params) {
		super(params);
		this.api = new Api();
	}

	componentDidMount () {
		this.api.listFolders().then((folders) => {
			this.setState({folders});
		});
		this.api.listGroups().then((groups) => {
			this.setState({groups});
		})
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
				quota: -3
			};
			this.setState({folders});
		});
	};

	deleteFolder (id) {
		const folderName = this.state.folders[id].mount_point;
		OC.dialogs.confirm(
			t('groupfolders', 'Are you sure you want to delete "{folderName}" and all files inside. This operation can not be undone', {folderName}),
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

	addGroup (folderId, group) {
		const folders = this.state.folders;
		folders[folderId].groups[group] = OC.PERMISSION_ALL;
		this.setState({folders});
		this.api.addGroup(folderId, group);
	}

	removeGroup (folderId, group) {
		const folders = this.state.folders;
		delete folders[folderId].groups[group];
		this.setState({folders});
		this.api.removeGroup(folderId, group);
	}

	setPermissions (folderId, group, newPermissions) {
		const folders = this.state.folders;
		folders[folderId].groups[group] = newPermissions;
		this.setState({folders});
		this.api.setPermissions(folderId, group, newPermissions);
	}

	setQuota (folderId, quota) {
		const folders = this.state.folders;
		folders[folderId].quota = quota;
		this.setState({folders});
		this.api.setQuota(folderId, quota);
	}

	render () {
		const rows = Object.keys(this.state.folders).map((id) => {
			const row = this.state.folders[id];
			return <tr key={id}>
				<td className="mountpoint">{row.mount_point}</td>
				<td className="groups">
					<FolderGroups
						edit={this.state.editing === id}
						showEdit={event => {
							event.stopPropagation();
							this.setState({editing: id})
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
					<a className="icon icon-delete"
					   onClick={this.deleteFolder.bind(this, id)}
					   title={t('groupfolders', 'Delete')}/>
				</td>
			</tr>
		});

		return <div id="groupfolders-react-root"
					onClick={() => {
						this.setState({editing: 0})
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
