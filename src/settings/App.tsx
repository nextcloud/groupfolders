/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import * as React from 'react'
import { Component, FormEvent } from 'react'

import { Api } from './Api'
import type { Circle, Folder, Group, AclManage } from '../types'
import { FolderGroups } from './FolderGroups'
import { QuotaSelect } from './QuotaSelect'
import './App.scss'
import { SubmitInput } from './SubmitInput'
import { SortArrow } from './SortArrow'
import FlipMove from 'react-flip-move'
import AsyncSelect from 'react-select/async'
import AdminGroupSelect from './AdminGroupSelect'
import SubAdminGroupSelect from './SubAdminGroupSelect'
import { loadState } from '@nextcloud/initial-state'

const bytesInOneGibibyte = Math.pow(1024, 3)
const defaultQuotaOptions = {
	'1 GB': bytesInOneGibibyte,
	'5 GB': bytesInOneGibibyte * 5,
	'10 GB': bytesInOneGibibyte * 10,
	Unlimited: -3,
}

export type SortKey = 'mount_point' | 'quota' | 'groups' | 'acl';

export interface AppState {
	delegatedAdminGroups: Group[],
	delegatedSubAdminGroups: Group[],
	folders: {[folderId: number]: Folder};
	groups: Group[],
	circles: Circle[],
	newMountPoint: string;
	editingGroup: number;
	editingMountPoint: number;
	renameMountPoint: string;
	filter: string;
	sort: SortKey;
	sortOrder: number;
	isAdminNextcloud: boolean;
	checkAppsInstalled: boolean;
}

export class App extends Component<unknown, AppState> implements OC.Plugin<OC.Search.Core> {

	api = new Api()

	state: AppState = {
		delegatedAdminGroups: [],
		delegatedSubAdminGroups: [],
		folders: [],
		groups: [],
		circles: [],
		newMountPoint: '',
		editingGroup: 0,
		editingMountPoint: 0,
		renameMountPoint: '',
		filter: '',
		sort: 'mount_point',
		sortOrder: 1,
		isAdminNextcloud: false,
		checkAppsInstalled: false,
	}

	componentDidMount() {
		this.api.listFolders().then((folders) => {
			this.setState({ folders: Object.fromEntries(folders.map((folder) => [folder.id, folder])) })
		})
		this.api.listGroups().then((groups) => {
			this.setState({ groups })
		})
		this.api.listCircles().then((circles) => {
			this.setState({ circles })
		})

		this.setState({ isAdminNextcloud: loadState('groupfolders', 'isAdminNextcloud') })
		this.setState({ checkAppsInstalled: loadState('groupfolders', 'checkAppsInstalled') })

		OC.Plugins.register('OCA.Search.Core', this)
	}

	createRow = (event: FormEvent) => {
		event.preventDefault()
		const mountPoint = this.state.newMountPoint
		if (!mountPoint) {
			return
		}
		this.setState({ newMountPoint: '' })
		this.api.createFolder(mountPoint).then((folder) => {
			const folders = this.state.folders
			folders[folder.id] = folder
			this.setState({ folders })
		})
	}

	attach = (search: OC.Search.Core) => {
		search.setFilter('settings', (query) => {
			this.setState({ filter: query })
		})
	}

	deleteFolder(folder: Folder) {
		OC.dialogs.confirm(
			t('groupfolders', 'Are you sure you want to delete "{folderName}" and all files inside? This operation cannot be undone', { folderName: folder.mount_point }),
			t('groupfolders', 'Delete "{folderName}"?', { folderName: folder.mount_point }),
			confirmed => {
				if (confirmed) {
					this.setState({ folders: Object.fromEntries(Object.values(this.state.folders).filter(item => item.id !== folder.id).map((folder) => [folder.id, folder])) })
					this.api.deleteFolder(folder.id)
				}
			},
			true,
		)
	}

	addGroup(folder: Folder, group: string) {
		const folders = this.state.folders
		folder.groups[group] = {
			displayName: group,
			permissions: OC.PERMISSION_ALL,
			type: 'group',
		}
		this.setState({ folders })
		this.api.addGroup(folder.id, group)
	}

	removeGroup(folder: Folder, group: string) {
		const folders = this.state.folders
		delete folder.groups[group]
		this.setState({ folders })
		this.api.removeGroup(folder.id, group)
	}

	setPermissions(folder: Folder, group: string, newPermissions: number) {
		const folders = this.state.folders
		folder.groups[group].permissions = newPermissions
		this.setState({ folders })
		this.api.setPermissions(folder.id, group, newPermissions)
	}

	setManageACL(folder: Folder, type: string, id: string, manageACL: boolean) {
		this.api.setManageACL(folder.id, type, id, manageACL)
	}

	searchMappings(folder: Folder, search: string) {
		return this.api.aclMappingSearch(folder.id, search)
	}

	setQuota(folder: Folder, quota: number) {
		const folders = this.state.folders
		folder.quota = quota
		this.setState({ folders })
		this.api.setQuota(folder.id, quota)
	}

	renameFolder(folder: Folder, newName: string) {
		const folders = this.state.folders
		folder.mount_point = newName
		this.setState({ folders, editingMountPoint: 0 })
		this.api.renameFolder(folder.id, newName)
	}

	setAcl(folder: Folder, acl: boolean) {
		const folders = this.state.folders
		folder.acl = acl
		this.setState({ folders })
		this.api.setACL(folder.id, acl)
	}

	onSortClick = (sort: SortKey) => {
		if (this.state.sort === sort) {
			this.setState({ sortOrder: -this.state.sortOrder })
		} else {
			this.setState({ sortOrder: 1, sort })
		}
	}

	static supportACL(): boolean {
		return parseInt(OC.config.version, 10) >= 16
	}

	showAdminDelegationForms() {
		if (this.state.isAdminNextcloud && this.state.checkAppsInstalled) {
			return <div id="groupfolders-admin-delegation">
				<h3>{ t('groupfolders', 'Group folder admin delegation') }</h3>
				<p><em>{ t('groupfolders', 'Nextcloud allows you to delegate the administration of group folders to non-admin users.') }</em></p>
				<p><em>{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST.') }</em></p>
				<p className="end-description-delegation"><em>{ t('groupfolders', 'They will have access to all group folders.') }</em></p>
				<AdminGroupSelect
					groups={this.state.groups}
					allGroups={this.state.groups}
					delegatedAdminGroups={this.state.delegatedAdminGroups} />
				<p><em>{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST only.') }</em></p>
				<p className="end-description-delegation"><em>{ t('groupfolders', 'They will only have access to group folders for which they have advanced permissions.') }</em></p>
				<SubAdminGroupSelect
					groups={this.state.groups}
					allGroups={this.state.groups}
					delegatedSubAdminGroups={this.state.delegatedSubAdminGroups} />
			</div>
		}
	}

	render() {
		const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)
		const groupHeader = isCirclesEnabled
			? t('groupfolders', 'Group or team')
			: t('groupfolders', 'Group')

		const rows = Object.values(this.state.folders)
			.filter(folder => {
				if (this.state.filter === '') {
					return true
				}
				return folder.mount_point.toLowerCase().indexOf(this.state.filter.toLowerCase()) !== -1
			})
			.sort((a, b) => {
				switch (this.state.sort) {
				case 'mount_point':
					return a.mount_point.localeCompare(b.mount_point) * this.state.sortOrder
				case 'quota':
					if (a.quota < 0 && b.quota >= 0) {
						return this.state.sortOrder
					}
					if (b.quota < 0 && a.quota >= 0) {
						return -this.state.sortOrder
					}
					return (a.quota - b.quota) * this.state.sortOrder
				case 'groups':
					return (Object.keys(a.groups).length - Object.keys(b.groups).length) * this.state.sortOrder
				case 'acl':
					if (a.acl && !b.acl) {
						return this.state.sortOrder
					}
					if (!a.acl && b.acl) {
						return -this.state.sortOrder
					}
				}
				return 0
			})
			.map(folder => {
				const id = folder.id
				return <tr key={id}>
					<td className="mountpoint">
						{this.state.editingMountPoint === id
							? <SubmitInput
								autoFocus={true}
								onSubmitValue={this.renameFolder.bind(this, folder)}
								onClick={event => {
									event.stopPropagation()
								}}
								initialValue={folder.mount_point}
							/>
							: <a
								className="action-rename"
								onClick={event => {
									event.stopPropagation()
									this.setState({ editingMountPoint: id })
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
								event.stopPropagation()
								this.setState({ editingGroup: id })
							}}
							groups={folder.groups}
							allCircles={this.state.circles}
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
						<input id={'acl-' + folder.id} type="checkbox" className="checkbox" checked={folder.acl} disabled={!App.supportACL()}
							title={
								App.supportACL()
									? t('groupfolders', 'Advanced permissions allows setting permissions on a per-file basis but comes with a performance overhead')
									: t('groupfolders', 'Advanced permissions are only supported with Nextcloud 16 and up')
							}
							onChange={(event) => this.setAcl(folder, event.target.checked)}
						/>
						<label htmlFor={'acl-' + folder.id}></label>
						{folder.acl
							&& <ManageAclSelect
								folder={folder}
								onChange={this.setManageACL.bind(this, folder)}
								onSearch={this.searchMappings.bind(this, folder)}
							/>
						}
					</td>
					<td className="remove">
						<a className="icon icon-delete icon-visible"
						   onClick={this.deleteFolder.bind(this, folder)}
						   title={t('groupfolders', 'Delete')}/>
					</td>
				</tr>
			})

		return <div id="groupfolders-react-root"
			onClick={() => {
				this.setState({ editingGroup: 0, editingMountPoint: 0 })
			}}>
			{this.showAdminDelegationForms()}
			<table>
				<thead>
					<tr>
						<th onClick={() => this.onSortClick('mount_point')}>
							{t('groupfolders', 'Folder name')}
							<SortArrow name='mount_point' value={this.state.sort}
								   direction={this.state.sortOrder}/>
						</th>
						<th onClick={() => this.onSortClick('groups')}>
							{groupHeader}
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
				<FlipMove typeName='tbody' enterAnimation="accordionVertical" leaveAnimation="accordionVertical">
					{rows}
					<tr>
						<td>
							<form action="#" onSubmit={this.createRow}>
								<input
									className="newgroup-name"
									value={this.state.newMountPoint}
									placeholder={t('groupfolders', 'Folder name')}
									onChange={(event) => {
										this.setState({ newMountPoint: event.target.value })
									}}/>
								<input type="submit"
									value={t('groupfolders', 'Create')}/>
							</form>
						</td>
						<td colSpan={3}/>
					</tr>
				</FlipMove>
			</table>
		</div>
	}

}

interface ManageAclSelectProps {
	folder: Folder;
	onChange: (type: string, id: string, manageAcl: boolean) => void;
	onSearch: (name: string) => Promise<{ groups: AclManage[]; users: AclManage[]; }>;
}

// eslint-disable-next-line jsdoc/require-jsdoc
function ManageAclSelect({ onChange, onSearch, folder }: ManageAclSelectProps) {
	const handleSearch = async (inputValue: string) => {
		const result = await onSearch(inputValue)
		return [...result.groups, ...result.users]
	}

	const typeLabel = (item) => {
		return item.type === 'user' ? t('groupfolders', 'User') : t('groupfolders', 'Group')
	}

	return <AsyncSelect
		loadOptions={handleSearch}
		isMulti
		cacheOptions
		defaultOptions
		defaultValue={folder.manage}
		isClearable={false}
		onChange={(option, details) => {
			if (details.action === 'select-option') {
				const addedOption = details.option
				onChange && addedOption && onChange(addedOption.type, addedOption.id, true)
			}
			if (details.action === 'remove-value') {
				const removedValue = details.removedValue
				onChange && onChange(removedValue.type, removedValue.id, false)
			}
		}}
		placeholder={t('groupfolders', 'Users/groups that can manage')}
		getOptionLabel={(option) => `${option.displayName} (${typeLabel(option)})`}
		getOptionValue={(option) => option.type + '/' + option.id }
		styles={{
			control: base => ({
				...base,
				minHeight: 25,
				backgroundColor: 'var(--color-main-background)',
				border: '2px solid var(--color-border-dark)',
				borderRadius: 'var(--border-radius-large)',
				color: 'var(--color-main-text)',
				outline: 'none',
				'&:hover': {
					borderColor: 'var(--color-primary-element)',
				},
			}),
			option: (base, state) => ({
				...base,
				backgroundColor: state.isFocused ? 'var(--color-background-dark)' : 'transparent',
			}),
			dropdownIndicator: base => ({
				...base,
				padding: 4,
			}),
			clearIndicator: base => ({
				...base,
				padding: 4,
			}),
			multiValue: base => ({
				...base,
				backgroundColor: 'var(--color-background-dark)',
				color: 'var(--color-main-text)',
			}),
			multiValueLabel: base => ({
				...base,
				color: 'var(--color-main-text)',
			}),
			valueContainer: base => ({
				...base,
				padding: '0px 6px',
			}),
			input: base => ({
				...base,
				margin: 0,
				padding: 0,
			}),
			menu: (provided) => ({
				...provided,
				backgroundColor: 'var(--color-main-background)',
				borderColor: 'var(--color-border)',
			}),
		}}
	/>
}
