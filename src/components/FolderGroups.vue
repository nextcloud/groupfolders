<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { DelegationCircle, DelegationGroup } from '../types/index.ts'

import { mdiClose } from '@mdi/js'
import { Permission } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { isCirclesEnabled } from '../utils/initialState.ts'

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

interface SelectOption {
	value: string
	label: string
}

const permissionWrite = Permission.UPDATE | Permission.CREATE

const emptyGroups = isCirclesEnabled
	? t('groupfolders', 'No other groups or teams available')
	: t('groupfolders', 'No other groups available')

const addPlaceholder = isCirclesEnabled
	? t('groupfolders', 'Add group or team')
	: t('groupfolders', 'Add group')

const rows = computed(() => Object.entries(props.groups).map(([id, permissions]) => {
	const displayName = props.allCircles.find((circle) => circle.singleId === id)?.displayName
		|| props.allGroups.find((group) => group.gid === id)?.displayName
		|| id
	return {
		id,
		permissions,
		writeLabel: t('groupfolders', 'Write permission for {group}', { group: displayName }),
		shareLabel: t('groupfolders', 'Share permission for {group}', { group: displayName }),
		deleteLabel: t('groupfolders', 'Delete permission for {group}', { group: displayName }),
		unassignLabel: t('groupfolders', 'Unassign {group}', { group: displayName }),
		displayName,
	}
}))

const addOptions = computed<SelectOption[]>(() => {
	const groupOptions = props.allGroups
		.filter((group) => props.groups[group.gid] === undefined)
		.map((group) => ({
			value: group.gid,
			label: group.displayName,
		}))
	const circleOptions = props.allCircles
		.filter((circle) => props.groups[circle.singleId] === undefined)
		.map((circle) => ({
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
	<div class="groupfolders-groups">
		<table
			v-if="rows.length > 0"
			class="groupfolders-groups__table">
			<thead>
				<tr>
					<th />
					<th>{{ t('groupfolders', 'Write') }}</th>
					<th>{{ t('groupfolders', 'Share') }}</th>
					<th>{{ t('groupfolders', 'Delete') }}</th>
					<th />
				</tr>
			</thead>
			<tbody>
				<tr
					v-for="row in rows"
					:key="row.id">
					<td>{{ row.displayName }}</td>
					<td class="groupfolders-groups__permissions">
						<NcCheckboxRadioSwitch
							:modelValue="hasPermissions(row.permissions, permissionWrite)"
							:aria-label="row.writeLabel"
							@update:modelValue="togglePermission(permissionWrite, row.id)" />
					</td>
					<td class="groupfolders-groups__permissions">
						<NcCheckboxRadioSwitch
							:modelValue="hasPermissions(row.permissions, Permission.SHARE)"
							:aria-label="row.shareLabel"
							@update:modelValue="togglePermission(Permission.SHARE, row.id)" />
					</td>
					<td class="groupfolders-groups__permissions">
						<NcCheckboxRadioSwitch
							:modelValue="hasPermissions(row.permissions, Permission.DELETE)"
							:aria-label="row.deleteLabel"
							@update:modelValue="togglePermission(Permission.DELETE, row.id)" />
					</td>
					<td>
						<NcButton
							variant="tertiary"
							:aria-label="row.unassignLabel"
							:title="t('groupfolders', 'Unassign')"
							@click="emit('remove-group', row.id)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiClose" />
							</template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		<div class="groupfolders-groups__add">
			<div
				v-if="addOptions.length === 0"
				class="groupfolders-groups__empty">
				<p>{{ emptyGroups }}</p>
			</div>
			<NcSelect
				v-else
				:modelValue="null"
				:options="addOptions"
				label="label"
				:clearable="false"
				:aria-label-combobox="addPlaceholder"
				:placeholder="addPlaceholder"
				@update:modelValue="onAdd" />
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
}
</style>
