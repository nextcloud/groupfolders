<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import { computed } from 'vue'
import { mdiCancel, mdiCheck } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import { NcIconSvgWrapper } from '@nextcloud/vue'
import { STATES } from '../model/AclButtonStates'

const state = defineModel<number>({ required: true })
const props = defineProps<{
	inherited?: boolean
	disabled?: boolean
	readOnly?: boolean
}>()

const isAllowed = computed(() => state.value === STATES.INHERIT_ALLOW || state.value === STATES.SELF_ALLOW)
const inheritedValue = computed(() => (state.value === STATES.INHERIT_ALLOW || state.value === STATES.INHERIT_DENY)
	? state.value
	: -1
)

const label = computed(() => {
	switch (state.value) {
	case STATES.INHERIT_DENY:
		return t('groupfolders', 'Denied (Inherited permission)')
	case STATES.INHERIT_ALLOW:
		return t('groupfolders', 'Allowed (Inherited permission)')
	case STATES.SELF_DENY:
		return t('groupfolders', 'Denied')
	case STATES.SELF_ALLOW:
		return t('groupfolders', 'Allowed')
	}
	return ''
})
</script>

<template>
	<div v-if="readOnly">
		<NcButton v-if="!isAllowed"
			v-tooltip="t('groupfolders', 'Denied')"
			:title="t('groupfolders', 'Denied')"
			:aria-label="t('groupfolders', 'Access denied')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCancel" />
			</template>
		</NcButton>
		<NcButton v-else
			v-tooltip="t('groupfolders', 'Allowed')"
			:title="t('groupfolders', 'Allowed')"
			:aria-label="t('groupfolders', 'Access allowed')">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCheck" />
			</template>
		</NcButton>
	</div>
	<div v-else>
		<NcActions :aria-label="label" :title="label">
			<template #icon>
				<NcIconSvgWrapper :class="{ [$style.AclStateButton_inherited]: inherited }"
					:path="isAllowed ? mdiCheck : mdiCancel" />
			</template>
			<NcActionRadio name="state"
				v-model="state"
				:value="inheritedValue"
				:disabled>
				{{ t('groupfolders', 'Inherit permission') }}
			</NcActionRadio>
			<NcActionRadio name="state"
				v-model="state"
				:value="STATES.SELF_DENY"
				:disabled>
				{{ t('groupfolders', 'Deny') }}
			</NcActionRadio>
			<NcActionRadio name="state"
				v-model="state"
				:value="STATES.SELF_ALLOW"
				:disabled="disabled">
				{{ t('groupfolders', 'Allow') }}
			</NcActionRadio>
		</NcActions>
	</div>
</template>

<style module>
.AclStateButton_inherited {
	opacity: 0.5;
	color: var(--color-text-maxcontrast);
}
</style>
