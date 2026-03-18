=== Secure Media Vault ===
Contributors: umangapps48
Donate link: https://phptutorialpoints.in/
Tags: media protection, secure files, file access control, media library, token url
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect WordPress media files from direct public access with token-based secure delivery, fine-grained access control, and SEO indexing protection.

**Author:** [Umang Prajapati](https://phptutorialpoints.in/) | [WordPress Profile](https://profiles.wordpress.org/umangapps48/) | [GitHub](https://github.com/umang48/secure-media-vault)

== Description ==

**Secure Media Vault** gives you full control over who can access your WordPress media files. Stop search engines, bots, and unauthorised visitors from downloading your protected images, PDFs, videos, or documents.

= Core Features =

**🔐 Media Protection System**
* Prevent direct URL access to files in `/wp-content/uploads/`
* Files are served through a secure PHP handler, not exposed directly
* Automatic `.htaccess` rules block direct file access on Apache servers
* Guidance provided for Nginx configurations

**👥 Fine-Grained Access Control**
Set a protection level for every file in the Media Library:

* **Public** – standard WordPress behaviour
* **Logged-in users only** – any authenticated user
* **Specific roles** – choose from all registered WordPress roles (Admin, Editor, Subscriber, etc.)
* **Password protected** – custom password per file
* **Restrict to posts/pages** – only accessible when referred from specific content

**🔗 Secure File Delivery**
* Replace original media URLs with HMAC-signed, time-limited token URLs
* Format: `example.com/protected-media/{file-id}/{token}/`
* Configurable token expiry (default: 1 hour)
* Hotlink protection prevents embedding on external domains
* Optional IP-address binding for tokens

**🚫 SEO & Indexing Protection**
* `X-Robots-Tag: noindex, nofollow` header on all protected file requests
* Optional `Disallow` entries in `robots.txt` for the uploads directory
* Disable and redirect WordPress media attachment pages
* wp_robots API integration for attachment pages

**📂 Media Library Integration**
* Protection Settings panel on every attachment edit screen
* Protection status column (`Protected / Public / Password / Role`) in list view
* Bulk Actions: protect multiple files, change access rules, make public

**⚡ Performance**
* Chunked streaming with HTTP Range support for large video/audio files
* Configurable file-size threshold for streaming vs. single-pass delivery
* Object cache support with cache invalidation on settings change
* Scheduled cleanup of expired tokens and old access logs

**🛡️ Security**
* HMAC-SHA256 signed tokens using WordPress secret keys
* Nonce verification on all AJAX requests and form actions
* All input sanitized and output escaped per WordPress standards
* No direct file inclusion; `ABSPATH` check on every file
* Clean uninstall via `uninstall.php`

= Nginx Support =
While `.htaccess` rules are written automatically for Apache, the plugin provides the correct Nginx configuration block in the admin dashboard for manual setup.

= WooCommerce Compatibility =
The access control system is designed to work alongside WooCommerce. Future versions will include native purchase-based access checks.

== Installation ==

1. Upload the `secure-media-vault` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Visit **Media Vault → Settings** to configure your default options.
4. Open any media file in the **Media Library** and set its protection level.
5. If using Nginx, add the server block rules shown in the **Dashboard**.
6. Re-save your **Permalink Settings** (`Settings → Permalinks`) if secure URLs return 404.

== Frequently Asked Questions ==

= Will this break my existing media URLs? =
Only files you explicitly protect will have their URLs replaced. Public files continue to behave normally.

= Does it work with Nginx? =
The PHP-level access control works on any web server. On Nginx, add the configuration rules shown in the Dashboard to block direct file access at the server level.

= What happens if a token expires? =
The user will receive an "Invalid or expired access token" message. Generate a new secure URL from the Media Library.

= Can I use it with a CDN? =
CDN bypass is possible for public files. For protected files, disable CDN caching or ensure the CDN forwards requests to WordPress for validation.

= Does this affect site performance? =
Large files are streamed in 1 MB chunks with HTTP Range support, so memory usage stays low even for large videos.

= Will protected files still be indexed by Google? =
No. The plugin sends `X-Robots-Tag: noindex, nofollow` on every protected-file response and disables media attachment pages.

== Screenshots ==

1. Dashboard with protection statistics and quick actions.
2. Media Library with Protection Status column.
3. Attachment edit screen showing Protection Settings panel.
4. Settings page with all configuration sections.
5. Access Logs table showing granted and denied requests.

== Changelog ==

= 1.0.0 =
* Initial release.
* Media protection with `.htaccess` rules and Nginx guidance.
* Per-file access control: public, logged-in, roles, password, posts.
* HMAC-SHA256 signed, time-limited token URLs.
* Hotlink protection and IP-binding option.
* SEO protection: X-Robots-Tag, robots.txt rules, attachment page redirect.
* Chunked file streaming with HTTP Range support.
* Bulk protect/unprotect actions in the Media Library.
* Access logging with configurable retention.
* Clean uninstall removes all data.

== Upgrade Notice ==

= 1.0.0 =
Initial release. After activation, re-save your Permalink Settings to ensure rewrite rules are flushed.
