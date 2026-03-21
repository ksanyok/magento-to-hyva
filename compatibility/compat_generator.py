"""
Phase 3: Hyvä Compatibility Module Analyzer & Generator

Analyzes a Magento project and generates:
1. Compatibility report (which modules need what)
2. composer require commands for available Hyvä compat packages
3. Stub modules for custom compatibility work
"""
import json
import os
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from config.hyva_known_modules import (
    HYVA_COMPAT_MODULES,
    MAGENTO_CORE_HYVA_MAPPING,
    COMPOSER_PACKAGE_COMPAT,
)


def analyze_installed_modules(project_path: str) -> dict:
    """
    Scan the Magento project for installed modules and categorize
    them for Hyvä compatibility.

    Returns dict with:
        - 'compatible': modules with known Hyvä compat packages
        - 'built_in': modules that work without changes
        - 'needs_custom': modules needing custom Hyvä compat work
        - 'core': Magento core modules (handled by Hyvä default theme)
    """
    result = {
        "compatible": [],
        "built_in": [],
        "needs_custom": [],
        "core": [],
    }

    # Scan app/code for custom/third-party modules
    code_path = os.path.join(project_path, "app", "code")
    if os.path.isdir(code_path):
        for vendor in sorted(os.listdir(code_path)):
            vendor_dir = os.path.join(code_path, vendor)
            if not os.path.isdir(vendor_dir):
                continue
            for module in sorted(os.listdir(vendor_dir)):
                module_dir = os.path.join(vendor_dir, module)
                if not os.path.isdir(module_dir):
                    continue

                module_name = f"{vendor}_{module}"
                has_frontend = _has_frontend_templates(module_dir)

                info = HYVA_COMPAT_MODULES.get(module_name, {})
                status = info.get("status", "unknown")

                entry = {
                    "module": module_name,
                    "has_frontend": has_frontend,
                    "notes": info.get("notes", ""),
                }

                if status == "built-in" or not has_frontend:
                    entry["reason"] = "No frontend templates" if not has_frontend else "Backend only"
                    result["built_in"].append(entry)
                elif status in ("official", "community"):
                    entry["package"] = info.get("package", "")
                    result["compatible"].append(entry)
                else:
                    result["needs_custom"].append(entry)

    # Scan composer.json for vendor modules
    composer_file = os.path.join(project_path, "composer.json")
    if os.path.isfile(composer_file):
        with open(composer_file) as f:
            composer = json.load(f)
        for pkg in composer.get("require", {}):
            if pkg in COMPOSER_PACKAGE_COMPAT:
                compat = COMPOSER_PACKAGE_COMPAT[pkg]
                if compat["status"] in ("official", "community") and compat.get("package"):
                    # Check if not already added from app/code scan
                    existing = {e["module"] for e in result["compatible"]}
                    pkg_name = compat.get("package", "")
                    if not any(e.get("package") == pkg_name for e in result["compatible"]):
                        result["compatible"].append({
                            "module": pkg,
                            "package": pkg_name,
                            "has_frontend": True,
                            "notes": compat.get("notes", ""),
                        })

    # Also scan composer.lock for actually installed packages
    lock_file = os.path.join(project_path, "composer.lock")
    if os.path.isfile(lock_file):
        with open(lock_file) as f:
            lock_data = json.load(f)
        for pkg_info in lock_data.get("packages", []):
            pkg_name = pkg_info.get("name", "")
            if pkg_name in COMPOSER_PACKAGE_COMPAT:
                compat = COMPOSER_PACKAGE_COMPAT[pkg_name]
                if compat["status"] in ("official", "community") and compat.get("package"):
                    if not any(e.get("package") == compat["package"] for e in result["compatible"]):
                        result["compatible"].append({
                            "module": pkg_name,
                            "package": compat["package"],
                            "has_frontend": True,
                            "notes": compat.get("notes", ""),
                            "source": "composer.lock",
                        })

    return result


def _has_frontend_templates(module_dir: str) -> bool:
    """Check if a module has any frontend phtml templates."""
    frontend_dir = os.path.join(module_dir, "view", "frontend")
    if not os.path.isdir(frontend_dir):
        return False
    for root, _, files in os.walk(frontend_dir):
        for f in files:
            if f.endswith(".phtml") or f.endswith(".html"):
                return True
    return False


def generate_composer_requirements(analysis: dict) -> list:
    """Generate composer require commands for available compat packages."""
    packages = []
    for entry in analysis["compatible"]:
        pkg = entry.get("package", "")
        if pkg:
            packages.append(pkg)
    return sorted(set(packages))


def generate_compatibility_report(analysis: dict, output_path: str):
    """Generate a detailed compatibility report as Markdown."""
    lines = [
        "# Hyvä Compatibility Report",
        "",
        "Auto-generated analysis of third-party module Hyvä compatibility.",
        "",
    ]

    # --- Compatible modules ---
    lines.append("## ✅ Modules with Available Hyvä Compatibility Packages")
    lines.append("")
    if analysis["compatible"]:
        lines.append("Install these via Composer:")
        lines.append("")
        lines.append("```bash")
        packages = generate_composer_requirements(analysis)
        for pkg in packages:
            lines.append(f"composer require {pkg}")
        lines.append("```")
        lines.append("")
        lines.append("| Module | Compat Package | Notes |")
        lines.append("|--------|---------------|-------|")
        for e in analysis["compatible"]:
            lines.append(f"| {e['module']} | `{e.get('package', '')}` | {e.get('notes', '')} |")
    else:
        lines.append("None found.")
    lines.append("")

    # --- Built-in / no frontend ---
    lines.append("## ➡️ Modules That Work Without Changes")
    lines.append("")
    if analysis["built_in"]:
        lines.append("| Module | Reason | Notes |")
        lines.append("|--------|--------|-------|")
        for e in analysis["built_in"]:
            lines.append(f"| {e['module']} | {e.get('reason', '')} | {e.get('notes', '')} |")
    lines.append("")

    # --- Needs custom work ---
    lines.append("## ⚠️ Modules Needing Custom Hyvä Compatibility")
    lines.append("")
    if analysis["needs_custom"]:
        lines.append("| Module | Has Frontend | Notes | Priority |")
        lines.append("|--------|-------------|-------|----------|")
        for e in analysis["needs_custom"]:
            fe = "Yes" if e["has_frontend"] else "No"
            priority = "HIGH" if e["has_frontend"] else "LOW"
            lines.append(f"| {e['module']} | {fe} | {e.get('notes', '')} | {priority} |")
    lines.append("")

    # --- Action items ---
    lines.append("## 📋 Action Items")
    lines.append("")
    lines.append("### 1. Install Hyvä Compat Packages")
    lines.append("```bash")
    packages = generate_composer_requirements(analysis)
    if packages:
        lines.append("composer require \\")
        for i, pkg in enumerate(packages):
            suffix = " \\" if i < len(packages) - 1 else ""
            lines.append(f"    {pkg}{suffix}")
    lines.append("```")
    lines.append("")

    custom_with_frontend = [e for e in analysis["needs_custom"] if e["has_frontend"]]
    if custom_with_frontend:
        lines.append("### 2. Custom Compatibility Modules Needed")
        lines.append("")
        for e in custom_with_frontend:
            module = e["module"]
            lines.append(f"- **{module}**: {e.get('notes', 'Needs custom Hyvä templates')}")
        lines.append("")
        lines.append("Stub modules have been generated in the `compatibility/stubs/` directory.")

    report = "\n".join(lines) + "\n"

    os.makedirs(output_path, exist_ok=True)
    report_file = os.path.join(output_path, "COMPATIBILITY_REPORT.md")
    with open(report_file, "w") as f:
        f.write(report)

    return report_file


def generate_stub_module(
    output_path: str,
    vendor: str,
    module_name: str,
    description: str,
    original_module: str,
) -> str:
    """
    Generate a skeleton Hyvä compatibility module.
    Returns path to the created module directory.
    """
    module_dir = os.path.join(output_path, vendor, module_name)
    os.makedirs(module_dir, exist_ok=True)

    full_module = f"{vendor}_{module_name}"

    # registration.php
    reg = f"""<?php
use Magento\\Framework\\Component\\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    '{full_module}',
    __DIR__
);
"""
    with open(os.path.join(module_dir, "registration.php"), "w") as f:
        f.write(reg)

    # etc/module.xml
    etc_dir = os.path.join(module_dir, "etc")
    os.makedirs(etc_dir, exist_ok=True)

    module_xml = f"""<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="{full_module}" setup_version="1.0.0">
        <sequence>
            <module name="{original_module}"/>
            <module name="Hyva_Theme"/>
        </sequence>
    </module>
</config>
"""
    with open(os.path.join(etc_dir, "module.xml"), "w") as f:
        f.write(module_xml)

    # etc/frontend/events.xml (skeleton for event observers)
    frontend_dir = os.path.join(etc_dir, "frontend")
    os.makedirs(frontend_dir, exist_ok=True)

    events_xml = f"""<?xml version="1.0"?>
<!--
    {full_module} — Hyvä Compatibility Events
    Register observers to inject Alpine.js replacements for
    {original_module} RequireJS/KnockoutJS components.
-->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <!-- Example: Register Hyvä-compatible templates via event observer -->
    <!--
    <event name="hyva_config_generate_before">
        <observer name="{full_module.lower()}_register_config"
                  instance="{vendor}\\{module_name}\\Observer\\RegisterConfig"/>
    </event>
    -->
</config>
"""
    with open(os.path.join(frontend_dir, "events.xml"), "w") as f:
        f.write(events_xml)

    # composer.json
    package_name = f"{vendor.lower()}/module-hyva-compat-{module_name.lower()}"
    composer = {
        "name": package_name,
        "description": description,
        "type": "magento2-module",
        "license": "proprietary",
        "require": {
            "hyva-themes/magento2-default-theme": "^1.3"
        },
        "autoload": {
            "files": ["registration.php"],
            "psr-4": {
                f"{vendor}\\{module_name}\\": ""
            }
        }
    }
    with open(os.path.join(module_dir, "composer.json"), "w") as f:
        json.dump(composer, f, indent=4)
        f.write("\n")

    # README.md
    readme = f"""# {full_module}

Hyvä compatibility module for `{original_module}`.

## Purpose

{description}

## What This Module Does

- Replaces RequireJS/KnockoutJS frontend components with Alpine.js equivalents
- Provides Hyvä-compatible phtml templates
- Registers with Hyvä's module system via `hyva_config_generate_before` event

## Installation

1. Copy this module to `app/code/{vendor}/{module_name}/`
2. Run `bin/magento setup:upgrade`
3. Run `bin/magento cache:flush`

## TODO

- [ ] Identify all frontend templates in `{original_module}` that need Hyvä versions
- [ ] Create Alpine.js replacements for any KO/RequireJS components
- [ ] Add to Tailwind purge config if needed
- [ ] Test on all store views
"""
    with open(os.path.join(module_dir, "README.md"), "w") as f:
        f.write(readme)

    return module_dir


def generate_swatch_compat_module(output_path: str) -> str:
    """
    Generate a Hyvä compatibility module for MediaDivision_SwatchImages.
    This replaces the RequireJS swatch renderer with Alpine.js.
    """
    module_dir = generate_stub_module(
        output_path=output_path,
        vendor="MediaDivision",
        module_name="SwatchImagesHyva",
        description="Hyvä compatibility for MediaDivision_SwatchImages — replaces RequireJS custom swatch renderer with Alpine.js",
        original_module="MediaDivision_SwatchImages",
    )

    # Create the Hyvä-compatible swatch renderer template
    template_dir = os.path.join(module_dir, "view", "frontend", "templates", "product", "listing")
    os.makedirs(template_dir, exist_ok=True)

    renderer_phtml = """<?php
/**
 * MediaDivision SwatchImages — Hyvä-compatible swatch renderer
 * Replaces RequireJS custom-swatch-renderer with Alpine.js
 */
declare(strict_types=1);

use Hyva\\Theme\\Model\\ViewModelRegistry;
use Magento\\Framework\\Escaper;
use Magento\\Swatches\\Block\\Product\\Renderer\\Listing\\Configurable;

/** @var Configurable $block */
/** @var Escaper $escaper */
/** @var ViewModelRegistry $viewModels */

$product = $block->getProduct();
$productId = $product->getId();

$jsonConfig = $block->getJsonConfig();
$jsonSwatchConfig = $block->getJsonSwatchConfig();
$numberToShow = $block->getNumberSwatchesPerProduct();
?>

<div class="flex flex-wrap gap-1 mt-2"
     x-data="swatchRenderer_<?= (int) $productId ?>()">
    <template x-for="(swatch, index) in visibleSwatches" :key="index">
        <button type="button"
                class="w-6 h-6 border border-container hover:border-dark transition-colors"
                :class="{ 'ring-1 ring-dark ring-offset-1': swatch.active }"
                :style="swatch.type === 'color' ? 'background-color: ' + swatch.value : ''"
                :title="swatch.label"
                @click="selectSwatch(index)"
                x-show="index < showCount || showAll">
            <template x-if="swatch.type === 'image'">
                <img :src="swatch.value" :alt="swatch.label"
                     class="w-full h-full object-cover">
            </template>
            <template x-if="swatch.type === 'text'">
                <span class="text-xs" x-text="swatch.label"></span>
            </template>
        </button>
    </template>
    <button x-show="swatches.length > showCount && !showAll"
            @click="showAll = true"
            class="text-xs text-dark-lighter hover:text-dark"
            x-text="'+' + (swatches.length - showCount)">
    </button>
</div>

<script>
    function swatchRenderer_<?= (int) $productId ?>() {
        const jsonConfig = <?= /** @noEscape */ $jsonConfig ?>;
        const swatchConfig = <?= /** @noEscape */ $jsonSwatchConfig ?>;

        const swatches = [];
        const attributes = jsonConfig.attributes || {};

        // Build swatch list from first visual attribute (typically color)
        for (const attrId in attributes) {
            const attr = attributes[attrId];
            if (!attr.options) continue;

            for (const option of attr.options) {
                const optId = option.id;
                const swatchData = (swatchConfig[attrId] && swatchConfig[attrId][optId]) || {};

                let type = 'text';
                let value = option.label || '';

                if (swatchData.type === '1' || swatchData.type === 1) {
                    type = 'color';
                    value = swatchData.value || '#ccc';
                } else if (swatchData.type === '2' || swatchData.type === 2) {
                    type = 'image';
                    value = swatchData.value || '';
                }

                swatches.push({
                    id: optId,
                    attrId: attrId,
                    label: option.label || '',
                    type: type,
                    value: value,
                    active: false,
                    products: option.products || [],
                });
            }
            break; // Only first visual attribute
        }

        return {
            swatches: swatches,
            showCount: <?= (int) $numberToShow ?>,
            showAll: false,

            get visibleSwatches() {
                return this.swatches;
            },

            selectSwatch(index) {
                this.swatches.forEach((s, i) => s.active = i === index);
                const swatch = this.swatches[index];
                if (swatch && swatch.products.length) {
                    // Dispatch event for gallery/price update
                    this.$dispatch('swatch-change', {
                        productId: <?= (int) $productId ?>,
                        optionId: swatch.id,
                        attrId: swatch.attrId,
                        products: swatch.products,
                    });
                }
            }
        };
    }
</script>
"""
    with open(os.path.join(template_dir, "renderer.phtml"), "w") as f:
        f.write(renderer_phtml)

    # Layout XML to use our template
    layout_dir = os.path.join(module_dir, "view", "frontend", "layout")
    os.makedirs(layout_dir, exist_ok=True)

    catalog_category_xml = """<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Override swatch renderer to use Hyvä/Alpine.js version -->
        <referenceBlock name="category.product.type.details.renderers">
            <block class="Magento\\Swatches\\Block\\Product\\Renderer\\Listing\\Configurable"
                   name="mediadivision.swatchimages.listing.renderer"
                   template="MediaDivision_SwatchImagesHyva::product/listing/renderer.phtml"/>
        </referenceBlock>
    </body>
</page>
"""
    with open(os.path.join(layout_dir, "catalog_category_view.xml"), "w") as f:
        f.write(catalog_category_xml)

    return module_dir


def generate_mailchimp_compat_module(output_path: str) -> str:
    """
    Generate a Hyvä compatibility module for Ebizmarts_MailChimp.
    Replaces the RequireJS-dependent tracking/subscribe templates.
    """
    module_dir = generate_stub_module(
        output_path=output_path,
        vendor="Ebizmarts",
        module_name="MailChimpHyva",
        description="Hyvä compatibility for Ebizmarts_MailChimp — replaces RequireJS tracking with vanilla JS",
        original_module="Ebizmarts_MailChimp",
    )

    # Hyvä-compatible MailChimp JS template
    template_dir = os.path.join(module_dir, "view", "frontend", "templates")
    os.makedirs(template_dir, exist_ok=True)

    mailchimpjs_phtml = """<?php
/**
 * MailChimp JS — Hyvä-compatible version
 * Replaces RequireJS-loaded MailChimp tracking with inline script
 */
declare(strict_types=1);

use Magento\\Framework\\Escaper;

/** @var \\Ebizmarts\\MailChimp\\Block\\Mailchimpjs $block */
/** @var Escaper $escaper */

$mcJsUrl = $block->getJsUrl();
$mcStoreId = $block->getMCStoreId();
?>

<?php if ($mcJsUrl) : ?>
<script>
    (function() {
        'use strict';
        var mcJs = document.createElement('script');
        mcJs.src = '<?= $escaper->escapeJs($mcJsUrl) ?>';
        mcJs.async = true;
        document.head.appendChild(mcJs);
    })();
</script>
<?php endif; ?>
"""
    with open(os.path.join(template_dir, "mailchimpjs.phtml"), "w") as f:
        f.write(mailchimpjs_phtml)

    # Layout override
    layout_dir = os.path.join(module_dir, "view", "frontend", "layout")
    os.makedirs(layout_dir, exist_ok=True)

    default_xml = """<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Override MailChimp JS block to use Hyvä-compatible template -->
        <referenceBlock name="mailchimp_js">
            <action method="setTemplate">
                <argument name="template" xsi:type="string">Ebizmarts_MailChimpHyva::mailchimpjs.phtml</argument>
            </action>
        </referenceBlock>
    </body>
</page>
"""
    with open(os.path.join(layout_dir, "default.xml"), "w") as f:
        f.write(default_xml)

    return module_dir


def run_phase3(project_path: str, output_path: str):
    """
    Run the full Phase 3 compatibility analysis and generation.
    """
    print(f"\n{'='*60}")
    print(f"  Phase 3: Hyvä Compatibility Modules")
    print(f"{'='*60}\n")

    # 1. Analyze installed modules
    print("[1/4] Analyzing installed modules...")
    analysis = analyze_installed_modules(project_path)
    print(f"  Compatible (package available): {len(analysis['compatible'])}")
    print(f"  Built-in (no changes needed):   {len(analysis['built_in'])}")
    print(f"  Needs custom work:              {len(analysis['needs_custom'])}")

    # 2. Generate compatibility report
    print("\n[2/4] Generating compatibility report...")
    report_path = generate_compatibility_report(analysis, output_path)
    print(f"  Report: {report_path}")

    # 3. Generate stub modules for custom compat
    print("\n[3/4] Generating stub compatibility modules...")
    stubs_dir = os.path.join(output_path, "stubs")

    modules_created = []

    # SwatchImages Hyvä compat
    custom_modules = {e["module"] for e in analysis["needs_custom"]}
    if "MediaDivision_SwatchImages" in custom_modules:
        path = generate_swatch_compat_module(stubs_dir)
        modules_created.append(("MediaDivision_SwatchImagesHyva", path))
        print(f"  ✓ MediaDivision_SwatchImagesHyva (full Alpine.js swatch renderer)")

    # MailChimp Hyvä compat
    if "Ebizmarts_MailChimp" in custom_modules or any(
        e["module"] == "ebizmarts/mailchimp-lib" for e in analysis["compatible"]
    ):
        path = generate_mailchimp_compat_module(stubs_dir)
        modules_created.append(("Ebizmarts_MailChimpHyva", path))
        print(f"  ✓ Ebizmarts_MailChimpHyva (tracking JS replacement)")

    # Generate generic stubs for remaining custom modules
    for entry in analysis["needs_custom"]:
        module = entry["module"]
        if not entry["has_frontend"]:
            continue
        # Skip if we already created a dedicated module
        if any(module in m[0] for m in modules_created):
            continue

        vendor, name = module.split("_", 1)
        stub_path = generate_stub_module(
            output_path=stubs_dir,
            vendor=vendor,
            module_name=f"{name}Hyva",
            description=f"Hyvä compatibility for {module}",
            original_module=module,
        )
        modules_created.append((f"{vendor}_{name}Hyva", stub_path))
        print(f"  ✓ {vendor}_{name}Hyva (skeleton)")

    # 4. Summary
    print(f"\n[4/4] Summary...")
    print(f"  Composer packages to install: {len(generate_composer_requirements(analysis))}")
    print(f"  Stub modules generated:       {len(modules_created)}")

    packages = generate_composer_requirements(analysis)
    if packages:
        print(f"\n  Run these commands to install Hyvä compatibility packages:")
        for pkg in packages:
            print(f"    composer require {pkg}")

    return analysis, modules_created


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Phase 3: Hyvä Compatibility Module Generator")
    parser.add_argument("--project", required=True, help="Path to Magento project")
    parser.add_argument("--output", required=True, help="Output directory for compat modules")
    args = parser.parse_args()

    run_phase3(os.path.abspath(args.project), os.path.abspath(args.output))
