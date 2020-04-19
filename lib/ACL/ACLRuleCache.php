<?php

namespace OCA\GroupFolders\ACL;

use OC\Memcache\APCu;
use OCA\GroupFolders\ACL\UserMapping\IUserMapping;
use OCA\GroupFolders\ACL\UserMapping\IUserMappingManager;
use OCP\IUser;

class ACLRuleCache
{
	const DERIVATION = 0.1;

	const DEFAULT_TTL = 3600 * 24;

	const INDEX_TTL = (3600 * 24) + 3600;

	/**
	 * @var APCu
	 */
	private $ruleCache;

	/**
	 * @var IUserMappingManager
	 */
	protected $userMappingManager;

	public function __construct(IUserMappingManager $userMappingManager) {
		$this->userMappingManager = $userMappingManager;
		$this->ruleCache = new APCu('acl');
	}

	protected function set($key, $value, $ttl = null) : void
	{
		if ($ttl === null) {
			$ttl = self::DEFAULT_TTL;
		}

		$ttl = rand($ttl, round($ttl * self::DERIVATION));

		$this->ruleCache->set($key, $value, $ttl);
	}

	protected function has($key) : bool
	{
		return $this->ruleCache->hasKey($key);
	}

	protected function get($key)
	{
		return $this->ruleCache->get($key);
	}

	protected function remove($key) : void
	{
		$this->ruleCache->remove($key);
	}

	protected function keyPath(int $storageId, string $path) : string
	{
		return 'path_' . $storageId . '_' . $path;
	}

	protected function keyFileId(int $fileId) : string
	{
		return 'file_' . $fileId;
	}

	protected function keyPathMapping(int $storageId, string $path, IUserMapping $mapping) : string
	{
		return $this->keyPath($storageId, $path) . '_' . $mapping->getType() . '_' . $mapping->getId();
	}

	public function clearByPathKey(string $pathKey) : void
	{
		$keys = $this->get($pathKey);
		if (!isset($keys['rule_path_keys'])) {
			$keys['rule_path_keys'] = [];
		}
		if (!isset($keys['rule_id_keys'])) {
			$keys['rule_id_keys'] = [];
		}

		foreach (array_merge($keys['rule_path_keys'], $keys['rule_id_keys']) as $key) {
			$this->remove($key);
		}
	}

	public function clearByPath(int $storageId, string $path) : void
	{
		$pathKey = $this->keyPath($storageId, $path);
		if (!$this->has($pathKey)) {
			return;
		}

		$this->clearByPathKey($pathKey);
	}

	public function clearByFileId(int $fileId) : void
	{
		$fileIdKey = $this->keyFileId($fileId);
		if (!$this->has($fileIdKey)) {
			return;
		}

		$this->clearByPathKey($this->get($fileIdKey));
	}

	/**
	 * @return Rule[]|null
	 */
	public function getByPath(IUser $user, int $storageId, string $path) : ?array
	{
		return $this->getByPathKey($user, $this->keyPath($storageId, $path));
	}

	/**
	 * @param string[] $paths
	 * @return (Rule[]|null)[]
	 */
	public function getByPaths(IUser $user, int $storageId, array $paths) : array
	{
		$rulePaths = [];

		foreach ($paths as $path) {
			$cached = $this->getByPath($user, $storageId, $path);
			if ($cached !== null) {
				$rulePaths[$path] = $cached;
			}
		}

		return $rulePaths;
	}

	/**
	 * @return Rule[]|null
	 */
	public function getByFileId(IUser $user, int $fileId) : ?array
	{
		$fileIdKey = $this->keyFileId($fileId);
		if (!$this->has($fileIdKey)) {
			return null;
		}

		$cached = $this->getByPathKey($user, $this->get($fileIdKey));
		if ($cached === null) {
			return null;
		}

		$rules = [];

		foreach ($cached as $rule) {
			if ($rule->getFileId() == $fileIdKey) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}

	/**
	 * @param (Rule[])[] $paths
	 */

	public function cachePath(int $storageId, array $paths) : void
	{
		foreach ($paths as $path => $rules) {
			$this->cacheRules($storageId, $path, $rules);
		}
	}

	/**
	 * @param Rule[] $rules
	 */
	public function cacheRules(int $storageId, string $path, array $rules) : void
	{
		$rulePathKeys = [];
		$fileIdKeys = [];

		foreach ($rules as $rule) {
			$rulePathKeys[] = $rulePathKey = $this->keyPathMapping($storageId, $path, $rule->getUserMapping());
			$fileIdKeys[] = $ruleFileIdKey = $this->keyFileId($rule->getFileId());
			$this->set($rulePathKey, $rule);
		}


		$pathKey = $this->keyPath($storageId, $path);
		$this->set($pathKey, [
			'rule_path_keys' => $rulePathKeys,
			'rule_id_keys' => $fileIdKeys
		], self::INDEX_TTL);
		foreach ($fileIdKeys as $fileIdKey) {
			$this->set($fileIdKey, $pathKey, self::INDEX_TTL);
		}
	}

	/**
	 * @param IUserMapping[] $userMappings
	 * @param Rule $rule
	 * @return bool
	 */
	protected function checkMappingInRule(array $userMappings, Rule $rule) : bool
	{
		foreach ($userMappings as $userMapping) {
			if ($userMapping->getId() == $rule->getUserMapping()->getId() &&
				$userMapping->getType() == $rule->getUserMapping()->getType()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param IUser $user
	 * @param string $pathKey
	 * @return Rule[]|null
	 */
	protected function getByPathKey(IUser $user, string $pathKey) : ?array
	{
		if (!$this->has($pathKey)) {
			return null;
		}

		$keys = $this->get($pathKey);
		if (!isset($keys['rule_path_keys'])) {
			return null;
		}

		$userMappings = $this->userMappingManager->getMappingsForUser($user);

		$rules = [];

		foreach ($keys['rule_path_keys'] as $key) {
			if (!$this->has($key)) {
				return null;
			}

			$rule = $this->get($key);

			if ($this->checkMappingInRule($userMappings, $rule)) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}
}
