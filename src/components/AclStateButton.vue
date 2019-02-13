<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<div v-if="readOnly">
		<button v-if="!isAllowed" class="icon-deny" v-tooltip="t('groupfolders', 'Denied')"></button>
		<button v-else class="icon-checkmark" v-tooltip="t('groupfolders', 'Allowed')"></button>
	</div>
	<div v-else style="position: relative;" v-click-outside="popoverClose">
		<button :disabled="disabled" @click="open = true" v-if="state === STATES.INHERIT_DENY" class="icon-deny inherited" v-tooltip="t('groupfolders', 'Denied (Inherited permission)')"></button>
		<button :disabled="disabled" @click="open = true" v-else-if="state === STATES.INHERIT_ALLOW" class="icon-checkmark inherited" v-tooltip="t('groupfolders', 'Allowed (Inherited permission)')"></button>
		<button :disabled="disabled" @click="open = true" v-else-if="state === STATES.SELF_DENY" class="icon-deny" v-tooltip="t('groupfolders', 'Denied')"></button>
		<button :disabled="disabled" @click="open = true" v-else-if="state === STATES.SELF_ALLOW" class="icon-checkmark" v-tooltip="t('groupfolders', 'Allowed')"></button>
		<div class="popovermenu" :class="{open: open}"><PopoverMenu :menu="menu"></PopoverMenu></div>
	</div>
</template>

<script>
	import { PopoverMenu } from 'nextcloud-vue'

	const STATES = {
		INHERIT_DENY: 0,
		INHERIT_ALLOW: 1,
		SELF_DENY: 2,
		SELF_ALLOW: 3
	}

	export default {
		name: 'AclStateButton',
		components: {PopoverMenu},
		props: {
			state: {
				type: Number,
				default: STATES.INHERIT_DENY
			},
			readOnly: {
				type: Boolean,
				default: false
			},
			disabled: {
				type: Boolean,
				default: false
			}
		},
		methods: {
			popoverClose() {
				this.open = false
			}
		},
		computed: {
			isAllowed() {
				return this.state & 1;
			}
		},
		data() {
			return {
				STATES: STATES,
				open: false,
				menu: [
					{
						icon: 'icon-history',
						text: 'Inherit permission',
						active: this.state === STATES.INHERIT_ALLOW || this.state === STATES.INHERIT_DENY,
						action: () => {
							this.$emit('update', STATES.INHERIT_ALLOW);
							this.popoverClose()
						}
					},
					{
						icon: 'icon-close',
						text: 'Deny',
						active: this.state === STATES.SELF_DENY,
						action: () => {
							this.$emit('update', STATES.SELF_DENY);
							this.popoverClose()
						}
					},
					{
						icon: 'icon-history',
						text: 'Allow',
						active: this.state === STATES.SELF_ALLOW,
						action: () => {
							this.$emit('update', STATES.SELF_ALLOW);
							this.popoverClose()
						}
					}
				]
			}
		}
	}
</script>

<style scoped>
	.popovermenu {
		top: 38px;
		right: -5px;
	}
	button {
		height: 24px;
		border-color: transparent;
	}
	button:hover {
		height: 24px;
		border-color: var(--color-primary, #0082c9);
	}
	.icon-deny {
		background-image: url('../../img/deny.svg');
	}
	.inherited {
		opacity: 0.5;
	}
</style>
