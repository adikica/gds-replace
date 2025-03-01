Global Database Search & Replace (Dev Mode)
Contributors: Adi Kica
Tags: database, search, replace, admin, developer, mass replace, CodeMirror
Requires at least: 5.0
Tested up to: 6.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Description
Global Database Search & Replace (Dev Mode) is a developer tool that allows you to search for a specific word or string across your entire WordPress database. It provides:

Global Search: Scan all tables (excluding specified columns and backup tables) to find instances of a word or string.
Direct Edit Links: When a match is found in posts or post meta, the plugin provides direct links to the edit page in the WordPress admin.
Code Editor Integration: Edit the raw content directly with a CodeMirror-based source code editor.
AJAX Updates: Securely update the database via AJAX with nonce verification.
Mass Replace: Easily replace a word or number in all posts with a single click. The plugin processes posts in batches and creates a backup of the posts table before replacing content.
This plugin is ideal for developers who need to update content across many posts or fix incorrect data in bulk.

Features
Global Database Search: Searches across all database tables, excluding columns like url, guid, slug, filename, source_url, post_name, option_value, package, real_path, path, wordpress_path and backup tables (those starting with your site's backup prefix).
Direct Edit Links: Provides a direct link to the edit page for posts, pages, and templates when a match is found.
CodeMirror Integration: Edit raw content in a modern, feature-rich source code editor.
Secure AJAX Updates: Uses AJAX and nonce verification for secure content updates without reloading the page.
Mass Replace Functionality: Replace a word or number in every post with a single click, with support for case-sensitive replacements.
Backup Posts Table: Automatically creates a backup of the posts table before performing a mass replace.
Installation
Upload the Plugin:
Upload the global-db-search folder to the /wp-content/plugins/ directory.

Activate the Plugin:
Activate the plugin through the 'Plugins' menu in WordPress.

Usage:

Navigate to Dashboard > DB Search.
Use the search form to look for a specific word or string.
View results and click the Edit button to open the CodeMirror source code editor in a modal.
Make your changes and click Update to save the changes via AJAX.
Use the mass replace form to perform a global replacement across all posts (a backup is created automatically).
Frequently Asked Questions
Q: Which columns are excluded from the search?
A: The plugin skips columns named url, guid, slug, filename, source_url, post_name, option_value, package, real_path, path, wordpress_path, meta_value, user_nicename, username, name as well as any tables with names starting with your backup prefix (e.g., wp_posts_backup).

Q: Is mass replace safe to use?
A: Yes, the plugin creates a backup of your posts table before performing a mass replace, ensuring you can revert if necessary.

Q: Can I use this plugin on a production site?
A: This tool is designed for developers. While it is safe when used correctly, always test on a staging environment first, especially when performing mass operations.
