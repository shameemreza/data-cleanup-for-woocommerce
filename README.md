# Data Cleanup for WooCommerce

Advanced tool for cleaning up WooCommerce data including users, customers, and orders with selective deletion options.

![Data Cleanup for WooCommerce](/.github/assets/data-cleanup-demo.gif)

## Description

Data Cleanup for WooCommerce is a powerful admin tool that helps store managers and administrators clean up their WooCommerce data. It provides a user-friendly interface to selectively delete users, customers, and orders based on various criteria.

### Key Features

- **User Management**

  - Search and filter users by role, activity, and metadata
  - View detailed user information including order history
  - Selectively delete users with confirmation
  - Identify admin users with clear indicators
  - Search across multiple user fields

- **Customer Management**

  - Filter WooCommerce customers by purchase history
  - View spending patterns and customer value metrics
  - Clean up inactive or test customer accounts
  - Batch selection tools for efficient management

- **Order Management**
  - Filter orders by status, date range, and more
  - Preview orders before deletion
  - Safely remove test or obsolete orders
  - Multiple status selection with status counts
  - Advanced search functionality for finding specific orders

### Perfect For

- Cleaning up test data after development
- Removing old or inactive customer accounts
- Maintaining a streamlined database for better performance
- Preparing for migrations or system upgrades

## Installation

### Minimum Requirements

- WordPress 6.0 or higher
- WooCommerce 7.0 or higher
- PHP 7.4 or higher

### Installation

1. Download the plugin zip file
2. Log in to your WordPress dashboard
3. Navigate to Plugins → Add New
4. Click "Upload Plugin"
5. Upload the zip file and click "Install Now"
6. Activate the plugin

## Usage

### Accessing the Plugin

After activation, navigate to WooCommerce → Data Cleanup in your WordPress admin menu.

### User Cleanup

1. Go to the "Users" tab
2. Use the search box to find specific users
3. Filter users by role, activity, or metadata using the dropdown filters
4. Select users to view additional information
5. Check the boxes next to users you want to delete
6. Click "Delete Selected" and confirm your action

### Customer Cleanup

1. Navigate to the "Customers" tab
2. Filter customers by purchase history or activity
3. Review customer metrics and order counts
4. Select customers to remove
5. Confirm deletion when prompted

### Order Cleanup

1. Go to the "Orders" tab
2. Use filters to select orders by status, date range, or custom criteria
3. Preview selected orders before deletion
4. Select orders to remove
5. Confirm deletion when prompted

## Frequently Asked Questions

### Is this plugin compatible with HPOS (High-Performance Order Storage)?

Yes, this plugin is fully compatible with WooCommerce HPOS and works with both traditional order storage and the new custom order tables.

### Will deleting users also delete their orders?

No, by default deleting users will not delete their orders. Orders will remain in the system but will no longer be associated with a specific user account.

### Is there a way to recover deleted data?

No, all deletions are permanent. We strongly recommend backing up your database before performing any deletion operations.

### Can I delete multiple users/orders at once?

Yes, the plugin supports batch selection and deletion for efficient data management.

## Support

If you encounter any issues or have questions, please contact us through:

- [GitHub Issues](https://github.com/shameemreza/data-cleanup-for-woocommerce/issues)

## Contributing

I welcome contributions from the community! Here's how you can help:

1. **Report Bugs**: Create a new issue on our GitHub repository
2. **Suggest Features**: Share your ideas for improvements
3. **Submit Pull Requests**: Code contributions are always welcome

## License

Data Cleanup for WooCommerce is licensed under the GPL v2 or later.

```
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
```

## Changelog

### 1.0.0 - 2023-10-15

- Initial release
- User management functionality
- Customer filtering and management
- Order cleanup tools with status filtering
- HPOS compatibility
