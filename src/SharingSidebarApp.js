/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import SharingSidebarView from './components/SharingSidebarView.vue'
import { Tooltip } from '@nextcloud/vue'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(Tooltip)

const View = Vue.extend(SharingSidebarView)

export default View
