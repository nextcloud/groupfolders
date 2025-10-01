<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\Streams;

/**
 * Create a directory handle from an iterator or array
 *
 * The following options should be passed in the context when opening the stream
 * [
 *     'dir' => [
 *        'array'    => string[]
 *        'iterator' => \Iterator
 *     ]
 * ]
 *
 * Either 'array' or 'iterator' need to be set, if both are set, 'iterator' takes preference
 */
class IteratorDirectory extends WrapperHandler implements Directory {
	/**
	 * @var resource
	 */
	public $context;

	/**
	 * @var \Iterator
	 */
	protected $iterator;

	/**
	 * Load the source from the stream context and return the context options
	 *
	 * @param string $name
	 * @return array
	 * @throws \BadMethodCallException
	 */
	protected function loadContext($name = null)
 {
 }

	/**
	 * @param string $path
	 * @param array $options
	 * @return bool
	 */
	public function dir_opendir($path, $options)
 {
 }

	/**
	 * @return string|bool
	 */
	public function dir_readdir()
 {
 }

	/**
	 * @return bool
	 */
	public function dir_closedir()
 {
 }

	/**
	 * @return bool
	 */
	public function dir_rewinddir()
 {
 }

	/**
	 * Creates a directory handle from the provided array or iterator
	 *
	 * @param \Iterator | array $source
	 * @return resource|false
	 *
	 * @throws \BadMethodCallException
	 */
	public static function wrap($source)
 {
 }
}
