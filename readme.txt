=== WP Zombaio ===
Contributors: BarryCarlyon
Donate link: http://barrycarlyon.co.uk/wordpress/wordpress-plugins/wp-zombaio/
Tags: zombaio, membership, adult
Requires at least: 3.4.2
Tested up to: 3.8
Stable tag: 1.0.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Catches Information from the Adult Payment Gateway Zombaio and acts accordingly

== Description ==

This Plugin allows Site Admins to easily configure and use the Adult Payment Gateway [Zombaio](https://www.zombaio.com/) for use with WordPress. In a more Secure and WordPress-y way that the existing recommended script.

Two ShortCodes help to make joining the site and displaying the Zombaio Site seal as easy as possible.
As well as logging transmitted data from Zombaio as a Custom Post Type.

Currently only User Add, User Delete and User Rebill are Processed.
But all data is logged.

In the future, Credit Purchase will be supported.

When a user delete/cancel occurs, the admin can choose whether the User is deleted from WordPress or just suspended from being able to login.

This plugin works with "Anyone can register" disabled, stopping users signing up without using a Zombaio based Join Form.

Built in Splash Page/redirection allows you to redirect logged out users to a WordPress Page of your Choosing, thus protecting your content from non members, as well as giving you the ability to warn users about the site content, or create a suitable "Join my Site" page. You can also choose pages that are unlocked/open to all users. For tours etc.

See [Other Notes](http://wordpress.org/extend/plugins/wp-zombaio/other_notes/) for Usage/Instructions

= Help =

For Extra help/support or otherwise, either use the [WordPress Support Forum](http://wordpress.org/support/plugin/wp-zombaio), or [Drop Me a Line](http://barrycarlyon.co.uk/wordpress/contact/)

You can also reach out to us on [Twitter](https://twitter.com/wpzombaio)!

= Whats Coming Soon =

* Credit Purchase and Spending there of
* Flexible Protection
* Suspend a User Manually

== Installation ==

1. Download the Plugin from Extend
1. Unzip the Zip File
1. Upload `wp-zombaio` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Use the Wizard to quicky run thru the setup and get everything going
1. Add the SideBar widget, or use the ShortCode to add a Join Site form.

== Screenshots ==

1. The ShortCodes and Widgets in Action
1. Activating the Plugin
1. Wizard Step 1
1. Wizard Step 2
1. Wizard Step 3
1. The Settings Page in Full
1. WP Widgets Page
1. Logs Interface
1. Page Edit - making a page public

== Changelog ==

= 1.0.7 (Coming Soon) =
* Fix to make Zombaio Simulator work (when ByPass IPN Verification is enabled)
* Optimized detect and Fire for processing payments
* Added a Post Payment Process Action, so you can run your own stuff after a payment has run
* Fix Widget editing admin form layout

= 1.0.6.2 =
* Fix a bug involving User Suspension/Deletion (reported by Jose Angel)
* Confirmed 3.8 compatability

= 1.0.6 =
* At the request of Sam Rockhard owner of New Market Media, added the ability to unlock pages
* Adds a option to the Admin Settings Page to enable/disable redirect off the home page
* Changes the pick a page to redirect to, to the better interface
* Adds a Meta Box to the post/page editor to make that post/page public (no need to set this on the target page)
* You an unlock a page/post by editing it and set "Allow access to All" to Yes
* Tested with 3.6 all seems good

= 1.0.4 =
* Tweaks to the Validate ZScript Code
* Actually fixed IPN IP Verfication so it actually works
* Accept and log chargebacks and declined messages
* New/improved logging interface
* Added the Guide
* Added notes on Caching Plugins and CloudFlare
* Improved the interface and layout (somewhat looks a little like Zombaios ZOA interface)
* Internationaslised the plugin/added translation support
* Added a Login Widget
* Added Login block, and the abilty to redirect users to a "Landing Page", default is the WP Login Page.
* [zombaio_join] shortcode supports additional arguments:
* align - form placement - choices: left center right
* buttonalign - button placement - choices: left center right
* width - form width
* [zombaio_seal] shortcode supports additiona arguments:
* align - placement - choices: left center right
* Added Shortcode [zombaio_login] renders a basic Login Form, its also a Widget

= 1.0.2 =
* Prettification (logo/icon)
* Added a Notice/warning when Anyone can Register is enabled.
* Fix a UI glitch when the sidebar and/or one or more forms are on a screen
* Stop Passwords being double hashed (and confusing users at login) as the selected Password won't work

= 1.0.0 =
* Initial Release

== Usage ==

With the block enabled, only the landing page is accessable, and users are forced to login or register.

You can run with Anyone can Register turned off, which means users can only use your Landing page, or the Zombaio Join Form/Widget to Register.

You can refer to the in plugin guide, which contains lots of setup information, settings notes and advice.

The Plugin provides three Shortcodes which are also available as Sidebar Widgets.

The first being the Zombaio Site Seal, which you can enter in the settings and then quickly add it where needed.

[zombaio_seal]

ShortCode Arguments

* align - save you having to wrap it in a div, you can use left, center or right to control page placement

The second being, the Join Form.

[zombaio_join]content[/zombaio_join]

ShortCode Arguments

* join_url - the Zombaio Join URL for the relevant subscription
* submit - the text to use on the Submit button
* align - save you having to wrap it in a div, you can use left, center or right to control page placement
* buttonalign - save you having to wrap it in a div, you can use left, center or right to button placement
* width - the Form Width, just a number

ShortCode Content

The shortcode content is added to the form beneath the password field and the join button.

The third and final item is, a Login Form.

[zombaio_login]

Renders a login form, also available as a Widget

== Upgrade Notice ==

There is nothing Special to do for all upgrades.

All new settings are spawned to default values automagically

You should however take a Database and File Backup before Hand. Just in case.

== Frequently Asked Questions ==

= Do I need the ZScript script? =

No, this is a replacement for the downloadable ZScript and the "Standard" Zombaio .htaccess/.htpasswd Protection. Existing ZScript protection should be removed

= What Details do I need for this Plugin? =

Just the ZombaioGWPass (aka Digest Key) and your SiteID.
SiteID's are specific to each Site/URL you add to the ZOA Website Manager.

We do not need your Account User name/login or Password.

= What settings do I need in Zombaio ZOA =

If you follow the Wizard inside the plugin it will guide you thru the setup and settings to use.

= What about the Zombaio Seal Code? =

We provide a shortcode and widget to display the Seal on your website, and instructions on where to obtain your code.

= I need help! =

Either use the [WordPress Support Forum](http://wordpress.org/support/plugin/wp-zombaio), or [Drop Me a Line](http://barrycarlyon.co.uk/wordpress/contact/)
