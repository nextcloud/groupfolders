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
	'submit-value': [value: string]
}>()

const value = ref(props.initialValue ?? '')

/**
 * Submit the current input text.
 */
function onSubmit() {
	emit('submit-value', value.value)
}
</script>

<template>
	<form @submit.prevent="onSubmit">
		<NcTextField v-model="value"
			:label="label"
			show-trailing-button
			trailing-button-icon="arrowEnd"
			:trailing-button-label="t('groupfolders', 'Save')"
			autofocus
			@trailing-button-click="onSubmit" />
	</form>
</template>
