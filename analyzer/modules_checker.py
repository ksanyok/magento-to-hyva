"""
Module Compatibility Checker — analyzes all installed Magento modules
and checks their Hyvä compatibility status.
"""
import os
import re
import json
import sys
from pathlib import Path
from dataclasses import dataclass, field, asdict
from typing import Optional

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
from config.hyva_known_modules import HYVA_COMPAT_MODULES, MAGENTO_CORE_HYVA_MAPPING, COMPOSER_PACKAGE_COMPAT


@dataclass
class ModuleInfo:
    name: str                  # e.g. "Amasty_Shopby"
    vendor: str                # e.g. "Amasty"
    source: str                # "app_code" or "composer"
    has_frontend: bool = False # Has frontend templates/layout/web
    has_adminhtml: bool = False
    hyva_status: str = "unknown"  # "official", "community", "none", "built-in", "unknown"
    hyva_package: str = ""
    hyva_notes: str = ""
    frontend_files: list = field(default_factory=list)
    complexity: str = "low"


@dataclass
class ModuleCompatReport:
    total_modules: int = 0
    modules_with_frontend: int = 0
    compatible: list = field(default_factory=list)  # official/community/built-in
    needs_work: list = field(default_factory=list)  # none status
    unknown: list = field(default_factory=list)      # not in DB
    summary: dict = field(default_factory=dict)


def scan_app_code_modules(app_code_path: str) -> list[ModuleInfo]:
    """Scan app/code/ for custom modules and check frontend presence."""
    modules = []
    if not os.path.exists(app_code_path):
        return modules

    for vendor in sorted(os.listdir(app_code_path)):
        vendor_path = os.path.join(app_code_path, vendor)
        if not os.path.isdir(vendor_path):
            continue
        for module_name in sorted(os.listdir(vendor_path)):
            module_path = os.path.join(vendor_path, module_name)
            if not os.path.isdir(module_path):
                continue
            
            full_name = f"{vendor}_{module_name}"
            info = ModuleInfo(
                name=full_name,
                vendor=vendor,
                source="app_code",
            )

            # Check for frontend views
            frontend_path = os.path.join(module_path, "view", "frontend")
            if os.path.exists(frontend_path):
                info.has_frontend = True
                for root, dirs, files in os.walk(frontend_path):
                    for f in files:
                        rel = os.path.relpath(os.path.join(root, f), frontend_path)
                        info.frontend_files.append(rel)

            # Check for adminhtml views
            adminhtml_path = os.path.join(module_path, "view", "adminhtml")
            if os.path.exists(adminhtml_path):
                info.has_adminhtml = True

            # Look up Hyvä compatibility
            if full_name in HYVA_COMPAT_MODULES:
                compat = HYVA_COMPAT_MODULES[full_name]
                info.hyva_status = compat["status"]
                info.hyva_package = compat.get("package", "")
                info.hyva_notes = compat.get("notes", "")
            elif not info.has_frontend:
                info.hyva_status = "built-in"
                info.hyva_notes = "No frontend views — backend only"
            else:
                info.hyva_status = "unknown"

            # Estimate complexity
            if info.has_frontend and len(info.frontend_files) > 10:
                info.complexity = "high"
            elif info.has_frontend:
                info.complexity = "medium"
            else:
                info.complexity = "low"

            modules.append(info)

    return modules


def scan_composer_modules(composer_json_path: str, config_php_path: str) -> list[ModuleInfo]:
    """Scan composer.json require + config.php for third-party modules."""
    modules = []
    
    # Parse composer.json for third-party packages
    if os.path.exists(composer_json_path):
        with open(composer_json_path, "r") as f:
            data = json.load(f)
        
        require = data.get("require", {})
        for pkg, version in require.items():
            if pkg.startswith("magento/") or pkg in ("php", "ext-*"):
                continue
            if pkg in ("cweagans/composer-patches",):
                continue
            
            # Convert package name to likely module name
            vendor = pkg.split("/")[0]
            info = ModuleInfo(
                name=pkg,
                vendor=vendor,
                source="composer",
            )

            # Try direct composer package lookup first
            matched = False
            if pkg in COMPOSER_PACKAGE_COMPAT:
                compat = COMPOSER_PACKAGE_COMPAT[pkg]
                info.hyva_status = compat["status"]
                info.hyva_package = compat.get("package", "")
                info.hyva_notes = compat.get("notes", "")
                matched = True
            
            # Fallback: try to match to known Hyvä compat via module name patterns
            if not matched:
                for mod_name, compat in HYVA_COMPAT_MODULES.items():
                    # Build expected composer package names from module name
                    # e.g. Amasty_Shopby -> amasty/shopby, amasty/module-shopby
                    parts = mod_name.split("_")
                    if len(parts) == 2:
                        mod_vendor = parts[0].lower()
                        mod_module = parts[1].lower()
                        # Match patterns: vendor/module, vendor/module-name
                        pkg_lower = pkg.lower()
                        pkg_parts = pkg_lower.split("/")
                        if len(pkg_parts) == 2 and pkg_parts[0] == mod_vendor:
                            pkg_name = pkg_parts[1].replace("module-", "").replace("-", "")
                            if pkg_name == mod_module.lower().replace("-", ""):
                                info.hyva_status = compat["status"]
                                info.hyva_package = compat.get("package", "")
                                info.hyva_notes = compat.get("notes", "")
                                matched = True
                                break
            
            if not matched:
                info.hyva_status = "unknown"

            modules.append(info)

    return modules


def check_config_modules(config_php_path: str) -> dict[str, bool]:
    """Parse config.php for enabled/disabled module status."""
    status = {}
    if not os.path.exists(config_php_path):
        return status
    
    with open(config_php_path, "r") as f:
        content = f.read()

    # Parse PHP array: 'Module_Name' => 1 or 0
    for m in re.finditer(r"'([A-Za-z0-9]+_[A-Za-z0-9]+)'\s*=>\s*(\d)", content):
        status[m.group(1)] = m.group(2) == "1"

    return status


def generate_compat_report(
    app_code_path: str,
    composer_json_path: str,
    config_php_path: str,
) -> ModuleCompatReport:
    """Generate a full compatibility report."""
    report = ModuleCompatReport()

    # Get module enable/disable status
    module_status = check_config_modules(config_php_path)

    # Scan app/code modules
    app_modules = scan_app_code_modules(app_code_path)
    
    # Scan composer modules
    composer_modules = scan_composer_modules(composer_json_path, config_php_path)

    all_modules = app_modules + composer_modules
    report.total_modules = len(all_modules)
    report.modules_with_frontend = sum(1 for m in all_modules if m.has_frontend)

    for mod in all_modules:
        # Check if module is enabled
        if mod.name in module_status and not module_status[mod.name]:
            continue  # Skip disabled modules

        if mod.hyva_status in ("official", "community", "built-in"):
            report.compatible.append(mod)
        elif mod.hyva_status == "none":
            report.needs_work.append(mod)
        else:
            report.unknown.append(mod)

    report.summary = {
        "total_modules": report.total_modules,
        "modules_with_frontend": report.modules_with_frontend,
        "compatible_count": len(report.compatible),
        "needs_work_count": len(report.needs_work),
        "unknown_count": len(report.unknown),
    }

    return report


def report_to_dict(report: ModuleCompatReport) -> dict:
    return {
        "summary": report.summary,
        "compatible": [asdict(m) for m in report.compatible],
        "needs_work": [asdict(m) for m in report.needs_work],
        "unknown": [asdict(m) for m in report.unknown],
    }


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python modules_checker.py <project_path>")
        sys.exit(1)
    
    project_path = sys.argv[1]
    report = generate_compat_report(
        app_code_path=os.path.join(project_path, "app", "code"),
        composer_json_path=os.path.join(project_path, "composer.json"),
        config_php_path=os.path.join(project_path, "app", "etc", "config.php"),
    )
    print(json.dumps(report_to_dict(report), indent=2))
