# GitHub Actions Workflows

## Release Workflow

### How to Create a Release

1. **Update version** in the following files:
   - `mantiload.php` (line 24: `define( 'MANTILOAD_VERSION', '1.5.1' )`)
   - `readme.txt` (line 7: `Stable tag: 1.5.1`)

2. **Add changelog** to `readme.txt`:
   ```
   == Changelog ==

   = 1.5.2 =
   * Fixed: Admin product search SSL connection issue
   * Fixed: Exact SKU matching for "Add to Order" functionality
   * Enhanced: Variable products now return variations when searching by parent SKU
   * Security: Improved input sanitization and output escaping
   * Compliance: Bundled select2 and chart.js locally (removed CDN dependencies)

   = 1.5.1 =
   ...
   ```

3. **Commit and push** your changes:
   ```bash
   git add .
   git commit -m "Release v1.5.2"
   git push origin main
   ```

4. **Create and push a tag**:
   ```bash
   git tag v1.5.2
   git push origin v1.5.2
   ```

5. **Automatic actions**:
   - GitHub Actions will automatically:
     - Extract the changelog for this version from readme.txt
     - Create a clean ZIP file (excluding development files)
     - Create a GitHub Release with the ZIP attachment
     - Generate release notes with installation instructions

### What Gets Excluded from ZIP

The following files/directories are excluded from the release ZIP:
- `.git*`, `.github`, `.claude`
- Development docs (DEVELOPMENT.md, etc.)
- `reset_mantiload.php` (internal tool)
- `node_modules`, `vendor`, `tests`
- Log files, OS files (.DS_Store, Thumbs.db)

### Release Notes Format

Release notes are automatically generated with:
- Version number
- Changelog from readme.txt
- Installation instructions
- Documentation links
- Requirements

### WordPress.org Deployment

To enable automatic deployment to WordPress.org SVN:

1. Add secrets to your GitHub repository:
   - `WP_ORG_USERNAME`: Your WordPress.org username
   - `WP_ORG_PASSWORD`: Your WordPress.org password

2. Set `if: false` to `if: true` in the "Upload to WordPress.org" step

3. Add the deployment script (example provided in comments)

### Troubleshooting

**If workflow fails:**
- Check that changelog exists in readme.txt for the version
- Verify tag format is `v1.2.3` (with 'v' prefix)
- Ensure all required files are committed

**Manual ZIP creation:**
```bash
# If you need to create a ZIP manually
cd /path/to/mantiload
zip -r mantiload.zip . -x "*.git*" ".github/*" ".claude/*" "*.md" "reset_mantiload.php"
```
