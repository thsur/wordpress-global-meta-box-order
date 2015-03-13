
# WordPress Custom Meta Box Order

Intuitively harmonize column layout and meta box positions across all backend users of your WordPress installation. 

## Quick overview

* Install plugin
* Switch to a post, a page, a custom post type or the dashboard
* Change order and position of a meta box (or all of them)
* Change the column layout
* Switch to a different user (maybe with the help of the [User Switching](https://wordpress.org/plugins/user-switching/) plugin)
* See your changes applied   

## Installation

Download and unpack, then move the folder `metabox-order` into your `plugins`folder. Head over to your WordPress installation and activate the plugin in the admin area. 

## How it works

The plugin is centered around the idea of having a blueprint user whose screen settings for meta boxes (presence, position and ordering) and column layout are cloned for every other backend user (but without actually overwriting them, see section ["Rollback"](#rollback) below).

To keep things simple, this blueprint user is fixed - actually, it's the first admin user found, so you'll need to be logged in as that user to globally change screen settings (or patch the constructor of the plugin's class to choose another user).   

## Where it works 

The plugin overrides screen settings in the following admin screens:

* Single Post Editing 
* Single Page Editing
* Single Custom Post Type Editing
* Dashboard

## What it changes

* Meta box 
    * visibillity 
    * ordering
    * column position
* Screen column layout

## <a name="rollback"></a>Rollback

The plugin doesn't write anything to the database, it just reads. So it never touches any user settings, but instead filters them on every turn. Though all applied changes _appear_ to be permanent from a user's perspective, they are not. Just deactivate the plugin and see all changes disappear.  

## On positioning the WYSIWYG editor

The position of WordPress' WYSIWYG editor can't be changed out of the box (mostly because it lacks one around it). Possible solutions to this problem include:

* [Box the editor yourself](http://www.farinspace.com/move-and-position-wordpress-visual-editor/) 
* [Alter the rendering process](http://wordpress.stackexchange.com/a/88103) (a solution limited to a specific kind of boxes)
* Swap the editor against an editor placed inside a meta box    
 

     

## License

GNU GPL v2 or later


