<?php

/**
 * Part of the Sentinel package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the Cartalyst PSL License.
 *
 * This source file is subject to the Cartalyst PSL License that is
 * bundled with this package in the LICENSE file.
 *
 * @package    Sentinel
 * @version    2.0.1
 * @author     Cartalyst LLC
 * @license    Cartalyst PSL
 * @copyright  (c) 2011-2015, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Cartalyst\Sentinel\Hashing;

interface HasherInterface
{
    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @return string
     * @throws \RuntimeException
     */
    public function hash($value);

    /**
     * Checks the string against the hashed value.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @return bool
     */
    public function check($value, $hashedValue);
}
