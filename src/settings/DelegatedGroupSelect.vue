<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { Api } from './Api'
import { reportApiError } from './apiError'
import type { DelegationGroup } from '../types'

const props = defineProps<{
	delegationClass: string
}>()

interface GroupOption {
	value: string
	label: string
}

const api = new Api()
const groups = ref<DelegationGroup[]>([])
const delegated = ref<DelegationGroup[]>([])

const isDisabled = computed(() => {
	const user = getCurrentUser()
	return user ? !user.isAdmin : true
})

const options = computed(() => {
	const byId = new Map<string, GroupOption>()
	for (const group of [...delegated.value, ...groups.value]) {
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
			.map(id => groups.value.find(group => group.gid === id))
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
	api.listGroups().then(result => {
		groups.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading groups', t('groupfolders', 'Groups could not be loaded'))
	})
	api.listDelegatedGroups(props.delegationClass).then(result => {
		delegated.value = result
	}).catch(error => {
		reportApiError(error, 'Error while loading delegated groups', t('groupfolders', 'Delegated groups could not be loaded'))
	})
})
</script>

<template>
	<NcSelect v-model="selected"
		class="delegated-admins-select"
		:options="options"
		:multiple="true"
		:disabled="isDisabled"
		:reduce="reduceOption"
		label="label"
		:input-label="t('groupfolders', 'Add group')"
		:placeholder="t('groupfolders', 'Add group')" />
</template>
