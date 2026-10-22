/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const BinaryTools = {
	toString(num) {
		return (num >>> 0).toString(2).padStart(8, '0')
	},

	firstHigh(num) {
		let position = 0
		while (num !== 0) {
			if (num & 1 > 0) {
				return position
			}
			position++
			num = num >> 1
		}
		return 0
	},

	test(num, bit) {
		return ((num >> bit) % 2 !== 0)
	},

	set(num, bit) {
		return num | 1 << bit
	},

	clear(num, bit) {
		return num & ~(1 << bit)
	},

	toggle(num, bit) {
		return this.test(num, bit) ? this.clear(num, bit) : this.set(num, bit)
	},
}

export default BinaryTools
