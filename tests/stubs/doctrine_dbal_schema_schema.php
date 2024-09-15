<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Visitor\NamespaceVisitor;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\SQL\Builder\CreateSchemaObjectsSQLBuilder;
use Doctrine\DBAL\SQL\Builder\DropSchemaObjectsSQLBuilder;
use Doctrine\Deprecations\Deprecation;

use function array_keys;
use function strpos;
use function strtolower;

/**
 * Object representation of a database schema.
 *
 * Different vendors have very inconsistent naming with regard to the concept
 * of a "schema". Doctrine understands a schema as the entity that conceptually
 * wraps a set of database objects such as tables, sequences, indexes and
 * foreign keys that belong to each other into a namespace. A Doctrine Schema
 * has nothing to do with the "SCHEMA" defined as in PostgreSQL, it is more
 * related to the concept of "DATABASE" that exists in MySQL and PostgreSQL.
 *
 * Every asset in the doctrine schema has a name. A name consists of either a
 * namespace.local name pair or just a local unqualified name.
 *
 * The abstraction layer that covers a PostgreSQL schema is the namespace of an
 * database object (asset). A schema can have a name, which will be used as
 * default namespace for the unqualified database objects that are created in
 * the schema.
 *
 * In the case of MySQL where cross-database queries are allowed this leads to
 * databases being "misinterpreted" as namespaces. This is intentional, however
 * the CREATE/DROP SQL visitors will just filter this queries and do not
 * execute them. Only the queries for the currently connected database are
 * executed.
 */
class Schema extends AbstractAsset
{
    /** @var Table[] */
    protected $_tables = [];

    /** @var Sequence[] */
    protected $_sequences = [];

    /** @var SchemaConfig */
    protected $_schemaConfig;

    /**
     * @param Table[]    $tables
     * @param Sequence[] $sequences
     * @param string[]   $namespaces
     *
     * @throws SchemaException
     */
    public function __construct(array $tables = [], array $sequences = [], ?SchemaConfig $schemaConfig = null, array $namespaces = [])
    {
    }

    /**
     * @deprecated
     *
     * @return bool
     */
    public function hasExplicitForeignKeyIndexes()
    {
    }

    /**
     * @return void
     *
     * @throws SchemaException
     */
    protected function _addTable(Table $table)
    {
    }

    /**
     * @return void
     *
     * @throws SchemaException
     */
    protected function _addSequence(Sequence $sequence)
    {
    }

    /**
     * Returns the namespaces of this schema.
     *
     * @return string[] A list of namespace names.
     */
    public function getNamespaces()
    {
    }

    /**
     * Gets all tables of this schema.
     *
     * @return Table[]
     */
    public function getTables()
    {
    }

    /**
     * @param string $name
     *
     * @return Table
     *
     * @throws SchemaException
     */
    public function getTable($name)
    {
    }

    /**
     * Does this schema have a namespace with the given name?
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasNamespace($name)
    {
    }

    /**
     * Does this schema have a table with the given name?
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasTable($name)
    {
    }

    /**
     * Gets all table names, prefixed with a schema name, even the default one if present.
     *
     * @deprecated Use {@see getTables()} and {@see Table::getName()} instead.
     *
     * @return string[]
     */
    public function getTableNames()
    {
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasSequence($name)
    {
    }

    /**
     * @param string $name
     *
     * @return Sequence
     *
     * @throws SchemaException
     */
    public function getSequence($name)
    {
    }

    /** @return Sequence[] */
    public function getSequences()
    {
    }

    /**
     * Creates a new namespace.
     *
     * @param string $name The name of the namespace to create.
     *
     * @return Schema This schema instance.
     *
     * @throws SchemaException
     */
    public function createNamespace($name)
    {
    }

    /**
     * Creates a new table.
     *
     * @param string $name
     *
     * @return Table
     *
     * @throws SchemaException
     */
    public function createTable($name)
    {
    }

    /**
     * Renames a table.
     *
     * @param string $oldName
     * @param string $newName
     *
     * @return Schema
     *
     * @throws SchemaException
     */
    public function renameTable($oldName, $newName)
    {
    }

    /**
     * Drops a table from the schema.
     *
     * @param string $name
     *
     * @return Schema
     *
     * @throws SchemaException
     */
    public function dropTable($name)
    {
    }

    /**
     * Creates a new sequence.
     *
     * @param string $name
     * @param int    $allocationSize
     * @param int    $initialValue
     *
     * @return Sequence
     *
     * @throws SchemaException
     */
    public function createSequence($name, $allocationSize = 1, $initialValue = 1)
    {
    }

    /**
     * @param string $name
     *
     * @return Schema
     */
    public function dropSequence($name)
    {
    }

    /**
     * Returns an array of necessary SQL queries to create the schema on the given platform.
     *
     * @return list<string>
     *
     * @throws Exception
     */
    public function toSql(AbstractPlatform $platform)
    {
    }

    /**
     * Return an array of necessary SQL queries to drop the schema on the given platform.
     *
     * @return list<string>
     *
     * @throws Exception
     */
    public function toDropSql(AbstractPlatform $platform)
    {
    }

    /**
     * @deprecated
     *
     * @return string[]
     *
     * @throws SchemaException
     */
    public function getMigrateToSql(Schema $toSchema, AbstractPlatform $platform)
    {
    }

    /**
     * @deprecated
     *
     * @return string[]
     *
     * @throws SchemaException
     */
    public function getMigrateFromSql(Schema $fromSchema, AbstractPlatform $platform)
    {
    }

    /**
     * @deprecated
     *
     * @return void
     */
    public function visit(Visitor $visitor)
    {
    }

    /**
     * Cloning a Schema triggers a deep clone of all related assets.
     *
     * @return void
     */
    public function __clone()
    {
    }
}
