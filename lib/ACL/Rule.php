<?php declare(strict_types=1);
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

namespace OCA\GroupFolders\ACL;

use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use Sabre\Xml\Reader;
use Sabre\Xml\Writer;
use Sabre\Xml\XmlDeserializable;
use Sabre\Xml\XmlSerializable;

class Rule implements XmlSerializable, XmlDeserializable, \JsonSerializable {
	const ACL = '{http://nextcloud.org/ns}acl';
	const PERMISSIONS = '{http://nextcloud.org/ns}acl-permissions';
	const MASK = '{http://nextcloud.org/ns}acl-mask';
	const MAPPING_TYPE = '{http://nextcloud.org/ns}acl-mapping-type';
	const MAPPING_ID = '{http://nextcloud.org/ns}acl-mapping-id';
	const MAPPING_DISPLAY_NAME = '{http://nextcloud.org/ns}acl-mapping-display-name';

	private $userMapping;
	private $fileId;

	// for every permission type a rule can either allow, deny or inherit
	// these 3 values are stored as 2 bitmaps, one that masks out all inherit values (1 -> set permission, 0 -> inherit)
	// and one that specifies the permissions to set for non inherited values (1-> allow, 0 -> deny)
	private $mask;
	private $permissions;

	public function __construct(IUserMapping $userMapping, int $fileId, int $mask, int $permissions) {
		$this->userMapping = $userMapping;
		$this->fileId = $fileId;
		$this->mask = $mask;
		$this->permissions = $permissions;
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

	public function applyPermissions(int $permissions) {
		$invertedMask = ~$this->mask;
		// create a bitmask that has all inherit and allow bits set to 1 and all deny bits to 0
		$denyMask = $invertedMask | $this->permissions;

		$permissions = $permissions & $denyMask;

		// a bitmask that has all allow bits set to 1 and all inherit and deny bits to 0
		$allowMask = $this->mask & $this->permissions;
		return $permissions | $allowMask;
	}

	function xmlSerialize(Writer $writer) {
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

	public function jsonSerialize() {
		return [
			'mapping' => [
				'type' => $this->getUserMapping()->getType(),
				'id' => $this->getUserMapping()->getId()
			],
			'mask' => $this->mask,
			'permissions' => $this->permissions
		];
	}

	static function xmlDeserialize(Reader $reader): Rule {
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
	 *
	 * @param array $rules
	 * @return Rule
	 */
	static function mergeRules(array $rules): Rule {
		// or'ing the masks to get a new mask that masks all set permissions
		$mask = array_reduce($rules, function (int $mask, Rule $rule) {
			return $mask | $rule->getMask();
		}, 0);
		// or'ing the permissions combines them with allow overwriting deny
		$permissions = array_reduce($rules, function (int $permissions, Rule $rule) {
			return $permissions | $rule->getPermissions();
		}, 0);

		return new Rule(
			new UserMapping('dummy', ''),
			-1,
			$mask,
			$permissions
		);
	}
}
