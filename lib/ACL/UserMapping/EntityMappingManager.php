<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Maxence Lange <maxence@artificial-owl.com>
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

namespace OCA\GroupFolders\ACL\UserMapping;

use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\FederatedUserException;
use OCA\Circles\Exceptions\FederatedUserNotFoundException;
use OCA\Circles\Exceptions\InvalidIdException;
use OCA\Circles\Exceptions\MemberNotFoundException;
use OCA\Circles\Exceptions\OwnerNotFoundException;
use OCA\Circles\Exceptions\RemoteInstanceException;
use OCA\Circles\Exceptions\RemoteNotFoundException;
use OCA\Circles\Exceptions\RemoteResourceNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\SingleCircleNotFoundException;
use OCA\Circles\Exceptions\UnknownRemoteException;
use OCA\Circles\Exceptions\UserTypeNotFoundException;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCP\Circles\ICirclesManager;
use OCP\IUser;


/**
 * Class EntityMappingManager
 *
 * @package OCA\GroupFolders\ACL\UserMapping
 */
class EntityMappingManager implements IEntityMappingManager {

	/** @var ICirclesManager */
	private $circlesManager;


	/**
	 * EntityMappingManager constructor.
	 *
	 * @param ICirclesManager $circlesManager
	 */
	public function __construct(ICirclesManager $circlesManager) {
		$this->circlesManager = $circlesManager;
	}


	/**
	 * @param IUser $user
	 * @param bool $userAssignable
	 *
	 * @return array
	 * @throws CircleNotFoundException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InvalidIdException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 * @throws UnknownRemoteException
	 * @throws UserTypeNotFoundException
	 */
	public function getMappingsForUser(IUser $user, bool $userAssignable = true): array {
		$federatedUser = $this->circlesManager->getFederatedUser($user->getUID(), Member::TYPE_USER);

		$mappings = [];
		foreach ($federatedUser->getMemberships() as $membership) {
			// TODO: lighten by providing details within getMemberships();
			$probe = new CircleProbe();
			$probe->includeSystemCircles();
			$probe->includeSingleCircles();
			$circle = $this->circlesManager->getCircle($membership->getCircleId(), $probe);

			$mappings[] =
				new UserMapping(
					'entity',
					$membership->getCircleId(),
					$circle->getDisplayName()
				);
		}

		return $mappings;
	}


	/**
	 * @param string $id
	 *
	 * @return IUserMapping|null
	 * @throws CircleNotFoundException
	 * @throws FederatedItemException
	 * @throws FederatedUserException
	 * @throws FederatedUserNotFoundException
	 * @throws InvalidIdException
	 * @throws MemberNotFoundException
	 * @throws OwnerNotFoundException
	 * @throws RemoteInstanceException
	 * @throws RemoteNotFoundException
	 * @throws RemoteResourceNotFoundException
	 * @throws RequestBuilderException
	 * @throws SingleCircleNotFoundException
	 * @throws UnknownRemoteException
	 * @throws UserTypeNotFoundException
	 */
	public function mappingFromId(string $id): ?IUserMapping {
		$federatedUser = $this->circlesManager->getFederatedUser($id);

		return new UserMapping('entity', $id, $federatedUser->getDisplayName());
	}

}
