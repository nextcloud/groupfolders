<?php

namespace Doctrine\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use SensitiveParameter;

use function get_class;
use function gettype;
use function implode;
use function is_object;
use function spl_object_hash;
use function sprintf;

/** @psalm-immutable */
class Exception extends \Exception
{
    public static function notSupported(string $method): self
    {
    }

    /** @param mixed $invalidPlatform */
    public static function invalidPlatformType($invalidPlatform): self
    {
    }

    /**
     * Returns a new instance for an invalid specified platform version.
     *
     * @param string $version        The invalid platform version given.
     * @param string $expectedFormat The expected platform version format.
     */
    public static function invalidPlatformVersionSpecified(string $version, string $expectedFormat): self
    {
    }

    /** @param string|null $url The URL that was provided in the connection parameters (if any). */
    public static function driverRequired(#[SensitiveParameter]
    ?string $url = null): self
    {
    }

    /** @param string[] $knownDrivers */
    public static function unknownDriver(string $unknownDriverName, array $knownDrivers): self
    {
    }

    public static function invalidWrapperClass(string $wrapperClass): self
    {
    }

    public static function invalidDriverClass(string $driverClass): self
    {
    }

    public static function noColumnsSpecifiedForTable(string $tableName): self
    {
    }

    public static function typeExists(string $name): self
    {
    }

    public static function unknownColumnType(string $name): self
    {
    }

    public static function typeNotFound(string $name): self
    {
    }

    public static function typeNotRegistered(Type $type): self
    {
    }

    public static function typeAlreadyRegistered(Type $type): self
    {
    }
}
