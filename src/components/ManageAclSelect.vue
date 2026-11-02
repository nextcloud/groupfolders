<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { AclManage, Folder } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { useFoldersStore } from '../store/folders.ts'

const props = defineProps<{
	folder: Folder
}>()

type AclOption = AclManage & { label: string }

const foldersStore = useFoldersStore()

const selected = computed(() => props.folder.manage.map(toOption))
const options = ref<AclOption[]>([])
const loading = ref(false)
let requestId = 0

/**
 * Human-readable type shown next to a manager name.
 *
 * @param item - User, group, or team that can manage advanced permissions
 */
function typeLabel(item: AclManage): string {
	switch (item.type) {
		case 'circle':
			return t('groupfolders', 'Team')
		case 'group':
			return t('groupfolders', 'Group')
		case 'user':
			return t('groupfolders', 'User')
		default:
			return t('groupfolders', 'Unknown')
	}
}

/**
 * Select option for one manager.
 *
 * @param item - Manager returned by the search API
 */
function toOption(item: AclManage): AclOption {
	return {
		...item,
		label: `${item.displayname} (${typeLabel(item)})`,
	}
}

/**
 * Stable key so a user and a group with the same id stay distinct.
 *
 * @param option - Manager option
 */
function optionKey(option: AclManage): string {
	return `${option.type}/${option.id}`
}

/**
 * Load manager suggestions for the text typed into the select.
 *
 * @param query - Current search text from NcSelect
 */
async function onSearch(query: string) {
	const current = ++requestId
	loading.value = true
	const result = await foldersStore.searchManageAclMappings(props.folder, query)
	if (current !== requestId) {
		return
	}
	options.value = result.map(toOption)
	loading.value = false
}

/**
 * Grant or revoke management for the managers added to or removed from the select.
 * The select keeps showing the saved managers until the server accepted a change.
 *
 * @param value - Managers currently selected
 */
function onUpdate(value: AclOption[]) {
	const previous = [...props.folder.manage]
	const previousKeys = new Set(previous.map(optionKey))
	const nextKeys = new Set(value.map(optionKey))

	for (const option of value) {
		if (!previousKeys.has(optionKey(option))) {
			foldersStore.setManageAcl(props.folder, option, true)
		}
	}
	for (const item of previous) {
		if (!nextKeys.has(optionKey(item))) {
			foldersStore.setManageAcl(props.folder, item, false)
		}
	}
}
</script>

<template>
	<NcSelect
		:modelValue="selected"
		class="manage-acl-select"
		:options="options"
		label="label"
		:multiple="true"
		:clearable="false"
		:filterable="false"
		:loading="loading"
		:getOptionKey="optionKey"
		:aria-label-combobox="t('groupfolders', 'Users, groups, and teams that can manage advanced permissions')"
		:placeholder="t('groupfolders', 'Users/groups that can manage')"
		@search="onSearch"
		@update:modelValue="onUpdate" />
</template>

<style lang="scss" scoped>
.manage-acl-select {
	width: 100%;
}
</style>
