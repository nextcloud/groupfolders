<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Core\Command;

use OC\Core\Command\User\ListCommand;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Base extends Command implements CompletionAwareInterface {
	public const OUTPUT_FORMAT_PLAIN = 'plain';
	public const OUTPUT_FORMAT_JSON = 'json';
	public const OUTPUT_FORMAT_JSON_PRETTY = 'json_pretty';

	protected string $defaultOutputFormat = self::OUTPUT_FORMAT_PLAIN;

	protected function configure()
 {
 }

	protected function writeArrayInOutputFormat(InputInterface $input, OutputInterface $output, iterable $items, string $prefix = '  - '): void
 {
 }

	protected function writeTableInOutputFormat(InputInterface $input, OutputInterface $output, array $items): void
 {
 }


	/**
	 * @param mixed $item
	 */
	protected function writeMixedInOutputFormat(InputInterface $input, OutputInterface $output, $item)
 {
 }

	protected function valueToString($value, bool $returnNull = true): ?string
 {
 }

	/**
	 * Throw InterruptedException when interrupted by user
	 *
	 * @throws InterruptedException
	 */
	protected function abortIfInterrupted()
 {
 }

	/**
	 * Changes the status of the command to "interrupted" if ctrl-c has been pressed
	 *
	 * Gives a chance to the command to properly terminate what it's doing
	 */
	public function cancelOperation(): void
 {
 }

	public function run(InputInterface $input, OutputInterface $output): int
 {
 }

	/**
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context)
 {
 }

	/**
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context)
 {
 }
}
