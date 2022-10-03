<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
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

namespace OCA\GroupFolders\Command\ExpireGroup;

use OCA\Files_Trashbin\Expiration;
use OCA\GroupFolders\Trash\TrashBackend;
use OCA\GroupFolders\Versions\GroupVersionsExpireManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpireGroupVersionsTrash extends ExpireGroupVersions {
	private TrashBackend $trashBackend;
	private Expiration $expiration;

	public function __construct(
		GroupVersionsExpireManager $expireManager,
		TrashBackend $trashBackend,
		Expiration $expiration
	) {
		parent::__construct($expireManager);
		$this->trashBackend = $trashBackend;
		$this->expiration = $expiration;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiry of versions and trashbin for files stored in group folders');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		parent::execute($input, $output);

		[$count, $size] = $this->trashBackend->expire($this->expiration);
		$output->writeln("<info>Removed $count expired trashbin items</info>");

		return 0;
	}
}
