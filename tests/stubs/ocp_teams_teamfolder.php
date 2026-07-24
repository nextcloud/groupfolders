<?php

declare(strict_types=1);

namespace OCP\Teams;

class TeamFolder implements \JsonSerializable {
	public function __construct(
		private int $id,
		private string $mountPoint,
	) {
	}

	public function getId(): int {
		return $this->id;
	}

	public function getMountPoint(): string {
		return $this->mountPoint;
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'mountPoint' => $this->mountPoint,
		];
	}
}