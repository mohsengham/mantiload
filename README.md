# ⚡ MantiLoad - Ultra-Fast Search & Filter

<div align="center">

![Version](https://img.shields.io/badge/version-1.1.0-blue?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-5.8+-green?style=flat-square)
![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0+-purple?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/license-GPLv2-red?style=flat-square)
![Downloads](https://img.shields.io/wordpress/plugin/dt/mantiload?style=flat-square)
![Rating](https://img.shields.io/wordpress/plugin/stars/mantiload?style=flat-square)

**Lightning-fast search powered by Manticore Search. Search 2000+ products in 5ms! 🚀**

[Features](#-features) • [Installation](#-installation) • [Usage](#-usage) • [Documentation](#-documentation) • [Contributing](#-contributing)

</div>

---

## 🎯 Why MantiLoad?

MantiLoad transforms your WordPress/WooCommerce site with **blazing-fast search** powered by Manticore Search engine. Get sub-millisecond search responses, advanced filtering, and a beautiful user experience.

### ⚡ Real-World Performance

Tested on a live WooCommerce store with **4,441 products**:

| Query Type | Response Time | Products Found |
|------------|---------------|----------------|
| "red" | **4.8ms** | 565 products |
| "dress" | **3.9ms** | 2,066 products |
| "dre" (wildcard) | **6.7ms** | 2,078 products |
| "ghermez" (synonym) | **3.9ms** | 565 products |

**Compare to competitors:**
- WordPress default: 200-1000ms (50-250x slower!)
- Relevanssi: 100-500ms (25-125x slower!)
- WooCommerce: 300-800ms (75-200x slower!)

---

## ✨ Features

### 🚀 Lightning-Fast Search
- ⚡ **Sub-5ms** search responses
- 🔍 Search 10,000+ products instantly
- 📊 Advanced BM25 relevance scoring
- 🎯 Proximity ranking
- ✨ Real-time indexing (no cron jobs!)

### 🧠 Smart Search
- 🔤 **Smart Synonyms**: "gown" finds "dress" automatically
- 🌟 **Wildcard Matching**: "dre" finds "dress", "dresses", etc.
- 🎯 **Typo Tolerance**: Handles common misspellings
- 📝 **Multi-word Search**: Intelligent phrase matching
- 🔄 **Bidirectional Synonyms**: Works both ways!

### 🛒 WooCommerce Optimized
- 💰 Price range filtering
- 📦 Stock status filtering
- 🏷️ Category & tag filtering
- 🎨 Attribute filtering (color, size, etc.)
- ⭐ Rating filtering
- 🔥 On-sale filtering
- 🔍 SKU search (high priority)

### 📊 Search Analytics & Insights ⭐ NEW!
- 📈 **Top Searches Widget**: See what customers search for
- 🔥 **Trending Detection**: Auto-detect searches with >50% growth
- ⚠️ **Zero Results Alerts**: Find queries with no results
- 💡 **Smart Suggestions**: Typo detection & synonym recommendations
- 📊 **Performance Metrics**: Avg time, success rate, result count
- 📥 **CSV Export**: Download search analytics data
- 🎯 **Actionable Insights**: Fix issues, add synonyms directly

### 🎨 Beautiful UI
- 💻 **Modal Search**: Cmd/K or Ctrl+K shortcut
- ⚡ **AJAX Dropdown**: Instant results as you type
- 🔍 **Search Icon Modal**: Beautiful blurry overlay
- 📱 **Mobile-Responsive**: Perfect on all devices
- 🌓 **RTL Support**: Persian, Arabic, Hebrew
- ⌨️ **Keyboard Navigation**: Arrow keys, Enter, Esc
- 🎯 **Accessible**: WCAG 2.1 compliant

### 👨‍💻 Developer Friendly
- 🔧 **WP-CLI Support**: Full command-line interface
- 🪝 **Hooks & Filters**: Extensive customization
- 📚 **Well-Documented**: Clean, namespaced code
- 🎨 **Template Overrides**: Customize HTML/CSS
- 🔌 **REST API**: Programmatic access
- 📦 **PSR-4 Autoloading**: Modern PHP standards

---

## 📦 Installation

### Prerequisites

**Server Requirements:**
- PHP 7.4+
- MySQL/MariaDB
- MySQLi extension

**WordPress:**
- WordPress 5.8+
- WooCommerce 6.0+ (optional)

### Step 1: Install Manticore Search

**Debian/Ubuntu:**
```bash
wget https://repo.manticoresearch.com/manticore-repo.noarch.deb
sudo dpkg -i manticore-repo.noarch.deb
sudo apt update
sudo apt install manticore manticore-extra
sudo systemctl start manticore
sudo systemctl enable manticore
```

**CentOS/RHEL:**
```bash
yum install https://repo.manticoresearch.com/manticore-repo.noarch.rpm
yum install manticore manticore-extra
systemctl start manticore
systemctl enable manticore
```

**Docker:**
```bash
docker pull manticoresearch/manticore
docker run -d --name manticore -p 9306:9306 -p 9308:9308 manticoresearch/manticore
```

**Verify Installation:**
```bash
mysql -h127.0.0.1 -P9306 -e "SHOW TABLES"
```

### Step 2: Install MantiLoad Plugin

**From WordPress.org:**
1. Go to **Plugins > Add New**
2. Search for "**MantiLoad**"
3. Click **Install Now** → **Activate**

**Manual Installation:**
```bash
cd wp-content/plugins/
git clone https://github.com/mantiload/mantiload.git
cd mantiload/
```

Then activate from WordPress Admin > Plugins.

### Step 3: Setup & Index

**Via Admin:**
1. Go to **MantiLoad > Indexing**
2. Click **"Create Indexes"**
3. Click **"Reindex All Posts"**

**Via WP-CLI (Faster):**
```bash
wp mantiload create_indexes
wp mantiload reindex --batch-size=500
```

Done! 🎉

---

## 🚀 Usage

### AJAX Search (Instant Results)

Add search box anywhere:

```php
<?php echo do_shortcode('[mantiload_search]'); ?>
```

Or use the widget: **Appearance > Widgets > MantiLoad Search**

### Search Icon (Mobile-Friendly) ⭐ NEW!

Beautiful search icon with blurry modal overlay - perfect for mobile menus!

**Shortcode:**
```php
[mantiload_search_icon]
```

**With Parameters:**
```php
[mantiload_search_icon size="large" style="circle" show_label="true" label="Search Products" fullscreen="true"]
```

**Available Parameters:**

| Parameter | Options | Default | Description |
|-----------|---------|---------|-------------|
| `size` | `small`, `medium`, `large` | `medium` | Icon size (20px, 24px, 32px) |
| `style` | `default`, `circle`, `rounded` | `default` | Icon background style |
| `show_label` | `true`, `false` | `false` | Show text label next to icon |
| `label` | Any text | `"Search"` | Custom label text |
| `fullscreen` | `true`, `false` | `false` | Open search in fullscreen overlay |
| `class` | CSS class | - | Additional CSS classes |

**Examples:**

```php
<!-- Clean icon only (perfect for mobile header) -->
[mantiload_search_icon]

<!-- Large icon with circular background -->
[mantiload_search_icon size="large" style="circle"]

<!-- Icon with label -->
[mantiload_search_icon show_label="true" label="Find Products"]

<!-- Fullscreen search -->
[mantiload_search_icon fullscreen="true" size="large" style="circle"]

<!-- Custom styling -->
[mantiload_search_icon size="large" style="rounded" class="my-custom-class"]
```

**Widget:** **Appearance > Widgets > MantiLoad Search Icon**

**Features:**
- 🌟 Beautiful blurry modal overlay
- 📱 100% width on mobile (fullscreen)
- ⚡ Ultra-fast Manticore search
- 🎨 Smooth slide-down animation
- ⌨️ Close with ESC, X button, or click outside
- 🌐 RTL support (Persian, Arabic, Hebrew)
- ⚙️ Auto-syncs with admin settings (min chars, delay)

**Perfect for:**
- Mobile header menus
- Navigation bars
- Theme builders (WoodMart, Elementor, etc.)
- Sticky headers
- Modern, minimal designs

### Modal Search (Cmd/K)

Built-in keyboard shortcut! Press:
- **Mac**: Cmd + K
- **Windows/Linux**: Ctrl + K

### Programmatic Search

```php
$search_engine = new \MantiLoad\Search\Search_Engine();

$results = $search_engine->search( 'laptop', array(
    'post_type' => 'product',
    'limit' => 20,
    'offset' => 0,
    'orderby' => 'relevance',
    'filters' => array(
        'min_price' => 500,
        'max_price' => 2000,
        'categories' => array( 12, 15 ),
        'on_sale' => true,
        'in_stock' => true,
    ),
) );

// Results
foreach ( $results['posts'] as $post ) {
    echo $post->post_title . ' - ' . $results['relevance'][$post->ID];
}

// Metadata
echo 'Total: ' . $results['total'];
echo 'Query time: ' . $results['query_time'] . 'ms';
```

### Smart Synonyms

Create synonyms in **MantiLoad > Synonyms**:

```
Term: gown
Synonyms: dress, evening dress, formal dress
```

Now when users search "gown", they'll also see "dress" results!

**Works bidirectionally**: Searching "dress" also finds "gown" products.

---

## 🔧 WP-CLI Commands

MantiLoad includes powerful CLI commands:

```bash
# Create indexes (one-time setup)
wp mantiload create_indexes

# Reindex all posts
wp mantiload reindex

# Reindex specific post type
wp mantiload reindex --post-type=product

# Reindex with custom batch size (faster)
wp mantiload reindex --batch-size=500

# Optimize indexes (improve performance)
wp mantiload optimize

# Show statistics
wp mantiload stats

# Search from command line
wp mantiload search "laptop" --post-type=product --limit=10

# Clear search logs
wp mantiload clear_logs

# Drop all indexes (careful!)
wp mantiload drop_indexes
```

---

## ⚙️ Configuration

### Field Weights

Configure in **MantiLoad > Settings**:

| Field | Default Weight | Description |
|-------|----------------|-------------|
| SKU | 150 | Highest priority (exact match) |
| Title | 100 | Product/post title |
| Excerpt | 75 | Short description |
| Categories | 60 | Category names |
| Content | 50 | Full content |
| Tags | 40 | Tag names |
| Attributes | 30 | WooCommerce attributes |

Higher weight = higher relevance in search results.

### Indexing Settings

- **Batch Size**: 100-500 (default: 200)
- **Real-time Indexing**: Enabled by default
- **Morphology**: English stemming
- **Min Infix Length**: 3 (enables wildcard search)

---

## 🪝 Hooks & Filters

### Modify Index Schema

```php
add_filter( 'mantiload_index_schema', function( $schema, $post_type ) {
    // Add custom field to index
    $schema['custom_field'] = array(
        'type' => 'text',
        'weight' => 80,
    );
    return $schema;
}, 10, 2 );
```

### Modify Indexed Data

```php
add_filter( 'mantiload_post_data', function( $data, $post ) {
    // Add custom field value
    $data['custom_field'] = get_post_meta( $post->ID, 'custom', true );
    return $data;
}, 10, 2 );
```

### After Search Results

```php
add_action( 'mantiload_search_results', function( $results, $query ) {
    // Log search query
    error_log( "Search: {$query} - {$results['total']} results in {$results['query_time']}ms" );
}, 10, 2 );
```

### Modify Search Query

```php
add_filter( 'mantiload_search_query', function( $query, $args ) {
    // Force lowercase
    return strtolower( $query );
}, 10, 2 );
```

---

## 📊 Performance Benchmarks

### Query Performance

Tested on WooCommerce store with 4,441 products:

| Products | Query | Time | Results |
|----------|-------|------|---------|
| 4,441 | "red" | 4.8ms | 565 |
| 4,441 | "dress" | 3.9ms | 2,066 |
| 4,441 | "dre" (wildcard) | 6.7ms | 2,078 |
| 4,441 | "red dress under $100" | 12ms | 247 |
| 4,441 | "jovani 123" (SKU) | 2.1ms | 1 |

### Indexing Performance

| Products | Batch Size | Time | Speed |
|----------|------------|------|-------|
| 1,000 | 100 | 8s | 125/s |
| 5,000 | 200 | 32s | 156/s |
| 10,000 | 500 | 58s | 172/s |

### Resource Usage

- **Memory**: 50-100MB (Manticore)
- **CPU**: <1% idle, <5% indexing
- **Disk**: ~1MB per 1,000 products

---

## 🏗️ Architecture

### Core Components

```
mantiload/
├── includes/
│   ├── class-manticore-client.php     # Manticore connection
│   ├── class-indexer.php               # Batch indexing
│   ├── class-search-engine.php         # Search query processor
│   ├── class-query-builder.php         # Dynamic SQL builder
│   ├── class-ajax-search.php           # Frontend AJAX
│   ├── class-admin-search.php          # Admin AJAX
│   ├── class-synonyms.php              # Smart synonyms
│   └── class-cli.php                   # WP-CLI commands
├── admin/
│   ├── class-admin-controller.php      # Admin pages
│   ├── views/                          # Admin templates
│   └── assets/                         # Admin CSS/JS
├── assets/
│   ├── css/                            # Frontend CSS
│   └── js/                             # Frontend JS
└── mantiload.php                       # Main plugin file
```

### Index Schema

Each post type gets its own Manticore RT index:

**Full-text fields:**
- `post_title` (weight: 100)
- `post_content` (weight: 50)
- `post_excerpt` (weight: 75)
- `sku` (weight: 150 - WooCommerce only)
- `categories` (weight: 60)
- `tags` (weight: 40)
- `attributes` (weight: 30 - WooCommerce only)

**Numeric attributes:**
- `id`, `post_date`, `price`, `stock_status`, `on_sale`, `featured`, `rating`, `total_sales`, `menu_order`

**Multi-valued attributes (MVA):**
- `product_cat_ids`, `product_tag_ids`, `pa_color_ids`, `pa_size_ids`, etc.

---

## 🐛 Troubleshooting

### Plugin won't activate

**Check Manticore is running:**
```bash
systemctl status manticore
mysql -h127.0.0.1 -P9306 -e "SHOW TABLES"
```

### No search results

**Reindex posts:**
```bash
wp mantiload reindex
```

**Check indexes exist:**
```bash
wp mantiload stats
```

### Slow indexing

**Increase batch size:**
```bash
wp mantiload reindex --batch-size=500
```

**Check server resources:**
```bash
top  # Check CPU/Memory
```

### Debug Mode

Enable in `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs: `wp-content/debug.log`

---

## 📚 Documentation

Full documentation available at: **https://docs.mantiload.com/**

Topics covered:
- Installation guide
- Configuration options
- Developer API
- Hooks & filters reference
- Performance optimization
- Troubleshooting
- Migration guides

---

## 🤝 Contributing

We love contributions! Here's how you can help:

### Reporting Bugs

Found a bug? [Open an issue](https://github.com/mantiload/mantiload/issues) with:
- WordPress version
- WooCommerce version (if applicable)
- PHP version
- Manticore version
- Steps to reproduce
- Expected vs actual behavior

### Suggesting Features

Have an idea? [Open a feature request](https://github.com/mantiload/mantiload/issues) with:
- Clear description
- Use case
- Expected behavior
- Why it would be useful

### Code Contributions

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'Add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

**Coding Standards:**
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- PSR-4 autoloading
- Proper documentation (PHPDoc)
- Security best practices (sanitization, escaping, nonces)

### Translation

Help translate MantiLoad!

1. Download POT file from `languages/mantiload.pot`
2. Use [Poedit](https://poedit.net/) to translate
3. Submit PO/MO files via Pull Request

---

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

**Latest: 1.1.0** (2025-01-30)
- ✨ NEW: Top Searches & Insights Widget with trending detection
- ✨ NEW: Zero Results Query Detection with smart suggestions
- ✨ NEW: Performance metrics dashboard (avg time, success rate)
- ✨ NEW: CSV export for search analytics
- ✨ NEW: Typo detection and synonym recommendations
- 🐛 FIX: Enter key now redirects to proper search results page
- ⚡ IMPROVE: Search URL includes post_type parameter by default

---

## 📄 License

MantiLoad is licensed under **GPLv2 or later**.

This means you are free to use, modify, and distribute this software. See [LICENSE](LICENSE) for details.

---

## 💬 Support

Need help? We're here for you:

- 📚 **Documentation**: https://docs.mantiload.com/
- 💬 **Support Forum**: https://wordpress.org/support/plugin/mantiload/
- 🐛 **Bug Reports**: https://github.com/mantiload/mantiload/issues
- 📧 **Email**: support@mantiload.com
- 💬 **Telegram**: @mantiload (Persian/English)

---

## 🌟 Credits

**Developed by:**
- MantiLoad Team
- https://mantiload.com

**Powered by:**
- [Manticore Search](https://manticoresearch.com/) - Ultra-fast search engine
- [WordPress](https://wordpress.org/) - Content management system
- [WooCommerce](https://woocommerce.com/) - E-commerce platform

**Special Thanks:**
- Manticore Search team for the amazing search engine
- WordPress community for the ecosystem
- All contributors and users!

---

## ⭐ Show Your Support

If you find MantiLoad useful, please:

- ⭐ **Star this repository** on GitHub
- ⭐ **Rate it 5-stars** on WordPress.org
- 🐦 **Share it** on social media
- 📝 **Write a review** on WordPress.org
- 💬 **Tell your friends** about it
- ☕ **Buy us a coffee** (donations page coming soon!)

---

<div align="center">

**Made with ⚡ and ❤️ by MantiLoad Team**

[Website](https://mantiload.com) • [Documentation](https://docs.mantiload.com/) • [Support](https://wordpress.org/support/plugin/mantiload/) • [GitHub](https://github.com/mantiload/mantiload)

</div>
