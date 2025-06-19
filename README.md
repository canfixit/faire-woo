# FaireWoo

WooCommerce plugin for Faire retailers to seamlessly sync products, orders, and inventory between Faire and WooCommerce.

## Features
- Import and export products between Faire and WooCommerce
- Sync orders and inventory in real time
- Supports simple and variable products
- Maps all core product data: attributes, pricing, inventory, media, taxonomy, dimensions, status, and custom fields
- Robust error handling and logging
- Extensible via WordPress hooks and filters

## Requirements
- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Faire retailer account

## Installation
1. Download or clone this repository to your `wp-content/plugins` directory:
   ```sh
   git clone https://github.com/yourusername/faire-woo.git
   ```
2. Activate **FaireWoo** from the WordPress Plugins admin page.
3. Ensure WooCommerce is active and configured.
4. Configure Faire API credentials and plugin settings (see plugin settings page).

## Usage
### Product Import/Export
- **Import from Faire:**
  - Use the admin UI or CLI to import products from your Faire account into WooCommerce.
  - All product data, images, inventory, and variations are mapped automatically.
- **Export to Faire:**
  - Export WooCommerce products to Faire with a single click or via bulk actions.

### Order & Inventory Sync
- Orders placed on Faire are automatically imported into WooCommerce.
- Inventory levels are kept in sync between both platforms.

### Extensibility
- The sync system is highly extensible via WordPress hooks and filters.
- See [`includes/sync/README.md`](includes/sync/README.md) for a full list of available hooks, filters, and extension examples.

## FAQ
**Q: Does this plugin support variable products?**
A: Yes, both simple and variable products (with variations) are fully supported.

**Q: Can I customize the sync logic?**
A: Yes! Use the provided hooks and filters to modify sync behavior, add custom fields, or integrate with other plugins.

**Q: Where do I report bugs or request features?**
A: Please open an issue on the [GitHub repository](https://github.com/yourusername/faire-woo).

## Support
- For help, open an issue on GitHub or contact the plugin author.

## License
[MIT](LICENSE)

## Contributing
Pull requests are welcome! Please see the [contributing guidelines](CONTRIBUTING.md) if available. 