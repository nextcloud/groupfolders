/*
 * SPDX-FileCopyrightText: 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

import Vue from 'vue'
import SharingSidebarView from './components/SharingSidebarView.vue'
import { Tooltip } from '@nextcloud/vue'
import ClickOutside from 'vue-click-outside'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(Tooltip)
Vue.directive('ClickOutside', ClickOutside)

const View = Vue.extend(SharingSidebarView)

export default View
