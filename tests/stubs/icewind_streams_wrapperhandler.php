<?php
/**
 * SPDX-FileCopyrightText: 2019 Robin Appelman <robin@icewind.nl>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Icewind\Streams;

class WrapperHandler {
	/** @var resource $context */
	protected $context;

	const NO_SOURCE_DIR = 1;

	/**
	 * get the protocol name that is generated for the class
	 * @param string|null $class
	 * @return string
	 */
	public static function getProtocol($class = null)
 {
 }

	/**
	 * @param resource|int $source
	 * @param resource|array $context
	 * @param string|null $protocol deprecated, protocol is now automatically generated
	 * @param string|null $class deprecated, class is now automatically generated
	 * @return resource|false
	 */
	protected static function wrapSource($source, $context = [], $protocol = null, $class = null, $mode = 'r+')
 {
 }

	protected static function isDirectoryHandle($resource)
 {
 }

	/**
	 * Load the source from the stream context and return the context options
	 *
	 * @param string|null $name if not set, the generated protocol name is used
	 * @return array
	 * @throws \BadMethodCallException
	 */
	protected function loadContext($name = null)
 {
 }
}
