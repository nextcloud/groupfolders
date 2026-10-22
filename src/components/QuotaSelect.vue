<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, nextTick, ref, type ComponentPublicInstance } from 'vue'
import { formatFileSize, parseFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { NcSelect, NcTextField } from '@nextcloud/vue'

const otherQuota = 'other'

interface QuotaOption {
	id: number | typeof otherQuota
	label: string
}

const props = defineProps<{
	options: Record<string, number>
	value: number
	size: number
}>()

const emit = defineEmits<{
	change: [quota: number]
}>()

const presetOptions = computed<QuotaOption[]>(() => Object.entries(props.options).map(([label, id]) => ({
	id,
	label,
})))

const selected = computed<QuotaOption>(() => {
	const match = presetOptions.value.find(option => option.id === props.value)
	if (match) {
		return match
	}
	if (props.value >= 0) {
		return {
			id: props.value,
			label: formatFileSize(props.value),
		}
	}
	return presetOptions.value[0] ?? {
		id: props.value,
		label: String(props.value),
	}
})

const isEditing = ref(false)
const isValidInput = ref(true)
const editedQuota = ref('')
const quotaSelect = ref<ComponentPublicInstance | null>(null)
/** vue-select opens on focus, including the focus the dialog applies on open. */
const openedByUser = ref(false)

type VueSelectState = { open: boolean }

/**
 * The vue-select instance rendered by NcSelect.
 *
 * @param field - Quota select component
 */
function vueSelectState(field: ComponentPublicInstance | null): VueSelectState | null {
	const component = field as {
		$?: { subTree?: { component?: { proxy?: VueSelectState } } }
	} | null
	const select = component?.$?.subTree?.component?.proxy
	if (!select || typeof select.open !== 'boolean') {
		return null
	}
	return select
}

/**
 * Hide the menu while the only focus came from the dialog opening.
 *
 * @param select - vue-select state passed by its dropdownShouldOpen prop
 */
function dropdownShouldOpen(select: { noDrop: boolean, open: boolean, mutableLoading: boolean }): boolean {
	if (!openedByUser.value) {
		return false
	}
	return select.noDrop ? false : select.open && !select.mutableLoading
}

/**
 * Allow the menu to open from the next click or key press.
 */
function markOpenedByUser() {
	openedByUser.value = true
}

/**
 * Close the menu when focus arrives without a user action on the field.
 */
function closeAutomaticOpen() {
	nextTick(() => {
		if (openedByUser.value) {
			return
		}
		const select = vueSelectState(quotaSelect.value)
		if (select) {
			select.open = false
		}
	})
}

const selectOptions = computed(() => {
	const options = presetOptions.value.some(option => option.id === selected.value.id)
		? [...presetOptions.value]
		: [...presetOptions.value, selected.value]
	options.push({
		id: otherQuota,
		label: t('groupfolders', 'Other …'),
	})
	return options
})

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
	return selectOptions.value.find(option => option.id === size) ?? {
		id: size,
		label: formatFileSize(size),
	}
}

/**
 * Option value stored for the current quota.
 *
 * @param option - Quota choice shown in the select
 */
function reduceQuota(option: QuotaOption): number | typeof otherQuota {
	return option.id
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
	emit('change', size)
}

/**
 * Discard the typed quota when focus leaves the field.
 *
 * @param event - Focus event from the quota editor
 */
function onQuotaFocusOut(event: FocusEvent) {
	const next = event.relatedTarget
	if (next instanceof Node && event.currentTarget instanceof Node && event.currentTarget.contains(next)) {
		return
	}
	isEditing.value = false
	isValidInput.value = true
}

/**
 * Focus the free-text quota field when it is shown.
 * Waiting one tick lets the quota select finish closing first.
 *
 * @param field - Mounted text field, or null once it is removed
 */
function focusQuotaField(field: Element | ComponentPublicInstance | null) {
	if (field === null || !('focus' in field) || typeof field.focus !== 'function') {
		return
	}
	const focus = field.focus.bind(field)
	nextTick(focus)
}

/**
 * Store a preset quota, or open the free-text field for Other.
 *
 * @param quota - Quota in bytes, a negative preset, or the Other choice
 */
function onUpdate(quota: number | typeof otherQuota | QuotaOption | null) {
	const value = typeof quota === 'object' && quota !== null ? quota.id : quota
	if (value === otherQuota) {
		editedQuota.value = ''
		isValidInput.value = true
		isEditing.value = true
		return
	}
	if (value === null || value === props.value) {
		return
	}
	emit('change', value)
}
</script>

<template>
	<div v-if="isEditing"
		class="groupfolders-quota"
		@focusout="onQuotaFocusOut"
		@keydown.enter.prevent="onEditedValue(editedQuota)">
		<NcTextField :ref="focusQuotaField"
			v-model="editedQuota"
			class="groupfolders-quota__input"
			:label="t('groupfolders', 'Quota')"
			:error="!isValidInput" />
	</div>
	<div v-else
		class="groupfolders-quota__holder"
		@focusin="closeAutomaticOpen"
		@pointerdown.capture="markOpenedByUser"
		@keydown.capture="markOpenedByUser">
		<div class="groupfolders-quota__bar"
			:style="{ width: usedPercentage + '%' }" />
		<NcSelect ref="quotaSelect"
			class="groupfolders-quota__select"
			:model-value="value"
			:options="selectOptions"
			label="label"
			:reduce="reduceQuota"
			:clearable="false"
			:taggable="true"
			:create-option="createQuota"
			:dropdown-should-open="dropdownShouldOpen"
			:aria-label-combobox="t('groupfolders', 'Quota')"
			:title="quotaTitle"
			:placeholder="t('groupfolders', 'Quota')"
			@update:model-value="onUpdate" />
	</div>
</template>

<style lang="scss" scoped>
.groupfolders-quota {
	&,
	&__input {
		--default-clickable-area: 36px;
		width: 100%;
		margin: 0;
	}

	&__input :deep(.input-field__input:focus + .input-field__label) {
		display: none;
	}

	&__holder {
		width: 100%;
		position: relative;
	}

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
