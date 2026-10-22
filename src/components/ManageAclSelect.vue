<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import type { AclManage, Folder } from '../types'

const props = defineProps<{
	folder: Folder
	searchMappings: (name: string) => Promise<{ groups: AclManage[], users: AclManage[], circles: AclManage[] }>
	setManageAcl: (type: string, id: string, manageAcl: boolean) => Promise<boolean>
}>()

type AclOption = AclManage & { label: string }

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

const selected = ref<AclOption[]>(props.folder.manage.map(toOption))
const options = ref<AclOption[]>([])
const loading = ref(false)
let requestId = 0

/**
 * Load manager suggestions for the text typed into the select.
 *
 * @param query - Current search text from NcSelect
 */
async function onSearch(query: string) {
	const current = ++requestId
	loading.value = true
	try {
		const result = await props.searchMappings(query)
		if (current !== requestId) {
			return
		}
		options.value = [...result.groups, ...result.users, ...result.circles].map(toOption)
	} finally {
		if (current === requestId) {
			loading.value = false
		}
	}
}

/**
 * Grant or revoke management, and undo the change in the select if saving fails.
 *
 * @param option - Manager being added or removed
 * @param manageAcl - Whether management is allowed
 */
async function saveChange(option: AclOption, manageAcl: boolean) {
	if (await props.setManageAcl(option.type, option.id, manageAcl)) {
		return
	}
	const key = optionKey(option)
	if (manageAcl) {
		selected.value = selected.value.filter(item => optionKey(item) !== key)
	} else if (!selected.value.some(item => optionKey(item) === key)) {
		selected.value = [...selected.value, option]
	}
}

/**
 * Grant or revoke management when the selection changes.
 *
 * @param value - Managers currently selected
 */
function onUpdate(value: AclOption[] | AclOption | null) {
	const next = Array.isArray(value) ? value : value ? [value] : []
	const previous = selected.value
	const previousKeys = new Set(previous.map(optionKey))
	const nextKeys = new Set(next.map(optionKey))
	selected.value = next

	for (const option of next) {
		if (!previousKeys.has(optionKey(option))) {
			saveChange(option, true)
		}
	}
	for (const option of previous) {
		if (!nextKeys.has(optionKey(option))) {
			saveChange(option, false)
		}
	}
}
</script>

<template>
	<NcSelect :model-value="selected"
		class="manage-acl-select"
		:options="options"
		label="label"
		:multiple="true"
		:clearable="false"
		:filterable="false"
		:loading="loading"
		:get-option-key="optionKey"
		:placeholder="t('groupfolders', 'Users/groups that can manage')"
		@search="onSearch"
		@update:model-value="onUpdate" />
</template>

<style lang="scss" scoped>
.manage-acl-select {
	width: 100%;
}
</style>
