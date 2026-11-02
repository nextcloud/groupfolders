<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { DelegationCircle, Folder } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import FolderGroups from './FolderGroups.vue'
import ManageAclSelect from './ManageAclSelect.vue'
import QuotaSelect from './QuotaSelect.vue'
import SubmitInput from './SubmitInput.vue'
import { useFoldersStore } from '../store/folders.ts'
import { useGroupsStore } from '../store/groups.ts'
import { isTeamSpace } from '../utils/folder.ts'
import { isCirclesEnabled } from '../utils/initialState.ts'

const props = defineProps<{
	folder: Folder
	circles: DelegationCircle[]
}>()

const emit = defineEmits<{
	close: []
}>()

const foldersStore = useFoldersStore()
const groupsStore = useGroupsStore()

const groupHeading = isCirclesEnabled
	? t('groupfolders', 'Group or team')
	: t('groupfolders', 'Group')

const teamSpace = computed(() => isTeamSpace(props.folder))

const dialogName = computed(() => t('groupfolders', 'Edit "{mountPoint}"', { mountPoint: props.folder.mount_point }))

const aclLabel = computed(() => t('groupfolders', 'Advanced permissions for "{mountPoint}"', { mountPoint: props.folder.mount_point }))
</script>

<template>
	<NcDialog
		class="folder-edit-dialog"
		size="small"
		:name="dialogName"
		@closing="emit('close')">
		<div class="folder-edit">
			<template v-if="!teamSpace">
				<div class="folder-edit__field">
					<SubmitInput
						:initialValue="folder.mount_point"
						:label="t('groupfolders', 'Folder name')"
						@submitValue="foldersStore.renameFolder(folder, $event)" />
				</div>

				<section class="folder-edit__section">
					<h3 class="folder-edit__heading">
						{{ groupHeading }}
					</h3>
					<FolderGroups
						:groups="folder.groups"
						:allCircles="circles"
						:allGroups="groupsStore.groups"
						@addGroup="foldersStore.addGroup(folder, $event)"
						@removeGroup="foldersStore.removeGroup(folder, $event)"
						@setPermissions="(group, permissions) => foldersStore.setPermissions(folder, group, permissions)" />
				</section>
			</template>

			<section class="folder-edit__section">
				<h3 class="folder-edit__heading">
					{{ t('groupfolders', 'Quota') }}
				</h3>
				<div class="folder-edit__quota">
					<QuotaSelect
						:value="folder.quota"
						:size="folder.size"
						@change="foldersStore.setQuota(folder, $event)" />
				</div>
			</section>

			<section
				v-if="!teamSpace"
				class="folder-edit__section">
				<div class="folder-edit__acl">
					<h3 class="folder-edit__heading">
						{{ t('groupfolders', 'Advanced Permissions') }}
					</h3>
					<NcCheckboxRadioSwitch
						class="folder-edit__acl-toggle"
						:modelValue="folder.acl"
						:aria-label="aclLabel"
						:title="t('groupfolders', 'Advanced permissions allows setting permissions on a per-file basis but comes with a performance overhead')"
						@update:modelValue="foldersStore.setAcl(folder, $event)" />
				</div>
				<ManageAclSelect
					v-if="folder.acl"
					class="folder-edit__managers"
					:folder="folder" />
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
