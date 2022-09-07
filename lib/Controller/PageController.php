<?php
/**
 * SPDX-FileCopyrightText: 2017 Robin Appelman <robin@icewind.nl>
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 */

namespace OCA\GroupFolders\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;

class PageController extends Controller {
	/**
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		$response = new TemplateResponse(
			$this->appName,
			'index',
			[
				'appId' => $this->appName
			]
		);

		return $response;
	}
}
