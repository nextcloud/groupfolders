<template>
	<div style="position: relative;" v-click-outside="popoverClose">
		<button @click="open = true" v-if="state === STATES.INHERIT_DENY" class="icon-deny inherited" v-tooltip="t('groupfolders', 'Denied (Inherited permission)')"></button>
		<button @click="open = true"  v-else-if="state === STATES.INHERIT_ALLOW" class="icon-checkmark inherited" v-tooltip="t('groupfolders', 'Allowed (Inherited permission)')"></button>
		<button @click="open = true"  v-else-if="state === STATES.SELF_DENY" class="icon-deny" v-tooltip="t('groupfolders', 'Denied')"></button>
		<button @click="open = true"  v-else-if="state === STATES.SELF_ALLOW" class="icon-checkmark" v-tooltip="t('groupfolders', 'Allowed')"></button>
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
			}
		},
		methods: {
			popoverClose() {
				this.open = false
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
							this.popoverClose()
						}
					},
					{
						icon: 'icon-close',
						text: 'Deny',
						active: this.state === STATES.SELF_DENY,
						action: () => {
							this.popoverClose()
						}
					},
					{
						icon: 'icon-history',
						text: 'Allow',
						active: this.state === STATES.SELF_ALLOW,
						action: () => {
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
