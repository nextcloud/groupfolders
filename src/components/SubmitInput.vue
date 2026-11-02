<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const props = defineProps<{
	label: string
	initialValue?: string
}>()

const emit = defineEmits<{
	submitValue: [value: string]
}>()

const value = ref(props.initialValue ?? '')

/**
 * Submit the current input text.
 */
function onSubmit() {
	emit('submitValue', value.value)
}
</script>

<template>
	<form @submit.prevent="onSubmit">
		<NcTextField
			v-model="value"
			:label="label"
			showTrailingButton
			trailingButtonIcon="arrowEnd"
			:trailingButtonLabel="t('groupfolders', 'Save')"
			autofocus
			@trailingButtonClick="onSubmit" />
	</form>
</template>
