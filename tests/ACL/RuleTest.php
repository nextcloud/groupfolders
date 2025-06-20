<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\groupfolders\tests\ACL;

use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use Test\TestCase;

class RuleTest extends TestCase {
	public static function permissionsProvider(): array {
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
	public function testApplyPermissions(int $input, int $mask, int $permissions, int $expected): void {
		$rule = new Rule($this->createMock(IUserMapping::class), 0, $mask, $permissions);
		$this->assertEquals($expected, $rule->applyPermissions($input));
	}

	public static function mergeRulesProvider(): array {
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
	public function testMergeRules(array $inputs, int $expectedMask, int $expectedPermissions): void {
		$inputRules = array_map(fn (array $input): Rule => new Rule($this->createMock(IUserMapping::class), 0, $input[0], $input[1]), $inputs);

		$result = Rule::mergeRules($inputRules);
		$this->assertEquals($expectedMask, $result->getMask());
		$this->assertEquals($expectedPermissions, $result->getPermissions());
	}
}
