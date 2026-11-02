<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { DelegationCircle, Folder } from '../types/index.ts'

import { mdiChevronLeft, mdiChevronRight, mdiMenuDown, mdiMenuUp, mdiPencil, mdiTrashCanOutline } from '@mdi/js'
import { showConfirmation } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref, useId } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import AdminGroupSelect from '../components/AdminGroupSelect.vue'
import FolderEditDialog from '../components/FolderEditDialog.vue'
import SubAdminGroupSelect from '../components/SubAdminGroupSelect.vue'
import { Api } from '../services/api.ts'
import { reportApiError } from '../services/apiError.ts'
import { useFoldersStore } from '../store/folders.ts'
import { useGroupsStore } from '../store/groups.ts'
import { isTeamSpace, quotaPresets } from '../utils/folder.ts'
import { checkAppsInstalled, isAdminNextcloud, isCirclesEnabled } from '../utils/initialState.ts'

type SortKey = 'mount_point' | 'quota' | 'groups' | 'acl'
type FolderFilter = 'all' | 'space' | 'folder'

interface SortColumn {
	key: SortKey
	label: string
	title?: string
}

const pageSize = 50

const api = new Api()
const foldersStore = useFoldersStore()
const groupsStore = useGroupsStore()

const tableId = useId()
const gotoPageId = useId()

const circles = ref<DelegationCircle[]>([])
const newMountPoint = ref('')
const newACLDefaultNoPermission = ref(false)
const editingFolder = ref<Folder | null>(null)
const folderFilter = ref<FolderFilter>('all')
const sort = ref<SortKey>('mount_point')
const sortOrder = ref(1)
const currentPage = ref(0)
const loading = ref(false)

const showAdminDelegation = isAdminNextcloud && checkAppsInstalled

const sortColumns: SortColumn[] = [
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
]

const lastPage = computed(() => Math.max(0, Math.ceil(foldersStore.totalFolders / pageSize) - 1))

const filteredFolders = computed(() => foldersStore.folders
	.filter((folder) => {
		if (folderFilter.value === 'space') {
			return isTeamSpace(folder)
		}
		if (folderFilter.value === 'folder') {
			return !isTeamSpace(folder)
		}
		return true
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
	return ids.map((groupId) => circles.value.find((circle) => circle.singleId === groupId)?.displayName
		|| groupsStore.groups.find((group) => group.gid === groupId)?.displayName
		|| folder.group_details?.[groupId]?.displayName
		|| groupId).join(', ')
}

/**
 * Quota preset or file size shown in the table.
 *
 * @param folder - Folder row
 */
function quotaLabel(folder: Folder): string {
	const preset = Object.entries(quotaPresets).find(([, quota]) => quota === folder.quota)
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
	return circles.value.find((circle) => circle.singleId === folder.team_circle_id)?.displayName ?? ''
}

/**
 * Create a group folder from the name field.
 */
async function createRow() {
	if (!newMountPoint.value) {
		return
	}
	if (await foldersStore.createFolder(newMountPoint.value, newACLDefaultNoPermission.value)) {
		newMountPoint.value = ''
	}
}

/**
 * Ask for confirmation, then delete the folder.
 *
 * @param folder - Folder to delete
 */
async function deleteFolder(folder: Folder) {
	const confirmed = await showConfirmation({
		name: t('groupfolders', 'Delete "{folderName}"?', { folderName: folder.mount_point }, { escape: false }),
		text: t('groupfolders', 'Are you sure you want to delete "{folderName}" and all files inside? This operation cannot be undone', { folderName: folder.mount_point }, { escape: false }),
		labelConfirm: t('groupfolders', 'Delete'),
		labelReject: t('groupfolders', 'Cancel'),
		severity: 'warning',
	})
	if (confirmed) {
		await foldersStore.deleteFolder(folder)
	}
}

/**
 * Sort direction for the API.
 *
 * @param order - 1 for ascending, -1 for descending
 */
function apiOrder(order: number): 'asc' | 'desc' {
	return order === 1 ? 'asc' : 'desc'
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

	const loadedFolders = foldersStore.folders.length
	if (Math.floor(loadedFolders / pageSize) > page) {
		currentPage.value = page
		return
	}

	loading.value = true
	const loaded = await foldersStore.loadMoreFolders((page + 1) * pageSize - loadedFolders + 1, sort.value, apiOrder(sortOrder.value))
	loading.value = false
	if (loaded) {
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
	const nextOrder = sort.value === nextSort ? -sortOrder.value : 1
	if (await foldersStore.loadFolders(pageSize + 1, nextSort, apiOrder(nextOrder))) {
		sort.value = nextSort
		sortOrder.value = nextOrder
		currentPage.value = 0
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
	foldersStore.loadFolders(pageSize + 1, sort.value, apiOrder(sortOrder.value))
	foldersStore.loadTotalFolders()
	groupsStore.fetchGroups()
	api.listCircles().then((result) => {
		circles.value = result
	}).catch((error) => {
		reportApiError(error, 'Error while loading teams', t('groupfolders', 'Teams could not be loaded'))
	})
})
</script>

<template>
	<NcSettingsSection
		class="groupfolders"
		:name="t('groupfolders', 'Group folders')">
		<div
			v-if="showAdminDelegation"
			class="groupfolders__delegation">
			<h3 class="groupfolders__heading">
				{{ t('groupfolders', 'Group folder admin delegation') }}
			</h3>
			<p><em>{{ t('groupfolders', 'Nextcloud allows you to delegate the administration of group folders to non-admin users.') }}</em></p>
			<p><em>{{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST.') }}</em></p>
			<p class="groupfolders__note">
				<em>{{ t('groupfolders', 'They will have access to all group folders.') }}</em>
			</p>
			<AdminGroupSelect class="groupfolders__delegated" />
			<p><em>{{ t('groupfolders', 'Specify below the groups that will be allowed to manage group folders and use its API/REST only.') }}</em></p>
			<p class="groupfolders__note">
				<em>{{ t('groupfolders', 'They will only have access to group folders for which they have advanced permissions.') }}</em>
			</p>
			<SubAdminGroupSelect class="groupfolders__delegated" />
		</div>

		<form
			class="groupfolders__create"
			@submit.prevent="createRow">
			<NcTextField
				v-model="newMountPoint"
				class="groupfolders__create-name"
				:label="t('groupfolders', 'Group folder name')" />
			<NcButton
				type="submit"
				variant="secondary">
				{{ t('groupfolders', 'Create group folder') }}
			</NcButton>
			<NcCheckboxRadioSwitch
				v-model="newACLDefaultNoPermission"
				class="groupfolders__create-default">
				{{ t('groupfolders', 'Do not grant any advanced permissions by default') }}
			</NcCheckboxRadioSwitch>
		</form>

		<div class="groupfolders__filters">
			<NcButton
				:pressed="folderFilter === 'all'"
				variant="secondary"
				:aria-controls="tableId"
				@click="onFolderFilter('all')">
				{{ t('groupfolders', 'All') }}
			</NcButton>
			<NcButton
				:pressed="folderFilter === 'space'"
				variant="secondary"
				:aria-controls="tableId"
				@click="onFolderFilter('space')">
				{{ t('groupfolders', 'Team folders') }}
			</NcButton>
			<NcButton
				:pressed="folderFilter === 'folder'"
				variant="secondary"
				:aria-controls="tableId"
				@click="onFolderFilter('folder')">
				{{ t('groupfolders', 'Group folders') }}
			</NcButton>
		</div>

		<table
			:id="tableId"
			class="groupfolders__table">
			<thead>
				<tr>
					<th
						v-for="column in sortColumns"
						:key="column.key"
						:aria-sort="ariaSort(column.key)">
						<NcButton
							class="groupfolders__sort"
							alignment="start-reverse"
							variant="tertiary"
							wide
							:title="column.title"
							@click="onSortClick(column.key)">
							<template #icon>
								<NcIconSvgWrapper
									v-if="sort === column.key"
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
			<TransitionGroup
				tag="tbody"
				name="groupfolders-row">
				<tr
					v-if="pageFolders.length === 0"
					key="empty"
					class="groupfolders__empty">
					<td colspan="5">
						{{ emptyMessage }}
					</td>
				</tr>
				<tr
					v-for="folder in pageFolders"
					:key="folder.id">
					<td class="groupfolders__cell groupfolders__cell--mountpoint">
						<span
							v-if="isTeamSpace(folder)"
							class="groupfolders__badge"
							:title="teamCircleName(folder)">
							{{ t('groupfolders', 'Team folder') }}
						</span>
						<span
							class="groupfolders__name"
							:class="{ 'groupfolders__name--locked': isTeamSpace(folder) }"
							:title="isTeamSpace(folder) ? t('groupfolders', 'This team folder belongs to a team and cannot be renamed') : undefined">
							{{ folder.mount_point }}
						</span>
					</td>
					<td
						class="groupfolders__cell groupfolders__cell--groups"
						:class="{ 'groupfolders__cell--locked': isTeamSpace(folder) }">
						{{ groupSummary(folder) }}
					</td>
					<td class="groupfolders__cell groupfolders__cell--quota">
						{{ quotaLabel(folder) }}
					</td>
					<td
						class="groupfolders__cell groupfolders__cell--acl"
						:class="{ 'groupfolders__cell--locked': isTeamSpace(folder) }"
						:title="isTeamSpace(folder) ? t('groupfolders', 'Advanced permissions are managed by the team') : undefined">
						{{ folder.acl ? t('groupfolders', 'Enabled') : t('groupfolders', 'Disabled') }}
					</td>
					<td class="groupfolders__cell groupfolders__cell--actions">
						<div class="groupfolders__actions">
							<NcButton
								variant="tertiary"
								:aria-label="editLabel(folder)"
								:title="t('groupfolders', 'Edit')"
								@click="editingFolder = folder">
								<template #icon>
									<NcIconSvgWrapper :path="mdiPencil" />
								</template>
							</NcButton>
							<NcButton
								variant="tertiary"
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
		<nav
			class="groupfolders__pagination"
			:aria-label="t('groupfolders', 'Pagination of group folders and team folders')">
			<div class="groupfolders__pagination-spacer" />
			<ul class="groupfolders__pagination-list">
				<li>
					<NcButton
						variant="secondary"
						:aria-label="t('groupfolders', 'Previous')"
						:title="t('groupfolders', 'Previous')"
						:disabled="currentPage === 0 || loading"
						@click="goToPage(currentPage - 1)">
						<template #icon>
							<NcIconSvgWrapper
								:path="mdiChevronLeft"
								directional />
						</template>
					</NcButton>
				</li>
				<li v-if="currentPage > 0">
					<NcButton
						variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: 1 })"
						@click="goToPage(0)">
						1
					</NcButton>
				</li>
				<li v-if="currentPage > 2">
					<span
						class="groupfolders__pagination-ellipsis"
						aria-hidden="true">&#8230;</span>
				</li>
				<li v-if="currentPage > 1">
					<NcButton
						variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: currentPage })"
						@click="goToPage(currentPage - 1)">
						{{ currentPage }}
					</NcButton>
				</li>
				<li>
					<NcButton
						variant="primary"
						:aria-label="t('groupfolders', 'Page {page}', { page: currentPage + 1 })"
						aria-current="page">
						{{ currentPage + 1 }}
					</NcButton>
				</li>
				<li v-if="(currentPage + 1) <= lastPage">
					<NcButton
						variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: currentPage + 2 })"
						@click="goToPage(currentPage + 1)">
						{{ currentPage + 2 }}
					</NcButton>
				</li>
				<li v-if="(currentPage + 3) <= lastPage">
					<span
						class="groupfolders__pagination-ellipsis"
						aria-hidden="true">&#8230;</span>
				</li>
				<li v-if="(currentPage + 2) <= lastPage">
					<NcButton
						variant="secondary"
						:aria-label="t('groupfolders', 'Page {page}', { page: lastPage + 1 })"
						@click="goToPage(lastPage)">
						{{ lastPage + 1 }}
					</NcButton>
				</li>
				<li>
					<NcButton
						variant="secondary"
						:aria-label="t('groupfolders', 'Next')"
						:title="t('groupfolders', 'Next')"
						:disabled="currentPage >= lastPage || loading"
						@click="goToPage(currentPage + 1)">
						<template #icon>
							<NcIconSvgWrapper
								:path="mdiChevronRight"
								directional />
						</template>
					</NcButton>
				</li>
			</ul>
			<div
				v-if="lastPage > 4"
				class="groupfolders__pagination-goto">
				<label :for="gotoPageId">
					{{ t('groupfolders', 'Page:') }}
				</label>
				<NcTextField
					:id="gotoPageId"
					class="groupfolders__pagination-page"
					type="number"
					labelOutside
					:modelValue="currentPage + 1"
					:min="1"
					:max="lastPage + 1"
					@update:modelValue="onGotoPage" />
				<span>/ {{ lastPage + 1 }}</span>
			</div>
		</nav>
		<FolderEditDialog
			v-if="editingFolder"
			:folder="editingFolder"
			:circles="circles"
			@close="editingFolder = null" />
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

	&__delegated {
		margin-bottom: 30px;
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

		&--locked {
			color: var(--color-text-maxcontrast);
			cursor: default;
		}
	}

	&__name {
		font-weight: bold;
		color: var(--color-main-text);

		&--locked {
			color: var(--color-text-maxcontrast);
			cursor: default;
			font-weight: inherit;
		}
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
