#!/usr/bin/env python3
"""
Hyvä Theme Generator — Main entry point.
Reads a Magento project, analyzes the Luma theme, and generates
a complete Hyvä child theme with converted templates and layouts.

Usage:
    python generate.py --project projects/ftcshop \
                       --vendor MediaDivision \
                       --theme FTCShopHyva \
                       --output output/ftcshop
"""
import argparse
import json
import os
import shutil
import sys
from pathlib import Path

# Add project root to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from generator.style_extractor import extract_design_tokens
from generator.hyva_theme import scaffold_hyva_theme
from generator.template_converter import get_strategy, TEMPLATE_STRATEGY
from generator.layout_converter import (
    process_all_layouts,
    generate_hyva_default_xml,
    generate_hyva_catalog_product_view_xml,
    generate_hyva_catalog_category_view_xml,
)
from compatibility.compat_generator import run_phase3


def find_luma_theme(project_path: str) -> str:
    """Find the Luma theme directory in the project."""
    design_path = os.path.join(project_path, "app", "design", "frontend")
    if not os.path.exists(design_path):
        return ""

    for vendor in os.listdir(design_path):
        vendor_dir = os.path.join(design_path, vendor)
        if not os.path.isdir(vendor_dir) or vendor == "Magento":
            continue
        for theme in os.listdir(vendor_dir):
            theme_dir = os.path.join(vendor_dir, theme)
            theme_xml = os.path.join(theme_dir, "theme.xml")
            if os.path.exists(theme_xml):
                return theme_dir
    return ""


def copy_templates(templates_dir: str, output_theme: str, strategy_map: dict) -> list:
    """
    Copy Hyvä template rewrites to the output theme directory.
    Returns list of copied template paths.
    """
    copied = []
    if not os.path.exists(templates_dir):
        return copied

    for root, _, files in os.walk(templates_dir):
        for fname in files:
            if not fname.endswith(".phtml"):
                continue
            src = os.path.join(root, fname)
            rel = os.path.relpath(src, templates_dir)
            dst = os.path.join(output_theme, rel)
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            shutil.copy2(src, dst)
            copied.append(rel)

    return copied


def copy_luma_assets(source_theme: str, output_theme: str) -> list:
    """
    Copy static assets (images, fonts) from the Luma theme
    that the Hyvä theme will also need.
    """
    copied = []
    asset_dirs = [
        ("web/images", "web/images"),
        ("web/fonts", "web/fonts"),
    ]

    for src_rel, dst_rel in asset_dirs:
        src_dir = os.path.join(source_theme, src_rel)
        dst_dir = os.path.join(output_theme, dst_rel)
        if not os.path.exists(src_dir):
            continue
        for root, _, files in os.walk(src_dir):
            for fname in files:
                src_file = os.path.join(root, fname)
                rel = os.path.relpath(src_file, src_dir)
                dst_file = os.path.join(dst_dir, rel)
                os.makedirs(os.path.dirname(dst_file), exist_ok=True)
                shutil.copy2(src_file, dst_file)
                copied.append(os.path.join(dst_rel, rel))

    return copied


def copy_i18n(source_theme: str, output_theme: str) -> list:
    """Copy translation CSV files from the Luma theme."""
    copied = []
    i18n_dir = os.path.join(source_theme, "i18n")
    if not os.path.exists(i18n_dir):
        return copied

    dst_dir = os.path.join(output_theme, "i18n")
    os.makedirs(dst_dir, exist_ok=True)

    for fname in os.listdir(i18n_dir):
        if fname.endswith(".csv"):
            shutil.copy2(os.path.join(i18n_dir, fname), os.path.join(dst_dir, fname))
            copied.append(fname)

    return copied


def extract_template_strings(theme_path: str) -> set:
    """Extract all __('...') translatable strings from .phtml templates."""
    import re
    strings = set()
    for phtml in Path(theme_path).rglob("*.phtml"):
        content = phtml.read_text(errors="replace")
        matches = re.findall(r"__\(['\"](.+?)['\"]\)", content)
        strings.update(matches)
    return strings


def enrich_i18n(output_theme: str, template_strings: set) -> int:
    """
    Add missing translatable strings from templates into all i18n CSV files.
    For English locales: identity translations (key = value).
    For German locales: provide German translations for common UI strings.
    For other locales: identity translations as placeholders.
    Returns the number of strings added.
    """
    import csv
    import io

    # German translations for common Hyvä frontend strings
    de_translations = {
        "Account Information": "Kontoinformationen",
        "Add to Cart": "In den Warenkorb",
        "Add to Wish List": "Zur Wunschliste hinzufügen",
        "Adding...": "Wird hinzugefügt...",
        "Address": "Adresse",
        "Already have an account? Sign in": "Bereits ein Konto? Anmelden",
        "Back": "Zurück",
        "Back to Address Book": "Zurück zum Adressbuch",
        "Cashmere Care": "Kaschmir Pflege",
        "Change Email": "E-Mail ändern",
        "Change Email and Password": "E-Mail und Passwort ändern",
        "Change Password": "Passwort ändern",
        "City": "Stadt",
        "Close": "Schliessen",
        "Close Window": "Fenster schliessen",
        "Close fullscreen": "Vollbild schliessen",
        "Close minicart": "Miniwarenkorb schliessen",
        "Company": "Firma",
        "Confirm New Password": "Neues Passwort bestätigen",
        "Confirm Password": "Passwort bestätigen",
        "Contact Information": "Kontaktinformationen",
        "Cotton Care": "Baumwoll Pflege",
        "Country": "Land",
        "Create New Customer Account": "Neues Kundenkonto erstellen",
        "Create an Account": "Konto erstellen",
        "Creating...": "Wird erstellt...",
        "Current Password": "Aktuelles Passwort",
        "Decrease quantity": "Menge verringern",
        "Edit Account Information": "Kontoinformationen bearbeiten",
        "Edit Address": "Adresse bearbeiten",
        "Email": "E-Mail",
        "Email address": "E-Mail-Adresse",
        "Fair": "Fair",
        "First Name": "Vorname",
        "Forgot Your Password?": "Passwort vergessen?",
        "Go back": "Zurück",
        "Good": "Gut",
        "Hide password": "Passwort verbergen",
        "Increase quantity": "Menge erhöhen",
        "Last Name": "Nachname",
        "Linen Care": "Leinen Pflege",
        "Login": "Anmelden",
        "Lyocell Care": "Lyocell Pflege",
        "Men Size Chart": "Herren Grössentabelle",
        "More Choices:": "Mehr Auswahl:",
        "New Password": "Neues Passwort",
        "New Products": "Neue Produkte",
        "New": "Neu",
        "Next": "Weiter",
        "Next image": "Nächstes Bild",
        "No Password": "Kein Passwort",
        "Notify Me": "Benachrichtigen",
        "Open fullscreen gallery": "Galerie im Vollbild öffnen",
        "Password": "Passwort",
        "Password Strength": "Passwortstärke",
        "Personal Information": "Persönliche Informationen",
        "Phone Number": "Telefonnummer",
        "Please select a region, state or province.": "Bitte wählen Sie eine Region oder ein Bundesland.",
        "Previous": "Zurück",
        "Previous image": "Vorheriges Bild",
        "Qty": "Menge",
        "Required Fields": "Pflichtfelder",
        "Save": "Speichern",
        "Save Address": "Adresse speichern",
        "Saving...": "Wird gespeichert...",
        "Search": "Suche",
        "Show password": "Passwort anzeigen",
        "Sign In": "Anmelden",
        "Sign Up for Newsletter": "Newsletter abonnieren",
        "Sign up to get notified when this product is back in stock.": "Melden Sie sich an, um benachrichtigt zu werden, wenn dieses Produkt wieder verfügbar ist.",
        "Sign-in Information": "Anmeldeinformationen",
        "Signing in...": "Anmeldung...",
        "Sizes Chart": "Grössentabelle",
        "State/Province": "Bundesland/Region",
        "Street Address": "Strassenadresse",
        "Strong": "Stark",
        "Subscribe": "Abonnieren",
        "Subscribing...": "Wird abonniert...",
        "Toggle minicart": "Miniwarenkorb umschalten",
        "Upcycled": "Upcycling",
        "Use as my default billing address": "Als Standard-Rechnungsadresse verwenden",
        "Use as my default shipping address": "Als Standard-Lieferadresse verwenden",
        "Very Strong": "Sehr stark",
        "View Cart": "Warenkorb anzeigen",
        "View image": "Bild ansehen",
        "We found other products you might like!": "Wir haben andere Produkte gefunden, die Ihnen gefallen könnten!",
        "Weak": "Schwach",
        "Wishlist": "Wunschliste",
        "Women Size Chart": "Damen Grössentabelle",
        "You May Also Like": "Das könnte Ihnen auch gefallen",
        "You have no items in your shopping cart.": "Ihr Warenkorb ist leer.",
        "Zip/Postal Code": "PLZ",
    }

    # Dutch translations
    nl_translations = {
        "Add to Cart": "In winkelwagen",
        "Add to Wish List": "Aan verlanglijst toevoegen",
        "Back": "Terug",
        "City": "Stad",
        "Close": "Sluiten",
        "Company": "Bedrijf",
        "Country": "Land",
        "Create an Account": "Account aanmaken",
        "Email": "E-mail",
        "First Name": "Voornaam",
        "Last Name": "Achternaam",
        "Login": "Inloggen",
        "Password": "Wachtwoord",
        "Qty": "Aantal",
        "Save": "Opslaan",
        "Search": "Zoeken",
        "Sign In": "Inloggen",
        "View Cart": "Winkelwagen bekijken",
    }

    # Danish translations
    da_translations = {
        "Add to Cart": "Læg i kurv",
        "Back": "Tilbage",
        "Close": "Luk",
        "Email": "E-mail",
        "First Name": "Fornavn",
        "Last Name": "Efternavn",
        "Login": "Log ind",
        "Password": "Adgangskode",
        "Qty": "Antal",
        "Save": "Gem",
        "Search": "Søg",
        "Sign In": "Log ind",
        "View Cart": "Se kurv",
    }

    # Swedish translations
    sv_translations = {
        "Add to Cart": "Lägg i varukorg",
        "Back": "Tillbaka",
        "Close": "Stäng",
        "Email": "E-post",
        "First Name": "Förnamn",
        "Last Name": "Efternamn",
        "Login": "Logga in",
        "Password": "Lösenord",
        "Qty": "Antal",
        "Save": "Spara",
        "Search": "Sök",
        "Sign In": "Logga in",
        "View Cart": "Visa varukorg",
    }

    # Polish translations
    pl_translations = {
        "Add to Cart": "Dodaj do koszyka",
        "Back": "Wstecz",
        "Close": "Zamknij",
        "Email": "E-mail",
        "First Name": "Imię",
        "Last Name": "Nazwisko",
        "Login": "Zaloguj się",
        "Password": "Hasło",
        "Qty": "Ilość",
        "Save": "Zapisz",
        "Search": "Szukaj",
        "Sign In": "Zaloguj się",
        "View Cart": "Zobacz koszyk",
    }

    locale_translations = {
        "de_DE": de_translations,
        "de_AT": de_translations,
        "de_CH": de_translations,
        "nl_NL": nl_translations,
        "da_DK": da_translations,
        "sv_SE": sv_translations,
        "pl_PL": pl_translations,
    }

    i18n_dir = os.path.join(output_theme, "i18n")
    if not os.path.isdir(i18n_dir):
        os.makedirs(i18n_dir, exist_ok=True)

    total_added = 0
    csv_files = [f for f in os.listdir(i18n_dir) if f.endswith(".csv")]

    # If no CSV files, create at least en_US and de_DE
    if not csv_files:
        csv_files = ["en_US.csv", "de_DE.csv"]
        for f in csv_files:
            Path(os.path.join(i18n_dir, f)).touch()

    for csv_name in csv_files:
        csv_path = os.path.join(i18n_dir, csv_name)
        locale = csv_name.replace(".csv", "")

        # Read existing translations
        existing_keys = set()
        existing_lines = []
        if os.path.isfile(csv_path):
            with open(csv_path, "r", errors="replace") as f:
                existing_lines = f.readlines()
            for line in existing_lines:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                # Parse CSV key (first field)
                try:
                    reader = csv.reader(io.StringIO(line))
                    row = next(reader)
                    if row:
                        existing_keys.add(row[0])
                except (csv.Error, StopIteration):
                    pass

        # Find missing strings
        missing = template_strings - existing_keys
        if not missing:
            continue

        # Get locale-specific translations
        trans_map = locale_translations.get(locale, {})

        # Append missing strings
        new_lines = []
        for s in sorted(missing):
            if locale.startswith("en_"):
                # English: identity translation
                translation = s
            elif s in trans_map:
                translation = trans_map[s]
            else:
                # Use English as placeholder for untranslated strings
                translation = s
            # Proper CSV quoting
            out = io.StringIO()
            writer = csv.writer(out)
            writer.writerow([s, translation])
            new_lines.append(out.getvalue())

        if new_lines:
            with open(csv_path, "a") as f:
                # Add separator comment
                f.write(f"# --- Hyvä frontend translations (auto-generated) ---\n")
                for line in new_lines:
                    f.write(line)
            total_added += len(new_lines)

    return total_added


def generate_layout_xmls(source_theme: str, output_theme: str) -> list:
    """
    Process layout XMLs: convert where needed, generate Hyvä-specific ones.
    """
    generated = []

    # Generate Hyvä-specific layout XMLs
    hyva_layouts = {
        "Magento_Theme/layout/default.xml":
            generate_hyva_default_xml({}),
        "Magento_Catalog/layout/catalog_product_view.xml":
            generate_hyva_catalog_product_view_xml(),
        "Magento_Catalog/layout/catalog_category_view.xml":
            generate_hyva_catalog_category_view_xml(),
    }

    for rel_path, content in hyva_layouts.items():
        dst = os.path.join(output_theme, rel_path)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        with open(dst, "w") as f:
            f.write(content)
        generated.append(rel_path)

    # Process source theme layouts — copy non-checkout ones
    layout_results = process_all_layouts(source_theme, output_theme)
    for result in layout_results:
        if result["action"] == "copy":
            src = os.path.join(source_theme, result["source"])
            dst = os.path.join(output_theme, result["source"])
            # Don't overwrite our generated layouts
            if not os.path.exists(dst):
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                shutil.copy2(src, dst)
                generated.append(result["source"])

    return generated


def main():
    parser = argparse.ArgumentParser(description="Generate Hyvä child theme from Magento Luma theme")
    parser.add_argument("--project", required=True, help="Path to Magento project (e.g. projects/ftcshop)")
    parser.add_argument("--vendor", default="MediaDivision", help="Theme vendor name")
    parser.add_argument("--theme", default="FTCShopHyva", help="Hyvä theme name")
    parser.add_argument("--title", default="FTC Cashmere Hyvä", help="Theme title")
    parser.add_argument("--output", required=True, help="Output directory (e.g. output/ftcshop)")
    parser.add_argument("--with-stubs", action="store_true",
                        help="Generate Hyvä parent theme + module stubs for testing without a license")
    args = parser.parse_args()

    project_path = os.path.abspath(args.project)
    output_path = os.path.abspath(args.output)

    print(f"\n{'='*60}")
    print(f"  Hyvä Theme Generator")
    print(f"{'='*60}")
    print(f"  Project:  {project_path}")
    print(f"  Vendor:   {args.vendor}")
    print(f"  Theme:    {args.theme}")
    print(f"  Output:   {output_path}")
    print(f"{'='*60}\n")

    # 1. Find Luma theme
    print("[1/6] Finding Luma theme...")
    luma_theme = find_luma_theme(project_path)
    if not luma_theme:
        print("  ERROR: No Luma theme found in project!")
        sys.exit(1)
    print(f"  Found: {luma_theme}")

    # 2. Extract design tokens from LESS
    print("\n[2/6] Extracting design tokens from LESS...")
    tokens = extract_design_tokens(luma_theme)
    print(f"  Colors:      {len(tokens.colors)}")
    print(f"  Fonts:       {len(tokens.fonts)}")
    print(f"  Breakpoints: {len(tokens.breakpoints)}")
    print(f"  Font sizes:  {len(tokens.font_sizes)}")

    # 3. Scaffold Hyvä theme (registration.php, theme.xml, tailwind.config.js, etc.)
    print("\n[3/6] Scaffolding Hyvä child theme...")
    theme_base = scaffold_hyva_theme(
        output_path=output_path,
        vendor=args.vendor,
        theme_name=args.theme,
        title=args.title,
        tokens=tokens,
        source_theme_path=luma_theme,
    )
    print(f"  Created: {theme_base}")

    # 4. Copy converted phtml templates
    print("\n[4/6] Copying Hyvä template rewrites...")
    templates_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), "generator", "templates")
    copied_templates = copy_templates(templates_dir, theme_base, TEMPLATE_STRATEGY)
    print(f"  Copied {len(copied_templates)} template files:")
    for t in sorted(copied_templates):
        print(f"    ✓ {t}")

    # Show template strategy summary
    rewrite_count = sum(1 for v in TEMPLATE_STRATEGY.values() if v == "rewrite")
    skip_count = sum(1 for v in TEMPLATE_STRATEGY.values() if v == "skip")
    copy_count = sum(1 for v in TEMPLATE_STRATEGY.values() if v == "copy")
    remove_count = sum(1 for v in TEMPLATE_STRATEGY.values() if v == "remove")
    print(f"\n  Strategy summary:")
    print(f"    Rewrite (Hyvä version):  {rewrite_count}")
    print(f"    Skip (Hyvä default):     {skip_count}")
    print(f"    Copy (unchanged):        {copy_count}")
    print(f"    Remove (KO templates):   {remove_count}")

    # 5. Generate/convert layout XMLs
    print("\n[5/6] Processing layout XMLs...")
    layout_files = generate_layout_xmls(luma_theme, theme_base)
    print(f"  Generated {len(layout_files)} layout files:")
    for l in sorted(layout_files):
        print(f"    ✓ {l}")

    # 6. Copy static assets and translations
    print("\n[6/7] Copying assets and translations...")
    assets = copy_luma_assets(luma_theme, theme_base)
    translations = copy_i18n(luma_theme, theme_base)

    # Extract translatable strings from templates and enrich CSV files
    template_strings = extract_template_strings(theme_base)
    enriched = enrich_i18n(theme_base, template_strings)

    print(f"  Assets:       {len(assets)} files")
    print(f"  Translations: {len(translations)} CSV files")
    print(f"  Enriched:     {enriched} strings added to {len(translations)} locales")
    print(f"  Template strings: {len(template_strings)} unique translatable strings")

    # 7. Phase 3: Compatibility modules
    print("\n[7/7] Phase 3: Analyzing module compatibility...")
    compat_output = os.path.join(output_path, "compatibility")
    compat_analysis, compat_modules = run_phase3(project_path, compat_output)

    # Generate summary report
    report = {
        "vendor": args.vendor,
        "theme": args.theme,
        "source_theme": luma_theme,
        "output_path": theme_base,
        "templates_converted": len(copied_templates),
        "layout_xmls": len(layout_files),
        "assets_copied": len(assets),
        "translations": len(translations),
        "design_tokens": {
            "colors": len(tokens.colors),
            "fonts": len(tokens.fonts),
            "breakpoints": len(tokens.breakpoints),
        },
        "compatibility": {
            "packages_available": len(compat_analysis.get("compatible", [])),
            "needs_custom": len(compat_analysis.get("needs_custom", [])),
            "stub_modules_generated": len(compat_modules),
        },
    }

    report_path = os.path.join(output_path, "GENERATION_REPORT.json")
    with open(report_path, "w") as f:
        json.dump(report, f, indent=2)

    # Optional: Generate Hyvä stubs for testing without a license
    stubs_generated = False
    if args.with_stubs:
        print("\n[Stubs] Generating Hyvä parent theme + module stubs...")
        from tests.hyva_stub_generator import generate_hyva_stub, generate_hyva_module_stub
        stubs_dir = os.path.join(output_path, "stubs")
        stub_theme = generate_hyva_stub(stubs_dir)
        stub_module = generate_hyva_module_stub(stubs_dir)
        stubs_generated = True
        print(f"  Stub theme:  {stub_theme}")
        print(f"  Stub module: {stub_module}")

    print(f"\n{'='*60}")
    print(f"  Generation complete!")
    print(f"{'='*60}")
    print(f"  Theme:     {theme_base}")
    print(f"  Templates: {len(copied_templates)}")
    print(f"  Layouts:   {len(layout_files)}")
    print(f"  Assets:    {len(assets)}")
    print(f"  Compat:    {len(compat_modules)} stub modules")
    print(f"  Report:    {report_path}")
    print(f"\n  Next steps:")
    if stubs_generated:
        print(f"  0. Copy stubs/{os.path.basename(stub_theme)} → app/design/frontend/Hyva/default/")
        print(f"     Copy stubs/{os.path.basename(stub_module)} → app/code/Hyva/Theme/")
    print(f"  1. cd {theme_base}/web/tailwind && npm install && npm run build")
    print(f"  2. Install Hyvä compat packages (see compatibility/COMPATIBILITY_REPORT.md)")
    print(f"  3. Copy stub modules from compatibility/stubs/ to app/code/")
    print(f"  4. Deploy theme to Magento and activate")
    print(f"  5. Test all pages and adjust templates as needed")
    print(f"{'='*60}\n")


if __name__ == "__main__":
    main()
