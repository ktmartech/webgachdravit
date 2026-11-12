#!/usr/bin/env python3
"""
Deterministic CSS Slicer for Step 1 (ADR D-17, D-31, D-34):
Slices docs/layout/assets/css/main.css + shared primitives from pages.css into modular @layer files,
strips .drv-proto-bar, .drv-shine, drv-site-header/footer, !important, and hardcoded HEX colors,
and adds D-18 / D-29 / D-35 interactive menu/header enhancements.
"""
from __future__ import annotations

import re
from pathlib import Path

THEME_DIR = Path(__file__).resolve().parent.parent
PUBLIC_DIR = THEME_DIR.parent.parent.parent
MAIN_CSS = PUBLIC_DIR / "docs" / "layout" / "assets" / "css" / "main.css"
LAYERS_DIR = THEME_DIR / "assets" / "src" / "css" / "layers"
COMP_DIR = LAYERS_DIR / "components"

HEX_MAP = {
    "#cbd5e1": "var(--c-navy-300)",
    "#94a3b8": "var(--c-navy-400)",
    "#e2e8f0": "var(--c-navy-200)",
    "#083a4f": "var(--c-navy-900)",
    "#0b192c": "var(--c-text-main)",
    "#1e3a5f": "var(--c-text-muted)",
    "#0d9488": "var(--c-teal-500)",
    "#0f766e": "var(--c-teal-700)",
    "#f59e0b": "var(--c-amber-500)",
    "#ffffff": "var(--c-white)",
    "#fff": "var(--c-white)",
    "#f8fafc": "var(--c-ivory)",
    "#f1f5f9": "var(--c-champagne)",
}


KEYFRAME_MAP = {
    "drvModalIn": "drv-modal-in",
    "drvModalOut": "drv-modal-out",
    "drvDrawerRightIn": "drv-drawer-right-in",
    "drvDrawerRightOut": "drv-drawer-right-out",
    "drvDrawerLeftIn": "drv-drawer-left-in",
    "drvDrawerLeftOut": "drv-drawer-left-out",
    "drvBottomSheetIn": "drv-bottom-sheet-in",
    "drvBottomSheetOut": "drv-bottom-sheet-out",
    "drvBackdropIn": "drv-backdrop-in",
    "drvBackdropOut": "drv-backdrop-out",
    "drvToastIn": "drv-toast-in",
    "drvMegaIn": "drv-mega-in",
}


def sanitize_css_block(raw: str) -> str:
    text = raw.replace(" !important", "").replace("!important", "")
    text = re.sub(r"^\s*-webkit-text-size-adjust:\s*100%;\s*\n", "", text, flags=re.MULTILINE)
    for old_kf, new_kf in KEYFRAME_MAP.items():
        text = text.replace(old_kf, new_kf)

    def repl_hex(match: re.Match[str]) -> str:
        val = match.group(0).lower()
        return HEX_MAP.get(val, "var(--c-navy-900)")

    text = re.sub(r"#[0-9a-fA-F]{3,8}\b", repl_hex, text)
    return text.strip()


def extract_lines(lines: list[str], start_1idx: int, end_1idx: int) -> str:
    return "\n".join(lines[start_1idx - 1 : end_1idx])


def main() -> None:
    COMP_DIR.mkdir(parents=True, exist_ok=True)
    lines = MAIN_CSS.read_text(encoding="utf-8").splitlines()

    # 1. reset.css (lines 14..98, stripping drv-site-header/footer & [hidden] !important)
    reset_raw = extract_lines(lines, 14, 97)
    reset_raw = re.sub(r"\s*drv-site-header,\s*drv-site-footer\s*\{[^}]*\}", "", reset_raw)
    reset_raw = re.sub(r"\s*\[hidden\]\s*\{[^}]*\}", "", reset_raw)
    (LAYERS_DIR / "reset.css").write_text(sanitize_css_block(reset_raw) + "\n", encoding="utf-8")

    # 2. base.css (lines 102..193 + smooth scroll-margin-top for anchor headings)
    base_raw = extract_lines(lines, 102, 193)
    base_extra = """
  h1[id],
  h2[id],
  h3[id],
  section[id] {
    scroll-margin-top: calc(var(--header-h-current) + var(--s-4));
  }
}"""
    base_raw = base_raw[: base_raw.rfind("}")] + base_extra
    (LAYERS_DIR / "base.css").write_text(sanitize_css_block(base_raw) + "\n", encoding="utf-8")

    # 3. layout.css (lines 198..404)
    layout_raw = extract_lines(lines, 198, 404)
    (LAYERS_DIR / "layout.css").write_text(sanitize_css_block(layout_raw) + "\n", encoding="utf-8")

    # 4. Modular components/*.css
    # 4a. primitives.css (4.1 Skip Link + 4.2 Icon sizing + 4.11 Section Head + 4.16 Breadcrumb)
    prim_body = "\n\n".join([
        extract_lines(lines, 410, 453),
        extract_lines(lines, 2690, 2721),
        extract_lines(lines, 2931, 2996),
    ])
    (COMP_DIR / "primitives.css").write_text(
        "@layer components {\n" + sanitize_css_block(prim_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4b. buttons.css (4.4 Buttons + 4.4b Copy Inline + 4.19 Load More)
    btn_body = "\n\n".join([
        extract_lines(lines, 516, 747),
        extract_lines(lines, 3879, 3903),
    ])
    (COMP_DIR / "buttons.css").write_text(
        "@layer components {\n" + sanitize_css_block(btn_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4c. badges-chips.css (4.5 Badges + 4.6 Chips + Swatches from pages.css)
    swatches_css = """
  /* Color & Size Swatches (drv_ui('swatch')) */
  .drv-color-swatch {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px 4px 4px;
    border-radius: var(--r-sm);
    border: var(--bw);
    background-color: var(--c-white);
    color: var(--c-text-main);
    font-size: var(--fs-xs);
    font-weight: 600;
    transition:
      border-color var(--dur-fast) var(--ease-out),
      background-color var(--dur-fast) var(--ease-out);
  }

  .drv-color-swatch:hover {
    border-color: var(--c-navy-800);
  }

  .drv-color-swatch.is-active {
    border-color: var(--c-navy-900);
    background-color: var(--c-ivory);
    box-shadow: inset 0 0 0 1px var(--c-navy-900);
    font-weight: 700;
    color: var(--c-navy-900);
  }

  .drv-color-swatch__thumb {
    width: 28px;
    height: 28px;
    border-radius: var(--r-xs);
    object-fit: cover;
    border: var(--bw);
    background-color: var(--c-champagne);
    flex-shrink: 0;
  }

  .drv-color-swatch__dot {
    width: 22px;
    height: 22px;
    border-radius: var(--r-xs);
    border: var(--bw);
    flex-shrink: 0;
  }

  .drv-swatch-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 32px;
    padding: 4px 12px;
    border-radius: var(--r-sm);
    border: var(--bw);
    background-color: var(--c-white);
    color: var(--c-text-main);
    font-size: var(--fs-xs);
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    transition:
      border-color var(--dur-fast) var(--ease-out),
      background-color var(--dur-fast) var(--ease-out),
      color var(--dur-fast) var(--ease-out);
  }

  .drv-swatch-pill:hover {
    border-color: var(--c-navy-900);
  }

  .drv-swatch-pill.is-active {
    border-color: var(--c-navy-900);
    background-color: var(--c-navy-900);
    color: var(--c-white);
  }"""
    badges_body = extract_lines(lines, 748, 899) + "\n" + swatches_css
    (COMP_DIR / "badges-chips.css").write_text(
        "@layer components {\n" + sanitize_css_block(badges_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4d. forms.css (4.7 Form Controls)
    forms_body = extract_lines(lines, 900, 1015)
    (COMP_DIR / "forms.css").write_text(
        "@layer components {\n" + sanitize_css_block(forms_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4e. header-mega.css (4.8 Global Header & Mega Menu + D-18/D-29/D-35 Keyboard Split Toggle & Checkout Topbar)
    header_extra = """
  /* Split keyboard toggle & Checkout Topbar (ADR D-18, D-29, D-30, D-35) */
  .drv-nav__item-head {
    display: inline-flex;
    align-items: center;
    height: 100%;
  }

  .drv-nav__sub-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: var(--header-nav-h);
    margin-left: -6px;
    color: var(--c-navy-900);
    border-radius: 0;
    transition:
      background-color var(--dur-fast) var(--ease-out),
      color var(--dur-fast) var(--ease-out);
  }

  .drv-nav__sub-toggle:focus-visible,
  .drv-nav__sub-toggle[aria-expanded="true"],
  .drv-nav__item:hover .drv-nav__sub-toggle,
  .drv-nav__item.is-open .drv-nav__sub-toggle {
    background-color: var(--c-navy-900);
    color: var(--c-white);
  }

  .drv-nav__sub-toggle i {
    transition: transform var(--dur-fast) var(--ease-out);
  }

  .drv-nav__sub-toggle[aria-expanded="true"] i,
  .drv-nav__item.is-open .drv-nav__sub-toggle i {
    transform: rotate(180deg);
  }

  .drv-nav__item.is-open > .drv-dropdown {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translateY(0);
  }

  .drv-dropdown__item.is-flyout-left > .drv-dropdown__flyout {
    left: auto;
    right: 100%;
  }

  .drv-nav__link.is-current {
    box-shadow: inset 0 -2px 0 var(--c-teal-500);
  }

  /* Minimalist Distraction-Free Checkout Topbar (ADR D-30) */
  .drv-checkout-topbar {
    position: sticky;
    top: 0;
    z-index: var(--z-header);
    background-color: var(--c-white);
    border-bottom: var(--bw);
    box-shadow: var(--sh-sm);
  }

  .drv-checkout-topbar__inner {
    min-height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--s-3);
    padding-block: var(--s-2);
  }

  .drv-checkout-topbar__back,
  .drv-checkout-topbar__support {
    display: inline-flex;
    align-items: center;
    gap: var(--s-2);
    font-size: var(--fs-sm);
    font-weight: 600;
    color: var(--c-navy-900);
  }

  .drv-checkout-topbar__back:hover,
  .drv-checkout-topbar__support:hover {
    color: var(--c-teal-700);
  }"""
    header_body = extract_lines(lines, 1016, 2034) + "\n" + header_extra
    (COMP_DIR / "header-mega.css").write_text(
        "@layer components {\n" + sanitize_css_block(header_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4f. cards-silo.css (4.9 Product Card minus 4.9c .drv-shine + 4.9b Quote Card + 4.10 Silo Tile)
    cards_body = "\n\n".join([
        extract_lines(lines, 2035, 2289),
        extract_lines(lines, 2314, 2689),
    ])
    (COMP_DIR / "cards-silo.css").write_text(
        "@layer components {\n" + sanitize_css_block(cards_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4g. calculators-tables.css (4.12 Spec Table + 4.13 Shop Look + 4.14 Unit Calc + 4.15 Qty Input + 4.20 GEO/FAQ + Summary Box + Steps)
    summary_steps_css = """
  /* 3-Step Commerce & BOQ Progress Bar (drv_ui('steps')) */
  .drv-cart-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1px;
    background-color: var(--c-navy-200);
    border: var(--bw);
    border-radius: var(--r-md);
    overflow: hidden;
  }

  .drv-cart-step {
    display: flex;
    align-items: center;
    gap: var(--s-3);
    padding: var(--s-3) var(--s-4);
    background-color: var(--c-white);
    color: var(--c-text-muted);
    text-decoration: none;
    transition: background-color var(--dur-fast) var(--ease-out);
  }

  a.drv-cart-step:hover {
    background-color: var(--c-ivory);
  }

  .drv-cart-step.is-active {
    background-color: var(--c-champagne);
    color: var(--c-navy-900);
    box-shadow: inset 0 -3px 0 var(--c-navy-900);
  }

  .drv-cart-step.is-done .drv-cart-step__num {
    color: var(--c-teal-700);
  }

  .drv-cart-step__num {
    font-family: var(--f-display);
    font-size: 1.375rem;
    line-height: 1;
    color: var(--c-navy-600);
    letter-spacing: 0.03em;
    flex-shrink: 0;
  }

  .drv-cart-step.is-active .drv-cart-step__num {
    color: var(--c-navy-900);
  }

  .drv-cart-step__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  .drv-cart-step__title {
    font-size: var(--fs-sm);
    font-weight: 700;
    color: var(--c-navy-900);
  }

  .drv-cart-step__sub {
    font-size: var(--fs-xs);
    color: var(--c-text-muted);
  }

  @media (width <= 639.98px) {
    .drv-cart-step {
      padding: 8px 6px;
      flex-direction: column;
      text-align: center;
      gap: 2px;
    }

    .drv-cart-step__sub {
      display: none;
    }
  }

  /* Summary Box & Physical Metrics Grid (drv_ui('summary-box')) */
  .drv-summary-box {
    background-color: var(--c-white);
    border: var(--bw-strong);
    border-radius: var(--r-md);
    padding: var(--s-5);
    display: flex;
    flex-direction: column;
    gap: var(--s-4);
  }

  @media (width >= 1024px) {
    .drv-summary-box--sticky {
      position: sticky;
      top: calc(var(--header-h-current) + var(--s-4));
    }
  }

  .drv-cart-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1px;
    background-color: var(--c-navy-200);
    border: var(--bw);
    border-radius: var(--r-sm);
    overflow: hidden;
    font-variant-numeric: tabular-nums;
  }

  .drv-cart-metric {
    background-color: var(--c-ivory);
    padding: var(--s-2) var(--s-3);
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .drv-cart-metric__label {
    font-size: var(--fs-xs);
    color: var(--c-text-muted);
    font-weight: 600;
  }

  .drv-cart-metric__val {
    font-size: var(--fs-base);
    font-weight: 700;
    color: var(--c-navy-900);
  }"""
    calc_body = "\n\n".join([
        extract_lines(lines, 2722, 2930),
        extract_lines(lines, 3904, 3985),
        summary_steps_css,
    ])
    (COMP_DIR / "calculators-tables.css").write_text(
        "@layer components {\n" + sanitize_css_block(calc_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4h. dialogs-toast.css (4.17 Dialogs + 4.21 Toast)
    dialogs_body = "\n\n".join([
        extract_lines(lines, 2997, 3396),
        extract_lines(lines, 3986, 4031),
    ])
    (COMP_DIR / "dialogs-toast.css").write_text(
        "@layer components {\n" + sanitize_css_block(dialogs_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4i. footer-dock.css (4.18 Dock + Dark 4-Col Footer + Mobile Bar)
    footer_body = extract_lines(lines, 3397, 3878)
    (COMP_DIR / "footer-dock.css").write_text(
        "@layer components {\n" + sanitize_css_block(footer_body) + "\n}\n",
        encoding="utf-8",
    )

    # 4j. motion.css (4.22 Motion System & Showcase)
    motion_body = extract_lines(lines, 4032, 4206)
    (COMP_DIR / "motion.css").write_text(
        "@layer components {\n" + sanitize_css_block(motion_body) + "\n}\n",
        encoding="utf-8",
    )

    # components.css barrel
    comp_barrel = "\n".join([
        '@import "./components/primitives.css";',
        '@import "./components/buttons.css";',
        '@import "./components/badges-chips.css";',
        '@import "./components/forms.css";',
        '@import "./components/header-mega.css";',
        '@import "./components/cards-silo.css";',
        '@import "./components/calculators-tables.css";',
        '@import "./components/dialogs-toast.css";',
        '@import "./components/footer-dock.css";',
        '@import "./components/motion.css";',
        "",
    ])
    (LAYERS_DIR / "components.css").write_text(comp_barrel, encoding="utf-8")

    # 5. utilities.css (lines 4212..4342 + [hidden] at highest layer with 0 !important)
    util_raw = extract_lines(lines, 4212, 4342)
    util_raw = util_raw.replace(
        "@layer utilities {",
        "@layer utilities {\n  [hidden] {\n    display: none;\n  }\n",
        1,
    )
    (LAYERS_DIR / "utilities.css").write_text(sanitize_css_block(util_raw) + "\n", encoding="utf-8")
    print("Sliced all @layer CSS modules successfully.")


if __name__ == "__main__":
    main()
