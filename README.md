# Magento Luma → Hyvä Migration Tool

Automated toolset for migrating Magento 2 shops from Luma-based themes to Hyvä frontend.

## Structure

- **analyzer/** — Phase 1: Scan & analyze existing Magento installation
- **generator/** — Phase 2: Generate Hyvä child theme from analysis
- **compatibility/** — Phase 3: Generate compatibility modules
- **config/** — Known modules DB, conversion rules
- **projects/** — Local copies of Magento installations (gitignored: vendor, pub/media)
- **output/** — Generated themes and modules (gitignored)

## Usage

### Phase 1: Analyze
```bash
python analyzer/scan.py --project projects/ftcshop --output output/ftcshop
```

### Phase 2: Generate (coming soon)
```bash
python generator/hyva_theme.py --report output/ftcshop/report.json --output output/ftcshop/theme
```

## Requirements
- Python 3.10+
- SSH access to Magento server (for remote scanning)
