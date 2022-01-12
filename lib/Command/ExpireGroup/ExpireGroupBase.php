<?php

declare(strict_types=1);
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

namespace OCA\GroupFolders\Command\ExpireGroup;

use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for the group folder expiration commands.
 */
class ExpireGroupBase extends Base {
	public function __construct() {
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('groupfolders:expire')
			->setDescription('Trigger expiration for files stored in group folders (trash and versions). Currently disabled.');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$output->writeln('<error>groupfolder expiration handling is currently disabled because there is nothing to expire. Enable the "Delete Files" or/and "Versions" app to enable this feature.</error>');
		return 0;
	}
}
