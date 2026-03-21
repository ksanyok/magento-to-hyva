#!/usr/bin/env python3
"""
Hyvä Parent Theme Stub Generator

Creates a minimal stub of the Hyvä default theme (Hyva/default) with
enough structure to:
1. Register as a valid Magento theme
2. Allow the child theme to be activated
3. Provide basic page rendering with Alpine.js + Tailwind CDN
4. Enable visual testing without a Hyvä license

This is NOT a replacement for the real Hyvä theme — it's a testing shim.
For production, a proper Hyvä license is required.
"""
import json
import os


def generate_hyva_stub(output_path: str):
    """Generate the Hyvä default theme stub."""
    base = os.path.join(output_path, "Hyva", "default")
    os.makedirs(base, exist_ok=True)

    files = {}

    # ─── registration.php ─────────────────────────────────────
    files["registration.php"] = """<?php
/**
 * Hyvä Default Theme STUB — for development/testing only.
 * Replace with the real Hyvä theme for production.
 */
use Magento\\Framework\\Component\\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/Hyva/default',
    __DIR__
);
"""

    # ─── theme.xml ────────────────────────────────────────────
    files["theme.xml"] = """<?xml version="1.0"?>
<theme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="urn:magento:framework:Config/etc/theme.xsd">
    <title>Hyvä Default (Stub)</title>
    <parent>Magento/blank</parent>
</theme>
"""

    # ─── composer.json ────────────────────────────────────────
    files["composer.json"] = json.dumps({
        "name": "hyva-themes/magento2-default-theme",
        "description": "Hyvä Default Theme STUB for development testing",
        "type": "magento2-theme",
        "license": "proprietary",
        "autoload": {
            "files": ["registration.php"]
        },
        "extra": {
            "_note": "This is a TESTING STUB. Replace with real Hyvä theme for production."
        }
    }, indent=4) + "\n"

    # ─── default.xml — base page structure ────────────────────
    files["Magento_Theme/layout/default.xml"] = """<?xml version="1.0"?>
<!--
    Hyvä Default Theme STUB — provides minimal page structure
    with Alpine.js and Tailwind CSS via CDN for testing.
-->
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <!-- Tailwind CSS via CDN (stub only — real Hyvä builds locally) -->
        <css src="https://cdn.jsdelivr.net/npm/tailwindcss@3.4/dist/tailwind.min.css"
             src_type="url"/>
    </head>
    <body>
        <referenceContainer name="after.body.start">
            <block class="Magento\\Framework\\View\\Element\\Template"
                   name="hyva.stub.alpine"
                   template="Magento_Theme::html/alpine-init.phtml"/>
        </referenceContainer>

        <!-- Remove Luma JS bundles that conflict with Alpine.js -->
        <referenceBlock name="requirejs-config" remove="true"/>
        <referenceBlock name="requirejs-min-resolver" remove="true"/>
    </body>
</page>
"""

    # ─── 1column layout ───────────────────────────────────────
    files["Magento_Theme/page_layout/1column.xml"] = """<?xml version="1.0"?>
<layout xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_layout.xsd">
    <update handle="empty"/>
    <referenceContainer name="page.wrapper">
        <container name="header.container" as="header_container" label="Page Header Container" htmlTag="header" htmlClass="w-full bg-white border-b border-gray-200"/>
        <container name="page.top" as="page_top" label="After Page Header" htmlTag="div" htmlClass="w-full"/>
        <container name="columns.top" label="Before Main Columns" htmlTag="div" htmlClass="w-full max-w-7xl mx-auto px-4"/>
        <container name="columns" label="Columns" htmlTag="main" htmlClass="w-full max-w-7xl mx-auto px-4 py-8">
            <container name="main" label="Main Content Container" htmlTag="div" htmlClass="w-full"/>
        </container>
        <container name="page.bottom.container" as="page_bottom_container" label="Before Page Footer Container" htmlTag="div" htmlClass="w-full"/>
        <container name="footer-container" as="footer" label="Page Footer Container" htmlTag="footer" htmlClass="w-full bg-gray-100 border-t border-gray-200 py-8"/>
    </referenceContainer>
</layout>
"""

    # ─── Alpine.js init template ──────────────────────────────
    files["Magento_Theme/templates/html/alpine-init.phtml"] = """<?php
/**
 * Hyvä Stub — Alpine.js initialization
 * Loads Alpine.js via CDN for development testing.
 */
?>
<!-- Alpine.js Collapse Plugin -->
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<!-- Alpine.js Focus Plugin -->
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.x.x/dist/cdn.min.js"></script>
<!-- Alpine.js Core -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
    /**
     * Hyvä private-content-loaded event stub.
     * The real Hyvä theme fires this from its customer-data system.
     * This stub fires it with empty data after page load.
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Simulate Hyvä's section data loading
        var sectionData = {
            cart: {
                summary_count: 0,
                items: [],
                subtotalAmount: ''
            },
            customer: {
                firstname: '',
                lastname: ''
            }
        };

        // Try to load from Magento's customer-data if available
        try {
            var storedSections = localStorage.getItem('mage-cache-storage');
            if (storedSections) {
                var parsed = JSON.parse(storedSections);
                if (parsed.cart) sectionData.cart = parsed.cart;
                if (parsed.customer) sectionData.customer = parsed.customer;
            }
        } catch(e) {}

        window.dispatchEvent(new CustomEvent('private-content-loaded', {
            detail: sectionData
        }));
    });
</script>
"""

    # ─── ViewModelRegistry stub ───────────────────────────────
    # This provides the PHP classes that templates use.
    # Real Hyvä has these in hyva-themes/magento2-theme-module.
    # Our stub provides empty implementations just so PHP doesn't fatal.

    files["Magento_Theme/templates/html/header.phtml"] = """<?php
/** Hyvä Stub — Header template */
?>
<div class="flex items-center justify-between px-4 py-3 max-w-7xl mx-auto">
    <a href="<?= $block->getUrl('') ?>" class="text-xl font-bold">
        <?= $block->escapeHtml($block->getLayout()->getBlock('logo') ?
            $block->getLayout()->getBlock('logo')->getLogoAlt() : 'Store') ?>
    </a>
    <nav class="flex items-center gap-4">
        <?= $block->getChildHtml() ?>
    </nav>
</div>
"""

    # ─── Write all files ──────────────────────────────────────
    for rel_path, content in files.items():
        full = os.path.join(base, rel_path)
        os.makedirs(os.path.dirname(full), exist_ok=True)
        with open(full, "w") as f:
            f.write(content)

    return base


def generate_hyva_module_stub(output_path: str):
    """
    Generate a stub for hyva-themes/magento2-theme-module.
    This provides the ViewModelRegistry and common ViewModels
    that our phtml templates reference.
    """
    base = os.path.join(output_path, "Hyva", "Theme")
    os.makedirs(base, exist_ok=True)

    files = {}

    files["registration.php"] = """<?php
/**
 * Hyvä Theme Module STUB — provides ViewModelRegistry and common ViewModels
 * for development/testing without a Hyvä license.
 */
use Magento\\Framework\\Component\\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Hyva_Theme',
    __DIR__
);
"""

    files["etc/module.xml"] = """<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Hyva_Theme" setup_version="1.0.0">
        <sequence>
            <module name="Magento_Theme"/>
        </sequence>
    </module>
</config>
"""

    # ViewModelRegistry — the core class our templates use
    files["Model/ViewModelRegistry.php"] = """<?php
declare(strict_types=1);

namespace Hyva\\Theme\\Model;

use Magento\\Framework\\ObjectManagerInterface;

/**
 * Hyvä ViewModelRegistry STUB
 * Provides require() method that templates use to get ViewModels.
 */
class ViewModelRegistry
{
    private ObjectManagerInterface $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @template T
     * @param class-string<T> $viewModelClass
     * @return T
     */
    public function require(string $viewModelClass)
    {
        return $this->objectManager->get($viewModelClass);
    }
}
"""

    # ProductPage ViewModel stub
    files["ViewModel/ProductPage.php"] = """<?php
declare(strict_types=1);

namespace Hyva\\Theme\\ViewModel;

use Magento\\Catalog\\Api\\Data\\ProductInterface;
use Magento\\Framework\\View\\Element\\Block\\ArgumentInterface;

/**
 * Hyvä ProductPage ViewModel STUB
 */
class ProductPage implements ArgumentInterface
{
    public function format(ProductInterface $product): array
    {
        return [];
    }

    public function getAddToCartPostParams(ProductInterface $product): string
    {
        return '{}';
    }
}
"""

    # ProductListItem ViewModel stub
    files["ViewModel/ProductListItem.php"] = """<?php
declare(strict_types=1);

namespace Hyva\\Theme\\ViewModel;

use Magento\\Catalog\\Api\\Data\\ProductInterface;
use Magento\\Framework\\View\\Element\\Block\\ArgumentInterface;

/**
 * Hyvä ProductListItem ViewModel STUB
 */
class ProductListItem implements ArgumentInterface
{
    public function getSecondImage(ProductInterface $product, string $imageId)
    {
        // Stub: returns null (no hover image)
        return null;
    }
}
"""

    # CurrentCategory ViewModel stub
    files["ViewModel/CurrentCategory.php"] = """<?php
declare(strict_types=1);

namespace Hyva\\Theme\\ViewModel;

use Magento\\Framework\\View\\Element\\Block\\ArgumentInterface;

/**
 * Hyvä CurrentCategory ViewModel STUB
 */
class CurrentCategory implements ArgumentInterface
{
    public function exists(): bool
    {
        return false;
    }
}
"""

    # ReCaptcha ViewModel stub
    files["ViewModel/ReCaptcha.php"] = """<?php
declare(strict_types=1);

namespace Hyva\\Theme\\ViewModel;

use Magento\\Framework\\View\\Element\\Block\\ArgumentInterface;

/**
 * Hyvä ReCaptcha ViewModel STUB
 */
class ReCaptcha implements ArgumentInterface
{
    public function getInputHtml(string $recaptchaId): string
    {
        return '';
    }

    public function getLegalNoticeHtml(string $recaptchaId): string
    {
        return '';
    }

    public function getValidationJsHtml(string $recaptchaId): string
    {
        return '';
    }
}
"""

    files["composer.json"] = json.dumps({
        "name": "hyva-themes/magento2-theme-module",
        "description": "Hyvä Theme Module STUB for development testing",
        "type": "magento2-module",
        "license": "proprietary",
        "autoload": {
            "files": ["registration.php"],
            "psr-4": {
                "Hyva\\Theme\\": ""
            }
        }
    }, indent=4) + "\n"

    files["README.md"] = """# Hyvä Theme Module STUB

**This is a TESTING STUB, not the real Hyvä theme module.**

## Purpose

Provides minimal implementations of Hyvä's core PHP classes:
- `Hyva\\Theme\\Model\\ViewModelRegistry` — ViewModel resolution
- `Hyva\\Theme\\ViewModel\\ProductPage` — Product page helpers
- `Hyva\\Theme\\ViewModel\\ProductListItem` — Product list helpers
- `Hyva\\Theme\\ViewModel\\CurrentCategory` — Category context
- `Hyva\\Theme\\ViewModel\\ReCaptcha` — reCaptcha integration

## Usage

1. Copy to `app/code/Hyva/Theme/` on your Magento installation
2. Run `bin/magento setup:upgrade`
3. Activate the child theme in Content → Design → Configuration

## Limitations

- No Tailwind build system (uses CDN fallback)
- No full Alpine.js integration (loaded via CDN)
- ViewModels return basic/empty data
- No Hyvä checkout
- Missing many real Hyvä features

## For Production

Purchase a Hyvä license at https://hyva.io and replace both:
- This module with `hyva-themes/magento2-theme-module`
- The theme stub with `hyva-themes/magento2-default-theme`
"""

    for rel_path, content in files.items():
        full = os.path.join(base, rel_path)
        os.makedirs(os.path.dirname(full), exist_ok=True)
        with open(full, "w") as f:
            f.write(content)

    return base


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Generate Hyvä parent theme stub for testing")
    parser.add_argument("--output", required=True, help="Output directory")
    args = parser.parse_args()

    theme_path = generate_hyva_stub(args.output)
    module_path = generate_hyva_module_stub(args.output)

    print(f"Generated Hyvä stub theme: {theme_path}")
    print(f"Generated Hyvä stub module: {module_path}")
    print(f"\nTo use:")
    print(f"  1. Copy {theme_path} → app/design/frontend/Hyva/default/")
    print(f"  2. Copy {module_path} → app/code/Hyva/Theme/")
    print(f"  3. bin/magento setup:upgrade")
    print(f"  4. Set theme to MediaDivision/FTCShopHyva in admin")
