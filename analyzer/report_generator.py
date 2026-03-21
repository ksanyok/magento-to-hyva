"""
Migration Report Generator — combines theme analysis and module compatibility
into a comprehensive migration report with effort estimates.
"""
import json
import os
from datetime import datetime


def generate_migration_report(
    theme_analysis: dict,
    module_report: dict,
    project_name: str,
    output_path: str,
) -> str:
    """Generate a full migration report in Markdown + JSON."""
    
    # Calculate effort estimates (in hours)
    effort = calculate_effort(theme_analysis, module_report)
    
    report = {
        "project": project_name,
        "generated_at": datetime.now().isoformat(),
        "magento_version": "2.4.8-p3",
        "theme_analysis": theme_analysis,
        "module_compatibility": module_report,
        "effort_estimate": effort,
    }
    
    # Save JSON report
    os.makedirs(output_path, exist_ok=True)
    json_path = os.path.join(output_path, "report.json")
    with open(json_path, "w") as f:
        json.dump(report, f, indent=2)
    
    # Generate Markdown report
    md = generate_markdown(report)
    md_path = os.path.join(output_path, "MIGRATION_REPORT.md")
    with open(md_path, "w") as f:
        f.write(md)
    
    return md_path


def calculate_effort(theme_analysis: dict, module_report: dict) -> dict:
    """Estimate migration effort in hours."""
    hours = {
        "base_hyva_setup": 4,
        "tailwind_config": 8,
        "template_conversion": 0,
        "layout_xml_conversion": 0,
        "js_rewrite": 0,
        "less_to_tailwind": 0,
        "module_compat": 0,
        "i18n_migration": 2,
        "testing_qa": 0,
    }
    
    # Template conversion
    for override in theme_analysis.get("overrides", []):
        c = override.get("complexity", "low")
        if c == "low":
            hours["template_conversion"] += 0.5
        elif c == "medium":
            hours["template_conversion"] += 2
        else:
            hours["template_conversion"] += 5
    
    # JS rewrite
    for js in theme_analysis.get("js_files", []):
        c = js.get("complexity", "low")
        if c == "low":
            hours["js_rewrite"] += 1
        elif c == "medium":
            hours["js_rewrite"] += 3
        else:
            hours["js_rewrite"] += 6
    
    # LESS to Tailwind (most styles will be replaced by Tailwind utilities)
    for less in theme_analysis.get("less_files", []):
        lines = less.get("line_count", 0)
        hours["less_to_tailwind"] += max(0.5, lines / 200)
    
    # Layout XML
    layout_overrides = [o for o in theme_analysis.get("overrides", []) if o.get("file_type") == "xml"]
    hours["layout_xml_conversion"] = len(layout_overrides) * 1.5
    
    # Module compat work
    needs_work = module_report.get("needs_work", [])
    for mod in needs_work:
        front_files = len(mod.get("frontend_files", []))
        if front_files > 5:
            hours["module_compat"] += 8
        elif front_files > 0:
            hours["module_compat"] += 4
        else:
            hours["module_compat"] += 2
    
    # Testing: 30% of total dev effort
    dev_total = sum(hours.values())
    hours["testing_qa"] = round(dev_total * 0.3, 1)
    
    hours["total"] = round(sum(hours.values()), 1)
    
    return hours


def generate_markdown(report: dict) -> str:
    """Generate a readable Markdown migration report."""
    theme = report["theme_analysis"]
    modules = report["module_compatibility"]
    effort = report["effort_estimate"]
    summary = theme.get("summary", {})
    
    md = f"""# Hyvä Migration Report: {report['project']}

Generated: {report['generated_at']}  
Magento Version: {report['magento_version']}

---

## Current Theme

- **Theme**: {theme['theme_name']}
- **Parent**: {theme['parent_theme']}
- **Total files**: {theme['total_files']}

## Theme Customization Summary

| Category | Count |
|----------|-------|
| Template overrides (.phtml, .xml, .html) | {summary.get('template_overrides', 0)} |
| LESS/CSS files | {summary.get('less_css_files', 0)} |
| JavaScript files | {summary.get('js_files', 0)} |
| i18n locales | {summary.get('i18n_locales', 0)} |
| Modules affected | {summary.get('modules_count', 0)} |

### Complexity Breakdown

| Complexity | Files |
|-----------|-------|
| Low (simple conversion) | {summary.get('complexity', {}).get('low', 0)} |
| Medium (moderate rewrite) | {summary.get('complexity', {}).get('medium', 0)} |
| High (full rewrite needed) | {summary.get('complexity', {}).get('high', 0)} |

### Luma-Specific Patterns Detected

| Pattern | Files affected |
|---------|---------------|
| KnockoutJS bindings | {summary.get('knockout_files', 0)} |
| RequireJS/x-magento-init | {summary.get('requirejs_files', 0)} |
| jQuery usage | {summary.get('jquery_files', 0)} |

---

## Template Overrides (Detail)

"""
    # Group overrides by module
    overrides_by_module = {}
    for o in theme.get("overrides", []):
        mod = o.get("module", "unknown")
        overrides_by_module.setdefault(mod, []).append(o)
    
    for mod in sorted(overrides_by_module.keys()):
        items = overrides_by_module[mod]
        md += f"\n### {mod}\n\n"
        md += "| File | Type | Complexity | Lines | Notes |\n"
        md += "|------|------|-----------|-------|-------|\n"
        for o in items:
            notes = "; ".join(o.get("notes", []))
            md += f"| {o['file_path']} | {o['file_type']} | {o['complexity']} | {o['line_count']} | {notes} |\n"

    md += f"""
---

## Module Compatibility

| Status | Count |
|--------|-------|
| Compatible (official/community/built-in) | {modules['summary'].get('compatible_count', 0)} |
| Needs Hyvä compat work | {modules['summary'].get('needs_work_count', 0)} |
| Unknown | {modules['summary'].get('unknown_count', 0)} |

### Modules Needing Custom Work

"""
    for mod in modules.get("needs_work", []):
        frontend = len(mod.get("frontend_files", []))
        md += f"- **{mod['name']}** — {mod.get('hyva_notes', 'Needs analysis')} (frontend files: {frontend})\n"

    md += f"""
### Compatible Modules (have Hyvä support)

"""
    for mod in modules.get("compatible", []):
        pkg = mod.get("hyva_package", "")
        pkg_str = f" → `{pkg}`" if pkg else ""
        md += f"- **{mod['name']}** [{mod['hyva_status']}]{pkg_str} — {mod.get('hyva_notes', '')}\n"

    md += f"""
---

## Effort Estimate

| Task | Hours |
|------|-------|
| Base Hyvä setup & config | {effort.get('base_hyva_setup', 0)} |
| Tailwind CSS configuration | {effort.get('tailwind_config', 0)} |
| Template conversion (phtml) | {effort.get('template_conversion', 0)} |
| Layout XML conversion | {effort.get('layout_xml_conversion', 0)} |
| JavaScript rewrite (KO→Alpine) | {effort.get('js_rewrite', 0)} |
| LESS → Tailwind conversion | {effort.get('less_to_tailwind', 0)} |
| Module compatibility | {effort.get('module_compat', 0)} |
| i18n migration | {effort.get('i18n_migration', 0)} |
| Testing & QA | {effort.get('testing_qa', 0)} |
| **TOTAL** | **{effort.get('total', 0)}** |

> Estimated working days: **{round(effort.get('total', 0) / 8, 1)}** (at 8h/day)  
> With AI assistance (estimated 40-60% speedup): **{round(effort.get('total', 0) / 8 * 0.5, 1)}** days

---

## i18n Locales

{', '.join(theme.get('i18n_locales', []))}

---

## Recommended Migration Order

1. Install Hyvä theme base + Tailwind setup
2. Create child theme `MediaDivision/FTCShopHyva`
3. Migrate layout XML files (low complexity first)
4. Convert phtml templates (catalog → checkout → customer → CMS)
5. Rewrite JavaScript (KnockoutJS → Alpine.js)
6. Convert LESS styles → Tailwind utility classes
7. Install/create module compatibility packages
8. Migrate i18n translations
9. Full QA testing across all store views
"""
    
    return md
