"""
LESS Style Extractor — parses LESS files to extract design tokens
(colors, fonts, breakpoints, spacing) for Tailwind config generation.
"""
import os
import re
from dataclasses import dataclass, field
from typing import Optional


@dataclass
class DesignTokens:
    colors: dict = field(default_factory=dict)
    fonts: dict = field(default_factory=dict)
    font_sizes: dict = field(default_factory=dict)
    breakpoints: dict = field(default_factory=dict)
    spacing: dict = field(default_factory=dict)
    border_radius: dict = field(default_factory=dict)
    box_shadow: dict = field(default_factory=dict)
    transitions: dict = field(default_factory=dict)
    z_index: dict = field(default_factory=dict)
    max_width: str = ""


def extract_less_variables(less_dir: str) -> dict[str, str]:
    """Extract all @variable definitions from LESS files."""
    variables = {}
    for root, _, files in os.walk(less_dir):
        for fname in sorted(files):
            if not fname.endswith(".less"):
                continue
            fpath = os.path.join(root, fname)
            try:
                with open(fpath, "r", encoding="utf-8", errors="ignore") as f:
                    content = f.read()
            except (IOError, OSError):
                continue
            for m in re.finditer(r"@([\w-]+)\s*:\s*([^;]+);", content):
                name = m.group(1).strip()
                value = m.group(2).strip()
                variables[name] = value
    return variables


def extract_design_tokens(theme_path: str) -> DesignTokens:
    """Extract all design tokens from a Magento theme's LESS files."""
    tokens = DesignTokens()

    # Collect LESS variables
    less_dirs = [
        os.path.join(theme_path, "web", "css", "source"),
        os.path.join(theme_path, "css", "source"),
    ]

    variables = {}
    for d in less_dirs:
        if os.path.exists(d):
            variables.update(extract_less_variables(d))

    # Extract colors
    color_vars = {
        "brand-primary": variables.get("brand-primary", "#ad8b70"),
        "brand-secondary": variables.get("brand-secondary", "#f3633c"),
        "green": variables.get("green", "#7dc97d"),
        "white": variables.get("white", "#fff"),
        "black": variables.get("black", "#111"),
        "light-black": variables.get("light-black", "#333"),
        "lighter-black": variables.get("lighter-black", "#555"),
        "lightest-black": variables.get("lightest-black", "#777"),
        "gray": variables.get("gray", "#b3b3b3"),
        "light-gray": variables.get("light-gray", "#e0e0e0"),
        "lighter-gray": variables.get("lighter-gray", "#eee"),
        "lightest-gray": variables.get("lightest-gray", "#f8f8f8"),
    }
    tokens.colors = color_vars

    # Extract fonts
    tokens.fonts = {
        "serif": variables.get("korpus-b", '"Korpus-B", serif'),
        "sans": variables.get("grotesk-b", '"KorpusGrotesk-B", sans-serif'),
    }

    # Font sizes
    tokens.font_sizes = {
        "xs": "10px",
        "sm": "12px",
        "base": "15px",
        "md": "16px",
        "lg": "18px",
        "xl": "20px",
        "2xl": "24px",
        "3xl": "28px",
        "4xl": "30px",
        "5xl": "40px",
        "6xl": "50px",
        "7xl": "60px",
        "8xl": "70px",
        "9xl": "90px",
    }

    # Breakpoints
    tokens.breakpoints = {
        "xxs": variables.get("screen__xxs", "320px"),
        "xs": variables.get("screen__xs", "480px"),
        "sm": variables.get("screen__s", "640px"),
        "md": variables.get("screen__m", "768px"),
        "lg": variables.get("screen__l", "1024px"),
        "xl": variables.get("screen__xl", "1440px"),
    }

    tokens.max_width = variables.get("layout__max-width", "1540px")

    # Z-index
    tokens.z_index = {
        "behind": "-1",
        "default": "0",
        "above": "1",
        "label": "2",
        "modal": "10",
        "toolbar": "20",
        "popup": "30",
        "minicart": "50000",
    }

    # Transitions
    tokens.transitions = {
        "fast": "150ms",
        "default": "300ms",
        "slow": "450ms",
        "slower": "600ms",
    }

    # Border radius
    tokens.border_radius = {
        "none": "0",
        "full": "50%",
    }

    # Box shadows
    tokens.box_shadow = {
        "none": "none",
        "sm": "0 2px 14px 0 rgba(80, 66, 55, 0.3)",
        "md": "0 2px 14px 0 rgba(80, 66, 55, 0.4)",
    }

    return tokens
