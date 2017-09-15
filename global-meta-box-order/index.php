<?php

/*

Plugin Name: Global Meta Box Order
Description: Harmonize column layout and meta box positions across all backend users of your WordPress installation.
Version: 1.0.3
Plugin URI: https://github.com/pontycode/wordpress-custom-metabox-order/
Author: nosurs
Author URI: https://github.com/pontycode
License: GPL v2 or later

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

namespace GlobalMetaBoxOrder;

define(__NAMESPACE__.'\VERSION', '1.0.3');

/**
 * Configuration
 *
 * Example
 * -------
 *
 * To replace the default function for getting the blueprint user id,
 * add the following to your theme's function.php:
 *
 * \GlobalMetaBoxOrder\Config::$getBlueprintUserId = function () { return $someUserId; };
 *
 */
class Config {

    /**
     * On which post & screen types to operate on.
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
     * Remove screen options for selected screens
     *
     * @var Boolean
     */
    public static $remove_screen_options = false;

    /**
     * Lock meta box order. When switched on, your users
     * won't be able to move the boxes around anymore.
     *
     * @var Boolean
     */
    public static $lock_meta_box_order = false;

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
 * Originally based on work & ideas by:
 *
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
     * What base screen we are on, if any.
     *
     * @return WP_Screen|false
     */
    protected function getCurrentScreen() {
        
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {

            return false;
        }

        $screen = get_current_screen();

        if (!($screen && property_exists($screen, 'base'))) {

            return false;
        }

        return $screen;
    }

    /**
     * Whether the given screen is in the list of allowed ones.
     *
     * @param  WP_Screen
     * @return Boolean
     */
    protected function isScreenAllowed(\WP_Screen $screen) {

        // Be as specific as it gets
        $screen = $screen->post_type ? $screen->post_type : $screen->base;
        return in_array($screen, $this->allowed_screens);
    }

    /**
     * Whether the given user is the blueprint user.
     *
     * @param  Int
     * @return Boolean
     */
    protected function isBlueprintUser($user_id) {

        return $this->clone_from_user_id === $user_id;
    }

    /**
     * Normalize a meta key, which might or might not
     * be prefixed.
     *
     * @param  String
     * @return String - a key without its prefix
     */
    protected function normalizeMetaKey($meta_key) {

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
    public function cloneMeta($abort, $user_id, $meta_key) {

        // Return early on wrong user or screen

        if ($this->isBlueprintUser($user_id)) {

           return $abort;
        }

        $screen = $this->getCurrentScreen();

        if (!$screen) {

            return $abort;
        }

        if (!$this->isScreenAllowed($screen)) {

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
     * Clone colum layout.
     *
     * @return void
     */
    protected function cloneColumnLayout() {

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
     * Remove screen options for selected screens.
     *
     * @return void
     */
    protected function removeScreenOptions() {

        add_filter('screen_options_show_screen', function () {

            return !$this->isScreenAllowed($this->getCurrentScreen());
        });
    }

    /**
     * Remove screen options for selected screens.
     *
     * @return void
     */
    protected function lockMetaBoxOrder() {

        add_action('admin_enqueue_scripts', function () {

            $screen = $this->getCurrentScreen();

            if (!$this->isScreenAllowed($screen)) {

                return;
            }

            wp_enqueue_script(

                'global_meta_box_order',
                plugin_dir_url(__FILE__).'lock_order.js',
                array('jquery', 'jquery-ui-sortable'),
                \GlobalMetaBoxOrder\VERSION,
                true
            );
        });
    }

    /**
     * Apply configuration
     *
     * @return void
     */
    protected function setup() {

        $screens  = Config::$filter;

        if (Config::$include_cpts) {

            $cpts    = get_post_types(array('_builtin' => false));
            $screens = array_merge($screens, $cpts);
        }

        foreach (Config::$exclude as $type) {

            $exclude = array_search($type, $screens);

            if ($exclude !== false) {

                unset($screens[$exclude]);
            }
        }

        foreach ($screens as $type) {

            $this->clone_meta_keys[] = 'meta-box-order_'.$type;
            $this->clone_meta_keys[] = 'metaboxhidden_'.$type;
        }

        $this->allowed_screens = $screens;
    }

    /**
     * Init actions.
     *
     * @return void
     */
    protected function init() {

        $this->addFilter(); // Main sorting action
        $this->cloneColumnLayout(); // Not configurable by design.
                                    // As to the why: Make a one column layout, switch to
                                    // a user with a two column layout, _don't_ clone, and
                                    // see everything stuffed in the wrong column.

        if (Config::$remove_screen_options) {

            $this->removeScreenOptions();
        }

        if (Config::$lock_meta_box_order) {

            $this->lockMetaBoxOrder();
        }
    }

    /**
     * Constructor.
     *
     * Proceeds to setup and further action only if
     * the current is not the blueprint user.
     */
    public function __construct() {

        $getId = Config::$getBlueprintUserId;

        if (is_callable($getId)) {

            $id      = $getId();
            $exists  = get_user_by('id', $id);
            $current = get_current_user_id();

            if ($id && $exists && $id != $current) {

                $this->clone_from_user_id = $id;

                $this->setup();
                $this->init();
            }
        }
    }
}

if (is_admin()) {

    add_action('wp_loaded', function () {

        new MetaBoxOrder();
    });
}
