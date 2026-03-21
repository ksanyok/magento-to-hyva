#!/usr/bin/env python3
"""
Hyvä Theme Validator — validates the generated theme without requiring
a Hyvä license or live Magento installation.

Checks:
1. PHP syntax of all .phtml files
2. XML well-formedness of all layout XMLs
3. Theme structure completeness
4. Alpine.js pattern validation (no KnockoutJS/RequireJS remnants)
5. Tailwind class consistency
6. Translation completeness
7. Font file references
"""
import json
import os
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path


class Validator:
    def __init__(self, theme_path: str, compat_path: str = ""):
        self.theme_path = theme_path
        self.compat_path = compat_path
        self.errors = []
        self.warnings = []
        self.passed = []

    def add_error(self, check: str, msg: str):
        self.errors.append(f"[FAIL] {check}: {msg}")

    def add_warning(self, check: str, msg: str):
        self.warnings.append(f"[WARN] {check}: {msg}")

    def add_pass(self, check: str, msg: str):
        self.passed.append(f"[ OK ] {check}: {msg}")

    # ─── 1. Theme structure ───────────────────────────────────
    def check_structure(self):
        """Verify all required theme files exist."""
        required = [
            "registration.php",
            "theme.xml",
            "composer.json",
            "package.json",
            "web/tailwind/tailwind.config.js",
            "web/tailwind/tailwind-source.css",
            "web/css/fonts.css",
        ]
        for f in required:
            path = os.path.join(self.theme_path, f)
            if os.path.isfile(path):
                self.add_pass("structure", f)
            else:
                self.add_error("structure", f"Missing required file: {f}")

        # Check directories
        required_dirs = ["web/fonts", "web/images", "i18n"]
        for d in required_dirs:
            path = os.path.join(self.theme_path, d)
            if os.path.isdir(path):
                count = len([f for f in os.listdir(path) if not f.startswith(".")])
                self.add_pass("structure", f"{d}/ ({count} files)")
            else:
                self.add_warning("structure", f"Directory missing: {d}/")

    # ─── 2. PHP Syntax ────────────────────────────────────────
    def check_php_syntax(self):
        """Validate PHP syntax of all .phtml files."""
        php_bin = self._find_php()
        if not php_bin:
            self.add_warning("php-syntax", "PHP not found, skipping syntax check")
            return

        phtml_files = list(Path(self.theme_path).rglob("*.phtml"))
        for pf in phtml_files:
            result = subprocess.run(
                [php_bin, "-l", str(pf)],
                capture_output=True, text=True, timeout=10
            )
            rel = os.path.relpath(str(pf), self.theme_path)
            if result.returncode == 0:
                self.add_pass("php-syntax", rel)
            else:
                err = result.stdout.strip() or result.stderr.strip()
                self.add_error("php-syntax", f"{rel}: {err}")

        # Also check compat stubs
        if self.compat_path:
            compat_phtml = list(Path(self.compat_path).rglob("*.phtml"))
            for pf in compat_phtml:
                result = subprocess.run(
                    [php_bin, "-l", str(pf)],
                    capture_output=True, text=True, timeout=10
                )
                rel = os.path.relpath(str(pf), self.compat_path)
                if result.returncode == 0:
                    self.add_pass("php-syntax", f"compat/{rel}")
                else:
                    err = result.stdout.strip() or result.stderr.strip()
                    self.add_error("php-syntax", f"compat/{rel}: {err}")

    def _find_php(self):
        """Find PHP binary."""
        for php in ["php", "/usr/bin/php", "/usr/local/bin/php", "/opt/homebrew/bin/php"]:
            try:
                result = subprocess.run([php, "-v"], capture_output=True, timeout=5)
                if result.returncode == 0:
                    return php
            except (FileNotFoundError, subprocess.TimeoutExpired):
                continue
        return None

    # ─── 3. XML Validation ────────────────────────────────────
    def check_xml_syntax(self):
        """Validate XML well-formedness of all layout files."""
        xml_files = list(Path(self.theme_path).rglob("*.xml"))
        for xf in xml_files:
            rel = os.path.relpath(str(xf), self.theme_path)
            try:
                ET.parse(str(xf))
                self.add_pass("xml-syntax", rel)
            except ET.ParseError as e:
                self.add_error("xml-syntax", f"{rel}: {e}")

        # Also check compat stubs
        if self.compat_path:
            compat_xml = list(Path(self.compat_path).rglob("*.xml"))
            for xf in compat_xml:
                rel = os.path.relpath(str(xf), self.compat_path)
                try:
                    ET.parse(str(xf))
                    self.add_pass("xml-syntax", f"compat/{rel}")
                except ET.ParseError as e:
                    self.add_error("xml-syntax", f"compat/{rel}: {e}")

    # ─── 4. No KnockoutJS/RequireJS remnants ──────────────────
    def check_no_legacy_js(self):
        """Ensure no KnockoutJS or RequireJS patterns remain in templates."""
        legacy_patterns = {
            r'data-bind\s*=': "KnockoutJS data-bind attribute",
            r'ko\s+(?:foreach|if|visible|text|html|css|style|attr)': "KnockoutJS binding",
            r'require\(\[': "RequireJS require()",
            r'define\(\[': "RequireJS define()",
            r'data-mage-init': "Magento widget init (needs Alpine.js replacement)",
            r'text/x-magento-init': "Magento JSON init script",
            r'mage/': "Magento JS module reference",
            r'\$\(': "jQuery selector (should use Alpine.js)",
        }

        phtml_files = list(Path(self.theme_path).rglob("*.phtml"))
        for pf in phtml_files:
            rel = os.path.relpath(str(pf), self.theme_path)
            content = pf.read_text(errors="replace")

            # Strip PHP/HTML comments before checking for legacy patterns
            stripped = re.sub(r'/\*.*?\*/', '', content, flags=re.DOTALL)  # /* ... */
            stripped = re.sub(r'//[^\n]*', '', stripped)                   # // ...
            stripped = re.sub(r'<!--.*?-->', '', stripped, flags=re.DOTALL) # <!-- ... -->

            found_any = False
            for pattern, desc in legacy_patterns.items():
                matches = re.findall(pattern, stripped)
                if matches:
                    self.add_error("no-legacy-js", f"{rel}: Found {desc} ({len(matches)}x)")
                    found_any = True

            if not found_any:
                self.add_pass("no-legacy-js", f"{rel}: Clean Alpine.js")

    # ─── 5. Hyvä ViewModels usage ─────────────────────────────
    def check_hyva_patterns(self):
        """Check that templates use Hyvä ViewModels correctly."""
        phtml_files = list(Path(self.theme_path).rglob("*.phtml"))
        for pf in phtml_files:
            rel = os.path.relpath(str(pf), self.theme_path)
            content = pf.read_text(errors="replace")

            # Check for ObjectManager usage (bad)
            if "ObjectManager::getInstance" in content:
                self.add_error("hyva-patterns", f"{rel}: Uses ObjectManager directly (should use ViewModel)")

            # Check for $escaper usage (Hyvä standard)
            # Skip check if template only uses $block->getChildHtml() or @noEscape JSON
            if "$block->" in content:
                if ("$escaper->" not in content and "escapeHtml" not in content
                        and "@noEscape" not in content
                        and "getChildHtml" not in content):
                    self.add_warning("hyva-patterns", f"{rel}: No $escaper usage found")

            # Check Alpine.js x-data presence
            if "x-data" in content:
                self.add_pass("hyva-patterns", f"{rel}: Has Alpine.js x-data")
            elif "<script" not in content and "<?=" in content:
                # Static template without Alpine — that's OK too
                pass

    # ─── 6. Font file references ──────────────────────────────
    def check_fonts(self):
        """Verify that fonts.css references actual files in web/fonts/."""
        fonts_css = os.path.join(self.theme_path, "web", "css", "fonts.css")
        fonts_dir = os.path.join(self.theme_path, "web", "fonts")

        if not os.path.isfile(fonts_css):
            self.add_error("fonts", "web/css/fonts.css not found")
            return

        content = open(fonts_css).read()
        # Extract all url() references
        urls = re.findall(r"url\(['\"]?\.\./fonts/([^'\")?]+)", content)

        for font_file in urls:
            full = os.path.join(fonts_dir, font_file)
            if os.path.isfile(full):
                self.add_pass("fonts", f"fonts/{font_file}")
            else:
                self.add_error("fonts", f"Missing font file: fonts/{font_file}")

    # ─── 7. Translation completeness ──────────────────────────
    def check_translations(self):
        """Check that translatable strings from templates exist in i18n CSVs."""
        i18n_dir = os.path.join(self.theme_path, "i18n")
        if not os.path.isdir(i18n_dir):
            self.add_warning("translations", "No i18n directory")
            return

        csv_files = [f for f in os.listdir(i18n_dir) if f.endswith(".csv")]
        if not csv_files:
            self.add_warning("translations", "No CSV translation files")
            return

        self.add_pass("translations", f"{len(csv_files)} translation files found")

        # Extract translatable strings from templates
        phtml_files = list(Path(self.theme_path).rglob("*.phtml"))
        template_strings = set()
        for pf in phtml_files:
            content = pf.read_text(errors="replace")
            # Match __('...') and __("...")
            matches = re.findall(r"__\(['\"](.+?)['\"]\)", content)
            template_strings.update(matches)

        if not template_strings:
            return

        # Check first non-English CSV for coverage
        for csv_name in csv_files:
            if csv_name.startswith("en_"):
                continue
            csv_path = os.path.join(i18n_dir, csv_name)
            content = open(csv_path, errors="replace").read()

            # Parse CSV strings (first column) using proper CSV parser
            import csv
            import io
            translated = set()
            reader = csv.reader(io.StringIO(content))
            for row in reader:
                if row:
                    translated.add(row[0].strip())

            missing = template_strings - translated
            covered = template_strings & translated
            coverage = len(covered) / len(template_strings) * 100 if template_strings else 100

            self.add_pass("translations", f"{csv_name}: {coverage:.0f}% coverage ({len(covered)}/{len(template_strings)})")

            if missing:
                # Show up to 10 missing strings
                shown = sorted(missing)[:10]
                for s in shown:
                    self.add_warning("translations", f"{csv_name}: Missing translation for '{s}'")
                if len(missing) > 10:
                    self.add_warning("translations", f"{csv_name}: ...and {len(missing) - 10} more missing")
            break  # Only check first non-English file

    # ─── 8. Theme.xml parent validation ───────────────────────
    def check_theme_xml(self):
        """Validate theme.xml content."""
        theme_xml = os.path.join(self.theme_path, "theme.xml")
        try:
            tree = ET.parse(theme_xml)
            root = tree.getroot()
            parent = root.find("parent")
            title = root.find("title")

            if parent is not None and parent.text:
                self.add_pass("theme-xml", f"Parent theme: {parent.text}")
                if "Hyva" not in parent.text and "hyva" not in parent.text.lower():
                    self.add_warning("theme-xml", f"Parent theme '{parent.text}' doesn't look like a Hyvä theme")
            else:
                self.add_error("theme-xml", "No parent theme defined")

            if title is not None and title.text:
                self.add_pass("theme-xml", f"Title: {title.text}")
        except ET.ParseError as e:
            self.add_error("theme-xml", f"Parse error: {e}")

    # ─── 9. Tailwind config validation ────────────────────────
    def check_tailwind_config(self):
        """Basic validation of tailwind.config.js."""
        tw_config = os.path.join(self.theme_path, "web", "tailwind", "tailwind.config.js")
        if not os.path.isfile(tw_config):
            self.add_error("tailwind", "tailwind.config.js not found")
            return

        content = open(tw_config).read()

        # Check for hyva-modules integration
        if "hyva-modules" in content or "hyvaModules" in content:
            self.add_pass("tailwind", "Hyvä modules integration present")
        else:
            self.add_warning("tailwind", "No Hyvä modules integration found")

        # Check content paths for phtml scanning
        if ".phtml" in content:
            self.add_pass("tailwind", "Scans .phtml files for classes")
        else:
            self.add_error("tailwind", "Not scanning .phtml files")

        # Check for FTC brand colors
        if "primary" in content:
            self.add_pass("tailwind", "Brand colors configured")

    # ─── Run all checks ──────────────────────────────────────
    def run_all(self):
        """Run all validation checks."""
        checks = [
            ("Theme Structure", self.check_structure),
            ("PHP Syntax", self.check_php_syntax),
            ("XML Syntax", self.check_xml_syntax),
            ("No Legacy JS", self.check_no_legacy_js),
            ("Hyvä Patterns", self.check_hyva_patterns),
            ("Font Files", self.check_fonts),
            ("Translations", self.check_translations),
            ("Theme XML", self.check_theme_xml),
            ("Tailwind Config", self.check_tailwind_config),
        ]

        print(f"\n{'='*60}")
        print(f"  Hyvä Theme Validator")
        print(f"{'='*60}")
        print(f"  Theme: {self.theme_path}")
        if self.compat_path:
            print(f"  Compat: {self.compat_path}")
        print(f"{'='*60}\n")

        for name, check_fn in checks:
            print(f"── {name} ──")
            check_fn()
            # Print results for this check
            prefix = name.lower().replace(" ", "-")
            for msg in self.passed:
                if msg.startswith(f"[ OK ] {prefix}") or any(
                    msg.startswith(f"[ OK ] {c}")
                    for c in [prefix, name.lower().replace(" ", "-")]
                ):
                    pass  # Will print all at end
            print()

        # Summary
        print(f"\n{'='*60}")
        print(f"  Results")
        print(f"{'='*60}")

        for msg in self.passed:
            print(f"  {msg}")

        if self.warnings:
            print(f"\n{'─'*60}")
            for msg in self.warnings:
                print(f"  {msg}")

        if self.errors:
            print(f"\n{'─'*60}")
            for msg in self.errors:
                print(f"  {msg}")

        print(f"\n{'='*60}")
        print(f"  ✅ Passed:   {len(self.passed)}")
        print(f"  ⚠️  Warnings: {len(self.warnings)}")
        print(f"  ❌ Errors:   {len(self.errors)}")
        print(f"{'='*60}\n")

        return len(self.errors) == 0


def main():
    import argparse
    parser = argparse.ArgumentParser(description="Validate generated Hyvä theme")
    parser.add_argument("--theme", required=True, help="Path to generated theme")
    parser.add_argument("--compat", default="", help="Path to compatibility stubs")
    args = parser.parse_args()

    v = Validator(
        os.path.abspath(args.theme),
        os.path.abspath(args.compat) if args.compat else ""
    )
    success = v.run_all()
    sys.exit(0 if success else 1)


if __name__ == "__main__":
    main()
