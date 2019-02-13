<template>
	<div>
		<div  class="groupfolder-entry">
			<div class="avatar icon-group-white"></div>
			<span class="username">{{ t('groupfolders', 'Groupfolder') }}</span>
		</div>
		<table>
			<thead>
				<tr>
					<th></th>
					<th></th>
					<th>{{ t('groupfolders', 'Read') }}</th>
					<th>{{ t('groupfolders', 'Write') }}</th>
					<th>{{ t('groupfolders', 'Share') }}</th>
					<th>{{ t('groupfolders', 'Delete') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><avatar user="admin" :size="24"></avatar></td>
					<td class="username">Username</td>
					<td><AclStateButton :state="0" /></td>
					<td><AclStateButton :state="1" /></td>
					<td><AclStateButton :state="2" /></td>
					<td><AclStateButton :state="3" /></td>
					<td><a class="icon-close" v-tooltip="t('groupfolders', 'Remove access rule')"></a></td>
				</tr>
				<tr>
					<td><avatar user="group" :isNoUser="true" :size="24"></avatar></td>
					<td>Group</td>
					<td><AclStateButton :state="0" /></td>
					<td><AclStateButton :state="1" /></td>
					<td><AclStateButton :state="2" /></td>
					<td><AclStateButton :state="3" /></td>
					<td><a class="icon-close" v-tooltip="t('groupfolders', 'Remove access rule')"></a></td>
				</tr>
			</tbody>
		</table>
		<button v-if="!showAclCreate" @click="toggleAclCreate"><span class="icon-add"></span> {{ t('groupfolders', 'Add advanced permission rule') }}</button>
		<multiselect v-if="showAclCreate" ref="multiselect" v-model="value" :options="options"></multiselect>
	</div>
</template>

<script>
	import { Avatar, Multiselect } from 'nextcloud-vue';
	import AclStateButton from './AclStateButton'
	export default {
		name: 'SharingSidebarView',
		props: ['fileModel'],
		components: {
			Avatar, Multiselect, AclStateButton
		},
		data() {
			return {
				showAclCreate: false,
				options: ['list', 'of', 'options'],
				value: null
			}
		},
		computed: {
			canShare() {
				return OC.PERMISSION_SHARE & this.fileModel.permissions !== 0
			}
		},
		methods: {
			toggleAclCreate() {
				this.showAclCreate = !this.showAclCreate;
			}
		}
	}
</script>

<style scoped>
	.groupfolder-entry {
		height: 44px;
		white-space: normal;
		display: inline-flex;
		align-items: center;
		position: relative;
	}
	.avatar.icon-group-white {
		display: inline-block;
		background-color: var(--color-primary, #0082c9);
		padding: 16px;
	}
	.groupfolder-entry .username {
		padding: 0 8px;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	table {
		width: 100%;
		margin-top: -44px;
		margin-bottom: 5px;
	}
	thead th {
		text-align: center;
		height: 44px;
	}
	tbody tr td:first-child {
		width: 24px;
		padding: 0;
		padding-left: 4px;
	}
	table .avatardiv {
		margin-top: 6px;
	}
	table .username {
		width: 50%;
	}
	table button {
		height: 26px;
		width: 24px !important;
		display: block;
		border-radius: 50%;
		margin: auto;
	}
	a.icon-close {
		display: inline-block;
		height: 24px;
		vertical-align: middle;
		background-size: 12px;
		opacity: .7;
		float: right;
	}
	a.icon-close:hover {
		opacity: 1;
	}

	.multiselect {
		margin-left: 44px;
		width: calc(100% - 44px);
	}
</style>
