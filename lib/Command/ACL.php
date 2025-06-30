<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GroupFolders\Command;

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

class ACL extends FolderCommand {
	private RuleManager $ruleManager;
	private ACLManagerFactory $aclManagerFactory;
	private IUserManager $userManager;

	public function __construct(
		FolderManager $folderManager,
		IRootFolder $rootFolder,
		RuleManager $ruleManager,
		MountProvider $mountProvider,
		ACLManagerFactory $aclManagerFactory,
		IUserManager $userManager
	) {
		parent::__construct($folderManager, $rootFolder, $mountProvider);
		$this->ruleManager = $ruleManager;
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
			->addArgument('permissions', InputArgument::IS_ARRAY + InputArgument::OPTIONAL, 'The permissions to set for the user or group as a white space separated list (ex: +read "-write"). Use "clear" to remove all permissions. Prepend the permission list with -- to allow parsing the - character.');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$folder = $this->getFolder($input, $output);
		if ($folder === false) {
			return -1;
		}
		if ($input->getOption('enable')) {
			$this->folderManager->setFolderACL($folder['id'], true);
		} elseif ($input->getOption('disable')) {
			$this->folderManager->setFolderACL($folder['id'], false);
		} elseif ($input->getOption('test')) {
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
				if ($this->folderManager->getFolderPermissionsForUser($user, $folder['id']) === 0) {
					$permissions = 0;
				} else {
					$permissions = $aclManager->getACLPermissionsForPath($jailPath . rtrim('/' . $path, '/'));
				}
				$permissionString = Rule::formatRulePermissions(Constants::PERMISSION_ALL, $permissions);
				$output->writeln($permissionString);
				return 0;
			} else {
				$output->writeln('<error>--user and <path> options needs to be set for permissions testing</error>');
				return -3;
			}
		} elseif (!$folder['acl']) {
			$output->writeln('<error>Advanced permissions not enabled for folder: ' . $folder['id'] . '</error>');
			return -2;
		} elseif (
			!$input->getArgument('path') &&
			!$input->getArgument('permissions') &&
			!$input->getOption('user') &&
			!$input->getOption('group')
		) {
			$this->printPermissions($input, $output, $folder);
		} elseif ($input->getOption('manage-add') && ($input->getOption('user') || $input->getOption('group'))) {
			$mappingType = $input->getOption('user') ? 'user' : 'group';
			$mappingId = $input->getOption('user') ? $input->getOption('user') : $input->getOption('group');
			$this->folderManager->setManageACL($folder['id'], $mappingType, $mappingId, true);
		} elseif ($input->getOption('manage-remove') && ($input->getOption('user') || $input->getOption('group'))) {
			$mappingType = $input->getOption('user') ? 'user' : 'group';
			$mappingId = $input->getOption('user') ? $input->getOption('user') : $input->getOption('group');
			$this->folderManager->setManageACL($folder['id'], $mappingType, $mappingId, false);
		} elseif (!$input->getArgument('path')) {
			$output->writeln('<error><path> argument has to be set when not using --enable or --disable</error>');
			return -3;
		} elseif (!$input->getArgument('permissions')) {
			$output->writeln('<error><permissions> argument has to be set when not using --enable or --disable</error>');
			return -3;
		} elseif ($input->getOption('user') && $input->getOption('group')) {
			$output->writeln('<error>--user and --group can not be used at the same time</error>');
			return -3;
		} elseif (!$input->getOption('user') && !$input->getOption('group')) {
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
				Constants::PERMISSION_ALL,
				$folder['quota'],
				null,
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
				return 0;
			}
			foreach ($permissionStrings as $permission) {
				if ($permission[0] !== '+' && $permission[0] !== '-') {
					$output->writeln('<error>incorrect format for permissions "' . $permission . '"</error>');
					return -3;
				}
				$name = substr($permission, 1);
				if (!isset(Rule::PERMISSIONS_MAP[$name])) {
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
		return 0;
	}

	private function printPermissions(InputInterface $input, OutputInterface $output, array $folder): void {
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
						return $rule->formatPermissions();
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

	private function parsePermissions(array $permissions): array {
		$mask = 0;
		$result = 0;

		foreach ($permissions as $permission) {
			$permissionValue = Rule::PERMISSIONS_MAP[substr($permission, 1)];
			$mask |= $permissionValue;
			if ($permission[0] === '+') {
				$result |= $permissionValue;
			}
		}
		return [$mask, $result];
	}
}
