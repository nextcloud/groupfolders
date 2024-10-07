<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="aclEnabled && !loading" id="groupfolder-acl-container">
		<div class="groupfolder-entry">
			<div class="avatar icon-group-white" />
			<span class="username" />
		</div>
		<table>
			<thead>
				<tr>
					<th />
					<th>{{ t('groupfolders', 'Group folder') }}</th>
					<th v-tooltip="t('groupfolders', 'Read')" class="state-column">
						{{ t('groupfolders', 'Read') }}
					</th>
					<th v-tooltip="t('groupfolders', 'Write')" class="state-column">
						{{ t('groupfolders', 'Write') }}
					</th>
					<th v-if="model.type === 'dir'" v-tooltip="t('groupfolders', 'Create')" class="state-column">
						{{
							t('groupfolders', 'Create') }}
					</th>
					<th v-tooltip="t('groupfolders', 'Delete')" class="state-column">
						{{ t('groupfolders', 'Delete') }}
					</th>
					<th v-tooltip="t('groupfolders', 'Share')" class="state-column">
						{{ t('groupfolders', 'Share') }}
					</th>
					<th class="state-column" />
				</tr>
			</thead>
			<tbody v-if="!isAdmin">
				<tr>
					<td>
						<NcAvatar user="admin" :size="24" />
					</td>
					<td class="username">
						{{ t('groupfolders', 'You') }}
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_READ, {
								permissions: model.permissions,
								mask: 31,
							})"
							:read-only="true" />
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_UPDATE, {
								permissions: model.permissions,
								mask: 31,
							})"
							:read-only="true" />
					</td>
					<td v-if="model.type === 'dir'" class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_CREATE, {
								permissions: model.permissions,
								mask: 31,
							})"
							:read-only="true" />
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_DELETE, {
								permissions: model.permissions,
								mask: 31,
							})"
							:read-only="true" />
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_SHARE, {
								permissions: model.permissions,
								mask: 31,
							})"
							:read-only="true" />
					</td>
				</tr>
			</tbody>
			<tbody v-else>
				<tr v-for="item in list" :key="item.mappingType + '-' + item.mappingId">
					<td>
						<NcAvatar :user="item.mappingId" :is-no-user="item.mappingType !== 'user'" :size="24" />
					</td>
					<td v-tooltip="getFullDisplayName(item.mappingDisplayName, item.mappingType)" class="username">
						{{ getFullDisplayName(item.mappingDisplayName, item.mappingType) }}
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_READ, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update="changePermission(item, OC.PERMISSION_READ, $event)" />
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_UPDATE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update="changePermission(item, OC.PERMISSION_UPDATE, $event)" />
					</td>
					<td v-if="model.type === 'dir'" class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_CREATE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update="changePermission(item, OC.PERMISSION_CREATE, $event)" />
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_DELETE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update="changePermission(item, OC.PERMISSION_DELETE, $event)" />
					</td>
					<td class="state-column">
						<AclStateButton :state="getState(OC.PERMISSION_SHARE, item)"
							:inherited="item.inherited"
							:disabled="loading"
							@update="changePermission(item, OC.PERMISSION_SHARE, $event)" />
					</td>
					<td class="state-column">
						<NcButton v-if="item.inherited === false"
							type="tertiary"
							:v-tooltip="t('groupfolders', 'Remove access rule')"
							:aria-label="t('groupfolders', 'Remove access rule')"
							@click="removeAcl(item)">
							<template #icon>
								<Close :size="16" />
							</template>
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>
		<NcButton v-if="isAdmin && !loading && !showAclCreate"
			@click="toggleAclCreate">
			<template #icon>
				<Plus :size="16" />
			</template>
			{{ t('groupfolders', 'Add advanced permission rule') }}
		</NcButton>
		<NcSelect v-if="isAdmin && !loading && showAclCreate"
			ref="select"
			v-model="value"
			:options="options"
			:loading="isSearching"
			:filterable="false"
			:placeholder="t('groupfolders', 'Select a user or group')"
			:get-option-key="() => 'unique'"
			@input="createAcl"
			@search="searchMappings">
			<template #option="option">
				<NcAvatar :user="option.id" :is-no-user="option.type !== 'user'" />
				{{ option.label }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import Vue from 'vue'
import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import logger from '../services/logger.ts'
import BinaryTools from './../BinaryTools.js'
import client from './../client.js'
import Rule from './../model/Rule.js'
import AclStateButton, { STATES } from './AclStateButton.vue'

let searchRequestCancelSource = null

export default {
	name: 'SharingSidebarView',
	directives: {
		tooltip: Tooltip,
	},
	components: {
		NcAvatar,
		NcSelect,
		NcButton,
		AclStateButton,
		Plus,
		Close,
	},
	props: {
		fileInfo: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			aclEnabled: false,
			aclCanManage: false,
			showAclCreate: false,
			groupFolderId: null,
			loading: false,
			isSearching: false,
			options: [],
			value: null,
			model: null,
			list: [],
		}
	},
	computed: {
		isAdmin() {
			return this.aclCanManage
		},
		isNotInherited() {
			return (permission, mask) => {
				return (permission & ~mask) === 0
			}
		},
		isAllowed() {
			return (permission, permissions) => {
				return (permission & permissions) > 0
			}
		},
		getState() {
			return (permission, item) => {
				const permitted = this.isAllowed(permission, item.permissions)
				if (this.isNotInherited(permission, item.mask)) {
					return permitted ? STATES.SELF_ALLOW : STATES.SELF_DENY
				} else {
					const inheritPermitted = this.isAllowed(permission, item.inheritedPermissions)
					if (this.isNotInherited(permission, item.inheritedMask)) {
						return inheritPermitted ? STATES.INHERIT_ALLOW : STATES.INHERIT_DENY
					} else {
						return STATES.INHERIT_DEFAULT
					}
				}
			}
		},
	},
	watch: {
		fileInfo(/* newVal, oldVal */) {
			// reload ACL entries if file changes
			this.loadAcls()
		},
	},
	beforeMount() {
		// load ACL entries for initial file
		this.loadAcls()
	},
	methods: {
		loadAcls() {
			this.options = []
			this.loading = true
			this.model = JSON.parse(JSON.stringify(this.fileInfo))
			client.propFind(this.model).then((data) => {
				if (data.acls) {
					this.list = data.acls
				}
				this.inheritedAclsById = data.inheritedAclsById
				this.aclEnabled = data.aclEnabled
				this.aclCanManage = data.aclCanManage
				this.groupFolderId = data.groupFolderId
				this.loading = false
				this.searchMappings('')
			})
		},
		getFullDisplayName(displayName, type) {
			if (type === 'group') {
				return `${displayName} (${t('groupfolders', 'Group')})`
			}

			return displayName
		},
		searchMappings(query) {
			if (searchRequestCancelSource) {
				searchRequestCancelSource.cancel('Operation canceled by another search request.')
			}
			searchRequestCancelSource = axios.CancelToken.source()
			this.isSearching = true
			axios.get(generateOcsUrl(`apps/groupfolders/folders/${this.groupFolderId}/search`) + '?format=json&search=' + query, {
				cancelToken: searchRequestCancelSource.token,
			}).then((result) => {
				this.isSearching = false
				const groups = Object.values(result.data.ocs.data.groups).map((group) => {
					return {
						unique: 'group:' + group.gid,
						type: 'group',
						id: group.gid,
						displayName: group.displayName,
						label: this.getFullDisplayName(group.displayName, 'group'),
					}
				})
				const users = Object.values(result.data.ocs.data.users).map((user) => {
					return {
						unique: 'user:' + user.uid,
						type: 'user',
						id: user.uid,
						displayName: user.displayName,
						label: this.getFullDisplayName(user.displayName, 'user'),
					}
				})
				this.options = [...groups, ...users].filter((entry) => {
					// filter out existing acl rules
					return !this.list.find((existingAcl) => entry.unique === existingAcl.getUniqueMappingIdentifier())
				})
			}).catch((error) => {
				if (!axios.isCancel(error)) {
					logger.error('Failed to search results for groupfolder ACL')
				}
			})
		},
		toggleAclCreate() {
			this.showAclCreate = !this.showAclCreate
			if (this.showAclCreate) {
				Vue.nextTick(() => {
					this.$refs.select.$el.querySelector('input').focus()
				})
			}
		},
		createAcl(option) {
			this.value = null
			const rule = new Rule()
			rule.fromValues(option.type, option.id, option.displayName, 0b00000, 0b11111)
			this.list.push(rule)
			client.propPatch(this.model, this.list.filter(rule => !rule.inherited)).then(() => {
				this.showAclCreate = false
			})
		},
		removeAcl(rule) {
			const index = this.list.indexOf(rule)
			const list = this.list.concat([]) // shallow clone
			if (index > -1) {
				list.splice(index, 1)
			}
			client.propPatch(this.model, list.filter(rule => !rule.inherited)).then(() => {
				this.list.splice(index, 1)
				const inheritedAcl = this.inheritedAclsById[rule.getUniqueMappingIdentifier()]
				if (inheritedAcl != null) {
					this.list.splice(index, 0, inheritedAcl)
				}
			})

		},
		async changePermission(item, permission, $event) {
			const index = this.list.indexOf(item)
			const inherit = $event === STATES.INHERIT_ALLOW || $event === STATES.INHERIT_DENY || $event === STATES.INHERIT_DEFAULT
			const allow = $event === STATES.SELF_ALLOW
			const bit = BinaryTools.firstHigh(permission)
			const itemRestorePoint = item.clone()
			item = item.clone()
			if (inherit) {
				item.mask = BinaryTools.clear(item.mask, bit)
				// we can ignore permissions, since they are inherited
			} else {
				item.mask = BinaryTools.set(item.mask, bit)
				if (allow) {
					item.permissions = BinaryTools.set(item.permissions, bit)
				} else {
					item.permissions = BinaryTools.clear(item.permissions, bit)
				}
			}
			item.inherited = false
			Vue.set(this.list, index, item)
			this.loading = true
			try {
				await client.propPatch(this.model, this.list.filter(rule => !rule.inherited))
				logger.debug('Permissions updated successfully')
			} catch (error) {
				logger.error('Failed to save changes:', { error })
				Vue.set(this.list, index, itemRestorePoint)
				showError(error)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
	#groupfolder-acl-container {
		margin-bottom: 20px;
	}

	.groupfolder-entry {
		height: 44px;
		white-space: normal;
		display: inline-flex;
		align-items: center;
		position: relative;
	}

	.avatar.icon-group-white {
		display: inline-block;
		background-color: var(--color-primary-element, #0082c9);
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

	table td, table th {
		padding: 0
	}

	thead th {
		height: 44px;
	}

	thead th:first-child,
	tbody tr td:first-child {
		width: 24px;
		padding: 0;
		padding-left: 4px;
	}

	table .avatardiv {
		margin-top: 6px;
	}

	table thead th:nth-child(2),
	table .username {
		padding-left: 13px;
		text-overflow: ellipsis;
		overflow: hidden;
		max-width: 0;
		min-width: 50px;
	}

	.state-column {
		text-align: center;
		width: 44px !important;
		padding: 3px;
	}

	thead .state-column {
		text-overflow: ellipsis;
		overflow: hidden;
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
		width: 100%;
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
