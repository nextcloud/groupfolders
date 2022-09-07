<?php
/**
 * SPDX-FileCopyrightText: 2017 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	/** @var IL10N */
	private $l;
	/** @var IURLGenerator */
	private $url;

	public function __construct(IL10N $l, IURLGenerator $url) {
		$this->l = $l;
		$this->url = $url;
	}

	/**
	 * @return string The ID of the section. It is supposed to be a lower case string, e.g. 'ldap'
	 *
	 * @psalm-return 'groupfolders'
	 */
	public function getID() {
		return 'groupfolders';
	}

	/**
	 * Returns the translated name as it should be displayed, e.g. 'LDAP / AD
	 * integration'. Use the L10N service to translate it.
	 *
	 * @return string
	 */
	public function getName() {
		return $this->l->t('Group folders');
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the settings navigation. The sections are arranged in ascending order of
	 * the priority values. It is required to return a value between 0 and 99.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 90;
	}

	/**
	 * 	 * {@inheritdoc}
	 *
	 * @return string
	 */
	public function getIcon() {
		return $this->url->imagePath('groupfolders', 'app-dark.svg');
	}
}
