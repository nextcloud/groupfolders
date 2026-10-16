<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'

const props = defineProps<{
	name: string
	value: string
	direction: number
}>()

const arrow = computed(() => props.direction < 0 ? '▼' : '▲')

const label = computed(() => props.direction < 0
	? t('groupfolders', 'sorted descending')
	: t('groupfolders', 'sorted ascending'))
</script>

<template>
	<span v-if="name === value">
		<span class="sort_arrow" aria-hidden="true">{{ arrow }}</span>
		<span class="hidden-visually">{{ label }}</span>
	</span>
</template>
