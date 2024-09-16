<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Question;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;

/**
 * Represents a Question.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Question
{
    /**
     * @param string                     $question The question to ask to the user
     * @param string|bool|int|float|null $default  The default answer to return if the user enters nothing
     */
    public function __construct(string $question, $default = null)
    {
    }

    /**
     * Returns the question.
     *
     * @return string
     */
    public function getQuestion()
    {
    }

    /**
     * Returns the default answer.
     *
     * @return string|bool|int|float|null
     */
    public function getDefault()
    {
    }

    /**
     * Returns whether the user response accepts newline characters.
     */
    public function isMultiline(): bool
    {
    }

    /**
     * Sets whether the user response should accept newline characters.
     *
     * @return $this
     */
    public function setMultiline(bool $multiline): self
    {
    }

    /**
     * Returns whether the user response must be hidden.
     *
     * @return bool
     */
    public function isHidden()
    {
    }

    /**
     * Sets whether the user response must be hidden or not.
     *
     * @return $this
     *
     * @throws LogicException In case the autocompleter is also used
     */
    public function setHidden(bool $hidden)
    {
    }

    /**
     * In case the response cannot be hidden, whether to fallback on non-hidden question or not.
     *
     * @return bool
     */
    public function isHiddenFallback()
    {
    }

    /**
     * Sets whether to fallback on non-hidden question if the response cannot be hidden.
     *
     * @return $this
     */
    public function setHiddenFallback(bool $fallback)
    {
    }

    /**
     * Gets values for the autocompleter.
     *
     * @return iterable|null
     */
    public function getAutocompleterValues()
    {
    }

    /**
     * Sets values for the autocompleter.
     *
     * @return $this
     *
     * @throws LogicException
     */
    public function setAutocompleterValues(?iterable $values)
    {
    }

    /**
     * Gets the callback function used for the autocompleter.
     */
    public function getAutocompleterCallback(): ?callable
    {
    }

    /**
     * Sets the callback function used for the autocompleter.
     *
     * The callback is passed the user input as argument and should return an iterable of corresponding suggestions.
     *
     * @return $this
     */
    public function setAutocompleterCallback(?callable $callback = null): self
    {
    }

    /**
     * Sets a validator for the question.
     *
     * @return $this
     */
    public function setValidator(?callable $validator = null)
    {
    }

    /**
     * Gets the validator for the question.
     *
     * @return callable|null
     */
    public function getValidator()
    {
    }

    /**
     * Sets the maximum number of attempts.
     *
     * Null means an unlimited number of attempts.
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the number of attempts is invalid
     */
    public function setMaxAttempts(?int $attempts)
    {
    }

    /**
     * Gets the maximum number of attempts.
     *
     * Null means an unlimited number of attempts.
     *
     * @return int|null
     */
    public function getMaxAttempts()
    {
    }

    /**
     * Sets a normalizer for the response.
     *
     * The normalizer can be a callable (a string), a closure or a class implementing __invoke.
     *
     * @return $this
     */
    public function setNormalizer(callable $normalizer)
    {
    }

    /**
     * Gets the normalizer for the response.
     *
     * The normalizer can ba a callable (a string), a closure or a class implementing __invoke.
     *
     * @return callable|null
     */
    public function getNormalizer()
    {
    }

    protected function isAssoc(array $array)
    {
    }

    public function isTrimmable(): bool
    {
    }

    /**
     * @return $this
     */
    public function setTrimmable(bool $trimmable): self
    {
    }
}
