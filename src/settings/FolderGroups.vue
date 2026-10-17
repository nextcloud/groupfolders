<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import type { DelegationCircle, DelegationGroup } from '../types'
import './FolderGroups.scss'

const props = withDefaults(defineProps<{
	groups: Record<string, number>
	allCircles?: DelegationCircle[]
	allGroups?: DelegationGroup[]
	edit: boolean
	readOnly?: boolean
}>(), {
	allCircles: () => [],
	allGroups: () => [],
	readOnly: false,
})

const emit = defineEmits<{
	'add-group': [name: string]
	'remove-group': [name: string]
	'set-permissions': [name: string, permissions: number]
	'show-edit': []
}>()

const permissionWrite = OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE
const permissionShare = OC.PERMISSION_SHARE
const permissionDelete = OC.PERMISSION_DELETE

const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)

const groupHeader = computed(() => isCirclesEnabled
	? t('groupfolders', 'Group or team')
	: t('groupfolders', 'Group'))

const displayNames = computed(() => Object.keys(props.groups).map(groupId => {
	return props.allCircles.find(circle => circle.singleId === groupId)?.displayName
		|| props.allGroups.find(group => group.gid === groupId)?.displayName
		|| groupId
}))

const emptyGroups = computed(() => isCirclesEnabled
	? t('groupfolders', 'No other groups or teams available')
	: t('groupfolders', 'No other groups available'))

const addPlaceholder = computed(() => isCirclesEnabled
	? t('groupfolders', 'Add group or team')
	: t('groupfolders', 'Add group'))

interface SelectOption {
	value: string
	label: string
}

const addOptions = computed(() => {
	const groupOptions = props.allGroups
		.filter(group => props.groups[group.gid] === undefined)
		.map(group => ({
			value: group.gid,
			label: group.displayName,
		}))
	const circleOptions = props.allCircles
		.filter(circle => props.groups[circle.singleId] === undefined)
		.map(circle => ({
			value: circle.singleId,
			label: t('groupfolders', '{displayName} (team)', { displayName: circle.displayName }),
		}))
	return [...groupOptions, ...circleOptions]
})

/**
 * Whether every bit in check is set on value.
 *
 * @param value - Current permission bitmask
 * @param check - Permission bits to test
 */
function hasPermissions(value: number, check: number): boolean {
	return (value & check) === check
}

/**
 * Toggle permission bits for one group.
 *
 * @param change - Permission bits to toggle
 * @param groupId - Group or team id
 * @param event - Change event from the permission checkbox
 */
function togglePermission(change: number, groupId: string, event: Event) {
	// Keep showing the saved permissions; the box updates once the parent stores the change
	(event.target as HTMLInputElement).checked = hasPermissions(props.groups[groupId], change)
	emit('set-permissions', groupId, props.groups[groupId] ^ change)
}

/**
 * Add the group or team chosen in the select.
 *
 * @param option - Selected group or team
 */
function onAdd(option: SelectOption | null) {
	if (option === null) {
		return
	}
	emit('add-group', option.value)
}
</script>

<template>
	<span v-if="readOnly && Object.keys(groups).length === 0"
		class="team-space-locked"><em>{{ t('groupfolders', 'None') }}</em></span>
	<span v-else-if="readOnly"
		class="team-space-locked">{{ displayNames.join(', ') }}</span>
	<table v-else-if="edit"
		class="group-edit"
		@click.stop>
		<thead>
			<tr>
				<th>{{ groupHeader }}</th>
				<th>{{ t('groupfolders', 'Write') }}</th>
				<th>{{ t('groupfolders', 'Share') }}</th>
				<th>{{ t('groupfolders', 'Delete') }}</th>
				<th />
			</tr>
		</thead>
		<tbody>
			<tr v-for="(permissions, groupId, index) in groups"
				:key="groupId">
				<td>{{ displayNames[index] }}</td>
				<td class="permissions">
					<input type="checkbox"
						:checked="hasPermissions(permissions, permissionWrite)"
						@change="togglePermission(permissionWrite, groupId, $event)">
				</td>
				<td class="permissions">
					<input type="checkbox"
						:checked="hasPermissions(permissions, permissionShare)"
						@change="togglePermission(permissionShare, groupId, $event)">
				</td>
				<td class="permissions">
					<input type="checkbox"
						:checked="hasPermissions(permissions, permissionDelete)"
						@change="togglePermission(permissionDelete, groupId, $event)">
				</td>
				<td>
					<button type="button"
						class="close-btn"
						:aria-label="t('groupfolders', 'Unassign')"
						@click="emit('remove-group', groupId)" />
				</td>
			</tr>
			<tr>
				<td colspan="5">
					<div v-if="addOptions.length === 0"
						class="no-options-available">
						<p>{{ emptyGroups }}</p>
					</div>
					<NcSelect v-else
						:model-value="null"
						:options="addOptions"
						label="label"
						:clearable="false"
						:placeholder="addPlaceholder"
						@update:model-value="onAdd" />
				</td>
			</tr>
		</tbody>
	</table>
	<button v-else-if="Object.keys(groups).length === 0"
		type="button"
		class="action-rename"
		:aria-label="t('groupfolders', 'Assign groups')"
		@click.stop="emit('show-edit')">
		{{ t('groupfolders', 'None') }}
	</button>
	<button v-else
		type="button"
		class="action-rename"
		:aria-label="t('groupfolders', 'Edit assigned groups: {groups}', { groups: displayNames.join(', ') })"
		@click.stop="emit('show-edit')">
		{{ displayNames.join(', ') }}
	</button>
</template>
