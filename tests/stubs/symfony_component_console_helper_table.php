<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\WrappableOutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides helpers to display a table.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Саша Стаменковић <umpirsky@gmail.com>
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author Max Grigorian <maxakawizard@gmail.com>
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class Table
{
    private const SEPARATOR_TOP = 0;
    private const SEPARATOR_TOP_BOTTOM = 1;
    private const SEPARATOR_MID = 2;
    private const SEPARATOR_BOTTOM = 3;
    private const BORDER_OUTSIDE = 0;
    private const BORDER_INSIDE = 1;

    public function __construct(OutputInterface $output)
    {
    }

    /**
     * Sets a style definition.
     */
    public static function setStyleDefinition(string $name, TableStyle $style)
    {
    }

    /**
     * Gets a style definition by name.
     *
     * @return TableStyle
     */
    public static function getStyleDefinition(string $name)
    {
    }

    /**
     * Sets table style.
     *
     * @param TableStyle|string $name The style name or a TableStyle instance
     *
     * @return $this
     */
    public function setStyle($name)
    {
    }

    /**
     * Gets the current table style.
     *
     * @return TableStyle
     */
    public function getStyle()
    {
    }

    /**
     * Sets table column style.
     *
     * @param TableStyle|string $name The style name or a TableStyle instance
     *
     * @return $this
     */
    public function setColumnStyle(int $columnIndex, $name)
    {
    }

    /**
     * Gets the current style for a column.
     *
     * If style was not set, it returns the global table style.
     *
     * @return TableStyle
     */
    public function getColumnStyle(int $columnIndex)
    {
    }

    /**
     * Sets the minimum width of a column.
     *
     * @return $this
     */
    public function setColumnWidth(int $columnIndex, int $width)
    {
    }

    /**
     * Sets the minimum width of all columns.
     *
     * @return $this
     */
    public function setColumnWidths(array $widths)
    {
    }

    /**
     * Sets the maximum width of a column.
     *
     * Any cell within this column which contents exceeds the specified width will be wrapped into multiple lines, while
     * formatted strings are preserved.
     *
     * @return $this
     */
    public function setColumnMaxWidth(int $columnIndex, int $width): self
    {
    }

    /**
     * @return $this
     */
    public function setHeaders(array $headers)
    {
    }

    public function setRows(array $rows)
    {
    }

    /**
     * @return $this
     */
    public function addRows(array $rows)
    {
    }

    /**
     * @return $this
     */
    public function addRow($row)
    {
    }

    /**
     * Adds a row to the table, and re-renders the table.
     *
     * @return $this
     */
    public function appendRow($row): self
    {
    }

    /**
     * @return $this
     */
    public function setRow($column, array $row)
    {
    }

    /**
     * @return $this
     */
    public function setHeaderTitle(?string $title): self
    {
    }

    /**
     * @return $this
     */
    public function setFooterTitle(?string $title): self
    {
    }

    /**
     * @return $this
     */
    public function setHorizontal(bool $horizontal = true): self
    {
    }

    /**
     * Renders table to output.
     *
     * Example:
     *
     *     +---------------+-----------------------+------------------+
     *     | ISBN          | Title                 | Author           |
     *     +---------------+-----------------------+------------------+
     *     | 99921-58-10-7 | Divine Comedy         | Dante Alighieri  |
     *     | 9971-5-0210-0 | A Tale of Two Cities  | Charles Dickens  |
     *     | 960-425-059-0 | The Lord of the Rings | J. R. R. Tolkien |
     *     +---------------+-----------------------+------------------+
     */
    public function render()
    {
    }
}
