<?php

namespace Doctrine\DBAL\Schema;

use Doctrine\DBAL\Schema\Exception\UnknownColumnOption;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function array_merge;
use function is_numeric;
use function method_exists;

/**
 * Object representation of a database column.
 */
class Column extends AbstractAsset
{
    /** @var Type */
    protected $_type;

    /** @var int|null */
    protected $_length;

    /** @var int */
    protected $_precision = 10;

    /** @var int */
    protected $_scale = 0;

    /** @var bool */
    protected $_unsigned = false;

    /** @var bool */
    protected $_fixed = false;

    /** @var bool */
    protected $_notnull = true;

    /** @var mixed */
    protected $_default;

    /** @var bool */
    protected $_autoincrement = false;

    /** @var mixed[] */
    protected $_platformOptions = [];

    /** @var string|null */
    protected $_columnDefinition;

    /** @var string|null */
    protected $_comment;

    /**
     * @deprecated Use {@link $_platformOptions} instead
     *
     * @var mixed[]
     */
    protected $_customSchemaOptions = [];

    /**
     * Creates a new Column.
     *
     * @param string  $name
     * @param mixed[] $options
     *
     * @throws SchemaException
     */
    public function __construct($name, Type $type, array $options = [])
    {
    }

    /**
     * @param mixed[] $options
     *
     * @return Column
     *
     * @throws SchemaException
     */
    public function setOptions(array $options)
    {
    }

    /** @return Column */
    public function setType(Type $type)
    {
    }

    /**
     * @param int|null $length
     *
     * @return Column
     */
    public function setLength($length)
    {
    }

    /**
     * @param int $precision
     *
     * @return Column
     */
    public function setPrecision($precision)
    {
    }

    /**
     * @param int $scale
     *
     * @return Column
     */
    public function setScale($scale)
    {
    }

    /**
     * @param bool $unsigned
     *
     * @return Column
     */
    public function setUnsigned($unsigned)
    {
    }

    /**
     * @param bool $fixed
     *
     * @return Column
     */
    public function setFixed($fixed)
    {
    }

    /**
     * @param bool $notnull
     *
     * @return Column
     */
    public function setNotnull($notnull)
    {
    }

    /**
     * @param mixed $default
     *
     * @return Column
     */
    public function setDefault($default)
    {
    }

    /**
     * @param mixed[] $platformOptions
     *
     * @return Column
     */
    public function setPlatformOptions(array $platformOptions)
    {
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return Column
     */
    public function setPlatformOption($name, $value)
    {
    }

    /**
     * @param string|null $value
     *
     * @return Column
     */
    public function setColumnDefinition($value)
    {
    }

    /** @return Type */
    public function getType()
    {
    }

    /** @return int|null */
    public function getLength()
    {
    }

    /** @return int */
    public function getPrecision()
    {
    }

    /** @return int */
    public function getScale()
    {
    }

    /** @return bool */
    public function getUnsigned()
    {
    }

    /** @return bool */
    public function getFixed()
    {
    }

    /** @return bool */
    public function getNotnull()
    {
    }

    /** @return mixed */
    public function getDefault()
    {
    }

    /** @return mixed[] */
    public function getPlatformOptions()
    {
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasPlatformOption($name)
    {
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function getPlatformOption($name)
    {
    }

    /** @return string|null */
    public function getColumnDefinition()
    {
    }

    /** @return bool */
    public function getAutoincrement()
    {
    }

    /**
     * @param bool $flag
     *
     * @return Column
     */
    public function setAutoincrement($flag)
    {
    }

    /**
     * @param string|null $comment
     *
     * @return Column
     */
    public function setComment($comment)
    {
    }

    /** @return string|null */
    public function getComment()
    {
    }

    /**
     * @deprecated Use {@link setPlatformOption()} instead
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return Column
     */
    public function setCustomSchemaOption($name, $value)
    {
    }

    /**
     * @deprecated Use {@link hasPlatformOption()} instead
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasCustomSchemaOption($name)
    {
    }

    /**
     * @deprecated Use {@link getPlatformOption()} instead
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getCustomSchemaOption($name)
    {
    }

    /**
     * @deprecated Use {@link setPlatformOptions()} instead
     *
     * @param mixed[] $customSchemaOptions
     *
     * @return Column
     */
    public function setCustomSchemaOptions(array $customSchemaOptions)
    {
    }

    /**
     * @deprecated Use {@link getPlatformOptions()} instead
     *
     * @return mixed[]
     */
    public function getCustomSchemaOptions()
    {
    }

    /** @return mixed[] */
    public function toArray()
    {
    }
}
