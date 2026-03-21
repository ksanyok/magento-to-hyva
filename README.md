# Magento Luma → Hyvä Migration Tool

Automated toolset for migrating Magento 2 shops from Luma-based themes to Hyvä frontend.
Converts KnockoutJS/RequireJS/jQuery templates to Alpine.js, LESS styles to Tailwind CSS.

**Tested with**: Magento 2.4.8 + Hyvä 1.4.5 (Tailwind CSS v4)

## Features

- **Design token extraction** — Automatically extracts colors, fonts, breakpoints, and font sizes from LESS variables
- **Tailwind v3 & v4 support** — Generates CSS-first config (`@theme` block) for v4 or JS config for v3
- **Safe template strategy** — Only overrides templates that need Hyvä-specific changes; skips product listing/view templates where Hyvä defaults work best
- **Layout XML conversion** — Adapts Luma layout XMLs for Hyvä block structure
- **i18n enrichment** — Extracts translatable strings from templates and enriches locale CSV files
- **Module compatibility analysis** — Identifies modules needing Hyvä compatibility packages, generates stub modules
- **Asset migration** — Copies fonts, images, icons, and social media assets

## Structure

- **analyzer/** — Phase 1: Scan & analyze existing Magento installation
  - `scan.py` — Main orchestrator
  - `theme_scanner.py` — Detects KO/RequireJS/jQuery patterns, assigns complexity
  - `modules_checker.py` — Checks Hyvä compatibility for all modules
  - `report_generator.py` — Generates Markdown + JSON reports with effort estimates
- **generator/** — Phase 2: Generate Hyvä child theme
  - `style_extractor.py` — Extracts design tokens from LESS files
  - `hyva_theme.py` — Scaffolds complete Hyvä child theme with Tailwind v3 or v4 config
  - `template_converter.py` — Strategy mapping for all template overrides
  - `layout_converter.py` — Converts Luma layout XMLs to Hyvä equivalents
  - `templates/` — Safe Hyvä phtml rewrites (Alpine.js, Tailwind CSS)
  - `templates/optional/` — Store-specific templates (product views, third-party modules) — not deployed by default
- **config/** — Known modules DB (Hyvä compatibility database)
- **projects/** — Local copies of Magento installations (gitignored: vendor, pub/media)
- **output/** — Generated themes and reports (gitignored)
- **tests/** — Theme validation and Hyvä stub generation

## Usage

### Phase 1: Analyze
```bash
python3 analyzer/scan.py --project projects/myshop --output output/myshop
```

### Phase 2: Generate Hyvä Theme

**Tailwind v4** (default, for Hyvä 1.4.5+):
```bash
python3 generate.py \
  --project projects/myshop \
  --vendor MyVendor \
  --theme MyShopHyva \
  --title "My Shop Hyvä" \
  --output output/myshop
```

**Tailwind v3** (for older Hyvä versions):
```bash
python3 generate.py \
  --project projects/myshop \
  --vendor MyVendor \
  --theme MyShopHyva \
  --title "My Shop Hyvä" \
  --output output/myshop \
  --tailwind-version 3
```

### CLI Options

| Option | Default | Description |
|--------|---------|-------------|
| `--project` | required | Path to local Magento project copy |
| `--vendor` | required | Vendor name for the theme |
| `--theme` | required | Theme name |
| `--title` | `"{theme} Theme"` | Human-readable theme title |
| `--output` | required | Output directory |
| `--tailwind-version` | `4` | Tailwind CSS version (3 or 4) |

### Output

The generator produces a complete Hyvä child theme:

**Tailwind v4** (default):
- `web/tailwind/hyva.config.json` — Design tokens as JSON (colors, fonts, screens)
- `web/tailwind/tailwind-source.css` — CSS-first config with `@import "tailwindcss"`, `@theme` block, `@source` directives
- `package.json` — Uses `@tailwindcss/cli ^4.1` and `@hyva-themes/hyva-modules ^1.3`

**Tailwind v3**:
- `web/tailwind/tailwind.config.js` — JS-based Tailwind config with design tokens
- `web/tailwind/tailwind-source.css` — Traditional `@tailwind` directives
- `package.json` — Uses `tailwindcss ^3.4` with PostCSS plugins

**Both versions also include**:
- `registration.php`, `theme.xml`, `composer.json`
- `web/css/fonts.css` — @font-face declarations auto-detected from source theme
- Template rewrites (Alpine.js replacements for safe overrides)
- Layout XMLs (adapted for Hyvä block structure)
- Static assets (images, fonts, translations)

## Template Strategy

Templates are handled with different strategies based on safety:

| Strategy | Count | Description |
|----------|-------|-------------|
| **Rewrite** | 9 | Safe Alpine.js rewrites for forms, search, widgets |
| **Skip** | 36 | Use Hyvä defaults — custom overrides break these |
| **Copy** | 3 | Unchanged from source theme |
| **Remove** | 5 | KO-only templates removed |

### Safe Rewrites (deployed automatically)

| Template | What Changed |
|----------|-------------|
| `form.mini.phtml` | jQuery autocomplete → Alpine.js fetch + keyboard nav |
| `languages.phtml` | KO dropdown → Alpine.js x-show with transitions |
| `newsletter.phtml` | mage/validation → HTML5 + Alpine.js loading state |
| `login.phtml` | jQuery validation → Alpine.js show/hide password |
| `register.phtml` | jQuery strength meter → Alpine.js password scoring |
| `edit.phtml` (account) | jQuery form → Alpine.js form handling |
| `address/edit.phtml` | jQuery region updater → Alpine.js x-model |
| `minicart.phtml` | KnockoutJS data-bind → Alpine.js private-content-loaded |
| `widget/grid.phtml` | jQuery slider → Alpine.js snap scroll |

### Skipped (use Hyvä defaults)

Product listing (`list.phtml`, `items.phtml`) and product view templates (`addtocart.phtml`, `gallery.phtml`, etc.) are **skipped** — Hyvä provides its own optimized versions that work better than custom overrides.

### Optional Templates

Store-specific templates are available in `generator/templates/optional/` but not deployed automatically. Copy them manually if needed for your specific store setup.

## Deployment

After generating the theme:

```bash
# 1. Build Tailwind CSS
cd output/myshop/MyVendor/MyShopHyva
npm install && npm run build

# 2. Copy theme to Magento
cp -r output/myshop/MyVendor/MyShopHyva /path/to/magento/app/design/frontend/MyVendor/MyShopHyva

# 3. Install Hyvä compat packages (see compatibility report)
composer require hyva-themes/hyva-compat-<module>

# 4. Copy stub modules
cp -r output/myshop/compatibility/stubs/* /path/to/magento/app/code/

# 5. Activate and deploy
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:flush
```

## Shared Hosting Notes

If deploying on shared hosting without OpenSearch/Elasticsearch:
- Install [swissup/module-search-mysql-legacy](https://github.com/nickthecoder/module-search-mysql-legacy) for MySQL-based catalog search
- Set search engine: `bin/magento config:set catalog/search/engine lmysql`
- Reindex: `bin/magento indexer:reindex`

## Requirements
- Python 3.10+
- Magento 2.4.x project files (local copy or SSH access)
- Hyvä Theme license (for deployment)
- Node.js 18+ (for Tailwind CSS build)
