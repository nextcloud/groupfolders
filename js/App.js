import {Component} from 'react';

import {Api} from './Api';
import {FolderGroups} from './FolderGroups';

import './App.css';

export class App extends Component {
	state = {
		folders: [],
		groups: [],
		newMountPoint: ''
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
				groups: {}
			};
			this.setState({folders});
		});
	};

	deleteFolder (id) {
		const folders = this.state.folders;
		delete folders[id];
		this.setState({folders});
		this.api.deleteFolder(id);
	};

	addGroup (folderId, group) {
		const folders = this.state.folders;
		folders[folderId].groups[group] = 31;
		this.setState({folders});
		this.api.addGroup(folderId, group);
	}

	removeGroup(folderId, group) {
		const folders = this.state.folders;
		delete folders[folderId].groups[group];
		this.setState({folders});
		this.api.removeGroup(folderId, group);
	}

	render () {
		const rows = Object.keys(this.state.folders).map((id) => {
			const row = this.state.folders[id];
			return <tr key={id}>
				<td>{row.mount_point}</td>
				<td>
					<FolderGroups
						groups={row.groups}
						allGroups={this.state.groups}
						onAddGroup={this.addGroup.bind(this, id)}
						removeGroup={this.removeGroup.bind(this, id)}
					/>
				</td>
				<td>
					<button onClick={this.deleteFolder.bind(this, id)}>delete
					</button>
				</td>
			</tr>
		});

		return <div>
			<table>
				<thead>
				<tr>
					<th>
						Mount point
					</th>
					<th>
						Groups
					</th>
				</tr>
				</thead>
				<tbody>
				{rows}
				<tr>
					<td>
						<form action="#" onSubmit={this.createRow}>
							<input value={this.state.newMountPoint}
								   placeholder="Mountpoint"
								   onChange={(event) => {
									   this.setState({newMountPoint: event.target.value})
								   }}/>
							<input type="submit" value="Create"/>
						</form>
					</td>
				</tr>
				</tbody>
			</table>
		</div>;
	}
}
