<?php

/*

Plugin Name: Custom Metabox Order
Description: Clone meta box order on the fly without overwriting user settings.
Version:     1.0.2
Plugin URI:  https://github.com/pontycode/wordpress-custom-metabox-order/
Author:      Pontycode
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

namespace MetaboxOrder;

/**
 * Clone meta box order on the fly, i.e., without
 * actually changing any user settings.
 *
 * Based on ideas by:
 * http://gist.github.com/franz-josef-kaiser/9100450
 * http://wordpress.stackexchange.com/a/144608
 * http://wordpress.stackexchange.com/a/19972
 */

class MetaboxOrder {

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
    protected $allowed_screens = array(

        'post', // Includes pages & custom post types
        'dashboard'
    );

    /**
     * Metabox keys to clone
     *
     * @var Array
     */
    protected $clone_meta_keys = array();

    /**
     * Set meta keys to clone.
     *
     * @return void
     */
    protected function setMetaKeys() {

        $screens    = array('post', 'page', 'dashboard');
        $post_types = get_post_types(array('_builtin' => false)); // Custom Post Types

        foreach (array_merge($screens, $post_types) as $slug) {

            $this->clone_meta_keys[] = 'meta-box-order_'.$slug;
            $this->clone_meta_keys[] = 'metaboxhidden_'.$slug;
        }
    }

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
     * @return String|false
     */
    protected function getCurrentScreen() {

        $screen = get_current_screen();

        if (!($screen && property_exists($screen, 'base'))) {

            return false;
        }

        return $screen->base;
    }

    /**
     * A meta key might or might not be prefixed, so
     * we need to normalize it.
     *
     * @param  String
     * @return String - the meta key with any prefix stripped
     */
    public function normalizeMetaKey($meta_key) {

        global $wpdb;

        if (strrpos($meta_key, $wpdb->prefix) === 0) {

            $meta_key = substr($meta_key, strlen($wpdb->prefix));
        }

        return $meta_key;
    }

    /**
     * Clone metabox order (technically, this will clone any key
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

        if (!in_array($screen, $this->allowed_screens)) {

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
     * Setup
     */
    public function __construct() {

        $clone_from = get_users(array('role' => 'administrator'));

        if (!empty($clone_from)) {

            $this->clone_from_user_id = $clone_from[0]->ID;

            $this->setMetaKeys();
            $this->addFilter();
            $this->cloneColumnLayout();
        }
    }
}

if (is_admin()) {

    add_action('wp_loaded', function () {

        new MetaboxOrder();
    });
}
