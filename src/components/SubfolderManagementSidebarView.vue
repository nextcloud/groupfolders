<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { INode } from '@nextcloud/files'
import type { Group, User, Circle } from '../types'
import type { SubfolderManager } from '../services/subfolderManagement'

import { mdiPlus, mdiTrashCanOutline } from '@mdi/js'
import axios, { isCancel, isAxiosError } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import { useDebounceFn } from '@vueuse/core'
import svgGroup from '@mdi/svg/svg/account-multiple-outline.svg?raw'
import svgTeam from '@mdi/svg/svg/account-group-outline.svg?raw'
import { getAcls } from '../services/acl'
import { logger } from '../services/logger'
import { getSubfolderManagement, setSubfolderManager, type SubfolderManagement } from '../services/subfolderManagement'

type IUserData = InstanceType<typeof NcSelectUsers>['$props']['options'][number]

interface MappingOption extends IUserData, SubfolderManager {
	unique: string
}

const props = defineProps<{
	node: INode
}>()

const select = useTemplateRef('select')
const management = ref<SubfolderManagement>()
const folderId = ref<number>()
const loading = ref(false)
const searching = ref(false)
const showManagerSelect = ref(false)
const options = ref<MappingOption[]>([])
const selected = ref<MappingOption>()

const quotaText = computed(() => {
	if (!management.value || management.value.quota === null) {
		return t('groupfolders', 'No separate limit')
	}

	return formatFileSize(management.value.quota)
})

const usageText = computed(() => {
	if (!management.value) {
		return ''
	}

	return t('groupfolders', '{used} used of {quota}', {
		used: formatFileSize(management.value.size),
		quota: quotaText.value,
	})
})

watch(() => props.node, () => {
	void loadManagement()
}, { immediate: true })

watch(selected, (value) => {
	if (value) {
		void updateManager(value, true)
	}
})

async function loadManagement() {
	management.value = undefined
	folderId.value = undefined
	showManagerSelect.value = false
	selected.value = undefined

	if (props.node.type !== 'folder') {
		return
	}

	try {
		const acls = await getAcls(props.node.path)
		if (!acls?.groupFolderId) {
			return
		}

		folderId.value = acls.groupFolderId
		management.value = await getSubfolderManagement(acls.groupFolderId, Number(props.node.id))
	} catch (error) {
		// A regular folder, an unassigned child, or a user without management
		// rights intentionally has no section to display.
		if (isAxiosError(error) && [403, 404].includes(error.response?.status ?? 0)) {
			return
		}

		logger.error('Failed to load subfolder management', { error })
	}
}

function getFullDisplayName(manager: SubfolderManager): string {
	if (manager.type === 'group') {
		return `${manager.displayname} (${t('groupfolders', 'Group')})`
	}
	if (manager.type === 'circle') {
		return `${manager.displayname} (${t('groupfolders', 'Team')})`
	}

	return manager.displayname
}

function toggleManagerSelect() {
	showManagerSelect.value = !showManagerSelect.value
	if (showManagerSelect.value) {
		nextTick(() => {
			const input = (select.value?.$el as HTMLElement | undefined)?.querySelector('input')
			input?.focus()
		})
	}
}

const debouncedSearch = useDebounceFn(searchMappings, 300)
let abortController: AbortController | undefined

async function searchMappings(query: string) {
	if (!folderId.value) {
		return
	}

	if (abortController) {
		abortController.abort('A newer search was requested')
	}
	abortController = new AbortController()
	searching.value = true

	try {
		const url = generateUrl('apps/groupfolders/folders/{id}/search?format=json&search={search}', {
			id: folderId.value,
			search: encodeURIComponent(query),
		})
		type SearchResponse = { ocs: { data: { groups: Record<string, Group>, users: Record<string, User>, circles: Record<string, Circle> } } }
		const { data } = await axios.get<SearchResponse>(url, { signal: abortController.signal })

		const groups = Object.values(data.ocs.data.groups).map((group) => ({
			unique: 'group:' + group.gid,
			isNoUser: true,
			type: 'group' as const,
			id: group.gid,
			iconSvg: svgGroup,
			displayname: group.displayname,
			displayName: group.displayname,
		}))
		const users = Object.values(data.ocs.data.users).map((user) => ({
			unique: 'user:' + user.uid,
			type: 'user' as const,
			id: user.uid,
			user: user.uid,
			displayname: user.displayname,
			displayName: user.displayname,
		}))
		const circles = Object.values(data.ocs.data.circles).map((circle) => ({
			unique: 'circle:' + circle.sid,
			isNoUser: true,
			type: 'circle' as const,
			id: circle.sid,
			iconSvg: svgTeam,
			displayname: circle.displayname,
			displayName: circle.displayname,
		}))
		options.value = [...groups, ...users, ...circles]
			.filter((option) => management.value?.managers.every((manager) => manager.type !== option.type || manager.id !== option.id)) as MappingOption[]
	} catch (error) {
		if (!isCancel(error)) {
			logger.error('Failed to search subfolder manager candidates', { error })
		}
	} finally {
		searching.value = false
	}
}

async function updateManager(manager: SubfolderManager, enabled: boolean) {
	if (!folderId.value || !management.value) {
		return
	}

	selected.value = undefined
	loading.value = true
	try {
		management.value = await setSubfolderManager(folderId.value, management.value.file_id, manager, enabled)
		showManagerSelect.value = false
	} catch (error) {
		logger.error('Failed to update subfolder manager', { error })
		showError(t('groupfolders', 'Could not update subfolder administrators'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<div v-if="management && !loading" class="subfolder-management">
		<h4 class="section-header">{{ t('groupfolders', 'Subfolder administration') }}</h4>
		<p>{{ usageText }}</p>
		<p v-if="management.quota === null" class="subfolder-management__hint">
			{{ t('groupfolders', 'This subfolder has no separate limit, but its contents still count towards the Team folder quota.') }}
		</p>

		<p v-if="management.can_assign" class="subfolder-management__hint">
			{{ t('groupfolders', 'Assign administrators who may manage permissions only in this subfolder and its contents.') }}
		</p>
		<p v-else class="subfolder-management__hint">
			{{ t('groupfolders', 'You can manage permissions in this subfolder and its contents.') }}
		</p>

		<ul class="subfolder-management__managers">
			<li v-for="manager in management.managers" :key="manager.type + ':' + manager.id">
				<NcAvatar :user="manager.id" :is-no-user="manager.type !== 'user'" :size="24" />
				<span>{{ getFullDisplayName(manager) }}</span>
				<NcButton v-if="management.can_assign"
					:aria-label="t('groupfolders', 'Remove subfolder administrator')"
					:title="t('groupfolders', 'Remove subfolder administrator')"
					variant="tertiary"
					@click="updateManager(manager, false)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiTrashCanOutline" />
					</template>
				</NcButton>
			</li>
		</ul>

		<NcButton v-if="management.can_assign && !showManagerSelect" @click="toggleManagerSelect">
			<template #icon>
				<NcIconSvgWrapper :path="mdiPlus" />
			</template>
			{{ t('groupfolders', 'Add subfolder administrator') }}
		</NcButton>

		<NcSelectUsers v-if="management.can_assign && showManagerSelect"
			ref="select"
			v-model="selected"
			:options="options"
			:loading="searching"
			:placeholder="t('groupfolders', 'Select a user or team')"
			@search="debouncedSearch" />
	</div>
</template>

<style scoped>
.subfolder-management__hint {
	color: var(--color-text-maxcontrast);
}

.subfolder-management__managers {
	margin: 0 0 8px;
	padding: 0;
	list-style: none;
}

.subfolder-management__managers li {
	display: flex;
	align-items: center;
	gap: 8px;
	min-height: 32px;
}

.subfolder-management__managers li span {
	flex: 1;
}
</style>
