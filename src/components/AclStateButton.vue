<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="readOnly">
		<NcButton v-if="!isAllowed"
			v-tooltip="t('groupfolders', 'Denied')"
			:title="t('groupfolders', 'Denied')"
			:aria-label="t('groupfolders', 'Access denied')">
			<template #icon>
				<Cancel :size="16" />
			</template>
		</NcButton>
		<NcButton v-else
			v-tooltip="t('groupfolders', 'Allowed')"
			:title="t('groupfolders', 'Allowed')"
			:aria-label="t('groupfolders', 'Access allowed')">
			<template #icon>
				<Check :size="16" />
			</template>
		</NcButton>
	</div>
	<div v-else>
		<NcActions :aria-label="label" :v-tooltip="label">
			<template #icon>
				<component :is="icon" :class="{inherited: isInherited}" :size="16" />
			</template>
			<NcActionRadio name="state"
				:checked="state === STATES.INHERIT_ALLOW || state === STATES.INHERIT_DENY"
				:disabled="disabled"
				@change="$emit('update', -1)">
				{{ t('groupfolders', 'Inherit permission') }}
			</NcActionRadio>
			<NcActionRadio name="state"
				:checked="state === STATES.SELF_DENY"
				:disabled="disabled"
				@change="$emit('update', STATES.SELF_DENY)">
				{{ t('groupfolders', 'Deny') }}
			</NcActionRadio>
			<NcActionRadio name="state"
				:checked="state === STATES.SELF_ALLOW"
				:disabled="disabled"
				@change="$emit('update', STATES.SELF_ALLOW)">
				{{ t('groupfolders', 'Allow') }}
			</NcActionRadio>
		</NcActions>
	</div>
</template>

<script>
import Check from 'vue-material-design-icons/Check.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionRadio from '@nextcloud/vue/dist/Components/NcActionRadio.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

export const STATES = {
	INHERIT_DENY: 0,
	INHERIT_ALLOW: 1,
	SELF_DENY: 2,
	SELF_ALLOW: 3,
}

export default {
	name: 'AclStateButton',
	directives: {
		tooltip: Tooltip,
	},
	components: {
		NcButton,
		NcActions,
		NcActionRadio,
		Check,
		Cancel,
	},
	props: {
		inherited: {
			type: Boolean,
			default: false,
		},
		state: {
			type: Number,
			default: STATES.INHERIT_DENY,
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
		disabled: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			STATES,
		}
	},
	computed: {
		isAllowed() {
			return this.state === STATES.INHERIT_ALLOW || this.state === STATES.SELF_ALLOW
		},
		isInherited() {
			return this.state === STATES.INHERIT_ALLOW || this.state === STATES.INHERIT_DENY
		},
		icon() {
			switch (this.state) {
			case STATES.INHERIT_ALLOW:
			case STATES.SELF_ALLOW:
				return Check
			default:
				return Cancel
			}
		},
		label() {
			switch (this.state) {
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
		},
	},
}
</script>

<style scoped>
	.inherited {
		opacity: 0.5;
		color: var(--color-text-maxcontrast);
	}
</style>
