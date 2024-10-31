=== Plugin Name ===
Contributors: wp_aengus
Donate link: http://www.am-process.org/scuba/?page_id=974
Tags: scuba, dive, log
Requires at least: 3.0.1
Tested up to: 3.9
Stable tag: 0.1.8
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl.html

This plugin turns a wordpress blog into an interactive online scuba dive log.

== Description ==

The Scuba Logger plugin extends the functionality of wordpress so that it becomes an online interactive dive log. From the admin section, details of scuba dives can be entered. Once they have been entered, dive summaries can be easily included in blog posts using shortcodes (e.g. [scubalogger type="dive" divenum="1"]). In addition, a shortcode can be used to generate a 'Query Page' from which the dive log can be searched. You can search for 'all dives greater than 30 metres on a wreck' for example. Finally, shortcodes can be used to include statistics of your dive log in blog posts. For example, you can include the total number of minutes spent underwater using [scubalogger type="logstat" detail="timeunderwater"].

== Installation ==

1. Unzip (if necessary)
1. Upload the folder `scubalogger` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Where do I find out more? =

On the [Scuba Logger webpage](http://www.am-process.org/scuba/?page_id=974 "Scuba Logger Webpage"). Comments are welcome.

== Changelog ==

= 0.1.8 =

Added options to switch between Metres/Feet and Celsius/Fahrenheit. Reduced number of decimal places shown when displaying depths etc. in blog posts.

= 0.1.7 =

Fixed glitch in version numbering.

= 0.1.6 =

Added new formats for total time underwater. Boat name is now displayed in a dive table, if it exists. Minor layout and display tweaks and bug fixes.

= 0.1.5 =

Fixed bug which caused all dives to show up as shore dives in the tables displayed in blog posts.

= 0.1.4 =

Changed scubalogger.php to reflect new version number.

= 0.1.3 =

Fixed formatting in readme.txt

= 0.1.2 =

Fixed bug in admin section that created an SQL injection vulnerability (thanks Rockfish Sec!)

= 0.1.1 =

Fixed path bug that prevented successful activation

= 0.0.1 =

The very first version. Tread cautiously!
