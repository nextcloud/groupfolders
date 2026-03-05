<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
use OC\ServerNotAvailableException;
use OCP\HintException;
use OCP\Server;
use Psr\Log\LoggerInterface;

class OC_Hook {
	public static $thrownExceptions = [];

	/**
	 * connects a function to a hook
	 *
	 * @param string $signalClass class name of emitter
	 * @param string $signalName name of signal
	 * @param string|object $slotClass class name of slot
	 * @param string $slotName name of slot
	 * @return bool
	 *
	 * This function makes it very easy to connect to use hooks.
	 *
	 * TODO: write example
	 */
	public static function connect($signalClass, $signalName, $slotClass, $slotName)
 {
 }

	/**
	 * emits a signal
	 *
	 * @param string $signalClass class name of emitter
	 * @param string $signalName name of signal
	 * @param mixed $params default: array() array with additional data
	 * @return bool true if slots exists or false if not
	 * @throws HintException
	 * @throws ServerNotAvailableException Emits a signal. To get data from the slot use references!
	 *
	 * TODO: write example
	 */
	public static function emit($signalClass, $signalName, $params = [])
 {
 }

	/**
	 * clear hooks
	 * @param string $signalClass
	 * @param string $signalName
	 */
	public static function clear($signalClass = '', $signalName = '')
 {
 }

	/**
	 * DO NOT USE!
	 * For unit tests ONLY!
	 */
	public static function getHooks()
 {
 }
}
