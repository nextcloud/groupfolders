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
	<div v-else style="position: relative;">
		<button :disabled="disabled" @click="$emit('update', true)" v-if="state === false" class="icon-deny" v-tooltip="t('groupfolders', 'Denied')"></button>
		<button :disabled="disabled" @click="$emit('update', false)" v-else="state === true" class="icon-checkmark" v-tooltip="t('groupfolders', 'Allowed')"></button>
	</div>
</template>

<script>
	export default {
		name: 'AclInheritButton',
		props: {
			state: {
				type: Boolean,
				default: true
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
		computed: {
			isAllowed() {
				return this.state.default;
			}
		}
	}
</script>

<style scoped>
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
</style>
