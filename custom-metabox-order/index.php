<?php

/*

Plugin Name: Custom Meta Box Order
Description: Clone meta box order on the fly without overwriting user settings.
Version:     1.0.2
Plugin URI:  https://github.com/pontycode/wordpress-custom-metabox-order/
Author:      Thsurs
Author URI:  https://github.com/pontycode
License:     GPL v2 or later

Copyright © 2013-2015 Pontycode

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

*/

namespace MetaBoxOrder;

/**
 * Configuration
 *
 * Example
 * -------
 *
 * To replace the default function for getting the blueprint user id,
 * add the following to your theme's function.php:
 *
 * \MetaBoxOrder\Config::$getBlueprintUserId = function () { return $someUserId; };
 *
 */
class Config {

    /**
     * Editing screens to operate on
     *
     * @var String
     */
    public static $screens = array(

        'post', // Includes pages & custom post types by default
        'dashboard'
    );

    /**
     * On which specific post & screen types to operate on.
     *
     * @var Array
     */
    public static $filter = array('post', 'page', 'dashboard');

    /**
     * Whether or not to include Custom Post Types.
     *
     * @var Boolean
     */
    public static $include_cpts = true;

    /**
     * Post types to exclude
     *
     * Use this if you want to include Custom Post Types,
     * but exclude some of them.
     *
     * @var Array
     */
    public static $exclude = array();

    /**
     * Register a function here returning
     * a valid user id.
     *
     * See below for default implementation.
     *
     * @var String
     */
    public static $getBlueprintUserId;
}

/**
 * Initialize an editor
 *
 * @return Integer - User Id
 */
Config::$getBlueprintUserId = function () {

    $clone_from = get_users(array('role' => 'administrator'));

    if (!empty($clone_from)) {

        return $clone_from[0]->ID;
    }
};

/**
 * Clone meta box order on the fly, i.e., without
 * actually changing any user settings.
 *
 * Based on work & ideas by:
 * http://gist.github.com/franz-josef-kaiser/9100450
 * http://wordpress.stackexchange.com/a/144608
 * http://wordpress.stackexchange.com/a/19972
 */
class MetaBoxOrder {

    /**
     * User to clone from
     *
     * @var Int
     */
    protected $clone_from_user_id;

    /**
     * Screens to clone in
     *
     * @var Array
     */
    protected $allowed_screens = array();

    /**
     * Meta box keys to clone
     *
     * @var Array
     */
    protected $clone_meta_keys = array();

    /**
     * To prevent an endless loop, we need to be able
     * to remove and re-apply {@see cloneMeta()}.
     *
     * @return void
     */
    protected function removeFilter() {

        remove_filter('get_user_metadata', array($this, 'cloneMeta'));
    }

    /**
     * To prevent an endless loop, we need to be able
     * to remove and re-apply {@see cloneMeta()}.
     *
     * @return void
     */
    protected function addFilter() {

        // http://developer.wordpress.org/reference/hooks/get_meta_type_metadata/
        add_filter('get_user_metadata', array($this, 'cloneMeta'), 10, 3);
    }

    /**
     * What screen we are on.
     *
     * @return WP_Screen|false
     */
    protected function getCurrentScreen() {

        $screen = get_current_screen();

        if (!($screen && property_exists($screen, 'base'))) {

            return false;
        }

        return $screen;
    }

    /**
     * Normalize a meta key, which might or might not
     * be prefixed.
     *
     * @param  String
     * @return String - a key without its prefix
     */
    public function normalizeMetaKey($meta_key) {

        global $wpdb;

        if (strrpos($meta_key, $wpdb->prefix) === 0) {

            $meta_key = substr($meta_key, strlen($wpdb->prefix));
        }

        return $meta_key;
    }

    /**
     * Clone meta box order (technically, this will clone any key
     * registered by {@see setMetaKeys()}).
     *
     * @return void
     */
    public function cloneMeta($abort, $userID, $meta_key) {

        if ($this->clone_from_user_id == $userID) {

           return $abort;
        }

        // Don't trigger on the wrong screens

        $screen = $this->getCurrentScreen();

        if (!$screen) {

            return $abort;
        }

        if (!in_array($screen->base, $this->allowed_screens)) {

            $this->removeFilter();
            return $abort;
        }

        // Clone

        $meta_key = $this->normalizeMetaKey($meta_key);

        if (!in_array($meta_key, $this->clone_meta_keys)) {

            return $abort;
        }

        // Remove ourselves...

        $this->removeFilter();

        // ...because we don't want to get caught in an endless loop while cloning

        $cloned = get_user_meta(

            $this->clone_from_user_id,
            $meta_key,
            true
        );

        if (is_array($cloned)) {

            $cloned = array($cloned); // wp-includes/meta.php::get_metadata() will ask for a $check[0] (~448ff.);
                                      // since this is what becomes $check, make sure it has [0].
        }

        // WP might call for the same user data dozens of times. Make sure it keeps us posted again.

        $this->addFilter();

        // The easy part. Return the clone.

        return $cloned;
    }

    /**
     * Clone colum layout
     *
     * @return void
     */
    public function cloneColumnLayout() {

        $screens = array_unique(array_map(function ($meta_key) {

            return substr($meta_key, strpos($meta_key, '_'));

        }, $this->clone_meta_keys));

        foreach ($screens as $screen) {

            $clone = get_user_option('screen_layout'.$screen, $this->clone_from_user_id);

            add_filter('get_user_option_screen_layout'.$screen, function ($columns) use ($clone) {

                return $clone ? $clone : $columns;

            }, 10, 1);
        }
    }

    /**
     * Apply configuration
     *
     * @return void
     */
        protected function setup() {

        $screens  = Config::$screens;
        $subtypes = Config::$filter;

        if (Config::$include_cpts) {

            $cpts     = get_post_types(array('_builtin' => false));
            $subtypes = array_merge($subtypes, $cpts);
        }

        foreach (Config::$exclude as $type) {

            $exclude = array_search($type, $subtypes);

            if ($exclude !== false) {

                unset($subtypes[$exclude]);
            }
        }

        foreach ($subtypes as $type) {

            $this->clone_meta_keys[] = 'meta-box-order_'.$type;
            $this->clone_meta_keys[] = 'metaboxhidden_'.$type;
        }

        $this->allowed_screens = $screens;
    }

    /**
     * Init
     */
    public function __construct() {

        $getId = Config::$getBlueprintUserId;

        if (is_callable($getId)) {

            $id   = $getId();
            $user = get_user_by('id', $id);

            if ($id && $user) {

                $this->clone_from_user_id = $id;

                $this->setup();
                $this->addFilter();
                $this->cloneColumnLayout();
            }
        }
    }
}

if (is_admin()) {

    add_action('wp_loaded', function () {

        new MetaBoxOrder();
    });
}
