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
 * Represents a command line argument.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InputArgument
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const IS_ARRAY = 4;

    /**
     * @param string                           $name        The argument name
     * @param int|null                         $mode        The argument mode: a bit mask of self::REQUIRED, self::OPTIONAL and self::IS_ARRAY
     * @param string                           $description A description text
     * @param string|bool|int|float|array|null $default     The default value (for self::OPTIONAL mode only)
     *
     * @throws InvalidArgumentException When argument mode is not valid
     */
    public function __construct(string $name, ?int $mode = null, string $description = '', $default = null)
    {
    }

    /**
     * Returns the argument name.
     *
     * @return string
     */
    public function getName()
    {
    }

    /**
     * Returns true if the argument is required.
     *
     * @return bool true if parameter mode is self::REQUIRED, false otherwise
     */
    public function isRequired()
    {
    }

    /**
     * Returns true if the argument can take multiple values.
     *
     * @return bool true if mode is self::IS_ARRAY, false otherwise
     */
    public function isArray()
    {
    }

    /**
     * Sets the default value.
     *
     * @param string|bool|int|float|array|null $default
     *
     * @throws LogicException When incorrect default value is given
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
}
