"""
Main scanner — orchestrates theme analysis, module check, and report generation.
Usage: python analyzer/scan.py --project projects/ftcshop --output output/ftcshop
"""
import argparse
import json
import os
import sys

# Add project root to path
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from analyzer.theme_scanner import scan_theme, analysis_to_dict
from analyzer.modules_checker import generate_compat_report, report_to_dict
from analyzer.report_generator import generate_migration_report


def find_theme_path(project_path: str) -> str:
    """Auto-detect the custom theme path in a Magento project."""
    design_path = os.path.join(project_path, "app", "design", "frontend")
    if not os.path.exists(design_path):
        raise FileNotFoundError(f"No app/design/frontend found at {design_path}")
    
    # Look for non-Magento themes
    for vendor in os.listdir(design_path):
        if vendor in ("Magento",):
            continue
        vendor_path = os.path.join(design_path, vendor)
        if os.path.isdir(vendor_path):
            for theme in os.listdir(vendor_path):
                theme_path = os.path.join(vendor_path, theme)
                if os.path.isdir(theme_path) and os.path.exists(os.path.join(theme_path, "theme.xml")):
                    return theme_path
    
    raise FileNotFoundError("No custom theme found in app/design/frontend/")


def main():
    parser = argparse.ArgumentParser(description="Magento Luma → Hyvä Migration Analyzer")
    parser.add_argument("--project", required=True, help="Path to Magento project")
    parser.add_argument("--output", required=True, help="Output directory for reports")
    parser.add_argument("--theme", help="Override theme path (auto-detected if omitted)")
    parser.add_argument("--name", help="Project name for report", default=None)
    args = parser.parse_args()

    project_path = os.path.abspath(args.project)
    output_path = os.path.abspath(args.output)
    project_name = args.name or os.path.basename(project_path)

    print(f"🔍 Analyzing project: {project_name}")
    print(f"   Project path: {project_path}")

    # 1. Find and scan theme
    theme_path = args.theme or find_theme_path(project_path)
    print(f"\n📁 Theme found: {theme_path}")
    print("   Scanning theme...")
    theme_analysis = scan_theme(theme_path)
    theme_dict = analysis_to_dict(theme_analysis)
    print(f"   ✅ {theme_analysis.total_files} files, "
          f"{len(theme_analysis.overrides)} template overrides, "
          f"{len(theme_analysis.js_files)} JS files, "
          f"{len(theme_analysis.less_files)} LESS/CSS files")

    # 2. Check module compatibility
    print("\n📦 Checking module compatibility...")
    module_report = generate_compat_report(
        app_code_path=os.path.join(project_path, "app", "code"),
        composer_json_path=os.path.join(project_path, "composer.json"),
        config_php_path=os.path.join(project_path, "app", "etc", "config.php"),
    )
    module_dict = report_to_dict(module_report)
    print(f"   ✅ {module_report.total_modules} modules total")
    print(f"   ✅ {len(module_report.compatible)} compatible with Hyvä")
    print(f"   ⚠️  {len(module_report.needs_work)} need custom work")
    print(f"   ❓ {len(module_report.unknown)} unknown status")

    # 3. Generate report
    print("\n📝 Generating migration report...")
    report_path = generate_migration_report(
        theme_analysis=theme_dict,
        module_report=module_dict,
        project_name=project_name,
        output_path=output_path,
    )
    print(f"   ✅ Report saved to: {report_path}")
    
    # Print summary
    json_path = os.path.join(output_path, "report.json")
    with open(json_path, "r") as f:
        report = json.load(f)
    
    effort = report.get("effort_estimate", {})
    total = effort.get("total", 0)
    print(f"\n{'='*60}")
    print(f"  MIGRATION ESTIMATE: {total} hours ({round(total/8, 1)} days)")
    print(f"  With AI assistance: ~{round(total/8 * 0.5, 1)} days")
    print(f"{'='*60}")


if __name__ == "__main__":
    main()
