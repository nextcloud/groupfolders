<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'
import { NcTextField } from '@nextcloud/vue'

const props = defineProps<{
	initialValue?: string
	ariaLabel?: string
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
	<form @submit.prevent="onSubmit"
		@click.stop>
		<NcTextField v-model="value"
			:label="ariaLabel"
			autofocus />
	</form>
</template>
