<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Exception\InvalidTableName;
use Doctrine\DBAL\Schema\Visitor\Visitor;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function array_filter;
use function array_keys;
use function array_merge;
use function in_array;
use function preg_match;
use function strlen;
use function strtolower;

use const ARRAY_FILTER_USE_KEY;

/**
 * Object Representation of a table.
 */
class Table extends AbstractAsset
{
    /** @var Column[] */
    protected $_columns = [];

    /** @var Index[] */
    protected $_indexes = [];

    /** @var string|null */
    protected $_primaryKeyName;

    /** @var UniqueConstraint[] */
    protected $uniqueConstraints = [];

    /** @var ForeignKeyConstraint[] */
    protected $_fkConstraints = [];

    /** @var mixed[] */
    protected $_options = [
        'create_options' => [],
    ];

    /** @var SchemaConfig|null */
    protected $_schemaConfig;

    /**
     * @param Column[]               $columns
     * @param Index[]                $indexes
     * @param UniqueConstraint[]     $uniqueConstraints
     * @param ForeignKeyConstraint[] $fkConstraints
     * @param mixed[]                $options
     *
     * @throws SchemaException
     * @throws Exception
     */
    public function __construct(string $name, array $columns = [], array $indexes = [], array $uniqueConstraints = [], array $fkConstraints = [], array $options = [])
    {
    }

    /** @return void */
    public function setSchemaConfig(SchemaConfig $schemaConfig)
    {
    }

    /** @return int */
    protected function _getMaxIdentifierLength()
    {
    }

    /**
     * Sets the Primary Key.
     *
     * @param string[]     $columnNames
     * @param string|false $indexName
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function setPrimaryKey(array $columnNames, $indexName = false)
    {
    }

    /**
     * @param string[] $columnNames
     * @param string[] $flags
     * @param mixed[]  $options
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function addIndex(array $columnNames, ?string $indexName = null, array $flags = [], array $options = [])
    {
    }

    /**
     * @param string[] $columnNames
     * @param string[] $flags
     * @param mixed[]  $options
     *
     * @return self
     */
    public function addUniqueConstraint(array $columnNames, ?string $indexName = null, array $flags = [], array $options = []): Table
    {
    }

    /**
     * Drops the primary key from this table.
     *
     * @return void
     *
     * @throws SchemaException
     */
    public function dropPrimaryKey()
    {
    }

    /**
     * Drops an index from this table.
     *
     * @param string $name The index name.
     *
     * @return void
     *
     * @throws SchemaException If the index does not exist.
     */
    public function dropIndex($name)
    {
    }

    /**
     * @param string[]    $columnNames
     * @param string|null $indexName
     * @param mixed[]     $options
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function addUniqueIndex(array $columnNames, $indexName = null, array $options = [])
    {
    }

    /**
     * Renames an index.
     *
     * @param string      $oldName The name of the index to rename from.
     * @param string|null $newName The name of the index to rename to.
     *                                  If null is given, the index name will be auto-generated.
     *
     * @return self This table instance.
     *
     * @throws SchemaException If no index exists for the given current name
     *                         or if an index with the given new name already exists on this table.
     */
    public function renameIndex($oldName, $newName = null)
    {
    }

    /**
     * Checks if an index begins in the order of the given columns.
     *
     * @param string[] $columnNames
     *
     * @return bool
     */
    public function columnsAreIndexed(array $columnNames)
    {
    }

    /**
     * @param string  $name
     * @param string  $typeName
     * @param mixed[] $options
     *
     * @return Column
     *
     * @throws SchemaException
     */
    public function addColumn($name, $typeName, array $options = [])
    {
    }

    /**
     * Change Column Details.
     *
     * @deprecated Use {@link modifyColumn()} instead.
     *
     * @param string  $name
     * @param mixed[] $options
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function changeColumn($name, array $options)
    {
    }

    /**
     * @param string  $name
     * @param mixed[] $options
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function modifyColumn($name, array $options)
    {
    }

    /**
     * Drops a Column from the Table.
     *
     * @param string $name
     *
     * @return self
     */
    public function dropColumn($name)
    {
    }

    /**
     * Adds a foreign key constraint.
     *
     * Name is inferred from the local columns.
     *
     * @param Table|string $foreignTable       Table schema instance or table name
     * @param string[]     $localColumnNames
     * @param string[]     $foreignColumnNames
     * @param mixed[]      $options
     * @param string|null  $name
     *
     * @return self
     *
     * @throws SchemaException
     */
    public function addForeignKeyConstraint($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options = [], $name = null)
    {
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return self
     */
    public function addOption($name, $value)
    {
    }

    /**
     * @return void
     *
     * @throws SchemaException
     */
    protected function _addColumn(Column $column)
    {
    }

    /**
     * Adds an index to the table.
     *
     * @return self
     *
     * @throws SchemaException
     */
    protected function _addIndex(Index $indexCandidate)
    {
    }

    /** @return self */
    protected function _addUniqueConstraint(UniqueConstraint $constraint): Table
    {
    }

    /** @return self */
    protected function _addForeignKeyConstraint(ForeignKeyConstraint $constraint)
    {
    }

    /**
     * Returns whether this table has a foreign key constraint with the given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasForeignKey($name)
    {
    }

    /**
     * Returns the foreign key constraint with the given name.
     *
     * @param string $name The constraint name.
     *
     * @return ForeignKeyConstraint
     *
     * @throws SchemaException If the foreign key does not exist.
     */
    public function getForeignKey($name)
    {
    }

    /**
     * Removes the foreign key constraint with the given name.
     *
     * @param string $name The constraint name.
     *
     * @return void
     *
     * @throws SchemaException
     */
    public function removeForeignKey($name)
    {
    }

    /**
     * Returns whether this table has a unique constraint with the given name.
     */
    public function hasUniqueConstraint(string $name): bool
    {
    }

    /**
     * Returns the unique constraint with the given name.
     *
     * @throws SchemaException If the unique constraint does not exist.
     */
    public function getUniqueConstraint(string $name): UniqueConstraint
    {
    }

    /**
     * Removes the unique constraint with the given name.
     *
     * @throws SchemaException If the unique constraint does not exist.
     */
    public function removeUniqueConstraint(string $name): void
    {
    }

    /**
     * Returns ordered list of columns (primary keys are first, then foreign keys, then the rest)
     *
     * @return Column[]
     */
    public function getColumns()
    {
    }

    /**
     * Returns the foreign key columns
     *
     * @deprecated Use {@see getForeignKey()} and {@see ForeignKeyConstraint::getLocalColumns()} instead.
     *
     * @return Column[]
     */
    public function getForeignKeyColumns()
    {
    }

    /**
     * Returns whether this table has a Column with the given name.
     *
     * @param string $name The column name.
     *
     * @return bool
     */
    public function hasColumn($name)
    {
    }

    /**
     * Returns the Column with the given name.
     *
     * @param string $name The column name.
     *
     * @return Column
     *
     * @throws SchemaException If the column does not exist.
     */
    public function getColumn($name)
    {
    }

    /**
     * Returns the primary key.
     *
     * @return Index|null The primary key, or null if this Table has no primary key.
     */
    public function getPrimaryKey()
    {
    }

    /**
     * Returns the primary key columns.
     *
     * @deprecated Use {@see getPrimaryKey()} and {@see Index::getColumns()} instead.
     *
     * @return Column[]
     *
     * @throws Exception
     */
    public function getPrimaryKeyColumns()
    {
    }

    /**
     * Returns whether this table has a primary key.
     *
     * @deprecated Use {@see getPrimaryKey()} instead.
     *
     * @return bool
     */
    public function hasPrimaryKey()
    {
    }

    /**
     * Returns whether this table has an Index with the given name.
     *
     * @param string $name The index name.
     *
     * @return bool
     */
    public function hasIndex($name)
    {
    }

    /**
     * Returns the Index with the given name.
     *
     * @param string $name The index name.
     *
     * @return Index
     *
     * @throws SchemaException If the index does not exist.
     */
    public function getIndex($name)
    {
    }

    /** @return Index[] */
    public function getIndexes()
    {
    }

    /**
     * Returns the unique constraints.
     *
     * @return UniqueConstraint[]
     */
    public function getUniqueConstraints(): array
    {
    }

    /**
     * Returns the foreign key constraints.
     *
     * @return ForeignKeyConstraint[]
     */
    public function getForeignKeys()
    {
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasOption($name)
    {
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getOption($name)
    {
    }

    /** @return mixed[] */
    public function getOptions()
    {
    }

    /**
     * @deprecated
     *
     * @return void
     *
     * @throws SchemaException
     */
    public function visit(Visitor $visitor)
    {
    }

    /**
     * Clone of a Table triggers a deep clone of all affected assets.
     *
     * @return void
     */
    public function __clone()
    {
    }

    public function setComment(?string $comment): self
    {
    }

    public function getComment(): ?string
    {
    }
}
