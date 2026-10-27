<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { mdiChevronLeft, mdiChevronRight, mdiMenuDown, mdiMenuUp, mdiPencil, mdiTrashCanOutline } from '@mdi/js'
import { computed, onMounted, ref } from 'vue'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcIconSvgWrapper, NcSettingsSection, NcTextField } from '@nextcloud/vue'
import type { AclManage, DelegationCircle, Folder } from '../types/index.ts'
import AdminGroupSelect from '../components/AdminGroupSelect.vue'
import FolderEditDialog from '../components/FolderEditDialog.vue'
import SubAdminGroupSelect from '../components/SubAdminGroupSelect.vue'
import { Api } from '../services/api.ts'
import { reportApiError } from '../services/apiError.ts'
import { useGroupsStore } from '../store/groups.ts'

type SortKey = 'mount_point' | 'quota' | 'groups' | 'acl'
type FolderFilter = 'all' | 'space' | 'folder'

const bytesInOneGibibyte = 1024 ** 3
const pageSize = 50
const defaultQuotaOptions = {
	[t('groupfolders', 'Default')]: -4,
	'1 GB': bytesInOneGibibyte,
	'5 GB': bytesInOneGibibyte * 5,
	'10 GB': bytesInOneGibibyte * 10,
	[t('groupfolders', 'Unlimited')]: -3,
}

const api = new Api()
const groupsStore = useGroupsStore()

const folders = ref<Folder[]>([])
const circles = ref<DelegationCircle[]>([])
const newMountPoint = ref('')
const newACLDefaultNoPermission = ref(false)
const editingFolder = ref<Folder | null>(null)
const filter = ref('')
const folderFilter = ref<FolderFilter>('all')
const sort = ref<SortKey>('mount_point')
const sortOrder = ref(1)
const isAdminNextcloud = ref(false)
const checkAppsInstalled = ref(false)
const currentPage = ref(0)
const loading = ref(false)
const totalFolders = ref(0)

const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)

const lastPage = computed(() => Math.max(0, Math.ceil(totalFolders.value / pageSize) - 1))

const sortColumns = computed(() => [
	{
		key: 'mount_point',
		label: t('groupfolders', 'Folder name'),
	},
	{
		key: 'groups',
		label: isCirclesEnabled
			? t('groupfolders', 'Group or team')
			: t('groupfolders', 'Group'),
		title: isCirclesEnabled
			? t('groupfolders', 'Sort by number of groups or teams that have access to this folder')
			: t('groupfolders', 'Sort by number of groups that have access to this folder'),
	},
	{
		key: 'quota',
		label: t('groupfolders', 'Quota'),
	},
	{
		key: 'acl',
		label: t('groupfolders', 'Advanced Permissions'),
	},
])

const filteredFolders = computed(() => folders.value
	.filter(folder => {
		const teamSpace = isTeamSpace(folder)
		if (folderFilter.value === 'space' && !teamSpace) {
			return false
		}
		if (folderFilter.value === 'folder' && teamSpace) {
			return false
		}
		if (filter.value === '') {
			return true
		}
		return folder.mount_point.toLowerCase().includes(filter.value.toLowerCase())
	})
	.sort((a, b) => (a.sortIndex ?? 0) - (b.sortIndex ?? 0)))

const pageFolders = computed(() => {
	const start = currentPage.value * pageSize
	return filteredFolders.value.slice(start, start + pageSize)
})

const emptyMessage = computed(() => {
	if (folderFilter.value === 'space') {
		return t('groupfolders', 'No team folders yet')
	}
	if (folderFilter.value === 'folder') {
		return t('groupfolders', 'No group folders yet')
	}
	return t('groupfolders', 'No group folders or team folders yet')
})

const showAdminDelegation = computed(() => isAdminNextcloud.value && checkAppsInstalled.value)

/**
 * Whether this folder is owned by a team.
 *
 * @param folder - Folder row
 */
function isTeamSpace(folder: Folder): boolean {
	return folder.team_circle_id !== null && folder.team_circle_id !== undefined
}

/**
 * Accessible name for the control that opens the folder editor.
 *
 * @param folder - Folder row
 */
function editLabel(folder: Folder): string {
	return t('groupfolders', 'Edit "{mountPoint}"', { mountPoint: folder.mount_point })
}

/**
 * Groups and teams shown in the table, without opening the editor.
 *
 * @param folder - Folder row
 */
function groupSummary(folder: Folder): string {
	const groups = folder.groups
	if (!groups || Array.isArray(groups)) {
		return t('groupfolders', 'None')
	}
	const ids = Object.keys(groups)
	if (ids.length === 0) {
		return t('groupfolders', 'None')
	}
	return ids.map(groupId => circles.value.find(circle => circle.singleId === groupId)?.displayName
		|| groupsStore.groups.find(group => group.gid === groupId)?.displayName
		|| folder.group_details?.[groupId]?.displayName
		|| groupId).join(', ')
}

/**
 * Quota preset or file size shown in the table.
 *
 * @param folder - Folder row
 */
function quotaLabel(folder: Folder): string {
	const preset = Object.entries(defaultQuotaOptions).find(([, quota]) => quota === folder.quota)
	if (preset) {
		return preset[0]
	}
	if (folder.quota >= 0) {
		return formatFileSize(folder.quota)
	}
	return String(folder.quota)
}

/**
 * Accessible name for the delete control.
 *
 * @param folder - Folder row
 */
function deleteLabel(folder: Folder): string {
	if (isTeamSpace(folder)) {
		return t('groupfolders', 'This folder belongs to a team and cannot be deleted directly; unlink it from its team first')
	}
	return t('groupfolders', 'Delete "{mountPoint}"', { mountPoint: folder.mount_point })
}

/**
 * Tooltip for the delete control.
 *
 * @param folder - Folder row
 */
function deleteTitle(folder: Folder): string {
	if (isTeamSpace(folder)) {
		return deleteLabel(folder)
	}
	return t('groupfolders', 'Delete')
}

/**
 * Display name of the team that owns a folder.
 *
 * @param folder - Folder row
 */
function teamCircleName(folder: Folder): string {
	if (!isTeamSpace(folder)) {
		return ''
	}
	return circles.value.find(circle => circle.singleId === folder.team_circle_id)?.displayName ?? ''
}

/**
 * Rename the folder open in the dialog.
 *
 * @param name - New mount point
 */
function onDialogRename(name: string) {
	if (editingFolder.value) {
		renameFolder(editingFolder.value, name)
	}
}

/**
 * Grant access from the folder dialog.
 *
 * @param group - Group or team id
 */
function onDialogAddGroup(group: string) {
	if (editingFolder.value) {
		addGroup(editingFolder.value, group)
	}
}

/**
 * Remove access from the folder dialog.
 *
 * @param group - Group or team id
 */
function onDialogRemoveGroup(group: string) {
	if (editingFolder.value) {
		removeGroup(editingFolder.value, group)
	}
}

/**
 * Store permissions from the folder dialog.
 *
 * @param group - Group or team id
 * @param permissions - Permission bitmask
 */
function onDialogPermissions(group: string, permissions: number) {
	if (editingFolder.value) {
		setPermissions(editingFolder.value, group, permissions)
	}
}

/**
 * Store the quota from the folder dialog.
 *
 * @param quota - Quota in bytes, or a negative preset
 */
function onDialogQuota(quota: number) {
	if (editingFolder.value) {
		setQuota(editingFolder.value, quota)
	}
}

/**
 * Enable or disable advanced permissions from the folder dialog.
 *
 * @param acl - Whether advanced permissions should be enabled
 */
function onDialogAcl(acl: boolean) {
	if (editingFolder.value) {
		onAclChange(editingFolder.value, acl)
	}
}

/**
 * Search advanced-permissions managers for the folder open in the dialog.
 *
 * @param name - Search text
 */
function onDialogSearch(name: string) {
	if (!editingFolder.value) {
		return Promise.resolve({ groups: [], users: [], circles: [] })
	}
	return searchMappings(editingFolder.value, name)
}

/**
 * Update an advanced-permissions manager for the folder open in the dialog.
 *
 * @param type - Mapping type
 * @param id - Mapping id
 * @param manageAcl - Whether management is allowed
 */
function onDialogManageAcl(type: string, id: string, manageAcl: boolean) {
	if (!editingFolder.value) {
		return Promise.resolve(false)
	}
	return setManageACL(editingFolder.value, type, id, manageAcl)
}

/**
 * Create a group folder from the name field.
 */
async function createRow() {
	const mountPoint = newMountPoint.value
	if (!mountPoint) {
		return
	}
	try {
		const folder = await api.createFolder(mountPoint, newACLDefaultNoPermission.value)
		folders.value.push(folder)
		newMountPoint.value = ''
	} catch (error) {
		reportApiError(error, 'Error while creating new folder', t('groupfolders', 'Folder could not be created'))
	}
}

/**
 * Ask for confirmation, then delete the folder.
 *
 * @param folder - Folder to delete
 */
function deleteFolder(folder: Folder) {
	OC.dialogs.confirm(
		t('groupfolders', 'Are you sure you want to delete "{folderName}" and all files inside? This operation cannot be undone', { folderName: folder.mount_point }),
		t('groupfolders', 'Delete "{folderName}"?', { folderName: folder.mount_point }),
		async (confirmed) => {
			if (!confirmed) {
				return
			}
			try {
				await api.deleteFolder(folder.id)
				if (editingFolder.value?.id === folder.id) {
					editingFolder.value = null
				}
				folders.value = folders.value.filter(item => item.id !== folder.id)
			} catch (error) {
				reportApiError(error, 'Error while deleting folder', t('groupfolders', 'Folder could not be deleted'))
			}
		},
		true,
	)
}

/**
 * Grant a group or team access to a folder.
 *
 * @param folder - Folder being edited
 * @param group - Group or team id
 */
async function addGroup(folder: Folder, group: string) {
	try {
		await api.addGroup(folder.id, group)
		// Replace the map. An empty assignment is a JSON array, and adding a
		// property on that array does not update the open editor.
		const current: unknown = folder.groups
		folder.groups = {
			...(Array.isArray(current) ? {} : current as Folder['groups']),
			[group]: OC.PERMISSION_ALL,
		}
	} catch (error) {
		reportApiError(error, 'Error while adding group to folder', t('groupfolders', 'Access could not be granted'))
	}
}

/**
 * Remove a group or team from a folder.
 *
 * @param folder - Folder being edited
 * @param group - Group or team id
 */
async function removeGroup(folder: Folder, group: string) {
	try {
		await api.removeGroup(folder.id, group)
		delete folder.groups[group]
	} catch (error) {
		reportApiError(error, 'Error while removing group from folder', t('groupfolders', 'Access could not be removed'))
	}
}

/**
 * Store a new permission bitmask for one group.
 *
 * @param folder - Folder being edited
 * @param group - Group or team id
 * @param newPermissions - Permission bitmask
 */
async function setPermissions(folder: Folder, group: string, newPermissions: number) {
	try {
		await api.setPermissions(folder.id, group, newPermissions)
		folder.groups[group] = newPermissions
	} catch (error) {
		reportApiError(error, 'Error while setting group permissions', t('groupfolders', 'Permissions could not be updated'))
	}
}

/**
 * Allow or deny one user, group, or team to manage advanced permissions.
 *
 * @param folder - Folder being edited
 * @param type - Mapping type
 * @param id - Mapping id
 * @param manageACL - Whether management is allowed
 * @return Whether the change was saved
 */
async function setManageACL(folder: Folder, type: string, id: string, manageACL: boolean): Promise<boolean> {
	try {
		await api.setManageACL(folder.id, type, id, manageACL)
		return true
	} catch (error) {
		reportApiError(error, 'Error while setting advanced permissions managers', t('groupfolders', 'Managers of advanced permissions could not be updated'))
		return false
	}
}

/**
 * Find users, groups, and teams that could manage advanced permissions.
 *
 * @param folder - Folder being edited
 * @param name - Search text
 */
async function searchMappings(folder: Folder, name: string): Promise<{ groups: AclManage[], users: AclManage[], circles: AclManage[] }> {
	try {
		return await api.aclMappingSearch(folder.id, name)
	} catch (error) {
		reportApiError(error, 'Error while searching advanced permissions managers', t('groupfolders', 'Users, groups, and teams could not be searched'))
		return { groups: [], users: [], circles: [] }
	}
}

/**
 * Update the folder quota.
 *
 * @param folder - Folder being edited
 * @param quota - Quota in bytes, or a negative preset
 */
async function setQuota(folder: Folder, quota: number) {
	try {
		await api.setQuota(folder.id, quota)
		folder.quota = quota
	} catch (error) {
		reportApiError(error, 'Error while setting folder quota', t('groupfolders', 'Quota could not be updated'))
	}
}

/**
 * Rename a folder.
 *
 * @param folder - Folder being renamed
 * @param newName - New mount point
 */
async function renameFolder(folder: Folder, newName: string) {
	try {
		await api.renameFolder(folder.id, newName)
		folder.mount_point = newName
	} catch (error) {
		reportApiError(error, 'Error while renaming folder', t('groupfolders', 'Folder could not be renamed'))
	}
}

/**
 * Enable or disable advanced permissions.
 * The checkbox stays on the saved value until the request succeeds.
 *
 * @param folder - Folder being edited
 * @param acl - Whether advanced permissions should be enabled
 */
async function onAclChange(folder: Folder, acl: boolean) {
	try {
		await api.setACL(folder.id, acl)
		folder.acl = acl
	} catch (error) {
		reportApiError(error, 'Error while setting advanced permissions', t('groupfolders', 'Advanced permissions could not be updated'))
	}
}

/**
 * Show a page of folders, loading further rows from the server when needed.
 *
 * @param page - Zero-based page index
 */
async function goToPage(page: number) {
	if (loading.value) {
		return
	}

	const loadedPage = Math.floor(folders.value.length / pageSize)
	if (loadedPage <= page) {
		loading.value = true
		try {
			const next = await api.listFolders(
				folders.value.length,
				(page + 1) * pageSize - folders.value.length + 1,
				sort.value,
				sortOrder.value === 1 ? 'asc' : 'desc',
			)
			folders.value = [...folders.value, ...next]
			currentPage.value = page
		} catch (error) {
			reportApiError(error, 'Error while loading folders', t('groupfolders', 'Folders could not be loaded'))
		} finally {
			loading.value = false
		}
	} else {
		currentPage.value = page
	}
}

/**
 * aria-sort value for a column, or undefined when that column is not sorted.
 *
 * @param key - Column to describe
 */
function ariaSort(key: SortKey): 'ascending' | 'descending' | undefined {
	if (sort.value !== key) {
		return undefined
	}
	return sortOrder.value > 0 ? 'ascending' : 'descending'
}

/**
 * Sort icon path. Descending only when this column is the active descending sort.
 *
 * @param key - Column to describe
 */
function sortIcon(key: SortKey): string {
	if (sort.value === key && sortOrder.value < 0) {
		return mdiMenuDown
	}
	return mdiMenuUp
}

/**
 * Sort by a column and reload the first page.
 *
 * @param nextSort - Column to sort by
 */
async function onSortClick(nextSort: SortKey) {
	const previousSort = sort.value
	const previousOrder = sortOrder.value
	let nextOrder = sortOrder.value
	if (sort.value === nextSort) {
		nextOrder = -nextOrder
	} else {
		nextOrder = 1
	}

	sortOrder.value = nextOrder
	sort.value = nextSort

	try {
		folders.value = await api.listFolders(0, pageSize + 1, nextSort, nextOrder === 1 ? 'asc' : 'desc')
		currentPage.value = 0
		editingFolder.value = null
	} catch (error) {
		// The list is still in the old order, so the sort arrow must match it
		sort.value = previousSort
		sortOrder.value = previousOrder
		reportApiError(error, 'Error while sorting folders', t('groupfolders', 'Folders could not be sorted'))
	}
}

/**
 * Jump to the page typed into the pagination field.
 *
 * @param value - One-based page number
 */
function onGotoPage(value: string | number) {
	const page = Math.min(parseInt(String(value), 10) - 1, lastPage.value)
	if (Number.isNaN(page) || page < 0) {
		return
	}
	goToPage(page)
}

/**
 * Show only team folders, only group folders, or both, from the first page.
 *
 * @param value - Selected filter
 */
function onFolderFilter(value: FolderFilter) {
	folderFilter.value = value
	currentPage.value = 0
}

onMounted(() => {
	api.listFolders(0, pageSize + 1, sort.value, sortOrder.value === 1 ? 'asc' : 'desc').then(result => {
		folders.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading folders', t('groupfolders', 'Folders could not be loaded'))
	})
	groupsStore.fetchGroups()
	api.listCircles().then(result => {
		circles.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading teams', t('groupfolders', 'Teams could not be loaded'))
	})
	api.countFolders().then(result => {
		totalFolders.value = result
	}).catch(error => {
		reportApiError(error, 'Error while counting folders', t('groupfolders', 'Number of folders could not be loaded'))
	})

	isAdminNextcloud.value = loadState('groupfolders', 'isAdminNextcloud', false)
	checkAppsInstalled.value = loadState('groupfolders', 'checkAppsInstalled', false)

	OC.Plugins.register('OCA.Search.Core', {
		attach(search: OC.Search.Core) {
			search.setFilter('settings', (query) => {
				filter.value = query
			})
		},
	})
})
</script>

<template>
	<NcSettingsSection class="groupfolders"
		:name="t('groupfolders', 'Group folders')">
		<div v-if="showAdminDelegation"
			class="groupfolders__delegation">
			<h3 class="groupfolders__heading">{{ t('groupfolders', 'Group folder admin delegation') }}</h3>
			<p><em>{{ t('groupfolders', 'Nextcloud allows you to delegate the administration of group folders to non-admin users.') }}</em></p>
			<p><em>{{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST.') }}</em></p>
			<p class="groupfolders__note">
				<em>{{ t('groupfolders', 'They will have access to all group folders.') }}</em>
			</p>
			<AdminGroupSelect />
			<p><em>{{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST only.') }}</em></p>
			<p class="groupfolders__note">
				<em>{{ t('groupfolders', 'They will only have access to group folders for which they have advanced permissions.') }}</em>
			</p>
			<SubAdminGroupSelect />
		</div>

		<form class="groupfolders__create"
			@submit.prevent="createRow">
			<NcTextField v-model="newMountPoint"
				class="groupfolders__create-name"
				label-outside
				:label="t('groupfolders', 'Group folder name')"
				:placeholder="t('groupfolders', 'Group folder name')"
				:aria-label="t('groupfolders', 'Group folder name')" />
			<NcButton type="submit"
				variant="secondary">
				{{ t('groupfolders', 'Create group folder') }}
			</NcButton>
			<NcCheckboxRadioSwitch v-model="newACLDefaultNoPermission"
				class="groupfolders__create-default">
				{{ t('groupfolders', 'Do not grant any advanced permissions by default') }}
			</NcCheckboxRadioSwitch>
		</form>

		<div class="groupfolders__filters">
			<NcButton :pressed="folderFilter === 'all'"
				variant="secondary"
				aria-controls="groupfolders-list"
				@click="onFolderFilter('all')">
				{{ t('groupfolders', 'All') }}
			</NcButton>
			<NcButton :pressed="folderFilter === 'space'"
				variant="secondary"
				aria-controls="groupfolders-list"
				@click="onFolderFilter('space')">
				{{ t('groupfolders', 'Team folders') }}
			</NcButton>
			<NcButton :pressed="folderFilter === 'folder'"
				variant="secondary"
				aria-controls="groupfolders-list"
				@click="onFolderFilter('folder')">
				{{ t('groupfolders', 'Group folders') }}
			</NcButton>
		</div>

		<table id="groupfolders-list"
			class="groupfolders__table">
			<thead>
				<tr>
					<th v-for="column in sortColumns"
						:key="column.key"
						:aria-sort="ariaSort(column.key)">
						<NcButton class="groupfolders__sort"
							alignment="start-reverse"
							variant="tertiary"
							wide
							:title="column.title"
							@click="onSortClick(column.key)">
							<template #icon>
								<NcIconSvgWrapper v-if="sort === column.key"
									class="groupfolders__sort-icon"
									:path="sortIcon(column.key)" />
							</template>
							<span class="groupfolders__sort-text">{{ column.label }}</span>
						</NcButton>
					</th>
					<th>
						<span class="hidden-visually">{{ t('groupfolders', 'Actions') }}</span>
					</th>
				</tr>
			</thead>
			<TransitionGroup tag="tbody"
				name="groupfolders-row">
				<tr v-if="pageFolders.length === 0"
					key="empty"
					class="groupfolders__empty">
					<td colspan="5">
						{{ emptyMessage }}
					</td>
				</tr>
				<tr v-for="folder in pageFolders"
					:key="folder.id">
					<td class="groupfolders__cell groupfolders__cell--mountpoint">
						<span v-if="isTeamSpace(folder)"
							class="groupfolders__badge"
							:title="teamCircleName(folder)">
							{{ t('groupfolders', 'Team folder') }}
						</span>
						<span class="groupfolders__name"
							:class="{ 'groupfolders__locked': isTeamSpace(folder) }"
							:title="isTeamSpace(folder) ? t('groupfolders', 'This team folder belongs to a team and cannot be renamed') : undefined">
							{{ folder.mount_point }}
						</span>
					</td>
					<td class="groupfolders__cell groupfolders__cell--groups"
						:class="{ 'groupfolders__locked': isTeamSpace(folder) }">
						{{ groupSummary(folder) }}
					</td>
					<td class="groupfolders__cell groupfolders__cell--quota">
						{{ quotaLabel(folder) }}
					</td>
					<td class="groupfolders__cell groupfolders__cell--acl"
						:class="{ 'groupfolders__locked': isTeamSpace(folder) }"
						:title="isTeamSpace(folder) ? t('groupfolders', 'Advanced permissions are managed by the team') : undefined">
						{{ folder.acl ? t('groupfolders', 'Enabled') : t('groupfolders', 'Disabled') }}
					</td>
					<td class="groupfolders__cell groupfolders__cell--actions">
						<div class="groupfolders__actions">
							<NcButton variant="tertiary"
								:aria-label="editLabel(folder)"
								:title="t('groupfolders', 'Edit')"
								@click="editingFolder = folder">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPencil" />
								</template>
							</NcButton>
							<NcButton variant="tertiary"
								:aria-label="deleteLabel(folder)"
								:title="deleteTitle(folder)"
								:disabled="isTeamSpace(folder)"
								@click="deleteFolder(folder)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiTrashCanOutline" />
								</template>
							</NcButton>
						</div>
					</td>
				</tr>
			</TransitionGroup>
		</table>
		<nav class="groupfolders__pagination"
			:aria-label="t('groupfolders', 'Pagination of group folders and team folders')">
			<div class="groupfolders__pagination-spacer" />
			<ul class="groupfolders__pagination-list">
				<li>
					<NcButton variant="secondary"
						:aria-label="t('groupfolders', 'Previous')"
						:title="t('groupfolders', 'Previous')"
						:disabled="currentPage === 0 || loading"
						@click="goToPage(currentPage - 1)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiChevronLeft"
								directional />
						</template>
					</NcButton>
				</li>
				<li v-if="currentPage > 0">
					<NcButton variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: 1 })"
						@click="goToPage(0)">
						1
					</NcButton>
				</li>
				<li v-if="currentPage > 2">
					<span class="groupfolders__pagination-ellipsis"
						aria-hidden="true">&#8230;</span>
				</li>
				<li v-if="currentPage > 1">
					<NcButton variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: currentPage })"
						@click="goToPage(currentPage - 1)">
						{{ currentPage }}
					</NcButton>
				</li>
				<li>
					<NcButton variant="primary"
						:aria-label="t('groupfolders', 'Page {page}', { page: currentPage + 1 })"
						aria-current="page">
						{{ currentPage + 1 }}
					</NcButton>
				</li>
				<li v-if="(currentPage + 1) <= lastPage">
					<NcButton variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: currentPage + 2 })"
						@click="goToPage(currentPage + 1)">
						{{ currentPage + 2 }}
					</NcButton>
				</li>
				<li v-if="(currentPage + 3) <= lastPage">
					<span class="groupfolders__pagination-ellipsis"
						aria-hidden="true">&#8230;</span>
				</li>
				<li v-if="(currentPage + 2) <= lastPage">
					<NcButton variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: lastPage + 1 })"
						@click="goToPage(lastPage)">
						{{ lastPage + 1 }}
					</NcButton>
				</li>
				<li>
					<NcButton variant="secondary"
						:aria-label="t('groupfolders', 'Next')"
						:title="t('groupfolders', 'Next')"
						:disabled="currentPage >= lastPage || loading"
						@click="goToPage(currentPage + 1)">
						<template #icon>
							<NcIconSvgWrapper :path="mdiChevronRight"
								directional />
						</template>
					</NcButton>
				</li>
			</ul>
			<div v-if="lastPage > 4"
				class="groupfolders__pagination-goto">
				<label for="groupfolders-goto-page">
					{{ t('groupfolders', 'Page:') }}
				</label>
				<NcTextField id="groupfolders-goto-page"
					class="groupfolders__pagination-page"
					type="number"
					label-outside
					:label="t('groupfolders', 'Page:')"
					:model-value="currentPage + 1"
					:min="1"
					:max="lastPage + 1"
					@update:model-value="onGotoPage" />
				<span>/ {{ lastPage + 1 }}</span>
			</div>
		</nav>
		<FolderEditDialog v-if="editingFolder"
			:folder="editingFolder"
			:circles="circles"
			:groups="groupsStore.groups"
			:quota-options="defaultQuotaOptions"
			:search-mappings="onDialogSearch"
			:set-manage-acl="onDialogManageAcl"
			@close="editingFolder = null"
			@rename="onDialogRename"
			@add-group="onDialogAddGroup"
			@remove-group="onDialogRemoveGroup"
			@set-permissions="onDialogPermissions"
			@set-quota="onDialogQuota"
			@set-acl="onDialogAcl" />
	</NcSettingsSection>
</template>

<style lang="scss" scoped>
.groupfolders {
	height: 100%;
	min-height: calc(100vh - 50px);

	&.settings-section {
		width: auto;
		padding-bottom: calc(var(--default-grid-baseline) * 10);
	}

	&__delegation {
		:deep(.groupfolders__delegated) {
			margin-bottom: 30px;
		}
	}

	&__heading {
		margin-top: 22px;
		margin-bottom: 22px;
	}

	&__note {
		margin-bottom: 20px;
	}

	&__create {
		display: flex;
		flex-flow: row nowrap;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);

		&-name {
			flex: 0 0 200px;
			width: 200px;
			max-width: calc(100% - 40px);
		}

		&-default {
			flex: 0 0 auto;
			padding: calc(var(--default-grid-baseline) * 2);
		}
	}

	&__filters {
		display: flex;
		flex-flow: row nowrap;
		align-items: center;
		gap: var(--default-grid-baseline);
		margin: 10px 0;
	}

	&__table {
		width: 100%;

		tr {
			height: 55px;
		}

		th {
			border-bottom: 2px solid var(--color-border);
			padding: 10px;
			position: relative;
			display: table-cell;
		}

		thead th {
			padding: 0;

			&:first-child {
				font-weight: bold;
				color: var(--color-main-text);
			}
		}

		tbody {
			tr:not(:last-child) {
				border-bottom: 1px solid var(--color-border);
			}

			tr:hover td {
				background-color: var(--color-background-dark);

				&:first-child {
					border-radius: 6px 0 0 6px;
				}

				&:last-child {
					border-radius: 0 6px 6px 0;
				}
			}

			td a {
				color: var(--color-text-lighter);
			}
		}
	}

	&__sort {
		--button-padding: 10px;
		color: inherit;
		font-weight: inherit;

		:deep(.button-vue__text),
		&-text {
			padding: 0;
			font-weight: inherit;
		}

		&-icon {
			color: var(--color-text-maxcontrast);
		}
	}

	&__cell {
		padding: 10px;
		position: relative;
		display: table-cell;

		&--mountpoint {
			width: 15%;
		}

		&--groups {
			white-space: normal;
		}

		&--quota {
			width: 180px;
		}

		&--actions {
			width: calc(var(--default-clickable-area) * 2);
			white-space: nowrap;
		}
	}

	&__name {
		font-weight: bold;
		color: var(--color-main-text);
	}

	&__actions {
		display: flex;
		gap: var(--default-grid-baseline);
	}

	&__empty td {
		padding: 32px 16px;
		color: var(--color-text-maxcontrast);
		text-align: center;
	}

	&__badge {
		display: inline-block;
		margin-inline-end: 6px;
		padding: 1px 6px;
		border-radius: var(--border-radius);
		background: var(--color-background-dark);
		color: var(--color-text-maxcontrast);
		font-size: 0.75em;
		font-weight: 600;
		vertical-align: middle;
	}

	&__locked {
		color: var(--color-text-maxcontrast);
		cursor: default;
		font-weight: inherit;
	}

	&__pagination {
		display: flex;
		align-items: center;
		position: absolute;
		bottom: calc(var(--default-grid-baseline) * 2);
		inset-inline-end: calc(var(--default-grid-baseline) * 2);

		&-spacer {
			flex: 1;
		}

		&-list {
			display: flex;
			gap: var(--default-grid-baseline);
			align-items: center;
			justify-content: center;
			background-color: var(--color-main-background);
			border-radius: var(--border-radius-container);
			padding: var(--default-grid-baseline);

			.button-vue {
				--button-padding: 0;
				padding: 0;
			}

			:deep(.button-vue__text) {
				padding: 0;
			}
		}

		&-ellipsis {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: var(--default-clickable-area);
			color: var(--color-text-maxcontrast);
		}

		&-goto {
			flex: 1;
			display: flex;
			justify-content: flex-end;
			align-items: center;
			gap: 6px;

			label,
			span {
				white-space: nowrap;
			}
		}

		&-page.input-field {
			width: 70px;

			:deep(.input-field__input) {
				text-align: center;
			}
		}
	}
}

.groupfolders-row-enter-active,
.groupfolders-row-leave-active {
	transition: opacity 200ms ease;
}

.groupfolders-row-enter-from,
.groupfolders-row-leave-to {
	opacity: 0;
}
</style>
