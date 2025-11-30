# MantiLoad

High-performance search engine for WordPress and WooCommerce powered by Manticore Search.

[![Version](https://img.shields.io/badge/version-1.6.1-blue?style=flat-square)](https://github.com/mantiload/mantiload/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.8+-green?style=flat-square)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0+-purple?style=flat-square)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPLv2-red?style=flat-square)](LICENSE)

## Overview

MantiLoad integrates Manticore Search into WordPress, providing professional-grade search performance for content and WooCommerce products. Built for sites that need reliable, fast search at scale.

**Performance:** Search 4,400+ products in 3-5ms. Standard WordPress search on the same dataset: 200-1000ms.

## Features

### Search Capabilities

- Full-text search with BM25 relevance ranking
- AJAX search with live results
- Wildcard and prefix matching
- Synonym support with bidirectional mapping
- Multi-word query handling
- Real-time indexing with automatic updates

### WooCommerce Integration

- Product search with SKU matching
- Filtering by price, category, attributes, stock status
- Related products algorithm
- Cart integration for quick add-to-order
- Variable product support
- Admin order search optimization

### User Interface

- Modal search overlay with keyboard shortcuts (Cmd/Ctrl+K)
- Dropdown search results
- Dedicated search results page
- Widget and shortcode support
- Mobile-responsive design
- RTL language support

### Administration

- Search analytics dashboard
- Performance monitoring
- Synonym management
- Index configuration
- WP-CLI commands for bulk operations

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL/MariaDB with MySQLi extension
- Manticore Search 13.0 or higher
- WooCommerce 6.0+ (optional, for product features)

## Installation

### Installing Manticore Search

**Ubuntu/Debian:**

```bash
wget https://repo.manticoresearch.com/manticore-repo.noarch.deb
dpkg -i manticore-repo.noarch.deb
apt update
apt install manticore
systemctl start manticore
systemctl enable manticore
```

**CentOS/RHEL:**

```bash
yum install https://repo.manticoresearch.com/manticore-repo.noarch.rpm
yum install manticore
systemctl start manticore
systemctl enable manticore
```

### Installing the Plugin

1. Download the latest release or clone this repository
2. Upload to `/wp-content/plugins/mantiload/`
3. Activate the plugin through WordPress admin
4. Navigate to MantiLoad > Indexing
5. Click "Create Indexes & Reindex All"

## Usage

### Basic Search

Add the search form to your site:

```php
// In your theme template
echo do_shortcode('[mantiload_search]');

// Or use the widget
// Appearance > Widgets > MantiLoad Search
```

### WP-CLI Commands

```bash
# Initialize indexes
wp mantiload create_indexes

# Rebuild all content
wp mantiload reindex

# Optimize indexes
wp mantiload optimize

# View statistics
wp mantiload stats

# Test a search query
wp mantiload search "laptop"
```

### Programmatic Search

```php
use MantiLoad\Search\Search_Engine;

$search_engine = new Search_Engine();
$results = $search_engine->search('laptop', [
    'post_type' => 'product',
    'limit' => 20,
    'filters' => [
        'min_price' => 500,
        'max_price' => 2000,
        'categories' => [12, 15],
        'on_sale' => true,
    ],
]);
```

### Hooks and Filters

```php
// Modify index schema
add_filter('mantiload_index_schema', function($schema, $post_type) {
    // Customize schema
    return $schema;
}, 10, 2);

// Customize indexed data
add_filter('mantiload_post_data', function($data, $post) {
    // Add custom fields
    $data['custom_field'] = get_post_meta($post->ID, 'custom', true);
    return $data;
}, 10, 2);

// Process search results
add_action('mantiload_search_results', function($results, $query) {
    // Log or modify results
}, 10, 2);
```

## Configuration

### Connection Settings

Default connection: `127.0.0.1:9306`

To customize, define constants in `wp-config.php`:

```php
define('MANTILOAD_HOST', '127.0.0.1');
define('MANTILOAD_PORT', 9306);
```

### Performance Tuning

Adjust these settings in MantiLoad > Settings:

- **Auto Query Interception**: Bypass MySQL for product queries
- **Stock Priority**: Show in-stock products first
- **Admin Search Optimization**: Faster admin product search

## Performance Benchmarks

Tested on a WooCommerce store with 4,400+ products:

| Query Type | Response Time | Products |
|-----------|---------------|----------|
| Single word | 3-5ms | 2,066 |
| Multi-word | 5-10ms | 1,234 |
| With filters | 10-20ms | 567 |

Comparison with WordPress default search on same dataset:
- WordPress: 200-1000ms
- MantiLoad: 3-20ms
- **Improvement: 10-200x faster**

## Development

### Project Structure

```
mantiload/
├── admin/              # Admin interface
├── assets/             # CSS, JS, images
├── includes/           # Core classes
│   ├── class-mantiload.php
│   ├── class-manticore-client.php
│   ├── class-query-integration.php
│   └── search/         # Search engine
├── languages/          # Translations
├── widgets/            # WordPress widgets
└── mantiload.php       # Main plugin file
```

### Building for Release

```bash
# Run tests
composer test

# Build release package
./build-release.sh
```

### Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Support

- **Documentation**: [mantiload.com/docs](https://mantiload.com/docs)
- **Issues**: [GitHub Issues](https://github.com/mantiload/mantiload/issues)
- **WordPress.org**: [Plugin Page](https://wordpress.org/plugins/mantiload/)

## License

This project is licensed under the GPLv2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

- Built with [Manticore Search](https://manticoresearch.com/)
- Inspired by ElasticPress and Relevanssi
- Maintained by the MantiLoad team
