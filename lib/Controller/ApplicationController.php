<?php
/**
 * @author Baptiste Fotia <baptiste.fotia@arawa.fr> for Arawa (https://arawa.fr)
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

namespace OCA\GroupFolders\Controller;

use OCA\GroupFolders\Service\ApplicationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;

class ApplicationController extends Controller {

    /** @var ApplicationService */
    private $applicationService;
    
    public function __construct(
        ApplicationService $applicationService
    )
    {
        $this->applicationService = $applicationService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function checkAppsBasedOnGroupfolders() {
        return new JSONResponse([ 'result' => $this->applicationService->checkAppsInstalled() ]);
    }
}