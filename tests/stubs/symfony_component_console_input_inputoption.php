<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;

/**
 * Represents a command line option.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InputOption
{
    /**
     * Do not accept input for the option (e.g. --yell). This is the default behavior of options.
     */
    public const VALUE_NONE = 1;

    /**
     * A value must be passed when the option is used (e.g. --iterations=5 or -i5).
     */
    public const VALUE_REQUIRED = 2;

    /**
     * The option may or may not have a value (e.g. --yell or --yell=loud).
     */
    public const VALUE_OPTIONAL = 4;

    /**
     * The option accepts multiple values (e.g. --dir=/foo --dir=/bar).
     */
    public const VALUE_IS_ARRAY = 8;

    /**
     * The option may have either positive or negative value (e.g. --ansi or --no-ansi).
     */
    public const VALUE_NEGATABLE = 16;

    /**
     * @param string|array|null                $shortcut The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param int|null                         $mode     The option mode: One of the VALUE_* constants
     * @param string|bool|int|float|array|null $default  The default value (must be null for self::VALUE_NONE)
     *
     * @throws InvalidArgumentException If option mode is invalid or incompatible
     */
    public function __construct(string $name, $shortcut = null, ?int $mode = null, string $description = '', $default = null)
    {
    }

    /**
     * Returns the option shortcut.
     *
     * @return string|null
     */
    public function getShortcut()
    {
    }

    /**
     * Returns the option name.
     *
     * @return string
     */
    public function getName()
    {
    }

    /**
     * Returns true if the option accepts a value.
     *
     * @return bool true if value mode is not self::VALUE_NONE, false otherwise
     */
    public function acceptValue()
    {
    }

    /**
     * Returns true if the option requires a value.
     *
     * @return bool true if value mode is self::VALUE_REQUIRED, false otherwise
     */
    public function isValueRequired()
    {
    }

    /**
     * Returns true if the option takes an optional value.
     *
     * @return bool true if value mode is self::VALUE_OPTIONAL, false otherwise
     */
    public function isValueOptional()
    {
    }

    /**
     * Returns true if the option can take multiple values.
     *
     * @return bool true if mode is self::VALUE_IS_ARRAY, false otherwise
     */
    public function isArray()
    {
    }

    public function isNegatable(): bool
    {
    }

    /**
     * @param string|bool|int|float|array|null $default
     */
    public function setDefault($default = null)
    {
    }

    /**
     * Returns the default value.
     *
     * @return string|bool|int|float|array|null
     */
    public function getDefault()
    {
    }

    /**
     * Returns the description text.
     *
     * @return string
     */
    public function getDescription()
    {
    }

    /**
     * Checks whether the given option equals this one.
     *
     * @return bool
     */
    public function equals(self $option)
    {
    }
}
