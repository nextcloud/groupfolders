<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { DelegationGroup } from '../types/index.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { Api } from '../services/api.ts'
import { reportApiError } from '../services/apiError.ts'
import { useGroupsStore } from '../store/groups.ts'

const props = defineProps<{
	delegationClass: string
}>()

interface GroupOption {
	value: string
	label: string
}

const api = new Api()
const groupsStore = useGroupsStore()
const delegated = ref<DelegationGroup[]>([])

const isDisabled = !getCurrentUser()?.isAdmin

/** Every known group by id, including delegated groups missing from the group list. */
const knownGroups = computed(() => new Map([...delegated.value, ...groupsStore.groups].map(group => [group.gid, group])))

const options = computed<GroupOption[]>(() => [...knownGroups.value.values()].map(group => ({
	value: group.gid,
	label: group.displayName,
})))

const selected = computed(() => delegated.value.map(group => group.gid))

/**
 * Option value stored in the multi-select model.
 *
 * @param option - Group choice shown in the select
 */
function reduceOption(option: GroupOption) {
	return option.value
}

/**
 * Save the selected groups, and restore the previous selection if saving fails.
 *
 * @param ids - Ids of the selected groups
 */
async function onUpdate(ids: string[]) {
	const previous = delegated.value
	const next = ids
		.map(id => knownGroups.value.get(id))
		.filter((group): group is DelegationGroup => group !== undefined)
	delegated.value = next
	try {
		await api.updateDelegatedGroups(next, props.delegationClass)
	} catch (error) {
		delegated.value = previous
		reportApiError(error, 'Error while updating delegated groups', t('groupfolders', 'Delegated groups could not be saved'))
	}
}

onMounted(() => {
	groupsStore.fetchGroups()
	api.listDelegatedGroups(props.delegationClass).then(result => {
		delegated.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading delegated groups', t('groupfolders', 'Delegated groups could not be loaded'))
	})
})
</script>

<template>
	<NcSelect :model-value="selected"
		:options="options"
		:multiple="true"
		:disabled="isDisabled"
		:reduce="reduceOption"
		label="label"
		:input-label="t('groupfolders', 'Add group')"
		:placeholder="t('groupfolders', 'Add group')"
		@update:model-value="onUpdate" />
</template>
