"""
Theme Scanner — analyzes a Magento Luma theme and catalogs all customizations.
Identifies template overrides, layout XMLs, JS, LESS/CSS, i18n, and web assets.
"""
import os
import re
import json
from pathlib import Path
from dataclasses import dataclass, field, asdict
from typing import Optional


@dataclass
class TemplateOverride:
    module: str              # e.g. "Magento_Catalog"
    file_path: str           # relative path within the module override
    file_type: str           # "phtml", "xml", "js", "html", "less", "css"
    full_path: str           # absolute path on disk
    complexity: str = "low"  # "low", "medium", "high"
    has_knockout: bool = False
    has_requirejs: bool = False
    has_jquery: bool = False
    has_less_variables: bool = False
    line_count: int = 0
    notes: list = field(default_factory=list)


@dataclass
class ThemeAnalysis:
    theme_name: str
    parent_theme: str
    theme_path: str
    overrides: list = field(default_factory=list)
    less_files: list = field(default_factory=list)
    js_files: list = field(default_factory=list)
    i18n_locales: list = field(default_factory=list)
    total_files: int = 0
    summary: dict = field(default_factory=dict)


def parse_theme_xml(theme_path: str) -> tuple[str, str]:
    """Extract theme name and parent from theme.xml"""
    theme_xml = os.path.join(theme_path, "theme.xml")
    name = "Unknown"
    parent = "Unknown"
    if os.path.exists(theme_xml):
        with open(theme_xml, "r") as f:
            content = f.read()
        m = re.search(r"<title>(.*?)</title>", content)
        if m:
            name = m.group(1)
        m = re.search(r"<parent>(.*?)</parent>", content)
        if m:
            parent = m.group(1)
    return name, parent


def analyze_file_complexity(file_path: str) -> TemplateOverride:
    """Analyze a single file for Luma-specific patterns that need migration."""
    ext = Path(file_path).suffix.lstrip(".")
    rel_parts = Path(file_path).parts
    
    # Find the module name from path
    # Pattern: .../ModuleName/templates/... or .../ModuleName/layout/... etc.
    module = "theme-level"
    for i, part in enumerate(rel_parts):
        if "_" in part and part[0].isupper():
            # Looks like Vendor_Module
            module = part
            break

    override = TemplateOverride(
        module=module,
        file_path=str(Path(*rel_parts[rel_parts.index(module)+1:])) if module != "theme-level" else str(Path(file_path).name),
        file_type=ext,
        full_path=file_path,
    )

    try:
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
    except (IOError, OSError):
        override.notes.append("Could not read file")
        return override

    override.line_count = content.count("\n") + 1

    # Detect Luma/KnockoutJS patterns
    ko_patterns = [
        r'data-bind\s*=',
        r'ko\s+(?:foreach|if|text|visible|css|attr|click)',
        r'<!-- ko ',
        r'ko\.observable',
        r'ko\.computed',
        r'getTemplate\(\)',
    ]
    for pattern in ko_patterns:
        if re.search(pattern, content, re.IGNORECASE):
            override.has_knockout = True
            break

    # RequireJS patterns
    if re.search(r'require\s*\[|define\s*\[|data-mage-init|x-magento-init', content, re.IGNORECASE):
        override.has_requirejs = True

    # jQuery patterns
    if re.search(r'\$\(|jQuery\(|\.widget\(|\.mage\(', content):
        override.has_jquery = True

    # LESS variable patterns
    if ext in ("less", "css"):
        override.has_less_variables = bool(re.search(r'@[a-zA-Z]', content))

    # Complexity scoring
    score = 0
    score += override.line_count // 50  # points per 50 lines
    if override.has_knockout:
        score += 3
        override.notes.append("KnockoutJS bindings — need Alpine.js rewrite")
    if override.has_requirejs:
        score += 2
        override.notes.append("RequireJS/x-magento-init — need Hyvä JS approach")
    if override.has_jquery:
        score += 1
        override.notes.append("jQuery usage detected")
    if override.has_less_variables:
        score += 2
        override.notes.append("LESS variables — need Tailwind conversion")

    # Additional phtml-specific patterns
    if ext == "phtml":
        if re.search(r'getChildHtml|getLayout|getBlockHtml', content):
            override.notes.append("Block rendering calls — check Hyvä layout")
        if re.search(r'escapeHtml|escapeUrl|escapeJs', content):
            pass  # These are fine in Hyvä too
        if re.search(r'jsLayout|getJsLayout', content):
            score += 3
            override.notes.append("jsLayout (checkout) — complex Hyvä migration")

    if ext == "xml":
        if re.search(r'<referenceContainer|<referenceBlock', content):
            override.notes.append("Layout XML references — need Hyvä layout equivalents")
        if re.search(r'uiComponent|jsLayout', content):
            score += 3
            override.notes.append("UI Component/jsLayout — complex migration")

    if ext == "html":
        # KnockoutJS templates
        if override.has_knockout:
            score += 2
            override.notes.append("KO template (.html) — likely needs full rewrite or removal")

    if score <= 2:
        override.complexity = "low"
    elif score <= 5:
        override.complexity = "medium"
    else:
        override.complexity = "high"

    return override


def scan_theme(theme_path: str) -> ThemeAnalysis:
    """Scan a complete Magento theme directory and produce analysis."""
    theme_name, parent_theme = parse_theme_xml(theme_path)

    analysis = ThemeAnalysis(
        theme_name=theme_name,
        parent_theme=parent_theme,
        theme_path=theme_path,
    )

    # Collect all files
    all_files = []
    for root, dirs, files in os.walk(theme_path):
        for filename in files:
            full = os.path.join(root, filename)
            all_files.append(full)

    analysis.total_files = len(all_files)

    # Categorize and analyze
    for fpath in sorted(all_files):
        ext = Path(fpath).suffix.lstrip(".")
        rel = os.path.relpath(fpath, theme_path)

        if ext in ("phtml", "xml", "html"):
            override = analyze_file_complexity(fpath)
            analysis.overrides.append(override)
        elif ext in ("less", "css"):
            override = analyze_file_complexity(fpath)
            analysis.less_files.append(override)
        elif ext == "js":
            override = analyze_file_complexity(fpath)
            analysis.js_files.append(override)
        elif ext == "csv" and "i18n" in rel:
            locale = Path(fpath).stem
            analysis.i18n_locales.append(locale)

    # Summary
    all_overrides = analysis.overrides + analysis.less_files + analysis.js_files
    modules_affected = set(o.module for o in all_overrides)
    
    complexity_counts = {"low": 0, "medium": 0, "high": 0}
    for o in all_overrides:
        complexity_counts[o.complexity] += 1

    analysis.summary = {
        "total_files": analysis.total_files,
        "template_overrides": len(analysis.overrides),
        "less_css_files": len(analysis.less_files),
        "js_files": len(analysis.js_files),
        "i18n_locales": len(analysis.i18n_locales),
        "modules_affected": sorted(modules_affected),
        "modules_count": len(modules_affected),
        "complexity": complexity_counts,
        "knockout_files": sum(1 for o in all_overrides if o.has_knockout),
        "requirejs_files": sum(1 for o in all_overrides if o.has_requirejs),
        "jquery_files": sum(1 for o in all_overrides if o.has_jquery),
    }

    return analysis


def analysis_to_dict(analysis: ThemeAnalysis) -> dict:
    """Convert ThemeAnalysis to a serializable dict."""
    d = {
        "theme_name": analysis.theme_name,
        "parent_theme": analysis.parent_theme,
        "theme_path": analysis.theme_path,
        "total_files": analysis.total_files,
        "summary": analysis.summary,
        "i18n_locales": analysis.i18n_locales,
        "overrides": [asdict(o) for o in analysis.overrides],
        "less_files": [asdict(o) for o in analysis.less_files],
        "js_files": [asdict(o) for o in analysis.js_files],
    }
    return d


if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python theme_scanner.py <theme_path>")
        sys.exit(1)
    
    theme_path = sys.argv[1]
    analysis = scan_theme(theme_path)
    print(json.dumps(analysis_to_dict(analysis), indent=2))
