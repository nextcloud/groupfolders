<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { formatFileSize, parseFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import './EditSelect.scss'

const props = defineProps<{
	options: Record<string, number>
	value: number
	size: number
}>()

const emit = defineEmits<{
	change: [quota: number]
}>()

const options = reactive<Record<string, number>>({ ...props.options })
if (props.value >= 0) {
	options[formatFileSize(props.value)] = props.value
}

const isEditing = ref(false)
const isValidInput = ref(true)

const usedPercentage = computed(() => {
	if (props.value >= 0) {
		return Math.min((props.size / props.value) * 100, 100)
	}

	const usedInGB = props.size / (10 * (2 ** 30))
	// Asymptotic curve approaching 50% at 10GB to visualize used space with infinite quota
	return 95 * (1 - (1 / (usedInGB + 1)))
})

const humanSize = computed(() => formatFileSize(props.size))

const quotaTitle = computed(() => t('settings', '{size} used', { size: humanSize.value }, { escape: false }).replace('&lt;', '<'))

/**
 * Apply a preset quota, or switch to the free-text editor.
 *
 * @param event - Change event from the quota select
 */
function onSelect(event: Event) {
	const select = event.target as HTMLSelectElement
	const selected = select.value
	// Keep showing the saved quota; the new value appears once the parent stores it
	select.value = String(props.value)
	if (selected === 'other') {
		isEditing.value = true
		return
	}
	emit('change', Number(selected))
}

/**
 * Parse a typed quota and store it when it is a valid file size.
 *
 * @param rawValue - Text entered in the quota field
 */
function onEditedValue(rawValue: string) {
	const size = parseFileSize(rawValue, true)
	if (!size) {
		isValidInput.value = false
		return
	}

	isValidInput.value = true
	isEditing.value = false
	options[formatFileSize(size)] = size
	emit('change', size)
}

/**
 * Save the typed quota when Enter is pressed.
 *
 * @param event - Key event from the quota field
 */
function onEditKeydown(event: KeyboardEvent) {
	if (event.key !== 'Enter') {
		return
	}
	const target = event.target
	if (!(target instanceof HTMLInputElement)) {
		return
	}
	onEditedValue(target.value)
}
</script>

<template>
	<input v-if="isEditing"
		class="editselect-input"
		:class="{ error: !isValidInput }"
		:aria-label="t('groupfolders', 'Quota')"
		autofocus
		@blur="isEditing = false"
		@keydown="onEditKeydown">
	<div v-else
		class="quotabar-holder">
		<div class="quotabar"
			:style="{ width: usedPercentage + '%' }" />
		<select class="editselect"
			:value="value"
			:aria-label="t('groupfolders', 'Quota')"
			:title="quotaTitle"
			@change="onSelect">
			<option v-for="(quota, label) in options"
				:key="label"
				:value="quota">
				{{ label }}
			</option>
			<option value="other">
				{{ t('groupfolders', 'Other …') }}
			</option>
		</select>
	</div>
</template>
