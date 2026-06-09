/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import TeamFoldersEncryption from '../components/TeamFoldersEncryption.vue'

const app = createApp(TeamFoldersEncryption)
app.mount('#groupfolders-settings-section')
