<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcDialog } from '@nextcloud/vue'
import type { AclManage, DelegationCircle, DelegationGroup, Folder } from '../types/index.ts'
import FolderGroups from './FolderGroups.vue'
import ManageAclSelect from './ManageAclSelect.vue'
import QuotaSelect from './QuotaSelect.vue'
import SubmitInput from './SubmitInput.vue'

const props = defineProps<{
	folder: Folder
	circles: DelegationCircle[]
	groups: DelegationGroup[]
	quotaOptions: Record<string, number>
	searchMappings: (name: string) => Promise<{ groups: AclManage[], users: AclManage[], circles: AclManage[] }>
	setManageAcl: (type: string, id: string, manageAcl: boolean) => Promise<boolean>
}>()

const emit = defineEmits<{
	close: []
	rename: [name: string]
	'add-group': [group: string]
	'remove-group': [group: string]
	'set-permissions': [group: string, permissions: number]
	'set-quota': [quota: number]
	'set-acl': [acl: boolean]
}>()

const isCirclesEnabled = loadState('groupfolders', 'isCirclesEnabled', false)

const teamSpace = computed(() => props.folder.team_circle_id !== null && props.folder.team_circle_id !== undefined)

const dialogName = computed(() => t('groupfolders', 'Edit "{mountPoint}"', { mountPoint: props.folder.mount_point }))

const groupHeading = computed(() => isCirclesEnabled
	? t('groupfolders', 'Group or team')
	: t('groupfolders', 'Group'))

const aclSupported = computed(() => parseInt(OC.config.version, 10) >= 16)

/**
 * Accessible name for the advanced-permissions checkbox.
 */
function aclLabel(): string {
	return t('groupfolders', 'Advanced permissions for "{mountPoint}"', { mountPoint: props.folder.mount_point })
}
</script>

<template>
	<NcDialog class="folder-edit-dialog"
		size="small"
		:name="dialogName"
		@closing="emit('close')">
		<div v-if="teamSpace"
			class="folder-edit">
			<section class="folder-edit__section">
				<h3 class="folder-edit__heading">
					{{ t('groupfolders', 'Quota') }}
				</h3>
				<div class="folder-edit__quota">
					<QuotaSelect :options="quotaOptions"
						:value="folder.quota"
						:size="folder.size"
						@change="emit('set-quota', $event)" />
				</div>
			</section>
		</div>
		<div v-else
			class="folder-edit">
			<div class="folder-edit__field">
				<SubmitInput :initial-value="folder.mount_point"
					:aria-label="t('groupfolders', 'Folder name')"
					@submit-value="emit('rename', $event)" />
			</div>

			<section class="folder-edit__section">
				<h3 class="folder-edit__heading">
					{{ groupHeading }}
				</h3>
				<FolderGroups :groups="folder.groups"
					:all-circles="circles"
					:all-groups="groups"
					@add-group="emit('add-group', $event)"
					@remove-group="emit('remove-group', $event)"
					@set-permissions="(group, permissions) => emit('set-permissions', group, permissions)" />
			</section>

			<section class="folder-edit__section">
				<h3 class="folder-edit__heading">
					{{ t('groupfolders', 'Quota') }}
				</h3>
				<div class="folder-edit__quota">
					<QuotaSelect :options="quotaOptions"
						:value="folder.quota"
						:size="folder.size"
						@change="emit('set-quota', $event)" />
				</div>
			</section>

			<section class="folder-edit__section">
				<div class="folder-edit__acl">
					<h3 class="folder-edit__heading">
						{{ t('groupfolders', 'Advanced Permissions') }}
					</h3>
					<NcCheckboxRadioSwitch class="folder-edit__acl-toggle"
						:model-value="folder.acl"
						:disabled="!aclSupported"
						:aria-label="aclLabel()"
						:title="t('groupfolders', 'Advanced permissions allows setting permissions on a per-file basis but comes with a performance overhead')"
						@update:model-value="emit('set-acl', $event)" />
				</div>
				<ManageAclSelect v-if="folder.acl"
					class="folder-edit__managers"
					:folder="folder"
					:search-mappings="searchMappings"
					:set-manage-acl="setManageAcl" />
			</section>
		</div>

		<template #actions>
			<NcButton @click="emit('close')">
				{{ t('groupfolders', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style lang="scss" scoped>
.folder-edit-dialog:deep(.modal-wrapper--small > .modal-container) {
	width: 450px;
	max-width: 90%;
}

.folder-edit {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 4);

	&__heading {
		margin: 0 0 calc(var(--default-grid-baseline) * 2);
		font-size: var(--default-font-size);
		font-weight: bold;
	}

	&__quota {
		width: 100%;
	}

	&__acl {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);

		.folder-edit__heading {
			margin: 0;
		}
	}

	&__acl-toggle {
		flex: 0 0 auto;
	}

	&__managers {
		width: 100%;
		margin-top: calc(var(--default-grid-baseline) * 2);
	}
}
</style>
