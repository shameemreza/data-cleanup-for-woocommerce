=== Data Cleanup for WooCommerce ===
Contributors: shameemreza
Tags: woocommerce, cleanup, orders, customers, bookings
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 7.0
WC tested up to: 9.9.5

Advanced tool for cleaning up WooCommerce data including users, customers, orders, and bookings with selective deletion options.

== Description ==

Data Cleanup for WooCommerce is a powerful admin tool that helps store managers and administrators clean up their WooCommerce data. It provides a user-friendly interface to selectively delete users, customers, orders, and bookings based on various criteria.

= Key Features =

**User Management**

* Search and filter users by role, activity, and metadata
* View detailed user information including order history
* Selectively delete users with confirmation
* Identify admin users with clear indicators
* Search across multiple user fields

**Customer Management**

* Filter WooCommerce customers by purchase history
* View spending patterns and customer value metrics
* Clean up inactive or test customer accounts
* Batch selection tools for efficient management

**Order Management**

* Filter orders by status, date range, and more
* Preview orders before deletion
* Safely remove test or obsolete orders
* Multiple status selection with status counts
* Advanced search functionality for finding specific orders

**Booking Management** (requires WooCommerce Bookings)

* View bookings by status with count summaries
* Delete individual or multiple bookings at once
* Filter and delete bookings by date range
* Option to delete related orders when removing bookings
* Interactive preview before deletion for safer operations

= Perfect For =

* Cleaning up test data after development
* Removing old or inactive customer accounts
* Maintaining a streamlined database for better performance
* Preparing for migrations or system upgrades
* Managing and cleaning booking data

= HPOS Compatible =

This plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS) and works with both traditional order storage and the new custom order tables.

== Installation ==

= Minimum Requirements =

* WordPress 6.0 or higher
* WooCommerce 7.0 or higher
* PHP 7.4 or higher
* WooCommerce Bookings 1.15.0 or higher (for booking management features)

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to Plugins > Add New
3. Search for "Data Cleanup for WooCommerce"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress dashboard
3. Navigate to Plugins > Add New
4. Click "Upload Plugin"
5. Upload the zip file and click "Install Now"
6. Activate the plugin

== Usage ==

= Accessing the Plugin =

After activation, navigate to WooCommerce > Data Cleanup in your WordPress admin menu.

= User Cleanup =

1. Go to the "Users" tab
2. Use the search box to find specific users
3. Filter users by role, activity, or metadata using the dropdown filters
4. Select users to view additional information
5. Check the boxes next to users you want to delete
6. Click "Delete Selected" and confirm your action

= Customer Cleanup =

1. Navigate to the "Customers" tab
2. Filter customers by purchase history or activity
3. Review customer metrics and order counts
4. Select customers to remove
5. Confirm deletion when prompted

= Order Cleanup =

1. Go to the "Orders" tab
2. Use filters to select orders by status, date range, or custom criteria
3. Preview selected orders before deletion
4. Select orders to remove
5. Confirm deletion when prompted

= Booking Cleanup =

1. Navigate to the "Bookings" tab
2. View bookings by status with count summaries
3. Click "List Bookings" to see bookings for a specific status
4. Use date range filter to find bookings in a specific time period
5. Select individual bookings or use "Select All" for bulk operations
6. Optionally check "Also delete related orders when deleting bookings"
7. Click "Delete Selected Bookings" to remove them

== Frequently Asked Questions ==

= Is this plugin compatible with HPOS (High-Performance Order Storage)? =

Yes, this plugin is fully compatible with WooCommerce HPOS and works with both traditional order storage and the new custom order tables.

= Will deleting users also delete their orders? =

No, by default deleting users will not delete their orders. Orders will remain in the system but will no longer be associated with a specific user account.

= When deleting bookings, will the associated orders be deleted too? =

Only if you check the "Also delete related orders when deleting bookings" option. By default, bookings are deleted without affecting their associated orders.

= Is there a way to recover deleted data? =

No, all deletions are permanent. I strongly recommend backing up your database before performing any deletion operations.

= Can I delete multiple users/orders/bookings at once? =

Yes, the plugin supports batch selection and deletion for efficient data management.

== Screenshots ==

1. Main plugin dashboard with cleanup options
2. User management interface with filters and selection
3. Customer cleanup with purchase history filtering
4. Order management with status filtering
5. Booking management interface with date filters

== Changelog ==

= 1.1.0 - 2023-12-15 =
* Added WooCommerce Bookings integration
* New booking management tab with status filtering
* Added date range selection for booking cleanup
* Added option to delete related orders when deleting bookings
* Improved UI for preview and selection of bookings
* Enhanced date picker functionality

= 1.0.0 - 2023-10-15 =
* Initial release
* User management functionality
* Customer filtering and management
* Order cleanup tools with status filtering
* HPOS compatibility

== Upgrade Notice ==

= 1.1.0 =
This update adds support for WooCommerce Bookings with selective deletion options for bookings. It also includes UI improvements and enhanced date selection capabilities.

= 1.0.0 =
Initial release with core functionality for cleaning up WooCommerce data.

== Support ==

If you encounter any issues or have questions, please contact through:

* [GitHub Issues](https://github.com/shameemreza/data-cleanup-for-woocommerce/issues)

== Privacy Policy ==

Data Cleanup for WooCommerce does not collect any personal data from your website visitors. It only provides tools for site administrators to manage existing WooCommerce data.

The plugin does not:
* Track users
* Send any data to external servers
* Create cookies

However, please note that when using this plugin to delete data, you should ensure you're complying with your local data protection laws and regulations regarding customer information retention. 