"""
Layout XML Converter — converts Luma layout XMLs to Hyvä-compatible equivalents.
Handles block references, moves, removes, and adds Hyvä-specific blocks.
"""
import os
import re
import xml.etree.ElementTree as ET
from pathlib import Path
from copy import deepcopy


# Layout XMLs that should be skipped entirely (Hyvä provides its own)
SKIP_LAYOUTS = {
    "checkout_index_index.xml",  # Hyvä checkout is completely different
    "checkout_cart_index.xml",   # Cart page handled by Hyvä
}

# Block class replacements for Hyvä
BLOCK_CLASS_MAP = {
    # Hyvä replaces Magento's swatches with its own
    "Magento\\Swatches\\Block\\Product\\Renderer\\Listing\\Configurable":
        "Hyva\\Theme\\Block\\Product\\Renderer\\Listing\\Configurable",
}

# Blocks that need removal in Hyvä (KO/RequireJS dependent)
BLOCKS_TO_REMOVE_IN_HYVA = {
    "catalog.compare.link",
    "mpstorelocator.store.pickup",
}


def convert_layout_xml(source_path: str, module_name: str) -> dict:
    """
    Analyze a Luma layout XML and produce a Hyvä-compatible version.

    Returns:
        dict with keys:
            - 'action': 'skip' | 'copy' | 'convert'
            - 'content': str (converted XML content if action is 'convert')
            - 'notes': list of str (conversion notes)
    """
    filename = os.path.basename(source_path)
    notes = []

    # Skip layouts that Hyvä replaces entirely
    if filename in SKIP_LAYOUTS:
        return {
            "action": "skip",
            "content": "",
            "notes": [f"Skipped: Hyvä provides its own {filename}"]
        }

    try:
        tree = ET.parse(source_path)
        root = tree.getroot()
    except ET.ParseError as e:
        return {
            "action": "skip",
            "content": "",
            "notes": [f"XML parse error: {e}"]
        }

    body = root.find("body")
    if body is None:
        return {
            "action": "copy",
            "content": ET.tostring(root, encoding="unicode", xml_declaration=True),
            "notes": ["No <body> element found, copied as-is"]
        }

    modified = False

    # Process referenceBlock elements
    for ref_block in body.findall(".//referenceBlock"):
        name = ref_block.get("name", "")

        # Check if block should be removed in Hyvä
        if name in BLOCKS_TO_REMOVE_IN_HYVA:
            ref_block.set("remove", "true")
            notes.append(f"Marked {name} for removal (not needed in Hyvä)")
            modified = True

    # Process block elements — update class references
    for block in body.iter("block"):
        block_class = block.get("class", "")
        if block_class in BLOCK_CLASS_MAP:
            new_class = BLOCK_CLASS_MAP[block_class]
            block.set("class", new_class)
            notes.append(f"Replaced block class: {block_class} → {new_class}")
            modified = True

        # Update template references for our converted templates
        template = block.get("template", "")
        if template:
            # Templates we've rewritten get the Hyvä versions
            pass  # Template overrides work via theme fallback

    # Process move elements — keep most as-is, they work in Hyvä too
    for move in body.findall("move"):
        element = move.get("element", "")
        notes.append(f"Preserved move: {element}")

    if not modified:
        return {
            "action": "copy",
            "content": "",
            "notes": ["No Hyvä-specific changes needed, will use original layout"]
        }

    # Generate output
    output = '<?xml version="1.0"?>\n'
    output += ET.tostring(root, encoding="unicode")
    # Clean up ET output formatting
    output = output.replace("ns0:", "").replace(":ns0", "").replace("xmlns:ns0=", "xmlns:xsi=")

    return {
        "action": "convert",
        "content": output,
        "notes": notes
    }


def generate_hyva_default_xml(brand_config: dict) -> str:
    """
    Generate the default.xml for the Hyvä child theme.
    This is the main layout that sets up the page structure.
    """
    return """<?xml version="1.0"?>
<page xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Remove blocks not compatible with Hyvä -->
        <referenceBlock name="catalog.compare.link" remove="true"/>
    </body>
</page>
"""


def generate_hyva_catalog_product_view_xml() -> str:
    """
    Generate catalog_product_view.xml for the Hyvä child theme.
    Minimal: Hyvä provides a complete product page layout by default.
    Only override if the source store has specific layout requirements.
    """
    return """<?xml version="1.0"?>
<page layout="1column"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Remove compare if not needed -->
        <referenceBlock name="view.addto.compare" remove="true"/>
    </body>
</page>
"""


def generate_hyva_catalog_category_view_xml() -> str:
    """
    Generate catalog_category_view.xml for the Hyvä child theme.
    Minimal: Hyvä provides a complete category page by default.
    """
    return """<?xml version="1.0"?>
<page layout="1column"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="urn:magento:framework:View/Layout/etc/page_configuration.xsd">
    <body>
        <!-- Category page uses Hyvä defaults -->
    </body>
</page>
"""


def process_all_layouts(source_theme_path: str, output_path: str) -> list:
    """
    Process all layout XML files from the source theme.
    Returns list of processing results.
    """
    results = []
    layout_dirs = []

    # Find all layout directories in the source theme
    for root_dir, dirs, files in os.walk(source_theme_path):
        if "layout" in dirs:
            layout_dirs.append(os.path.join(root_dir, "layout"))
        if "page_layout" in dirs:
            layout_dirs.append(os.path.join(root_dir, "page_layout"))

    for layout_dir in layout_dirs:
        for xml_file in sorted(os.listdir(layout_dir)):
            if not xml_file.endswith(".xml"):
                continue

            source_file = os.path.join(layout_dir, xml_file)
            rel_path = os.path.relpath(source_file, source_theme_path)

            # Determine module from path
            parts = rel_path.split(os.sep)
            module_name = parts[0] if parts else ""

            result = convert_layout_xml(source_file, module_name)
            result["source"] = rel_path
            result["filename"] = xml_file
            results.append(result)

    return results
