/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import TeamFoldersMountPrefix from './views/TeamFoldersMountPrefix.vue'

const app = createApp(TeamFoldersMountPrefix)
app.mount('#groupfolders-mountprefix-section')
