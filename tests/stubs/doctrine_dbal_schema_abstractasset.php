<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;

use function array_map;
use function crc32;
use function dechex;
use function explode;
use function implode;
use function str_replace;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;

/**
 * The abstract asset allows to reset the name of all assets without publishing this to the public userland.
 *
 * This encapsulation hack is necessary to keep a consistent state of the database schema. Say we have a list of tables
 * array($tableName => Table($tableName)); if you want to rename the table, you have to make sure this does not get
 * recreated during schema migration.
 */
abstract class AbstractAsset
{
    /** @var string */
    protected $_name = '';

    /**
     * Namespace of the asset. If none isset the default namespace is assumed.
     *
     * @var string|null
     */
    protected $_namespace;

    /** @var bool */
    protected $_quoted = false;

    /**
     * Sets the name of this asset.
     *
     * @param string $name
     *
     * @return void
     */
    protected function _setName($name)
    {
    }

    /**
     * Is this asset in the default namespace?
     *
     * @param string $defaultNamespaceName
     *
     * @return bool
     */
    public function isInDefaultNamespace($defaultNamespaceName)
    {
    }

    /**
     * Gets the namespace name of this asset.
     *
     * If NULL is returned this means the default namespace is used.
     *
     * @return string|null
     */
    public function getNamespaceName()
    {
    }

    /**
     * The shortest name is stripped of the default namespace. All other
     * namespaced elements are returned as full-qualified names.
     *
     * @param string|null $defaultNamespaceName
     *
     * @return string
     */
    public function getShortestName($defaultNamespaceName)
    {
    }

    /**
     * The normalized name is full-qualified and lower-cased. Lower-casing is
     * actually wrong, but we have to do it to keep our sanity. If you are
     * using database objects that only differentiate in the casing (FOO vs
     * Foo) then you will NOT be able to use Doctrine Schema abstraction.
     *
     * Every non-namespaced element is prefixed with the default namespace
     * name which is passed as argument to this method.
     *
     * @deprecated Use {@see getNamespaceName()} and {@see getName()} instead.
     *
     * @param string $defaultNamespaceName
     *
     * @return string
     */
    public function getFullQualifiedName($defaultNamespaceName)
    {
    }

    /**
     * Checks if this asset's name is quoted.
     *
     * @return bool
     */
    public function isQuoted()
    {
    }

    /**
     * Checks if this identifier is quoted.
     *
     * @param string $identifier
     *
     * @return bool
     */
    protected function isIdentifierQuoted($identifier)
    {
    }

    /**
     * Trim quotes from the identifier.
     *
     * @param string $identifier
     *
     * @return string
     */
    protected function trimQuotes($identifier)
    {
    }

    /**
     * Returns the name of this schema asset.
     *
     * @return string
     */
    public function getName()
    {
    }

    /**
     * Gets the quoted representation of this asset but only if it was defined with one. Otherwise
     * return the plain unquoted value as inserted.
     *
     * @return string
     */
    public function getQuotedName(AbstractPlatform $platform)
    {
    }

    /**
     * Generates an identifier from a list of column names obeying a certain string length.
     *
     * This is especially important for Oracle, since it does not allow identifiers larger than 30 chars,
     * however building idents automatically for foreign keys, composite keys or such can easily create
     * very long names.
     *
     * @param string[] $columnNames
     * @param string   $prefix
     * @param int      $maxSize
     *
     * @return string
     */
    protected function _generateIdentifierName($columnNames, $prefix = '', $maxSize = 30)
    {
    }
}
