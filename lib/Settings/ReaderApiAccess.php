<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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


namespace OCA\GroupFolders\Settings;

use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\IDelegatedSettings;
use OCP\AppFramework\Http\TemplateResponse;

class ReaderApiAccess implements IDelegatedSettings {

	private $config;

	private $l;
	

	public function __construct(IConfig $config, IL10N $l)
	{
		$this->config = $config;
		$this->l = $l;
	}
	
	public function getForm() {

		return new TemplateResponse(
			'groupfolders',
			'index',
			[
				'appId' => 'groupfolders'
			],
			''
		);
	}
	
	public function getSection() {
		return 'groupfolders';
	}

	public function getPriority() {
		return 91;
	}
	
	public function getName(): ?string {
		return $this->l->t('Reader API Access');
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}