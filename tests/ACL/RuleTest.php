<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\groupfolders\tests\ACL;

use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use Test\TestCase;

class RuleTest extends TestCase {
	public function permissionsProvider() {
		return [
			[0b00000000, 0b00000000, 0b00000000, 0b00000000],
			[0b10101010, 0b00000000, 0b11110000, 0b10101010], //empty mask should have no effect
			[0b10101010, 0b11111111, 0b11110000, 0b11110000], //full mask should set all permissions
			[0b11111111, 0b10101010, 0b11110000, 0b11110101], //partial mask only where mask = 1
			[0b00000000, 0b10101010, 0b11110000, 0b10100000]
		];
	}

	/**
	 * @dataProvider permissionsProvider
	 */
	public function testApplyPermissions($input, $mask, $permissions, $expected) {
		$rule = new Rule($this->createMock(IUserMapping::class), 0, $mask, $permissions);
		$this->assertEquals($expected, $rule->applyPermissions($input));
	}

	public function mergeRulesProvider() {
		return [
			[[
				[0b00001111, 0b00000011],
				[0b00001111, 0b00000011],
			], 0b00001111, 0b00000011],
			[[
				[0b00001111, 0b00000000],
				[0b00001111, 0b00000011],
			], 0b00001111, 0b00000011],
			[[
				[0b00000011, 0b00000011],
				[0b00001100, 0b00000000],
			], 0b00001111, 0b00000011],
			[[
				[0b00001100, 0b00000000],
				[0b00000011, 0b00000011],
				[0b00001111, 0b00000100],
			], 0b00001111, 0b00000111],
		];
	}

	/**
	 * @dataProvider mergeRulesProvider
	 */
	public function testMergeRules($inputs, $expectedMask, $expectedPermissions) {
		$inputRules = array_map(function (array $input) {
			return new Rule($this->createMock(IUserMapping::class), 0, $input[0], $input[1]);
		}, $inputs);

		$result = Rule::mergeRules($inputRules);
		$this->assertEquals($expectedMask, $result->getMask());
		$this->assertEquals($expectedPermissions, $result->getPermissions());
	}
}
