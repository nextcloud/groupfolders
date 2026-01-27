<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { INode } from '@nextcloud/files'
import type { operations } from '../types/openapi/openapi.ts'

import { mdiAccountGroupOutline, mdiInformationOutline, mdiPlus, mdiTrashCanOutline } from '@mdi/js'
import axios, { isCancel } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { Permission } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { nextTick, ref, useTemplateRef, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import AclStateButton from './AclStateButton.vue'
import BinaryTools from './../BinaryTools.js'
import { STATES } from '../model/AclButtonStates'
import Rule from './../model/Rule.ts'
import { getAcls, HintException, setAcls } from '../services/acl'
import { logger } from '../services/logger.ts'
import { useDebounceFn } from '@vueuse/core'
import svgGroup from '@mdi/svg/svg/account-multiple-outline.svg?raw'
import svgTeam from '@mdi/svg/svg/account-group-outline.svg?raw'

type IUserData = InstanceType<typeof NcSelectUsers>['$props']['options'][number]

interface MappingOption extends IUserData {
	type: 'user' | 'group' | 'circle'
	unique: string
}

const props = defineProps<{
	node: INode
}>()

const mappingSelect = useTemplateRef('select')

// ACL data from server
const aclEnabled = ref(false)
const aclCanManage = ref(false)
const groupFolderId = ref<number>()
const aclBasePermission = ref<number>(Permission.ALL)
const list = ref<Rule[]>([])
const inheritedAcls = ref<Rule[]>([])

// component state
const showAclCreate = ref(false)
const loading = ref(false)
const isSearching = ref(false)
const options = ref<MappingOption[]>([])
const value = ref<MappingOption>()
watch(value, () => {
	if (value.value) {
		createAcl(value.value)
	}
})

watch(() => props.node, async () => {
	const nodeAcls = await getAcls(props.node.path)

	aclEnabled.value = nodeAcls?.enabled ?? false
	aclCanManage.value = nodeAcls?.canManage ?? false
	groupFolderId.value = nodeAcls?.groupFolderId
	aclBasePermission.value = nodeAcls?.basePermission ?? Permission.ALL
	list.value = nodeAcls?.aclList ?? []
	inheritedAcls.value = nodeAcls?.inheritedAclList ?? []
}, { immediate: true })

/**
 * Check if the permission is inherited or self-set and return the appropriate state.
 *
 * @param permission - The permission to check
 * @param item - The ACL rule item
 */
function getState(permission: number, item) {
	// check if not inherited the permission
	if ((permission & ~item.mask) === 0) {
		return ((permission & item.permissions) > 0) ? STATES.SELF_ALLOW : STATES.SELF_DENY
	} else {
		return ((permission & item.inheritedPermissions) > 0) ? STATES.INHERIT_ALLOW : STATES.INHERIT_DENY
	}
}

/**
 * Get the full display name for a mapping, including type information.
 *
 * @param displayName - The display name of the mapping
 * @param type - The type of the mapping (user, group, circle)
 */
function getFullDisplayName(displayName: string, type: string) {
	if (type === 'group') {
		return `${displayName} (${t('groupfolders', 'Group')})`
	}
	if (type === 'circle') {
		return `${displayName} (${t('groupfolders', 'Team')})`
	}

	return displayName
}

const debouncedSearch = useDebounceFn(searchMappings, 300)

let abortController: AbortController | undefined

/**
 * Search for mappings (users, groups, circles) to add to the ACL.
 *
 * @param query - The search query
 */
async function searchMappings(query: string) {
	if (abortController) {
		abortController.abort('Operation canceled by another search request.')
	}

	abortController = new AbortController()
	isSearching.value = true

	try {
		const url = generateUrl(`apps/groupfolders/folders/{id}/search?format=json&search={search}`, {
			id: groupFolderId.value,
			search: encodeURIComponent(query),
		})

		type FolderAclMappingSearchResponse = operations['folder-acl-mapping-search']['responses'][200]['content']['application/json']
		const { data } = await axios.get<FolderAclMappingSearchResponse>(url, { signal: abortController.signal })
		const groups = Object.values(data.ocs.data.groups).map((group) => {
			return {
				unique: 'group:' + group.gid,
				isNoUser: true,
				type: 'group',
				id: group.gid,
				iconSvg: svgGroup,
				displayName: group.displayname,
			} as MappingOption
		})
		const users = Object.values(data.ocs.data.users).map((user) => {
			return {
				unique: 'user:' + user.uid,
				type: 'user',
				id: user.uid,
				user: user.uid,
				displayName: user.displayname,
			} as MappingOption
		})
		const circles = Object.values(data.ocs.data.circles).map((user) => {
			return {
				unique: 'circle:' + user.sid,
				type: 'circle',
				id: user.sid,
				displayName: user.displayname,
				iconSvg: svgTeam,
				isNoUser: true,
			} as MappingOption
		})
		options.value = [...groups, ...users, ...circles]
			// filter out existing acl rules
			.filter((entry) => list.value.every((rule) => entry.unique !== rule.getUniqueMappingIdentifier()))
	} catch (error) {
		if (!isCancel(error)) {
			logger.error('Failed to search results for groupfolder ACL', { error })
		}
	} finally {
		isSearching.value = false
	}
}

/**
 * Toggle the visibility of the ACL creation select.
 */
function toggleAclCreate() {
	showAclCreate.value = !showAclCreate.value
	if (showAclCreate.value) {
		nextTick(() => {
			const el = mappingSelect.value?.$el as HTMLElement
			const input = el?.querySelector('input')
			input?.focus()
		})
	}
}

/**
 * Create a new ACL from the given mapping option.
 * This is called when a mapping is selected from the select dropdown.
 *
 * @param option - The mapping option to create the ACL for
 */
async function createAcl(option: MappingOption) {
	value.value = undefined
	const rule = Rule.fromValues(
		option.type,
		option.id,
		option.displayName,
		0b00000,
		0b11111,
		false,
		aclBasePermission.value,
	)
	list.value.push(rule)

	await setAcls(props.node.path, list.value.filter((rule) => !rule.inherited))
	showAclCreate.value = false
}

/**
 * Remove an ACL rule.
 *
 * @param rule - The rule to remove
 */
async function removeAcl(rule: Rule) {
	const newAcls = list.value
		.filter((r) => r !== rule)

	await setAcls(props.node.path, newAcls.filter((r) => !r.inherited))
	list.value = newAcls
}

/**
 * Change permission for an ACL item.
 *
 * @param item - The ACL item to change
 * @param permission - The permission to change
 * @param state - The new state
 */
async function changePermission(item: Rule, permission: number, state: number) {
	const bit = BinaryTools.firstHigh(permission)
	const inherit = state === -1

	// Check if removed all custom overrides of an inherited permission
	// in which case we can just remove the ACL entry
	if (inherit && !item.inherited) {
		const original = inheritedAcls.value.find((r) => r.getUniqueMappingIdentifier() === item.getUniqueMappingIdentifier())
		const mask = BinaryTools.clear(item.mask, bit)
		if (original && (original.permissions & mask) === (item.permissions & mask)) {
			return await removeAcl(item)
		}
	}

	const index = list.value.indexOf(item)
	const allow = state === STATES.SELF_ALLOW
	const itemRestorePoint = item.clone()
	item = item.clone()
	if (inherit) {
		item.mask = BinaryTools.clear(item.mask, bit)
		// we can ignore permissions, since they are inherited
	} else {
		item.mask = BinaryTools.set(item.mask, bit)
		if (allow) {
			item.permissions = BinaryTools.set(item.permissions, bit)
		} else {
			item.permissions = BinaryTools.clear(item.permissions, bit)
		}
	}

	item.inherited = false
	list.value.splice(index, 1, item)
	loading.value = true
	try {
		await setAcls(props.node.path, list.value.filter((rule) => !rule.inherited))
		logger.debug('Permissions updated successfully')
	} catch (error) {
		logger.error('Failed to save changes:', { error })
		list.value.splice(index, 1, itemRestorePoint)
		if (error instanceof HintException) {
			showError(error.message)
		} else {
			showError(t('groupfolders', 'Could not save changes. Please try again.'))
		}
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div v-if="aclEnabled && !loading" id="groupfolder-acl-container">
		<h4 class="section-header">{{ t('groupfolders', 'Advanced permissions for Team folder') }}</h4>

		<table>
			<thead>
				<tr>
					<th>
						<NcIconSvgWrapper
							aria-hidden="true"
							class="groupfolder-avatar"
							inline
							:path="mdiAccountGroupOutline"
							:size="16"
							:title="node.displayname" />

						<span class="hidden-visually">
							{{ t('groupfolders', 'Group, team, or user') }}
						</span>
					</th>
					<th :title="t('groupfolders', 'Read')" class="state-column">
						{{ t('groupfolders', 'Read') }}
					</th>
					<th :title="t('groupfolders', 'Write')" class="state-column">
						{{ t('groupfolders', 'Write') }}
					</th>
					<th v-if="node?.type === 'folder'" :title="t('groupfolders', 'Create')" class="state-column">
						{{ t('groupfolders', 'Create') }}
					</th>
					<th :title="t('groupfolders', 'Delete')" class="state-column">
						{{ t('groupfolders', 'Delete') }}
					</th>
					<th :title="t('groupfolders', 'Share')" class="state-column">
						{{ t('groupfolders', 'Share') }}
					</th>
					<th class="state-column" />
				</tr>
			</thead>
			<tbody v-if="!aclCanManage">
				<tr>
					<td class="username">
						<NcAvatar user="admin" :size="24" />
						{{ t('groupfolders', 'You') }}
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.READ, {
								permissions: node.permissions,
								mask: 31,
							})"
							read-only />
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.UPDATE, {
								permissions: node.permissions,
								mask: 31,
							})"
							read-only />
					</td>
					<td v-if="node?.type === 'folder'" class="state-column">
						<AclStateButton :model-value="getState(Permission.CREATE, {
								permissions: node.permissions,
								mask: 31,
							})"
							read-only />
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.DELETE, {
								permissions: node.permissions,
								mask: 31,
							})"
							read-only />
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.SHARE, {
								permissions: node.permissions,
								mask: 31,
							})"
							read-only />
					</td>
				</tr>
			</tbody>
			<tbody v-else>
				<tr v-for="item in [...inheritedAcls, ...list]" :key="item.mappingType + '-' + item.mappingId">
					<td :title="getFullDisplayName(item.mappingDisplayName, item.mappingType)" class="username">
						<NcAvatar :user="item.mappingId" :is-no-user="item.mappingType !== 'user'" :size="24" />
						<span class="hidden-visually">{{ getFullDisplayName(item.mappingDisplayName, item.mappingType) }}</span>
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.READ, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update:model-value="changePermission(item, Permission.READ, $event)" />
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.UPDATE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update:model-value="changePermission(item, Permission.UPDATE, $event)" />
					</td>
					<td v-if="node?.type === 'folder'" class="state-column">
						<AclStateButton :model-value="getState(Permission.CREATE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update:model-value="changePermission(item, Permission.CREATE, $event)" />
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.DELETE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update:model-value="changePermission(item, Permission.DELETE, $event)" />
					</td>
					<td class="state-column">
						<AclStateButton :model-value="getState(Permission.SHARE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update="changePermission(item, Permission.SHARE, $event)" />
					</td>
					<td class="state-column">
						<NcButton v-if="item.inherited === false"
							:aria-label="t('groupfolders', 'Remove access rule')"
							:title="t('groupfolders', 'Remove access rule')"
							variant="tertiary"
							@click="removeAcl(item)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiTrashCanOutline" />
							</template>
						</NcButton>
						<NcIconSvgWrapper v-else
							:aria-label="t('groupfolders', 'Inherited permissions')"
							:title="t('groupfolders', 'Inherited permission cannot be removed')"
							:path="mdiInformationOutline" />
					</td>
				</tr>
			</tbody>
		</table>

		<NcButton v-if="aclCanManage && !loading && !showAclCreate"
			@click="toggleAclCreate">
			<template #icon>
				<NcIconSvgWrapper :path="mdiPlus" />
			</template>
			{{ t('groupfolders', 'Add new rule') }}
		</NcButton>

		<NcSelectUsers v-if="aclCanManage && !loading && showAclCreate"
			ref="select"
			v-model="value"
			:options="options"
			:loading="isSearching"
			:placeholder="t('groupfolders', 'Select a user or team')"
			@search="debouncedSearch" />
	</div>
</template>

<style scoped>
.groupfolder-avatar {
	border-radius: 50%;
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	padding: var(--default-grid-baseline);
}

	#groupfolder-acl-container {
		border-top: 2px solid var(--color-border);
		margin-bottom: 20px;
	}

	.groupfolder-entry {
		height: var(--default-clickable-area);
		white-space: normal;
		display: inline-flex;
		align-items: center;
		position: relative;
	}

	.avatar.icon-group-white {
		display: inline-block;
		background-color: var(--color-primary-element, #0082c9);
		padding: 16px;
	}

	.groupfolder-entry .username {
		padding: 0 8px;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	table {
		width: 100%;
		margin-bottom: 5px;
	}

	table td, table th {
		padding: 0
	}

	thead th {
		height: var(--default-clickable-area);
	}

	thead th:first-child,
	tbody tr td:first-child {
		width: 24px;
		padding: 0;
		padding-inline-start: 4px;
	}

	table .avatardiv {
		margin-top: 6px;
	}

.state-column {
	text-align: center;
	width: 44px !important;
	padding: 3px;
}

thead .state-column {
	text-overflow: ellipsis;
	overflow: hidden;
}

.section-header {
	margin: 0;
	margin-block: 4px 8px;
	display: flex;
	align-items: center;
	font-size: var(--default-font-size);
}
</style>
