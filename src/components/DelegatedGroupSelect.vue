<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { Api } from '../services/api.ts'
import { reportApiError } from '../services/apiError.ts'
import { useGroupsStore } from '../store/groups.ts'
import type { DelegationGroup } from '../types'

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

const isDisabled = computed(() => {
	const user = getCurrentUser()
	return user ? !user.isAdmin : true
})

const options = computed(() => {
	const byId = new Map<string, GroupOption>()
	for (const group of [...delegated.value, ...groupsStore.groups]) {
		byId.set(group.gid, {
			value: group.gid,
			label: group.displayName,
		})
	}
	return [...byId.values()]
})

const selected = computed<string[]>({
	get() {
		return delegated.value.map(group => group.gid)
	},
	set(ids) {
		const next = ids
			.map(id => groupsStore.groups.find(group => group.gid === id))
			.filter((group): group is DelegationGroup => group !== undefined)
		delegated.value = next
		api.updateDelegatedGroups(next, props.delegationClass).catch(error => {
			reportApiError(error, 'Error while updating delegated groups', t('groupfolders', 'Delegated groups could not be saved'))
		})
	},
})

/**
 * Option value stored in the multi-select model.
 *
 * @param option - Group choice shown in the select
 */
function reduceOption(option: GroupOption) {
	return option.value
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
	<NcSelect v-model="selected"
		class="groupfolders__delegated"
		:options="options"
		:multiple="true"
		:disabled="isDisabled"
		:reduce="reduceOption"
		label="label"
		:input-label="t('groupfolders', 'Add group')"
		:placeholder="t('groupfolders', 'Add group')" />
</template>
