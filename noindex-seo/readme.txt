=== noindex SEO ===
Contributors: robotstxt, javiercasares
Tags: seo, noindex, nofollow, noarchive, robots
Requires at least: 4.0
Tested up to: 7.1
Stable tag: 3.1.3
Requires PHP: 5.6
Version: 3.1.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Control search engine indexing with robots directives using HTML meta tags or HTTP headers.

== Description ==

Fine-grained control over how search engines index and display your WordPress content. Apply 5 independent robots directives to 22 different page contexts with flexible implementation methods.

**5 Robots Directives:**

* **noindex**: Prevent search engines from indexing the page
* **nofollow**: Prevent search engines from following links on the page
* **noarchive**: Prevent search engines from showing cached versions
* **nosnippet**: Prevent search engines from showing text snippets in results
* **noimageindex**: Prevent search engines from indexing images on the page

**Implementation Methods:**

* HTML Meta Tags: Traditional method, easy to verify in page source (default)
* HTTP Headers: More robust, works with all content types including PDFs and images
* Both: Maximum compatibility for all scenarios

**Control Levels:**

* Global Settings: Apply directives to 22 different page contexts (posts, pages, archives, etc.)
* Granular Control (Optional): Override global settings for individual posts, pages, and custom post types via meta boxes in the editor

**Perfect for:**

* Blocking indexing of attachment pages while allowing link following
* Preventing duplicate content issues with flexible directive combinations
* Controlling archive page indexing with granular control
* Managing pagination SEO with independent settings
* Protecting private content from search engine caching
* Preventing snippet display while still indexing content

**Main pages**

* Front Page: Block the indexing of the site's front page.
* Home: Block the indexing of the site's home page.

**Pages and Posts**

* Page: Block the indexing of the site's pages.
* Privacy Policy: Block the indexing of the site's privacy policy page.
* Single: Block the indexing of a post on the site.
* Singular: Block the indexing of a post or a page of the site.

**Taxonomies**

* Category: Block the indexing of the site categories. The lists where the posts appear.
* Tag: Block the indexing of the site's tags. The lists where the posts appear.

**Dates**

* Date: Block the indexing when any date-based archive page (i.e. a monthly, yearly, daily or time-based archive) of the site. The lists where the posts appear.
* Day: Block the indexing when a daily archive of the site. The lists where the posts appear.
* Month: Block the indexing when a monthly archive of the site. The lists where the posts appear.
* Time: Block the indexing when an hourly, "minutely", or "secondly" archive of the site. The lists where the posts appear.
* Year: Block the indexing when a yearly archive of the site. The lists where the posts appear.

**Archives**

* Archive: Block the indexing of any type of Archive page. Category, Tag, Author and Date based pages are all types of Archives. The lists where the posts appear.
* Author: Block the indexing of the author's page, where the author's publications appear.
* Post Type Archive: Block the indexing of any post type page.

**Pagination**

* Pagination: Block the indexing of the pagination, i.e. all pages other than the main page of an archive.

**Search**

* Search: Block the indexing of the internal search result pages.

**Attachments**

* Attachment: Block the indexing of an attachment document to a post or page. An attachment is an image or other file uploaded through the post editor's upload utility. Attachments can be displayed on their own "page" or template. This will not cause the indexing of the image or file to be blocked.

**Previews**

* Customize Preview: Block the indexing when a content is being displayed in customize mode.
* Preview: Block the indexing when a single post is being displayed in draft mode.

**Error Page**

* Error 404: This will cause an error page to be blocked from being indexed. As it is an error page, it should not be indexed per se, but just in case.

Important note: if you have any doubt about any of the following items it is best not to activate the option as you could lose results in the search engines.

== Using the plugin ==

= REST API =

The plugin exposes two read-only endpoints under `/wp-json/noindex-seo/v1/` so headless consumers can read the configuration:

* `GET /settings`: returns the consolidated settings array (contexts and directives plus config). Requires an authenticated user with the `manage_options` capability.
* `GET /effective?post_id=123`: returns the list of active directives for a specific post, computed with the same precedence used on the front-end (per-post override, then per-post-type default, then the built-in context). Public only for published, non-password-protected posts; everything else requires `edit_post` capabilities.

On WordPress versions with XML sitemaps (5.5+), URLs that are noindexed are automatically excluded from the core sitemaps.

== Installation ==

= Automatic download =

Visit the plugin section in your WordPress, search for [noindex-seo]; download and install the plugin.

= Manual download =

Extract the contents of the ZIP and upload the contents to the `/wp-content/plugins/noindex-seo/` directory. Once uploaded, it will appear in your plugin list.

== Frequently Asked Questions ==

= Does the noindex directive remove the URL from the XML sitemap? =

Yes. On WordPress 5.5 or newer, any URL that receives a noindex directive from the plugin is excluded from the core XML sitemaps (`/wp-sitemap.xml`), both through the global context settings and through the granular per-post / per-term overrides.

= What is the difference between the HTML meta tags and the HTTP headers implementation methods? =

Both send the same robots directives to search engines. The meta tag method outputs a `<meta name="robots">` tag in the HTML head and is easy to verify in the page source. The HTTP header method sends an `X-Robots-Tag` header, which also works for non-HTML content such as PDFs, images and feeds. The "Both" method sends the two signals at the same time.

= Will I lose my settings when I uninstall the plugin? =

No. By default all plugin data is preserved on uninstall. If you want a complete cleanup, enable the "Delete all plugin data on uninstall" option in the settings page before removing the plugin.

== Compatibility ==

* WordPress: 4.0 - 7.1
* PHP: 5.6 - 8.5

== Changelog ==

= 3.1.3 =

Compatibility release: WordPress 4.0+ and PHP 5.6+ supported again (the classic `<meta name="robots">` output covers WordPress versions without the `wp_robots` API, and every newer-API integration degrades gracefully). Branding moved to robotstxt.software.

= 3.1.2 =

Audit-driven maintenance release: fixed the composer-audit gate in the preflight script, un-nested the settings page forms, added `load_plugin_textdomain()` for the bundled translations, pinned all dev dependencies, wired up coverage measurement.

= 3.1.1 =

Security: tightened the ACL on the REST endpoints. `/settings` now requires `manage_options`; `/effective` is public only for published, non-password-protected posts and requires `edit_post` for everything else.

= Previous versions =

If you want to see the full changelog, visit the [noindex SEO plugin page](https://www.robotstxt.software/plugins/noindex-seo/).

== Compliance ==

This plugin adheres to the following security measures and review protocols for each version:

* [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
* [WordPress Plugin Security](https://developer.wordpress.org/plugins/wordpress-org/plugin-security/)
* [WordPress APIs Security](https://developer.wordpress.org/apis/security/)
* [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
* [Plugin Check (PCP)](https://wordpress.org/plugins/plugin-check/)

== Privacy ==

* This plugin does not collect any information about your site, your identity, the plugins, themes or content the site has.

== Vulnerabilities ==

* No vulnerabilities have been published up to version 3.1.3.

Found a security vulnerability? Please report it to us privately at the [noindex SEO GitHub repository](https://github.com/javiercasares/noindex-seo/security/advisories/new).
