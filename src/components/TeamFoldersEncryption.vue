<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'
import { NcNoteCard, NcCheckboxRadioSwitch, NcSettingsSection } from '@nextcloud/vue'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

const serverSideEncryptionEnabled = ref<string>(loadState<string>('groupfolders', 'server_side_encryption', 'no'))
const folderEncryptionEnabled = ref<boolean>(loadState<boolean>('groupfolders', 'enable_encryption', false))

const loading = ref(false)

async function handleChange(isEnabled: boolean) {
	if (loading.value || serverSideEncryptionEnabled.value === 'no' || (folderEncryptionEnabled.value == true && isEnabled == false)) {
		return
	}
	const previous = folderEncryptionEnabled.value
	folderEncryptionEnabled.value = isEnabled
	loading.value = true
	try {
		const url = generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/{appId}/{key}',
			{ appId: 'groupfolders', key: 'enable_encryption' });
		await axios.post(url, { value: isEnabled ? 'true' : 'false' })
	} catch {
		folderEncryptionEnabled.value = previous
		showError(t('groupfolders', 'Failed to update encryption setting'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcSettingsSection :name="t('groupfolders', 'Team folders encryption')">
		<NcCheckboxRadioSwitch
			:class="{ disabled: folderEncryptionEnabled || serverSideEncryptionEnabled === 'no' }"
			type="switch"
			:model-value="folderEncryptionEnabled"
			:aria-disabled="loading || serverSideEncryptionEnabled === 'no' || folderEncryptionEnabled == true"
			:loading="loading"
			:description="!folderEncryptionEnabled ? t('groupfolders', 'Encrypt team folders using server-side encryption. Remember that the data cannot be accessed if the encryption key is lost.') : null"
			@update:model-value="handleChange">
			{{ t('groupfolders', 'Enable Team Folders encryption') }}
		</NcCheckboxRadioSwitch>

		<p v-if=" serverSideEncryptionEnabled === 'yes' && folderEncryptionEnabled == true" id="team-folders-encryption-disable-hint" class="disable-hint">
			{{ t('groupfolders', 'Disabling team folders encryption is only possible using OCC, please refer to the documentation.') }}
		</p>

		<NcNoteCard v-if="serverSideEncryptionEnabled === 'no'" type="warning"
			:text="t('settings', 'Team Folders encryption cannot be enabled on the server because server-side encryption is disabled.')" />
	</NcSettingsSection>
</template>

<style scoped>

.disabled {
	opacity: .75;
}

.disabled :deep(*) {
	cursor: not-allowed !important;
}

.disable-hint {
	color: var(--color-text-maxcontrast);
	padding-inline-start: 10px;
}
</style>
