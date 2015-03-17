
# WordPress Global Meta Box Order

Intuitively harmonize column layout and meta box positions across all backend users of your WordPress installation permanently, but nondestructively. 

## Quick overview

* Install plugin
* Switch to a post, a page, a custom post type, or the dashboard
* Change order and position of a meta box (or all of them)
* Change the column layout
* Switch to a different user (maybe with the help of the [User Switching](https://wordpress.org/plugins/user-switching/) plugin)
* See your changes applied   

## Installation

Download and unpack, then move the folder `global-meta-box-order` into your `plugins`folder. Head over to your WordPress installation and activate the plugin in the admin area.

## Rollback

The plugin doesn't write anything to the database, it just reads. So it never touches any user settings, but instead filters them on every turn. Though all applied changes _appear_ to be permanent from a user's perspective, they are not. Just deactivate the plugin and see all changes disappear.  

## How it works

The plugin operates on a blueprint user whose screen settings for meta boxes (visibillity, position and ordering) and column layout are cloned for every other backend user on the fly.

By default, this blueprint user is the first admin user found, so you'll need to be logged in as that user to globally change screen settings.   

For how to change the default blueprint user, see [Configuration](#configuration) below.

## Where it works 

The plugin overrides screen settings in the following admin screens:

* Single Post Editing 
* Single Page Editing
* Single Custom Post Type Editing
* Dashboard

## What it changes

* Meta box visibillity, ordering, and column position
* Screen column layout

## <a id="configuration"></a>Configuration

The backend integration is kept to a minimum. No navigation entry, no options page, no options entry in the database. Instead it's easily configurable from inside your theme's `functions.php`.

As an example, let's change the default blueprint user - or better, the mechanism that retrieves hers or his id:

```PHP
// We're sure the plugin is active, so let's
// alias the config for easier access 
use \GlobalMetaBoxOrder\Config as BoxOrderConfig;

BoxOrderConfig::$getBlueprintUserId = function () { return 1; };
```

Or, more involved:

```PHP
if (is_admin()) {

    // Make sure plugin is active
    $config_loaded = class_exists('\GlobalMetaBoxOrder\Config');

    if (class_exists('\GlobalMetaBoxOrder\Config')) {

        \GlobalMetaBoxOrder\Config::$getBlueprintUserId = function () { 
            
            $user = get_user_by('slug', 'jimbo');
            return $user ? $user->ID : false; 
        };
    }
}
```

For more options to set, see their [source](../blob/master/).

## Questions & Answers


## On positioning the WYSIWYG editor

The position of WordPress' WYSIWYG editor can't be changed out of the box (mostly because it lacks one around it). Possible solutions to this problem include:

* [Box the editor yourself](http://www.farinspace.com/move-and-position-wordpress-visual-editor/) 
* [Alter the rendering process](http://wordpress.stackexchange.com/a/88103) (a solution limited to a specific kind of boxes)
* Swap the editor against an editor placed inside a meta box    
 

     

## License

GNU GPL v2 or later


