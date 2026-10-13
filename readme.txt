=== Knock First – Password Protect Your Site ===
Contributors: stettzy
Tags: password protect, password protection, private site, maintenance, staging
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Password protect your entire website with one shared password. Ideal for private sites, staging, launches, and client previews.

== Description ==

Knock First makes it easy to password protect an entire WordPress website with one shared password. It is ideal for private sites, staging websites, client previews, coming-soon pages, and launches.

The plugin is disabled by default. After activation, open **Settings > Site Password**, choose a password, and enable protection.

Features:

* One switch protects the public-facing site.
* Passwords are stored as one-way WordPress password hashes, never as plain text.
* Access uses a signed, HTTP-only browser cookie that is invalidated when the password changes.
* Choose an access duration from one hour to 30 days.
* Optionally let logged-in WordPress users bypass the gate.
* Administrators and the standard WordPress login always remain accessible.
* Public REST API requests are protected, core XML sitemaps are disabled, and robots are asked not to index the protected site.
* The unlock screen is responsive, keyboard friendly, and uses no external services or assets.
* No tracking, advertising, remote requests, or user accounts.

= Important security note =

This plugin is a simple privacy gate. It is not a replacement for WordPress user accounts, server-level access control, or another security system when protecting sensitive or regulated data. Public files already uploaded to the Media Library may remain reachable by their direct URLs, and another plugin can expose data through its own custom endpoints.

== Installation ==

1. Install and activate Knock First.
2. Go to **Settings > Site Password**.
3. Enter a shared password.
4. Select **Require the site password** and save your changes.
5. Open the site in a private browser window to test the unlock screen.

Protection is off immediately after activation, so activating the plugin cannot unexpectedly lock visitors out.

== Frequently Asked Questions ==

= Can I still sign in to WordPress? =

Yes. The standard WordPress login and administration screens remain available. Administrators always bypass the gate.

= Is the password stored in plain text? =

No. Knock First uses the WordPress password hashing API. The original password cannot be retrieved from the saved setting.

= What happens when I change the password? =

All existing Knock First access cookies become invalid immediately. Visitors must enter the new password.

= Does this hide media files? =

Not when someone already knows a file's direct URL. WordPress usually serves uploaded files directly through the web server, before plugins run. Use server-level protection or private object storage when the files themselves are sensitive.

= Does this stop search engines? =

While protection is enabled, the unlock page sends no-index directives, the virtual robots.txt disallows crawling, and WordPress core sitemaps are disabled. Search engines control their own behavior, and previously indexed URLs can take time to disappear.

= Will it work with page caching? =

The plugin sends no-cache headers and sets the standard `DONOTCACHEPAGE` constant while enabled. Some host-level or CDN caches run before WordPress and may need a manual exclusion or cache purge.

= Does the plugin collect personal data? =

No. It makes no remote requests and stores no visitor records. It sets one necessary HTTP-only cookie after a visitor enters the correct password.

== Changelog ==

= 1.0.2 =
* Rename the plugin to Knock First – Password Protect Your Site and align the publication slug and text domain.

= 1.0.1 =
* Fix saving the first site password when the settings option does not already exist.
* Migrate settings from the early development option key.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.2 =
Updates the plugin name and publication slug to the distinctive Knock First identity.
