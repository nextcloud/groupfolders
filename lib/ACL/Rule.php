<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCP\Constants;
use Sabre\Xml\Reader;
use Sabre\Xml\Writer;
use Sabre\Xml\XmlDeserializable;
use Sabre\Xml\XmlSerializable;

class Rule implements XmlSerializable, XmlDeserializable, \JsonSerializable {
	public const ACL = '{http://nextcloud.org/ns}acl';
	public const PERMISSIONS = '{http://nextcloud.org/ns}acl-permissions';
	public const MASK = '{http://nextcloud.org/ns}acl-mask';
	public const MAPPING_TYPE = '{http://nextcloud.org/ns}acl-mapping-type';
	public const MAPPING_ID = '{http://nextcloud.org/ns}acl-mapping-id';
	public const MAPPING_DISPLAY_NAME = '{http://nextcloud.org/ns}acl-mapping-display-name';

	public const PERMISSIONS_MAP = [
		'read' => Constants::PERMISSION_READ,
		'write' => Constants::PERMISSION_UPDATE,
		'create' => Constants::PERMISSION_CREATE,
		'delete' => Constants::PERMISSION_DELETE,
		'share' => Constants::PERMISSION_SHARE,
	];

	private int $permissions;

	/**
	 * @param int $mask for every permission type a rule can either allow, deny or inherit
	 *                  these 3 values are stored as 2 bitmaps, one that masks out all inherit values (1 -> set permission, 0 -> inherit)
	 *                  and one that specifies the permissions to set for non inherited values (1-> allow, 0 -> deny)
	 */
	public function __construct(
		private IUserMapping $userMapping,
		private int $fileId,
		private int $mask,
		int $permissions,
	) {
		$this->permissions = $permissions & $mask;
	}

	public function getUserMapping(): IUserMapping {
		return $this->userMapping;
	}

	public function getFileId(): int {
		return $this->fileId;
	}

	public function getMask(): int {
		return $this->mask;
	}

	public function getPermissions(): int {
		return $this->permissions;
	}

	/**
	 * Apply this rule to an existing permission set, returning the resulting permissions
	 *
	 * All permissions included in the current mask will overwrite the existing permissions
	 */
	public function applyPermissions(int $permissions): int {
		$invertedMask = ~$this->mask;
		// create a bitmask that has all inherit and allow bits set to 1 and all deny bits to 0
		$denyMask = $invertedMask | $this->permissions;

		$permissions = $permissions & $denyMask;

		// a bitmask that has all allow bits set to 1 and all inherit and deny bits to 0
		$allowMask = $this->mask & $this->permissions;

		return $permissions | $allowMask;
	}

	/**
	 * Apply the deny permissions this rule to an existing permission set, returning the resulting permissions
	 *
	 * Only the deny permissions included in the current mask will overwrite the existing permissions
	 *
	 * @param int $permissions
	 * @return int
	 */
	public function applyDenyPermissions(int $permissions): int {
		$invertedMask = ~$this->mask;
		// create a bitmask that has all inherit and allow bits set to 1 and all deny bits to 0
		$denyMask = $invertedMask | $this->permissions;

		return $permissions & $denyMask;
	}

	/**
	 * @return void
	 */
	public function xmlSerialize(Writer $writer): void {
		$data = [
			self::ACL => [
				self::MAPPING_TYPE => $this->getUserMapping()->getType(),
				self::MAPPING_ID => $this->getUserMapping()->getId(),
				self::MAPPING_DISPLAY_NAME => $this->getUserMapping()->getDisplayName(),
				self::MASK => $this->getMask(),
				self::PERMISSIONS => $this->getPermissions()
			]
		];
		$writer->write($data);
	}

	public function jsonSerialize(): array {
		return [
			'mapping' => [
				'type' => $this->getUserMapping()->getType(),
				'id' => $this->getUserMapping()->getId()
			],
			'mask' => $this->mask,
			'permissions' => $this->permissions
		];
	}

	public static function xmlDeserialize(Reader $reader): Rule {
		$elements = \Sabre\Xml\Deserializer\keyValue($reader);

		return new Rule(
			new UserMapping(
				$elements[self::MAPPING_TYPE],
				$elements[self::MAPPING_ID]
			),
			-1,
			(int)$elements[self::MASK],
			(int)$elements[self::PERMISSIONS]
		);
	}

	/**
	 * merge multiple rules that apply on the same file where allow overwrites deny
	 */
	public static function mergeRules(array $rules): Rule {
		// or'ing the masks to get a new mask that masks all set permissions
		$mask = array_reduce($rules, fn (int $mask, Rule $rule): int => $mask | $rule->getMask(), 0);
		// or'ing the permissions combines them with allow overwriting deny
		$permissions = array_reduce($rules, fn (int $permissions, Rule $rule): int => $permissions | $rule->getPermissions(), 0);

		return new Rule(
			new UserMapping('dummy', ''),
			-1,
			$mask,
			$permissions
		);
	}

	/**
	 * apply a new rule on top of the existing
	 *
	 * All non-inherit fields of the new rule will overwrite the current permissions
	 */
	public function applyRule(Rule $rule): void {
		$this->permissions = $rule->applyPermissions($this->permissions);
		$this->mask |= $rule->getMask();
	}

	/**
	 * Create a default, no-op rule
	 */
	public static function defaultRule(): Rule {
		return new Rule(
			new UserMapping('dummy', ''),
			-1,
			0,
			0
		);
	}

	public static function formatRulePermissions(int $mask, int $permissions): string {
		$result = [];
		foreach (self::PERMISSIONS_MAP as $name => $value) {
			if (($mask & $value) === $value) {
				$type = ($permissions & $value) === $value ? '+' : '-';
				$result[] = $type . $name;
			}
		}

		return implode(', ', $result);
	}

	public function formatPermissions(): string {
		return self::formatRulePermissions($this->mask, $this->permissions);
	}
}
