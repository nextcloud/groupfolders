/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FormEvent } from 'react'
import type { AclManage, DelegationCircle, DelegationGroup, Folder } from '../types/index.ts'

import { orderBy } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import * as React from 'react'
import { Component } from 'react'
import FlipMove from 'react-flip-move'
import AsyncSelect from 'react-select/async'
import AdminGroupSelect from './AdminGroupSelect.tsx'
import { Api } from './Api.ts'
import { FolderGroups } from './FolderGroups.tsx'
import { QuotaSelect } from './QuotaSelect.tsx'
import { SortArrow } from './SortArrow.tsx'
import SubAdminGroupSelect from './SubAdminGroupSelect.tsx'
import { SubmitInput } from './SubmitInput.tsx'

import './App.scss'

const bytesInOneGibibyte = Math.pow(1024, 3)
const defaultQuotaOptions = {
	[t('groupfolders', 'Default')]: -4,
	'1 GB': bytesInOneGibibyte,
	'5 GB': bytesInOneGibibyte * 5,
	'10 GB': bytesInOneGibibyte * 10,
	[t('groupfolders', 'Unlimited')]: -3,
}

const pageSize = 50

export type SortKey = 'mount_point' | 'quota' | 'groups' | 'acl'

export interface AppState {
	delegatedAdminGroups: DelegationGroup[]
	delegatedSubAdminGroups: DelegationGroup[]
	folders: Folder[]
	groups: DelegationGroup[]
	circles: DelegationCircle[]
	newMountPoint: string
	editingGroup: number
	editingMountPoint: number
	renameMountPoint: string
	filter: string
	sort: SortKey
	sortOrder: number
	isAdminNextcloud: boolean
	checkAppsInstalled: boolean
	currentPage: number
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
		currentPage: 0,
	}

	componentDidMount() {
		// list first pageSize + 1 folders so we know if there are more pages
		this.api.listFolders(0, pageSize + 1, this.state.sort).then((folders) => {
			this.setState({ folders })
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
			folders.push(folder)
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
			(confirmed) => {
				if (confirmed) {
					this.setState({ folders: this.state.folders.filter((item) => item.id !== folder.id) })
					this.api.deleteFolder(folder.id)
				}
			},
			true,
		)
	}

	addGroup(folder: Folder, group: string) {
		const folders = this.state.folders
		folder.groups[group] = OC.PERMISSION_ALL
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
		folder.groups[group] = newPermissions
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

	async goToPage(page: number) {
		const loadedPage = Math.floor(this.state.folders.length / pageSize)
		if (loadedPage <= page) {
			const folders = await this.api.listFolders(this.state.folders.length, (page + 1) * pageSize - this.state.folders.length + 1, this.state.sort)
			this.setState({
				folders: [...this.state.folders, ...folders],
				currentPage: page,
			})
		} else {
			this.setState({
				currentPage: page,
			})
		}
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
			return (
				<div id="groupfolders-admin-delegation">
					<h3>{ t('groupfolders', 'Team folder admin delegation') }</h3>
					<p><em>{ t('groupfolders', 'Nextcloud allows you to delegate the administration of Team folders to non-admin users.') }</em></p>
					<p><em>{ t('groupfolders', 'Specify below the groups that will be allowed to manage Team folders and use its API/REST.') }</em></p>
					<p className="end-description-delegation"><em>{ t('groupfolders', 'They will have access to all Team folders.') }</em></p>
					<AdminGroupSelect
						groups={this.state.groups}
						allGroups={this.state.groups}
						delegatedAdminGroups={this.state.delegatedAdminGroups}
					/>
					<p><em>{ t('groupfolders', 'Specify below the groups that will be allowed to manage Team folders and use its API/REST only.') }</em></p>
					<p className="end-description-delegation"><em>{ t('groupfolders', 'They will only have access to Team folders for which they have advanced permissions.') }</em></p>
					<SubAdminGroupSelect
						groups={this.state.groups}
						allGroups={this.state.groups}
						delegatedSubAdminGroups={this.state.delegatedSubAdminGroups}
					/>
				</div>
			)
		}
	}

	render() {
		const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)
		const groupHeader = isCirclesEnabled
			? t('groupfolders', 'Group or team')
			: t('groupfolders', 'Group')

		const groupHeaderSort = isCirclesEnabled
			? t('groupfolders', 'Sort by number of groups or teams that have access to this folder')
			: t('groupfolders', 'Sort by number of groups that have access to this folder')

		const identifiers = [
			...(this.state.sort === 'mount_point' ? [(v: Folder) => v.mount_point] : []),
			...(this.state.sort === 'quota' ? [(v: Folder) => v.quota] : []),
			...(this.state.sort === 'groups' ? [(v: Folder) => Object.keys(v.groups).length] : []),
			...(this.state.sort === 'acl' ? [(v: Folder) => v.acl] : []),
			// Always sort by the name at the end
			(v: Folder) => v.mount_point,
			// Then by ID
			(v: Folder) => v.id,
		]

		const direction = new Array(identifiers.length)
			.fill(this.state.sortOrder === 1 ? 'asc' : 'desc')

		const rows = orderBy(
			this.state.folders
				.filter((folder) => {
					if (this.state.filter === '') {
						return true
					}
					return folder.mount_point.toLowerCase().includes(this.state.filter.toLowerCase())
				}),
			identifiers,
			direction,
		)
			.slice(this.state.currentPage * pageSize, this.state.currentPage * pageSize + pageSize)
			.map((folder) => {
				const id = folder.id
				return (
					<tr key={id}>
						<td className="mountpoint">
							{this.state.editingMountPoint === id
								? (
										<SubmitInput
											autoFocus={true}
											onSubmitValue={this.renameFolder.bind(this, folder)}
											onClick={(event) => {
												event.stopPropagation()
											}}
											initialValue={folder.mount_point}
										/>
									)
								: (
										<a
											className="action-rename"
											onClick={(event) => {
												event.stopPropagation()
												this.setState({ editingMountPoint: id })
											}}
										>
											{folder.mount_point}
										</a>
									)}
						</td>
						<td className="groups">
							<FolderGroups
								edit={this.state.editingGroup === id}
								showEdit={(event) => {
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
							<QuotaSelect
								options={defaultQuotaOptions}
								value={folder.quota}
								size={folder.size}
								onChange={this.setQuota.bind(this, folder)}
							/>
						</td>
						<td className="acl">
							<input
								id={'acl-' + folder.id}
								type="checkbox"
								className="checkbox"
								checked={folder.acl}
								disabled={!App.supportACL()}
								title={
									App.supportACL()
										? t('groupfolders', 'Advanced permissions allows setting permissions on a per-file basis but comes with a performance overhead')
										: t('groupfolders', 'Advanced permissions are only supported with Nextcloud 16 and up')
								}
								onChange={(event) => this.setAcl(folder, event.target.checked)}
							/>
							<label htmlFor={'acl-' + folder.id}></label>
							{folder.acl
								&& (
									<ManageAclSelect
										folder={folder}
										onChange={this.setManageACL.bind(this, folder)}
										onSearch={this.searchMappings.bind(this, folder)}
									/>
								)}
						</td>
						<td className="remove">
							<a
								className="icon icon-delete icon-visible"
								onClick={this.deleteFolder.bind(this, folder)}
								title={t('groupfolders', 'Delete')}
							/>
						</td>
					</tr>
				)
			})

		return (
			<div
				id="groupfolders-react-root"
				onClick={() => {
					this.setState({ editingGroup: 0, editingMountPoint: 0 })
				}}
			>
				{this.showAdminDelegationForms()}
				<table>
					<thead>
						<tr>
							<th onClick={() => this.onSortClick('mount_point')}>
								{t('groupfolders', 'Folder name')}
								<SortArrow
									name="mount_point"
									value={this.state.sort}
									direction={this.state.sortOrder}
								/>
							</th>
							<th
								onClick={() => this.onSortClick('groups')}
								title={groupHeaderSort}
							>
								{groupHeader}
								<SortArrow
									name="groups"
									value={this.state.sort}
									direction={this.state.sortOrder}
								/>
							</th>
							<th onClick={() => this.onSortClick('quota')}>
								{t('groupfolders', 'Quota')}
								<SortArrow
									name="quota"
									value={this.state.sort}
									direction={this.state.sortOrder}
								/>
							</th>
							<th onClick={() => this.onSortClick('acl')}>
								{t('groupfolders', 'Advanced Permissions')}
								<SortArrow
									name="acl"
									value={this.state.sort}
									direction={this.state.sortOrder}
								/>
							</th>
							<th />
						</tr>
					</thead>
					<FlipMove typeName="tbody" enterAnimation="accordionVertical" leaveAnimation="accordionVertical">
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
										}}
									/>
									<input
										type="submit"
										value={t('groupfolders', 'Create')}
									/>
								</form>
							</td>
							<td colSpan={3} />
						</tr>
					</FlipMove>
				</table>
				<nav className="groupfolders-pagination" aria-label={t('groupfolders', 'Pagination of team folders')}>
					<ul className="groupfolders-pagination__list">
						<li>
							<button
								aria-label={t('groupfolders', 'Previous')}
								className="groupfolders-pagination__button"
								disabled={this.state.currentPage === 0}
								title={t('groupfolders', 'Previous')}
								onClick={() => this.goToPage(this.state.currentPage - 1)}
							>
								⮜
							</button>
						</li>
						{
						// show the "1" button if we are not on the first page
							this.state.currentPage > 0 && <li><button onClick={() => this.goToPage(0)}>1</button></li>
						}
						{
						// show the ellipsis button if there are more than 2 pages before the current
							this.state.currentPage > 2 && <li><button disabled>&#8230;</button></li>
						}
						{
						// show the page right before the current - if there is such a page
							this.state.currentPage > 1 && <li><button onClick={() => this.goToPage(this.state.currentPage - 1)}>{this.state.currentPage}</button></li>
						}
						{ /* the current page as a button */}
						<li><button aria-current="page" aria-disabled className="primary">{this.state.currentPage + 1}</button></li>
						{
						// show the next page if it exists (we know at least that the next exists or not)
							(this.state.currentPage + 1) < (this.state.folders.length / pageSize)
							&& (
								<li>
									<button onClick={() => this.goToPage(this.state.currentPage + 1)}>{this.state.currentPage + 2}</button>
								</li>
							)
						}
						{
						// If we know more than two next pages exist we show the ellipsis for the intermediate pages
							(this.state.currentPage + 3) < (this.state.folders.length / pageSize)
							&& (
								<li>
									<button disabled>&#8230;</button>
								</li>
							)
						}
						{
						// If more than one next page exist we show the last page as a button
							(this.state.currentPage + 2) < (this.state.folders.length / pageSize)
							&& (
								<li>
									<button onClick={() => this.goToPage(Math.floor(this.state.folders.length / pageSize))}>{Math.floor(this.state.folders.length / pageSize) + 1}</button>
								</li>
							)
						}
						<li>
							<button
								aria-label={t('groupfolders', 'Next')}
								className="groupfolders-pagination__button"
								disabled={this.state.currentPage >= Math.floor(this.state.folders.length / pageSize)}
								title={t('groupfolders', 'Next')}
								onClick={() => this.goToPage(this.state.currentPage + 1)}
							>
								⮞
							</button>
						</li>
					</ul>
				</nav>
			</div>
		)
	}
}

interface ManageAclSelectProps {
	folder: Folder
	onChange: (type: string, id: string, manageAcl: boolean) => void
	onSearch: (name: string) => Promise<{ groups: AclManage[], users: AclManage[], circles: AclManage[] }>
}

// eslint-disable-next-line jsdoc/require-jsdoc
function ManageAclSelect({ onChange, onSearch, folder }: ManageAclSelectProps) {
	const handleSearch = async (inputValue: string) => {
		const result = await onSearch(inputValue)
		return [...result.groups, ...result.users, ...result.circles]
	}

	const typeLabel = (item: AclManage) => {
		switch (item.type) {
			case 'circle':
				return t('groupfolders', 'Team')
			case 'group':
				return t('groupfolders', 'Group')
			case 'user':
				return t('groupfolders', 'User')
			default:
				return t('groupfolders', 'Unknown')
		}
	}

	return (
		<AsyncSelect
			loadOptions={handleSearch}
			isMulti
			cacheOptions
			defaultOptions
			defaultValue={folder.manage}
			isClearable={false}
			onChange={(option, details) => {
				if (details.action === 'select-option') {
					const addedOption = details.option
					if (onChange && addedOption) {
						onChange(addedOption.type, addedOption.id, true)
					}
				}
				if (details.action === 'remove-value') {
					const removedValue = details.removedValue
					if (onChange) {
						onChange(removedValue.type, removedValue.id, false)
					}
				}
			}}
			placeholder={t('groupfolders', 'Users/groups that can manage')}
			getOptionLabel={(option) => `${option.displayname} (${typeLabel(option)})`}
			getOptionValue={(option) => option.type + '/' + option.id}
			styles={{
				control: (base) => ({
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
				dropdownIndicator: (base) => ({
					...base,
					padding: 4,
				}),
				clearIndicator: (base) => ({
					...base,
					padding: 4,
				}),
				multiValue: (base) => ({
					...base,
					backgroundColor: 'var(--color-background-dark)',
					color: 'var(--color-main-text)',
				}),
				multiValueLabel: (base) => ({
					...base,
					color: 'var(--color-main-text)',
				}),
				valueContainer: (base) => ({
					...base,
					padding: '0px 6px',
				}),
				input: (base) => ({
					...base,
					margin: 0,
					padding: 0,
					color: 'var(--color-main-text)',
				}),
				menu: (provided) => ({
					...provided,
					backgroundColor: 'var(--color-main-background)',
					borderColor: 'var(--color-border)',
				}),
			}}
		/>
	)
}
