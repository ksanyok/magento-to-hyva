# Magento Luma → Hyvä Migration Tool

Automated toolset for migrating Magento 2 shops from Luma-based themes to Hyvä frontend.
Converts KnockoutJS/RequireJS/jQuery templates to Alpine.js, LESS styles to Tailwind CSS.

## Structure

- **analyzer/** — Phase 1: Scan & analyze existing Magento installation
  - `scan.py` — Main orchestrator
  - `theme_scanner.py` — Detects KO/RequireJS/jQuery patterns, assigns complexity
  - `modules_checker.py` — Checks Hyvä compatibility for all modules
  - `report_generator.py` — Generates Markdown + JSON reports with effort estimates
- **generator/** — Phase 2: Generate Hyvä child theme
  - `style_extractor.py` — Extracts design tokens from LESS files
  - `hyva_theme.py` — Scaffolds complete Hyvä child theme (registration.php, tailwind.config.js, etc.)
  - `template_converter.py` — Strategy mapping for all template overrides
  - `layout_converter.py` — Converts Luma layout XMLs to Hyvä equivalents
  - `templates/` — Hyvä phtml rewrites (Alpine.js, Tailwind CSS)
- **config/** — Known modules DB (Hyvä compatibility database)
- **projects/** — Local copies of Magento installations (gitignored: vendor, pub/media)
- **output/** — Generated themes and reports (gitignored)

## Usage

### Phase 1: Analyze
```bash
python3 analyzer/scan.py --project projects/ftcshop --output output/ftcshop
```

### Phase 2: Generate Hyvä Theme
```bash
python3 generate.py \
  --project projects/ftcshop \
  --vendor MediaDivision \
  --theme FTCShopHyva \
  --title "FTC Cashmere Hyvä" \
  --output output/ftcshop
```

### Output
The generator produces a complete Hyvä child theme:
- `registration.php`, `theme.xml`, `composer.json`
- `web/tailwind/tailwind.config.js` — Brand colors, fonts, spacing from LESS tokens
- `web/tailwind/tailwind-source.css` — Base/component/utility layers
- `web/css/fonts.css` — @font-face declarations
- 16 converted phtml templates (Alpine.js replacements for KO/jQuery)
- 9 layout XMLs (adapted for Hyvä block structure)
- Static assets (images, fonts, translations)

## Template Conversions

| Template | What Changed |
|----------|-------------|
| `form.mini.phtml` | jQuery autocomplete → Alpine.js fetch + keyboard nav |
| `languages.phtml` | KO dropdown → Alpine.js x-show with transitions |
| `newsletter.phtml` | mage/validation → HTML5 + Alpine.js loading state |
| `login.phtml` | jQuery validation → Alpine.js show/hide password |
| `register.phtml` | jQuery strength meter → Alpine.js password scoring |
| `addtocart.phtml` | jQuery modal → Alpine.js size chart modal, qty +/- |
| `gallery.phtml` | mage/gallery + magnifier → Alpine.js gallery + fullscreen |
| `minicart.phtml` | KnockoutJS data-bind → Alpine.js private-content-loaded |
| `product/list.phtml` | ObjectManager → ViewModels, inline CSS → Tailwind grid |
| `list/items.phtml` | Upsell/related → Alpine.js horizontal slider |
| `overview.phtml` | ObjectManager + jQuery popups → Alpine.js modals |
| `underview.phtml` | ObjectManager → block child rendering |
| `widget/grid.phtml` | jQuery slider → Alpine.js snap scroll |
| `address/edit.phtml` | jQuery region updater → Alpine.js x-model |
| `view_email.phtml` | mage/validation → Alpine.js form |
| `link.phtml` | Custom helper → SVG heart icon + badge |

## Requirements
- Python 3.10+
- SSH access to Magento server (for initial data copy)
- Hyvä Theme license (for deployment)
