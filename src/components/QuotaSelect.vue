<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { formatFileSize, parseFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { quotaPresets } from '../utils/folder.ts'

interface QuotaOption {
	id: number
	label: string
}

const props = defineProps<{
	value: number
	size: number
}>()

const emit = defineEmits<{
	change: [quota: number]
}>()

const presetOptions: QuotaOption[] = Object.entries(quotaPresets).map(([label, id]) => ({ id, label }))

/** vue-select opens on focus, including the focus a dialog puts on its first field when it opens. */
const userInteracted = ref(false)

const selected = computed<QuotaOption>(() => {
	const match = presetOptions.find((option) => option.id === props.value)
	if (match) {
		return match
	}
	if (props.value >= 0) {
		return {
			id: props.value,
			label: formatFileSize(props.value),
		}
	}
	return presetOptions[0]
})

const options = computed(() => presetOptions.some((option) => option.id === selected.value.id)
	? presetOptions
	: [...presetOptions, selected.value])

const usedPercentage = computed(() => {
	if (props.value >= 0) {
		return Math.min((props.size / props.value) * 100, 100)
	}

	const usedInGB = props.size / (10 * (2 ** 30))
	// Asymptotic curve approaching 50% at 10GB to visualize used space with infinite quota
	return 95 * (1 - (1 / (usedInGB + 1)))
})

const quotaTitle = computed(() => t('groupfolders', '{size} used', { size: formatFileSize(props.size) }))

/**
 * Keep the menu closed until the user interacted with the field.
 *
 * @param select - vue-select state passed by its dropdownShouldOpen prop
 * @param select.noDrop - Whether the select never shows a menu
 * @param select.open - Whether vue-select considers itself open
 * @param select.mutableLoading - Whether options are loading
 */
function dropdownShouldOpen(select: { noDrop: boolean, open: boolean, mutableLoading: boolean }): boolean {
	return userInteracted.value && !select.noDrop && select.open && !select.mutableLoading
}

/**
 * Allow the menu to open from now on.
 * When the field only got focus from the dialog, vue-select already counts as open,
 * so the first click would close it; moving focus away first lets that click open the menu.
 *
 * @param event - Pointer event on the field
 */
function onPointerDown(event: PointerEvent) {
	if (userInteracted.value) {
		return
	}
	userInteracted.value = true
	const active = document.activeElement
	if (active instanceof HTMLElement && event.currentTarget instanceof Node && event.currentTarget.contains(active)) {
		active.blur()
	}
}

/**
 * Turn a typed size into a quota option.
 * Unparseable text keeps the current quota so it is not offered as a new choice.
 *
 * @param input - Text typed into the select
 */
function createQuota(input: string): QuotaOption {
	const size = parseFileSize(input, true)
	if (size === null || size <= 0) {
		return selected.value
	}
	return options.value.find((option) => option.id === size) ?? {
		id: size,
		label: formatFileSize(size),
	}
}

/**
 * Option value stored for the current quota.
 *
 * @param option - Quota choice shown in the select
 */
function reduceQuota(option: QuotaOption): number {
	return option.id
}

/**
 * Store the chosen or typed quota.
 *
 * @param quota - Quota in bytes, or a negative preset
 */
function onUpdate(quota: number | null) {
	if (quota === null || quota === props.value) {
		return
	}
	emit('change', quota)
}
</script>

<template>
	<div
		class="groupfolders-quota"
		@pointerdown.capture="onPointerDown"
		@keydown.capture="userInteracted = true">
		<div
			class="groupfolders-quota__bar"
			:style="{ width: usedPercentage + '%' }" />
		<NcSelect
			class="groupfolders-quota__select"
			:modelValue="value"
			:options="options"
			label="label"
			:reduce="reduceQuota"
			:clearable="false"
			:taggable="true"
			:createOption="createQuota"
			:dropdownShouldOpen="dropdownShouldOpen"
			:aria-label-combobox="t('groupfolders', 'Quota')"
			:title="quotaTitle"
			:placeholder="t('groupfolders', 'Quota')"
			@update:modelValue="onUpdate" />
	</div>
</template>

<style lang="scss" scoped>
.groupfolders-quota {
	width: 100%;
	position: relative;

	&__bar {
		position: absolute;
		z-index: 0;
		top: 2px;
		bottom: 2px;
		inset-inline-start: 0;
		border-radius: var(--border-radius-element);
		background-color: var(--color-background-darker);
		pointer-events: none;
	}

	&__select.v-select.select {
		position: relative;
		z-index: 1;
		min-width: 0;
		width: 100%;
		margin: 0;

		:deep(.vs__dropdown-toggle) {
			background: transparent;
		}
	}
}
</style>
