<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { Api } from './Api'
import { reportApiError } from './apiError'
import type { AclManage, DelegationCircle, DelegationGroup, Folder } from '../types'
import AdminGroupSelect from './AdminGroupSelect.vue'
import FolderGroups from './FolderGroups.vue'
import ManageAclSelect from './ManageAclSelect.vue'
import QuotaSelect from './QuotaSelect.vue'
import SortArrow from './SortArrow.vue'
import SubAdminGroupSelect from './SubAdminGroupSelect.vue'
import SubmitInput from './SubmitInput.vue'
import './App.scss'

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

const folders = ref<Folder[]>([])
const groups = ref<DelegationGroup[]>([])
const circles = ref<DelegationCircle[]>([])
const newMountPoint = ref('')
const newACLDefaultNoPermission = ref(false)
const editingGroup = ref(0)
const editingMountPoint = ref(0)
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

const groupHeader = computed(() => isCirclesEnabled
	? t('groupfolders', 'Group or team')
	: t('groupfolders', 'Group'))

const groupHeaderSort = computed(() => isCirclesEnabled
	? t('groupfolders', 'Sort by number of groups or teams that have access to this folder')
	: t('groupfolders', 'Sort by number of groups that have access to this folder'))

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
 * Accessible name for the rename control.
 *
 * @param folder - Folder row
 */
function renameLabel(folder: Folder): string {
	return t('groupfolders', 'Rename "{mountPoint}"', { mountPoint: folder.mount_point })
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
 * Advanced permissions are available from Nextcloud 16 onward.
 */
function supportACL(): boolean {
	return parseInt(OC.config.version, 10) >= 16
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
		folder.groups[group] = OC.PERMISSION_ALL
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
		editingMountPoint.value = 0
	} catch (error) {
		reportApiError(error, 'Error while renaming folder', t('groupfolders', 'Folder could not be renamed'))
	}
}

/**
 * Enable or disable advanced permissions.
 *
 * @param folder - Folder being edited
 * @param event - Change event from the advanced-permissions checkbox
 */
async function onAclChange(folder: Folder, event: Event) {
	const target = event.target
	if (!(target instanceof HTMLInputElement)) {
		return
	}
	const acl = target.checked
	try {
		await api.setACL(folder.id, acl)
		folder.acl = acl
	} catch (error) {
		// folder.acl did not change, so Vue will not reset the checkbox itself
		target.checked = folder.acl
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
 * @param event - Input event from the page field
 */
function onGotoPage(event: Event) {
	const target = event.target
	if (!(target instanceof HTMLInputElement)) {
		return
	}
	const page = Math.min(parseInt(target.value, 10) - 1, lastPage.value)
	if (page >= 0) {
		goToPage(page)
	}
}

/**
 * Close open rename and group editors.
 */
function clearEditing() {
	editingGroup.value = 0
	editingMountPoint.value = 0
}

onMounted(() => {
	api.listFolders(0, pageSize + 1, sort.value, sortOrder.value === 1 ? 'asc' : 'desc').then(result => {
		folders.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading folders', t('groupfolders', 'Folders could not be loaded'))
	})
	api.listGroups().then(result => {
		groups.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading groups', t('groupfolders', 'Groups could not be loaded'))
	})
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
	<div id="groupfolders-app-root"
		@click="clearEditing">
		<div v-if="showAdminDelegation"
			id="groupfolders-admin-delegation">
			<h3>{{ t('groupfolders', 'Group folder admin delegation') }}</h3>
			<p><em>{{ t('groupfolders', 'Nextcloud allows you to delegate the administration of group folders to non-admin users.') }}</em></p>
			<p><em>{{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST.') }}</em></p>
			<p class="end-description-delegation">
				<em>{{ t('groupfolders', 'They will have access to all group folders.') }}</em>
			</p>
			<AdminGroupSelect />
			<p><em>{{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST only.') }}</em></p>
			<p class="end-description-delegation">
				<em>{{ t('groupfolders', 'They will only have access to group folders for which they have advanced permissions.') }}</em>
			</p>
			<SubAdminGroupSelect />
		</div>

		<form action="#"
			@submit.prevent="createRow">
			<input v-model="newMountPoint"
				class="newgroup-name"
				:placeholder="t('groupfolders', 'Group folder name')"
				:aria-label="t('groupfolders', 'Group folder name')">
			<input type="submit"
				:value="t('groupfolders', 'Create group folder')">
			<label class="newgroup-permissions-default">
				<input v-model="newACLDefaultNoPermission"
					type="checkbox"
					style="vertical-align: middle">
				<span style="vertical-align: middle">{{ t('groupfolders', 'Do not grant any advanced permissions by default') }}</span>
			</label>
		</form>

		<div class="folder-filter-tabs"
			role="tablist">
			<button type="button"
				role="tab"
				:aria-selected="folderFilter === 'all'"
				:class="{ active: folderFilter === 'all' }"
				@click="folderFilter = 'all'; currentPage = 0">
				{{ t('groupfolders', 'All') }}
			</button>
			<button type="button"
				role="tab"
				:aria-selected="folderFilter === 'space'"
				:class="{ active: folderFilter === 'space' }"
				@click="folderFilter = 'space'; currentPage = 0">
				{{ t('groupfolders', 'Team folders') }}
			</button>
			<button type="button"
				role="tab"
				:aria-selected="folderFilter === 'folder'"
				:class="{ active: folderFilter === 'folder' }"
				@click="folderFilter = 'folder'; currentPage = 0">
				{{ t('groupfolders', 'Group folders') }}
			</button>
		</div>

		<table>
			<thead>
				<tr>
					<th>
						<button type="button"
							class="sort-header"
							@click="onSortClick('mount_point')">
							{{ t('groupfolders', 'Folder name') }}
							<SortArrow name="mount_point"
								:value="sort"
								:direction="sortOrder" />
						</button>
					</th>
					<th>
						<button type="button"
							class="sort-header"
							:title="groupHeaderSort"
							@click="onSortClick('groups')">
							{{ groupHeader }}
							<SortArrow name="groups"
								:value="sort"
								:direction="sortOrder" />
						</button>
					</th>
					<th>
						<button type="button"
							class="sort-header"
							@click="onSortClick('quota')">
							{{ t('groupfolders', 'Quota') }}
							<SortArrow name="quota"
								:value="sort"
								:direction="sortOrder" />
						</button>
					</th>
					<th>
						<button type="button"
							class="sort-header"
							@click="onSortClick('acl')">
							{{ t('groupfolders', 'Advanced Permissions') }}
							<SortArrow name="acl"
								:value="sort"
								:direction="sortOrder" />
						</button>
					</th>
					<th>
						<span class="hidden-visually">{{ t('groupfolders', 'Actions') }}</span>
					</th>
				</tr>
			</thead>
			<TransitionGroup tag="tbody"
				name="folder-row">
				<tr v-if="pageFolders.length === 0"
					key="empty"
					class="folder-list-empty">
					<td colspan="5">
						{{ emptyMessage }}
					</td>
				</tr>
				<tr v-for="folder in pageFolders"
					:key="folder.id">
					<td class="mountpoint">
						<span v-if="isTeamSpace(folder)"
							class="team-space-badge"
							:title="teamCircleName(folder)">
							{{ t('groupfolders', 'Team folder') }}
						</span>
						<SubmitInput v-if="editingMountPoint === folder.id && !isTeamSpace(folder)"
							:initial-value="folder.mount_point"
							:aria-label="t('groupfolders', 'Folder name')"
							@submit-value="renameFolder(folder, $event)" />
						<span v-else-if="isTeamSpace(folder)"
							class="team-space-locked"
							:title="t('groupfolders', 'This team folder belongs to a team and cannot be renamed')">
							{{ folder.mount_point }}
						</span>
						<button v-else
							type="button"
							class="action-rename"
							:aria-label="renameLabel(folder)"
							@click.stop="editingMountPoint = folder.id">
							{{ folder.mount_point }}
						</button>
					</td>
					<td class="groups">
						<FolderGroups :edit="editingGroup === folder.id && !isTeamSpace(folder)"
							:read-only="isTeamSpace(folder)"
							:groups="folder.groups"
							:all-circles="circles"
							:all-groups="groups"
							@show-edit="editingGroup = folder.id"
							@add-group="addGroup(folder, $event)"
							@remove-group="removeGroup(folder, $event)"
							@set-permissions="(group, permissions) => setPermissions(folder, group, permissions)" />
					</td>
					<td class="quota">
						<QuotaSelect :options="defaultQuotaOptions"
							:value="folder.quota"
							:size="folder.size"
							@change="setQuota(folder, $event)" />
					</td>
					<td class="acl">
						<span v-if="isTeamSpace(folder)"
							class="team-space-locked"
							:title="t('groupfolders', 'Advanced permissions are managed by the team')">
							{{ folder.acl ? t('groupfolders', 'Enabled') : t('groupfolders', 'Disabled') }}
						</span>
						<template v-else>
							<input :id="'acl-' + folder.id"
								type="checkbox"
								class="checkbox"
								:checked="folder.acl"
								:disabled="!supportACL()"
								@change="onAclChange(folder, $event)">
							<label :for="'acl-' + folder.id"
								:title="t('groupfolders', 'Advanced permissions allows setting permissions on a per-file basis but comes with a performance overhead')">
								<span class="hidden-visually">{{ t('groupfolders', 'Advanced permissions for "{mountPoint}"', { mountPoint: folder.mount_point }) }}</span>
							</label>
							<ManageAclSelect v-if="folder.acl"
								:folder="folder"
								:search-mappings="(name) => searchMappings(folder, name)"
								:set-manage-acl="(type, id, manageAcl) => setManageACL(folder, type, id, manageAcl)" />
						</template>
					</td>
					<td class="remove">
						<button v-if="isTeamSpace(folder)"
							type="button"
							class="icon icon-delete disabled"
							:aria-label="t('groupfolders', 'Delete')"
							:title="t('groupfolders', 'This folder belongs to a team and cannot be deleted directly; unlink it from its team first')"
							disabled />
						<button v-else
							type="button"
							class="icon icon-delete icon-visible"
							:aria-label="t('groupfolders', 'Delete')"
							:title="t('groupfolders', 'Delete')"
							@click="deleteFolder(folder)" />
					</td>
				</tr>
			</TransitionGroup>
		</table>
		<nav class="groupfolders-pagination"
			style="display: flex; align-items: center"
			:aria-label="t('groupfolders', 'Pagination of group folders and team folders')">
			<div style="flex: 1" />
			<ul class="groupfolders-pagination__list">
				<li>
					<button type="button"
						class="groupfolders-pagination__button"
						:aria-label="t('groupfolders', 'Previous')"
						:title="t('groupfolders', 'Previous')"
						:disabled="currentPage === 0 || loading"
						@click="goToPage(currentPage - 1)">
						⮜
					</button>
				</li>
				<li v-if="currentPage > 0">
					<button type="button"
						@click="goToPage(0)">
						1
					</button>
				</li>
				<li v-if="currentPage > 2">
					<button type="button"
						disabled>
						&#8230;
					</button>
				</li>
				<li v-if="currentPage > 1">
					<button type="button"
						@click="goToPage(currentPage - 1)">
						{{ currentPage }}
					</button>
				</li>
				<li>
					<button type="button"
						class="primary"
						aria-current="page"
						aria-disabled="true">
						{{ currentPage + 1 }}
					</button>
				</li>
				<li v-if="(currentPage + 1) <= lastPage">
					<button type="button"
						@click="goToPage(currentPage + 1)">
						{{ currentPage + 2 }}
					</button>
				</li>
				<li v-if="(currentPage + 3) <= lastPage">
					<button type="button"
						disabled>
						&#8230;
					</button>
				</li>
				<li v-if="(currentPage + 2) <= lastPage">
					<button type="button"
						@click="goToPage(lastPage)">
						{{ lastPage + 1 }}
					</button>
				</li>
				<li>
					<button type="button"
						class="groupfolders-pagination__button"
						:aria-label="t('groupfolders', 'Next')"
						:title="t('groupfolders', 'Next')"
						:disabled="currentPage >= lastPage || loading"
						@click="goToPage(currentPage + 1)">
						⮞
					</button>
				</li>
			</ul>
			<div v-if="lastPage > 4"
				class="groupfolders-pagination__goto-page">
				<label style="white-space: nowrap">
					{{ t('groupfolders', 'Page:') }}
				</label>
				<input type="number"
					:min="1"
					:max="lastPage + 1"
					:value="currentPage + 1"
					style="width: 70px; text-align: center"
					@input="onGotoPage">
				<span style="white-space: nowrap">/ {{ lastPage + 1 }}</span>
			</div>
		</nav>
	</div>
</template>
