"""
Hyvä Theme Scaffolder — generates a complete Hyvä child theme
from design tokens and Magento project analysis.
"""
import os
import json
import shutil
from pathlib import Path
from generator.style_extractor import DesignTokens


def generate_registration_php(vendor: str, theme_name: str) -> str:
    return f"""<?php
use Magento\\Framework\\Component\\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/{vendor}/{theme_name}',
    __DIR__
);
"""


def generate_theme_xml(title: str, parent: str = "Hyva/default") -> str:
    return f"""<?xml version="1.0"?>
<theme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="urn:magento:framework:Config/etc/theme.xsd">
    <title>{title}</title>
    <parent>{parent}</parent>
</theme>
"""


def generate_composer_json(vendor: str, theme_name: str) -> str:
    package = f"{vendor.lower()}/theme-frontend-{theme_name.lower()}"
    return json.dumps({
        "name": package,
        "description": f"Hyvä child theme for {theme_name}",
        "type": "magento2-theme",
        "license": "proprietary",
        "require": {
            "hyva-themes/magento2-default-theme": "^1.3"
        },
        "autoload": {
            "files": ["registration.php"]
        }
    }, indent=4) + "\n"


def generate_tailwind_config(tokens: DesignTokens, vendor: str = "MediaDivision", theme: str = "FTCShopHyva") -> str:
    """Generate tailwind.config.js from design tokens."""
    colors_js = "{\n"
    # Map FTC color names to Tailwind-friendly names
    color_mapping = {
        "brand-primary": ("primary", "Brand tan/caramel"),
        "brand-secondary": ("secondary", "Coral/orange-red"),
        "green": ("accent", "Sage green"),
        "black": ("dark", "Near-black text"),
        "light-black": ("dark-light", "Dark gray"),
        "lighter-black": ("dark-lighter", "Medium gray"),
        "lightest-black": ("dark-lightest", "Light gray text"),
        "gray": ("gray-DEFAULT", "Medium gray"),
        "light-gray": ("gray-light", "Light gray borders"),
        "lighter-gray": ("gray-lighter", "Very light gray"),
        "lightest-gray": ("gray-lightest", "Off-white"),
    }

    color_groups = {}
    for less_name, value in tokens.colors.items():
        if less_name in color_mapping:
            tw_name, _comment = color_mapping[less_name]
            parts = tw_name.split("-", 1)
            if len(parts) == 2 and parts[0] in ("gray", "dark"):
                group = parts[0]
                shade = parts[1]
                if group not in color_groups or not isinstance(color_groups[group], dict):
                    # Promote existing string to DEFAULT shade in a dict
                    existing = color_groups.get(group)
                    color_groups[group] = {}
                    if isinstance(existing, str):
                        color_groups[group]["DEFAULT"] = existing
                color_groups[group][shade] = value
            else:
                # If this key already exists as a dict, add as DEFAULT
                if tw_name in color_groups and isinstance(color_groups[tw_name], dict):
                    color_groups[tw_name]["DEFAULT"] = value
                else:
                    color_groups[tw_name] = value

    for name, val in color_groups.items():
        if isinstance(val, dict):
            colors_js += f"        '{name}': {{\n"
            for shade, sv in val.items():
                colors_js += f"            '{shade}': '{sv}',\n"
            colors_js += f"        }},\n"
        else:
            colors_js += f"        '{name}': '{val}',\n"

    colors_js += "      }"

    font_family_serif = tokens.fonts.get("serif", '"Korpus-B", serif')
    font_family_sans = tokens.fonts.get("sans", '"KorpusGrotesk-B", sans-serif')

    breakpoints_js = "{\n"
    for name, val in tokens.breakpoints.items():
        breakpoints_js += f"        '{name}': '{val}',\n"
    breakpoints_js += "      }"

    font_sizes_js = "{\n"
    for name, val in tokens.font_sizes.items():
        breakpoints_js_line = f"['{val}', {{ lineHeight: '1.4' }}]"
        font_sizes_js += f"        '{name}': {breakpoints_js_line},\n"
    font_sizes_js += "      }"

    return f"""const {{ spacing }} = require('tailwindcss/defaultTheme');

const hyvaModules = require('@hyva-themes/hyva-modules');

module.exports = hyvaModules.mergeTailwindConfig({{
  theme: {{
    extend: {{
      screens: {breakpoints_js},
      colors: {colors_js},
      fontFamily: {{
        'serif': [{font_family_serif}],
        'sans': [{font_family_sans}],
      }},
      fontSize: {font_sizes_js},
      maxWidth: {{
        'content': '{tokens.max_width}',
      }},
      borderRadius: {{
        'none': '0',
        'full': '50%',
      }},
      boxShadow: {{
        'sm': '0 2px 14px 0 rgba(80, 66, 55, 0.3)',
        'md': '0 2px 14px 0 rgba(80, 66, 55, 0.4)',
      }},
      transitionDuration: {{
        'fast': '150ms',
        'DEFAULT': '300ms',
        'slow': '450ms',
        'slower': '600ms',
      }},
      zIndex: {{
        'behind': '-1',
        'label': '2',
        'modal': '10',
        'toolbar': '20',
        'popup': '30',
        'minicart': '50000',
      }},
    }},
  }},
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
  // Scan phtml and layout xml files for Tailwind classes
  content: [
    // this theme
    '../../../../../../app/design/frontend/{vendor}/{theme}/**/*.phtml',
    '../../../../../../app/design/frontend/{vendor}/{theme}/web/tailwind/*.css',
    // parent theme
    '../../../../../../vendor/hyva-themes/magento2-default-theme/**/*.phtml',
    '../../../../../../vendor/hyva-themes/magento2-default-theme/**/*.xml',
    // compatibility modules (custom Hyvä stubs)
    '../../../../../../app/code/**/*Hyva*/**/*.phtml',
    // hyva modules
    ...hyvaModules.getModuleJitContent(),
  ],
}});
"""


def generate_tailwind_source_css() -> str:
    return """@tailwind base;
@tailwind components;
@tailwind utilities;

/* FTC Cashmere — brand layer on top of Hyvä */
@layer base {
  body {
    @apply font-sans text-base text-dark bg-[#f9f7f8];
  }

  h1, h2, h3, h4, h5, h6 {
    @apply font-serif uppercase;
  }

  a {
    @apply text-dark transition-colors duration-DEFAULT;
  }

  a:hover {
    @apply text-primary;
  }
}

@layer components {
  .btn-primary {
    @apply inline-block px-8 py-3 bg-dark text-white uppercase text-sm
           tracking-wider transition-opacity duration-DEFAULT
           hover:opacity-90;
  }

  .btn-secondary {
    @apply inline-block px-8 py-3 border border-dark text-dark uppercase text-sm
           tracking-wider transition-all duration-DEFAULT
           hover:bg-dark hover:text-white;
  }

  .page-title {
    @apply font-serif text-5xl md:text-7xl lg:text-9xl uppercase text-center;
  }

  .section-title {
    @apply font-serif text-3xl md:text-4xl uppercase;
  }

  .product-name {
    @apply font-sans text-lg uppercase;
  }

  .product-price {
    @apply font-sans text-3xl;
  }

  .product-price--old {
    @apply line-through text-dark-lightest text-sm;
  }

  .product-price--special {
    @apply text-secondary;
  }

  .container-ftc {
    @apply w-full max-w-content mx-auto px-4 md:px-6;
  }
}
"""


def generate_package_json(theme_name: str) -> str:
    return json.dumps({
        "name": theme_name.lower(),
        "version": "1.0.0",
        "private": True,
        "scripts": {
            "build": "npx tailwindcss -i web/tailwind/tailwind-source.css -o web/css/styles.css --minify",
            "watch": "npx tailwindcss -i web/tailwind/tailwind-source.css -o web/css/styles.css --watch"
        },
        "devDependencies": {
            "tailwindcss": "^3.4",
            "@tailwindcss/forms": "^0.5",
            "@tailwindcss/typography": "^0.5",
            "@hyva-themes/hyva-modules": "^1.1"
        }
    }, indent=4) + "\n"


def generate_default_xml() -> str:
    """Generate Magento_Theme/layout/default.xml with Hyvä head assets."""
    return """<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <css src="css/styles.css"/>
    </head>
</page>
"""


def generate_default_head_xml() -> str:
    """Generate default_head_blocks.xml for custom fonts."""
    return """<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <head>
        <!-- FTC Cashmere custom fonts -->
        <css src="css/fonts.css"/>
    </head>
</page>
"""


def generate_fonts_css(source_fonts_dir: str = "") -> str:
    """Generate @font-face declarations for custom fonts.

    If source_fonts_dir is provided, scans it to discover actual font files
    and generates correct src references. Otherwise uses sensible defaults.
    """
    # Default font definitions — if source dir exists, we'll auto-detect
    font_defs = [
        {
            "family": "Korpus-B",
            "basename": "korpus-b-webfont",
            "weight": "normal",
            "style": "normal",
        },
        {
            "family": "KorpusGrotesk-B",
            "basename": "Korpus-Grotesk-B-webfont",
            "weight": "normal",
            "style": "normal",
        },
        {
            "family": "Sartex",
            "basename": "sartex",
            "weight": "normal",
            "style": "normal",
        },
    ]

    # Map extensions to CSS format names
    format_map = {
        ".woff2": "woff2",
        ".woff": "woff",
        ".ttf": "truetype",
        ".otf": "opentype",
        ".eot": "embedded-opentype",
        ".svg": "svg",
    }

    # Preferred order for modern browsers
    format_order = [".woff2", ".woff", ".ttf", ".otf", ".eot", ".svg"]

    css = "/* FTC Cashmere Brand Fonts */\n"

    for fdef in font_defs:
        basename = fdef["basename"]
        available_exts = []

        if source_fonts_dir and os.path.isdir(source_fonts_dir):
            # Scan source directory for actual files matching this basename
            for ext in format_order:
                candidate = os.path.join(source_fonts_dir, basename + ext)
                if os.path.isfile(candidate):
                    available_exts.append(ext)
        else:
            # Default: assume woff2 + woff are available
            available_exts = [".woff2", ".woff"]

        if not available_exts:
            continue

        src_parts = []
        for ext in available_exts:
            fmt = format_map[ext]
            src_parts.append(f"url('../fonts/{basename}{ext}') format('{fmt}')")

        src_str = ",\n         ".join(src_parts)

        css += f"""
@font-face {{
    font-family: '{fdef["family"]}';
    src: {src_str};
    font-weight: {fdef["weight"]};
    font-style: {fdef["style"]};
    font-display: swap;
}}
"""

    return css


def scaffold_hyva_theme(
    output_path: str,
    vendor: str,
    theme_name: str,
    title: str,
    tokens: DesignTokens,
    source_theme_path: str = "",
):
    """Create the complete Hyvä child theme directory structure."""
    base = os.path.join(output_path, vendor, theme_name)
    os.makedirs(base, exist_ok=True)

    # Detect source fonts directory for accurate @font-face generation
    source_fonts_dir = ""
    if source_theme_path:
        candidate = os.path.join(source_theme_path, "web", "fonts")
        if os.path.isdir(candidate):
            source_fonts_dir = candidate

    # Core theme files
    files = {
        "registration.php": generate_registration_php(vendor, theme_name),
        "theme.xml": generate_theme_xml(title),
        "composer.json": generate_composer_json(vendor, theme_name),
        "package.json": generate_package_json(theme_name),
        "web/tailwind/tailwind.config.js": generate_tailwind_config(tokens, vendor, theme_name),
        "web/tailwind/tailwind-source.css": generate_tailwind_source_css(),
        "web/css/fonts.css": generate_fonts_css(source_fonts_dir),
        "Magento_Theme/layout/default.xml": generate_default_xml(),
        "Magento_Theme/layout/default_head_blocks.xml": generate_default_head_xml(),
    }

    # Create directories for fonts (placeholder)
    os.makedirs(os.path.join(base, "web", "fonts"), exist_ok=True)
    os.makedirs(os.path.join(base, "web", "images", "icons"), exist_ok=True)
    os.makedirs(os.path.join(base, "web", "images", "social"), exist_ok=True)
    os.makedirs(os.path.join(base, "web", "css"), exist_ok=True)
    os.makedirs(os.path.join(base, "i18n"), exist_ok=True)

    for rel_path, content in files.items():
        full_path = os.path.join(base, rel_path)
        os.makedirs(os.path.dirname(full_path), exist_ok=True)
        with open(full_path, "w") as f:
            f.write(content)

    return base
