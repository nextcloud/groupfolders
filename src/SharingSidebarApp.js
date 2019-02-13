import Vue from 'vue';
import SharingSidebarView from './components/SharingSidebarView.vue';
import VTooltip from 'v-tooltip';
import ClickOutside from 'vue-click-outside';

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(VTooltip);
Vue.directive('ClickOutside', ClickOutside)

const View = Vue.extend(SharingSidebarView);

export default View;
