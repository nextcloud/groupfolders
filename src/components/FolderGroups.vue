<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { mdiClose } from '@mdi/js'
import { computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcIconSvgWrapper, NcSelect } from '@nextcloud/vue'
import type { DelegationCircle, DelegationGroup } from '../types'

const props = withDefaults(defineProps<{
	groups: Record<string, number>
	allCircles?: DelegationCircle[]
	allGroups?: DelegationGroup[]
}>(), {
	allCircles: () => [],
	allGroups: () => [],
})

const emit = defineEmits<{
	'add-group': [name: string]
	'remove-group': [name: string]
	'set-permissions': [name: string, permissions: number]
}>()

const permissionWrite = OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE
const permissionShare = OC.PERMISSION_SHARE
const permissionDelete = OC.PERMISSION_DELETE

const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)

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
 * Accessible name for one permission checkbox.
 *
 * @param kind - Permission column
 * @param index - Row index in the group list
 */
function permissionLabel(kind: 'write' | 'share' | 'delete', index: number): string {
	const group = displayNames.value[index]
	switch (kind) {
	case 'write':
		return t('groupfolders', 'Write permission for {group}', { group })
	case 'share':
		return t('groupfolders', 'Share permission for {group}', { group })
	case 'delete':
		return t('groupfolders', 'Delete permission for {group}', { group })
	}
}

/**
 * Accessible name for the control that removes a group.
 *
 * @param index - Row index in the group list
 */
function unassignLabel(index: number): string {
	return t('groupfolders', 'Unassign {group}', { group: displayNames.value[index] })
}

/**
 * Toggle permission bits for one group.
 * The checkbox stays on the saved value until the parent stores the change.
 *
 * @param change - Permission bits to toggle
 * @param groupId - Group or team id
 */
function togglePermission(change: number, groupId: string) {
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
	<div class="groupfolders-groups"
		@click.stop>
		<table v-if="Object.keys(groups).length > 0"
			class="groupfolders-groups__table">
			<thead>
				<tr>
					<th></th>
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
					<td class="groupfolders-groups__permissions">
						<NcCheckboxRadioSwitch :model-value="hasPermissions(permissions, permissionWrite)"
							:aria-label="permissionLabel('write', index)"
							@update:model-value="togglePermission(permissionWrite, groupId)" />
					</td>
					<td class="groupfolders-groups__permissions">
						<NcCheckboxRadioSwitch :model-value="hasPermissions(permissions, permissionShare)"
							:aria-label="permissionLabel('share', index)"
							@update:model-value="togglePermission(permissionShare, groupId)" />
					</td>
					<td class="groupfolders-groups__permissions">
						<NcCheckboxRadioSwitch :model-value="hasPermissions(permissions, permissionDelete)"
							:aria-label="permissionLabel('delete', index)"
							@update:model-value="togglePermission(permissionDelete, groupId)" />
					</td>
					<td>
						<NcButton variant="tertiary"
							:aria-label="unassignLabel(index)"
							:title="t('groupfolders', 'Unassign')"
							@click="emit('remove-group', groupId)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiClose" />
							</template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		<div class="groupfolders-groups__add">
			<div v-if="addOptions.length === 0"
				class="groupfolders-groups__empty">
				<p>{{ emptyGroups }}</p>
			</div>
			<NcSelect v-else
				:model-value="null"
				:options="addOptions"
				label="label"
				:clearable="false"
				:placeholder="addPlaceholder"
				@update:model-value="onAdd" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.groupfolders-groups {
	width: 100%;
	background-color: var(--color-main-background, #fff);

	td {
		padding: 2px 10px;
	}

	td:first-child,
	th:first-child {
		min-width: 170px;
		white-space: normal;
	}

	td:last-child,
	th:last-child {
		width: var(--default-clickable-area);
	}

	&__table {
		width: 100%;
		padding-bottom: 10px;
	}

	&__add {
		width: 100%;

		.v-select {
			width: 100%;
		}
	}

	&__empty {
		padding: 2px 10px;
	}

	&__permissions {
		text-align: center;

		.checkbox-radio-switch {
			vertical-align: middle;
		}
	}

	img {
		opacity: 0;
		transition: opactity 0.5s;
	}

	tr:hover img {
		opacity: 0.5;
	}

	tr:hover img:hover {
		opacity: 1;
	}
}
</style>
