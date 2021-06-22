/*
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
