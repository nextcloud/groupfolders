<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { NcButton, NcNoteCard, NcSettingsSection, NcTextField } from '@nextcloud/vue'
import { ref } from 'vue'

const saved = ref<string>(loadState<string>('groupfolders', 'mount_prefix', ''))
const prefix = ref<string>(saved.value)
const loading = ref(false)

/**
 * Normalize the folder path the same way the server does.
 *
 * @param value The path entered by the admin
 */
function sanitize(value: string): string {
	return value.replace(/\\/g, '/')
		.split('/')
		.map((segment) => segment.trim())
		.filter((segment) => segment !== '' && segment !== '.' && segment !== '..')
		.join('/')
}

/**
 * Store the parent folder in the app config.
 */
async function save() {
	if (loading.value) {
		return
	}
	const value = sanitize(prefix.value)
	loading.value = true
	try {
		const url = generateOcsUrl('/apps/provisioning_api/api/v1/config/apps/{appId}/{key}', { appId: 'groupfolders', key: 'mount_prefix' })
		await axios.post(url, { value })
		saved.value = value
		prefix.value = value
		showSuccess(t('groupfolders', 'Parent folder saved'))
	} catch {
		showError(t('groupfolders', 'Failed to save the parent folder'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcSettingsSection
		:name="t('groupfolders', 'Parent folder for team folders')"
		:description="t('groupfolders', 'All team folders are mounted inside this folder in every user\'s files (e.g. \'TeamFolders\'). Subfolders like \'Shared/Teams\' are possible. Leave empty to mount team folders directly in the root of the users\' files.')">
		<form class="mount-prefix" @submit.prevent="save">
			<NcTextField
				v-model="prefix"
				class="mount-prefix__input"
				:label="t('groupfolders', 'Parent folder')"
				:placeholder="t('groupfolders', 'e.g. TeamFolders')"
				:disabled="loading" />
			<NcButton
				class="mount-prefix__save"
				type="submit"
				variant="primary"
				:disabled="loading || sanitize(prefix) === saved">
				{{ t('groupfolders', 'Save') }}
			</NcButton>
		</form>
		<NcNoteCard
			type="info"
			:text="t('groupfolders', 'Changing this setting moves the team folders for all users. Folders that were created automatically for an old value are not removed. Current value: {value}', { value: saved === '' ? '/' : '/' + saved })" />
	</NcSettingsSection>
</template>

<style scoped>
.mount-prefix {
	display: flex;
	align-items: flex-end;
	gap: 8px;
	max-width: 500px;
	margin-bottom: 12px;
}

.mount-prefix__input {
	flex: 1 1 auto;
	min-width: 0;
}

.mount-prefix__save {
	flex-shrink: 0;
	width: auto !important;
	min-width: fit-content;
}
</style>
