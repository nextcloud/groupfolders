<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

namespace OCA\GroupFolders\Command;

use OC\Core\Command\Base;
use OCA\GroupFolders\ACL\ACLManagerFactory;
use OCA\GroupFolders\ACL\Rule;
use OCA\GroupFolders\ACL\RuleManager;
use OCA\GroupFolders\ACL\UserMapping\UserMapping;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\GroupFolders\Mount\MountProvider;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ACL extends Base {
	const PERMISSIONS_MAP = [
		'read' => Constants::PERMISSION_READ,
		'write' => Constants::PERMISSION_UPDATE,
		'create' => Constants::PERMISSION_CREATE,
		'delete' => Constants::PERMISSION_DELETE,
		'share' => Constants::PERMISSION_SHARE,
	];

	private $folderManager;
	private $rootFolder;
	private $ruleManager;
	private $mountProvider;
	private $aclManagerFactory;
	private $userManager;

	public function __construct(
		FolderManager $folderManager,
		IRootFolder $rootFolder,
		RuleManager $ruleManager,
		MountProvider $mountProvider,
		ACLManagerFactory $aclManagerFactory,
		IUserManager $userManager
	) {
		parent::__construct();
		$this->folderManager = $folderManager;
		$this->rootFolder = $rootFolder;
		$this->ruleManager = $ruleManager;
		$this->mountProvider = $mountProvider;
		$this->aclManagerFactory = $aclManagerFactory;
		$this->userManager = $userManager;
	}

	protected function configure() {
		$this
			->setName('groupfolders:permissions')
			->setDescription('Configure advanced permissions for a configured group folder')
			->addArgument('folder_id', InputArgument::REQUIRED, 'Id of the folder to configure')
			->addOption('enable', 'e', InputOption::VALUE_NONE, 'Enable advanced permissions for the folder')
			->addOption('disable', 'd', InputOption::VALUE_NONE, 'Disable advanced permissions for the folder')
			->addOption('manage-add', 'm', InputOption::VALUE_NONE, 'Add manage permission for user or group')
			->addOption('manage-remove', 'r', InputOption::VALUE_NONE, 'Remove manage permission for user or group')
			->addArgument('path', InputArgument::OPTIONAL, 'The path within the folder to set permissions for')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'The user to configure the permissions for')
			->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'The group to configure the permissions for')
			->addOption('test', 't', InputOption::VALUE_NONE, 'Test the permissions for the set path')
			->addArgument('permissions', InputArgument::IS_ARRAY + InputArgument::OPTIONAL);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folderId = $input->getArgument('folder_id');
		$folder = $this->folderManager->getFolder($folderId, $this->rootFolder->getMountPoint()->getNumericStorageId());
		if ($folder) {
			if ($input->getOption('enable')) {
				$this->folderManager->setFolderACL($folderId, true);
			} else if ($input->getOption('disable')) {
				$this->folderManager->setFolderACL($folderId, false);
			} else if ($input->getOption('test')) {
				if ($input->getOption('user') && ($input->getArgument('path'))) {
					$mappingId = $input->getOption('user');
					$user = $this->userManager->get($mappingId);
					if (!$user) {
						$output->writeln('<error>User not found: ' . $mappingId . '</error>');
						return -1;
					}
					$jailPath = $this->mountProvider->getJailPath((int)$folder['id']);
					$path = $input->getArgument('path');
					$aclManager = $this->aclManagerFactory->getACLManager($user);
					$permissions = $aclManager->getACLPermissionsForPath($jailPath . rtrim('/' . $path, '/'));
					$permissionString = $this->formatRulePermissions(Constants::PERMISSION_ALL, $permissions);
					$output->writeln($permissionString);
					return;
				} else {
					$output->writeln('<error>--user and <path> options needs to be set for permissions testing</error>');
					return -3;
				}
			} else if (!$folder['acl']) {
				$output->writeln('<error>Advanced permissions not enabled for folder: ' . $folderId . '</error>');
				return -2;
			} else if (
				!$input->getArgument('path') &&
				!$input->getArgument('permissions') &&
				!$input->getOption('user') &&
				!$input->getOption('group')
			) {
				$this->printPermissions($input, $output, $folder);
			} else if ($input->getOption('manage-add') && ($input->getOption('user') || $input->getOption('group'))) {
				$mappingType = $input->getOption('user') ? 'user' : 'group';
				$mappingId = $input->getOption('user') ? $input->getOption('user') : $input->getOption('group');
				$this->folderManager->setManageACL($folderId, $mappingType, $mappingId, true);
			} else if ($input->getOption('manage-remove') && ($input->getOption('user') || $input->getOption('group'))) {
				$mappingType = $input->getOption('user') ? 'user' : 'group';
				$mappingId = $input->getOption('user') ? $input->getOption('user') : $input->getOption('group');
				$this->folderManager->setManageACL($folderId, $mappingType, $mappingId, false);
			} else if (!$input->getArgument('path')) {
				$output->writeln('<error><path> argument has to be set when not using --enable or --disable</error>');
				return -3;
			} else if (!$input->getArgument('permissions')) {
				$output->writeln('<error><permissions> argument has to be set when not using --enable or --disable</error>');
				return -3;
			} else if ($input->getOption('user') && $input->getOption('group')) {
				$output->writeln('<error>--user and --group can not be used at the same time</error>');
				return -3;
			} else if (!$input->getOption('user') && !$input->getOption('group')) {
				$output->writeln('<error>either --user or --group has to be used when not using --enable or --disable</error>');
				return -3;
			} else {
				$mappingType = $input->getOption('user') ? 'user' : 'group';
				$mappingId = $input->getOption('user') ? $input->getOption('user') : $input->getOption('group');
				$path = $input->getArgument('path');
				$path = trim($path, '/');
				$permissionStrings = $input->getArgument('permissions');

				$mount = $this->mountProvider->getMount(
					$folder['id'],
					'/dummy/files/' . $folder['mount_point'],
					$folder['permissions'],
					$folder['quota'],
					$folder['rootCacheEntry'],
					null,
					$folder['acl']
				);
				$id = $mount->getStorage()->getCache()->getId($path);
				if ($id === -1) {
					$output->writeln('<error>Path not found in folder: ' . $path . '</error>');
					return -1;
				}

				if ($permissionStrings === ['clear']) {
					$this->ruleManager->deleteRule(new Rule(
						new UserMapping($mappingType, $mappingId),
						$id,
						0,
						0
					));
				} else {
					foreach ($permissionStrings as $permission) {
						if ($permission[0] !== '+' && $permission[0] !== '-') {
							$output->writeln('<error>incorrect format for permissions "' . $permission . '"</error>');
							return -3;
						}
						$name = substr($permission, 1);
						if (!isset(self::PERMISSIONS_MAP[$name])) {
							$output->writeln('<error>incorrect format for permissions2 "' . $permission . '"</error>');
							return -3;
						}
					}

					[$mask, $permissions] = $this->parsePermissions($permissionStrings);

					$this->ruleManager->saveRule(new Rule(
						new UserMapping($mappingType, $mappingId),
						$id,
						$mask,
						$permissions
					));
				}
			}
		} else {
			$output->writeln('<error>Folder not found: ' . $folderId . '</error>');
			return -1;
		}
		return 0;
	}

	private function printPermissions(InputInterface $input, OutputInterface $output, array $folder) {
		$jailPath = $this->mountProvider->getJailPath((int)$folder['id']);
		$rules = $this->ruleManager->getAllRulesForPrefix(
			$this->rootFolder->getMountPoint()->getNumericStorageId(),
			$jailPath
		);
		$jailPathLength = strlen($jailPath) + 1;
		$outputFormat = $input->getOption('output');

		switch ($outputFormat) {
			case parent::OUTPUT_FORMAT_JSON:
			case parent::OUTPUT_FORMAT_JSON_PRETTY:
				$paths = array_map(function ($rawPath) use ($jailPathLength) {
					$path = substr($rawPath, $jailPathLength);
					return $path ?: '/';
				}, array_keys($rules));
				$items = array_combine($paths, $rules);
				ksort($items);

				$output->writeln(json_encode($items, $outputFormat === parent::OUTPUT_FORMAT_JSON_PRETTY ? JSON_PRETTY_PRINT : 0));
				break;
			default:
				$items = array_map(function (array $rulesForPath, string $path) use ($jailPathLength) {
					/** @var Rule[] $rulesForPath */
					$mappings = array_map(function (Rule $rule) {
						return $rule->getUserMapping()->getType() . ': ' . $rule->getUserMapping()->getId();
					}, $rulesForPath);
					$permissions = array_map(function (Rule $rule) {
						return $this->formatRulePermissions($rule->getMask(), $rule->getPermissions());
					}, $rulesForPath);
					$formattedPath = substr($path, $jailPathLength);
					return [
						'path' => $formattedPath ?: '/',
						'mappings' => implode("\n", $mappings),
						'permissions' => implode("\n", $permissions),
					];
				}, $rules, array_keys($rules));
				usort($items, function ($a, $b) {
					return $a['path'] <=> $b['path'];
				});

				$table = new Table($output);
				$table->setHeaders(['Path', 'User/Group', 'Permissions']);
				$table->setRows($items);
				$table->render();
				break;
		}
	}

	private function formatRulePermissions(int $mask, int $permissions): string {
		$result = [];
		foreach (self::PERMISSIONS_MAP as $name => $value) {
			if (($mask & $value) === $value) {
				$type = ($permissions & $value) === $value ? '+' : '-';
				$result[] = $type . $name;
			}
		}
		return implode(', ', $result);
	}

	private function parsePermissions(array $permissions): array {
		$mask = 0;
		$result = 0;

		foreach ($permissions as $permission) {
			$permissionValue = self::PERMISSIONS_MAP[substr($permission, 1)];
			$mask |= $permissionValue;
			if ($permission[0] === '+') {
				$result |= $permissionValue;
			}
		}
		return [$mask, $result];
	}
}
