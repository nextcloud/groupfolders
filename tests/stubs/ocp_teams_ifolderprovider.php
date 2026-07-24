<?php

declare(strict_types=1);

namespace OCP\Teams;

interface ITeamFolderProvider extends ITeamResourceProvider {
	public function getTeamFolder(string $teamId): ?TeamFolder;

	public function createTeamFolder(Team $team, int $quota = 0): TeamFolder;

	public function unlinkTeamFolder(string $teamId): ?TeamFolder;

	public function removeTeamFolder(string $teamId): bool;
}