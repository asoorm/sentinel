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

namespace Cartalyst\Sentinel\Permissions;

interface PermissibleInterface
{
    /**
     * Returns the permissions instance.
     *
     * @return \Cartalyst\Sentinel\Permissions\PermissionsInterface
     */
    public function getPermissionsInstance();

    /**
     * Adds a permission.
     *
     * @param  string  $permission
     * @param  bool  $value
     * @return \Cartalyst\Sentinel\Permissions\PermissibleInterface
     */
    public function addPermission($permission, $value = true);

    /**
     * Updates a permission.
     *
     * @param  string  $permission
     * @param  bool  $value
     * @param  bool  $create
     * @return \Cartalyst\Sentinel\Permissions\PermissibleInterface
     */
    public function updatePermission($permission, $value = true, $create = false);

    /**
     * Removes a permission.
     *
     * @param  string  $permission
     * @return \Cartalyst\Sentinel\Permissions\PermissibleInterface
     */
    public function removePermission($permission);
}
