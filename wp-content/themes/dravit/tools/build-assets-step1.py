#!/usr/bin/env python3
"""
Deterministic Step 1 Asset Builder for Dravit Theme (ADR D-12, D-28):
1. Subsets Quicksand Variable (400..700, Latin + Vietnamese + math/unit symbols) -> assets/fonts/quicksand-vf-vi.woff2
2. Subsets Anton 400 (Latin + Vietnamese) -> assets/fonts/anton-vi.woff2
3. Packs ~165 Phosphor Icons (Regular + Bold + Fill) into 1 single WOFF2 -> assets/fonts/phosphor-subset.woff2 + assets/src/css/layers/icons.css
4. Optimizes Dravit Logos (8071x2389 PNG) -> WebP 1x (150x44), WebP 2x (300x88), and compressed PNG fallback (~4KB)
5. Copies local SVG tile/room stand-ins to assets/images/placeholders/
"""
from __future__ import annotations

import copy
import os
import re
import shutil
import urllib.request
from pathlib import Path

from fontTools.merge import Merger
from fontTools.subset import Options, Subsetter
from fontTools.ttLib import TTFont
from PIL import Image

THEME_DIR = Path(__file__).resolve().parent.parent
PUBLIC_DIR = THEME_DIR.parent.parent.parent
LAYOUT_DIR = PUBLIC_DIR / "docs" / "layout"
NODE_MODULES = THEME_DIR / "node_modules"
FONT_CACHE_DIR = THEME_DIR / "tools" / ".font-cache"

FONTS_OUT = THEME_DIR / "assets" / "fonts"
IMAGES_OUT = THEME_DIR / "assets" / "images"
PLACEHOLDERS_OUT = IMAGES_OUT / "placeholders"
ICONS_CSS_OUT = THEME_DIR / "assets" / "src" / "css" / "layers" / "icons.css"

ICON_NAMES = sorted(set([
    "armchair", "arrow-counter-clockwise", "arrow-down", "arrow-left", "arrow-right",
    "arrow-up", "arrow-up-right", "arrows-clockwise", "arrows-counter-clockwise",
    "arrows-down-up", "arrows-in-line-vertical", "arrows-left-right", "arrows-out",
    "arrows-out-line-horizontal", "arrows-out-simple", "bank", "bathtub", "bed",
    "book-open", "book-open-text", "bookmarks", "bookmarks-simple", "bounding-box",
    "briefcase", "browsers", "buildings", "calculator", "calendar-blank",
    "calendar-check", "calendar-plus", "car", "caret-double-left", "caret-down",
    "caret-left", "caret-right", "caret-up", "caret-up-down", "chat-centered-text",
    "chat-circle-dots", "chat-circle-text", "check", "check-circle", "check-fat",
    "check-square", "circle", "clipboard-text", "clock", "cloud-rain", "coins",
    "columns", "compass", "cooking-pot", "copy", "credit-card", "cube",
    "currency-circle-dollar", "diamonds-four", "door-open", "download-simple",
    "drop", "drop-half-bottom", "envelope-simple", "eraser", "eye",
    "facebook-logo", "file-pdf", "file-text", "file-xls", "files", "function",
    "funnel", "gear", "globe", "grid-four", "hand-coins", "hand-pointing", "handshake",
    "hard-hat", "headset", "heart", "hexagon", "house", "house-line",
    "identification-card", "image", "images", "info", "instagram-logo",
    "lightbulb", "lightning", "link", "list", "list-bullets", "list-checks",
    "list-numbers", "lock-simple", "magnifying-glass", "magnifying-glass-minus",
    "magnifying-glass-plus", "map-pin", "minus", "moon", "navigation-arrow",
    "note-pencil", "package", "paint-brush", "paint-roller", "palette",
    "paper-plane-right", "paper-plane-tilt", "pen-nib", "pencil-simple",
    "percent", "phone", "phone-call", "phone-incoming", "pinterest-logo",
    "plus", "plus-circle", "printer", "qr-code", "question", "quotes",
    "receipt", "rectangle", "road-horizon", "robot", "ruler", "scales",
    "scissors", "seal-check", "seal-percent", "share-network", "shield-check",
    "shield-warning", "shopping-bag", "shopping-bag-open", "shopping-cart",
    "shopping-cart-simple", "sidebar-simple", "sliders", "sliders-horizontal",
    "sort-ascending", "sort-descending", "sparkle", "spinner", "square",
    "squares-four", "stack", "stack-simple", "star", "storefront", "sun",
    "sun-dim", "swimming-pool", "tag", "thumbs-down", "thumbs-up", "tiktok-logo",
    "trash", "tree", "truck", "upload-simple", "user", "users", "wall",
    "warehouse", "warning", "warning-circle", "whatsapp-logo", "wrench", "x",
    "x-circle", "youtube-logo",
]))


def parse_unicode_ranges() -> list[int]:
    """Build complete Latin + Vietnamese + Technical Typography codepoint set."""
    ranges = [
        (0x0020, 0x007E),  # Basic ASCII
        (0x00A0, 0x00FF),  # Latin-1 Supplement (°, ², ³, ×, ÷, etc.)
        (0x0102, 0x0103),  # Ă ă
        (0x0110, 0x0111),  # Đ đ
        (0x0128, 0x0129),  # Ĩ ĩ
        (0x0131, 0x0131),
        (0x0152, 0x0153),  # Œ œ
        (0x0168, 0x0169),  # Ũ ũ
        (0x01A0, 0x01A1),  # Ơ ơ
        (0x01AF, 0x01B0),  # Ư ư
        (0x02BB, 0x02BC),
        (0x02C6, 0x02C6),
        (0x02DA, 0x02DA),
        (0x02DC, 0x02DC),
        (0x0300, 0x0309),  # Combining diacritics
        (0x0323, 0x0323),
        (0x0329, 0x0329),
        (0x1EA0, 0x1EF9),  # Full Vietnamese Extended Latin
        (0x2000, 0x206F),  # General punctuation (–, —, ‘, ’, “, ”, …, •)
        (0x20AB, 0x20AB),  # ₫
        (0x20AC, 0x20AC),  # €
        (0x2122, 0x2122),  # ™
        (0x2190, 0x2199),  # Arrows
        (0x2212, 0x2215),  # −, ∕
        (0x2248, 0x2248),  # ≈
        (0x2260, 0x2265),  # ≠, ≤, ≥
    ]
    cps: set[int] = set()
    for start, end in ranges:
        cps.update(range(start, end + 1))
    return sorted(cps)


def subset_font_to_woff2(font: TTFont, out_file: Path, unicodes: list[int]) -> int:
    opts = Options()
    opts.flavor = "woff2"
    opts.layout_features = ["kern", "liga", "calt", "locl", "mark", "mkmk", "tnum", "lnum"]
    opts.name_IDs = [1, 2, 3, 4, 5, 6]
    subsetter = Subsetter(options=opts)
    subsetter.populate(unicodes=unicodes)
    subsetter.subset(font)
    font.flavor = "woff2"
    font.save(str(out_file))
    font.close()
    return out_file.stat().st_size


def fetch_cached_source_ttf(url: str, filename: str) -> Path | None:
    FONT_CACHE_DIR.mkdir(parents=True, exist_ok=True)
    cached = FONT_CACHE_DIR / filename
    if cached.exists() and cached.stat().st_size > 10000:
        return cached
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "DravitBuild/1.0"})
        with urllib.request.urlopen(req, timeout=20) as resp:
            data = resp.read()
        if len(data) > 10000:
            cached.write_bytes(data)
            return cached
    except Exception:
        return None
    return None


def combine_variable_slices_offline(part_files: list[Path]) -> TTFont:
    """Offline fallback for variable font slices sharing identical fvar axes."""
    base = TTFont(str(part_files[0]))
    base.flavor = None
    for tbl in ["HVAR", "MVAR", "STAT", "GSUB", "GPOS", "GDEF"]:
        if tbl in base:
            del base[tbl]

    base_order = list(base.getGlyphOrder())
    base_glyf = base["glyf"]
    base_hmtx = base["hmtx"].metrics
    base_gvar = base["gvar"].variations if "gvar" in base else None
    merged_cmap = dict(base.getBestCmap() or {})

    for idx, part in enumerate(part_files[1:], start=1):
        src = TTFont(str(part))
        src_cmap = src.getBestCmap() or {}
        src_glyf = src["glyf"]
        src_hmtx = src["hmtx"].metrics
        src_gvar = src["gvar"].variations if "gvar" in src else {}

        def import_glyph(gname: str) -> str:
            dst_name = gname if gname not in base_glyf.glyphs else f"s{idx}_{gname}"
            if dst_name in base_glyf.glyphs and dst_name != gname:
                return dst_name
            if gname in base_glyf.glyphs and dst_name == gname:
                dst_name = f"s{idx}_{gname}"
                if dst_name in base_glyf.glyphs:
                    return dst_name
            g = copy.deepcopy(src_glyf[gname])
            if g.isComposite():
                for comp in g.components:
                    comp.glyphName = import_glyph(comp.glyphName)
            base_order.append(dst_name)
            base_glyf.glyphs[dst_name] = g
            base_hmtx[dst_name] = src_hmtx[gname]
            if base_gvar is not None and gname in src_gvar:
                base_gvar[dst_name] = copy.deepcopy(src_gvar[gname])
            return dst_name

        for cp, gname in src_cmap.items():
            if cp in merged_cmap:
                continue
            merged_cmap[cp] = import_glyph(gname)
        src.close()

    base.setGlyphOrder(base_order)
    base_glyf.glyphOrder = base_order
    base["maxp"].numGlyphs = len(base_order)
    for table in base["cmap"].tables:
        if table.isUnicode():
            table.cmap = dict(merged_cmap)
    return base


def build_quicksand_woff2(unicodes: list[int]) -> int:
    out_file = FONTS_OUT / "quicksand-vf-vi.woff2"
    ttf_src = fetch_cached_source_ttf(
        "https://raw.githubusercontent.com/google/fonts/main/ofl/quicksand/Quicksand%5Bwght%5D.ttf",
        "Quicksand-Variable.ttf",
    )
    if ttf_src is not None:
        font = TTFont(str(ttf_src))
        # Restrict variable weight axis cleanly to 400..700 if instantiable, or keep native axis
        return subset_font_to_woff2(font, out_file, unicodes)

    qs_dir = NODE_MODULES / "@fontsource-variable" / "quicksand" / "files"
    combined = combine_variable_slices_offline([
        qs_dir / "quicksand-latin-wght-normal.woff2",
        qs_dir / "quicksand-latin-ext-wght-normal.woff2",
        qs_dir / "quicksand-vietnamese-wght-normal.woff2",
    ])
    return subset_font_to_woff2(combined, out_file, unicodes)


def build_anton_woff2(unicodes: list[int]) -> int:
    out_file = FONTS_OUT / "anton-vi.woff2"
    ttf_src = fetch_cached_source_ttf(
        "https://raw.githubusercontent.com/google/fonts/main/ofl/anton/Anton-Regular.ttf",
        "Anton-Regular.ttf",
    )
    if ttf_src is not None:
        font = TTFont(str(ttf_src))
        return subset_font_to_woff2(font, out_file, unicodes)

    anton_dir = NODE_MODULES / "@fontsource" / "anton" / "files"
    part_files = [
        anton_dir / "anton-latin-400-normal.woff2",
        anton_dir / "anton-latin-ext-400-normal.woff2",
        anton_dir / "anton-vietnamese-400-normal.woff2",
    ]
    tmp_ttfs: list[str] = []
    for idx, src in enumerate(part_files):
        f = TTFont(str(src))
        f.flavor = None
        tmp_path = str(FONTS_OUT / f"_tmp_anton_{idx}.ttf")
        f.save(tmp_path)
        f.close()
        tmp_ttfs.append(tmp_path)
    try:
        merged = Merger().merge(tmp_ttfs)
        return subset_font_to_woff2(merged, out_file, unicodes)
    finally:
        for p in tmp_ttfs:
            if os.path.exists(p):
                os.remove(p)


def parse_phosphor_css_map(css_path: Path) -> dict[str, int]:
    text = css_path.read_text(encoding="utf-8")
    pattern = re.compile(r"\.ph(?:-bold|-fill)?\.ph-([a-z0-9-]+):before\s*\{\s*content:\s*\"\\([0-9a-fA-F]+)\";")
    mapping: dict[str, int] = {}
    for match in pattern.finditer(text):
        mapping[match.group(1)] = int(match.group(2), 16)
    return mapping


def build_phosphor_subset() -> tuple[int, int]:
    phos_root = NODE_MODULES / "@phosphor-icons" / "web" / "src"
    variants = [
        ("regular", phos_root / "regular" / "Phosphor.ttf", phos_root / "regular" / "style.css", 0xE000),
        ("bold", phos_root / "bold" / "Phosphor-Bold.ttf", phos_root / "bold" / "style.css", 0xE300),
        ("fill", phos_root / "fill" / "Phosphor-Fill.ttf", phos_root / "fill" / "style.css", 0xE600),
    ]

    base_font = TTFont(str(variants[0][1]))
    for tbl in ["GSUB", "GPOS", "GDEF", "kern"]:
        if tbl in base_font:
            del base_font[tbl]

    base_glyf = base_font["glyf"]
    base_hmtx = base_font["hmtx"].metrics
    notdef_glyph = copy.deepcopy(base_glyf[".notdef"])
    notdef_metric = base_hmtx.get(".notdef", (1000, 0))

    new_glyph_order = [".notdef"]
    new_glyf_glyphs = {".notdef": notdef_glyph}
    new_hmtx = {".notdef": notdef_metric}
    new_cmap: dict[int, str] = {}

    css_rules: dict[str, list[tuple[str, int]]] = {"regular": [], "bold": [], "fill": []}

    def copy_glyph_recursive(src_glyf, src_hmtx, src_gname: str, prefix: str) -> str:
        dst_gname = f"{prefix}_{src_gname}"
        if dst_gname in new_glyf_glyphs:
            return dst_gname

        g = copy.deepcopy(src_glyf[src_gname])
        if g.isComposite():
            for comp in g.components:
                comp.glyphName = copy_glyph_recursive(src_glyf, src_hmtx, comp.glyphName, prefix)

        new_glyph_order.append(dst_gname)
        new_glyf_glyphs[dst_gname] = g
        new_hmtx[dst_gname] = src_hmtx[src_gname]
        return dst_gname

    for weight_key, ttf_path, css_path, pua_base in variants:
        code_map = parse_phosphor_css_map(css_path)
        src_font = TTFont(str(ttf_path))
        src_cmap = src_font.getBestCmap() or {}
        src_glyf = src_font["glyf"]
        src_hmtx = src_font["hmtx"].metrics

        offset = 0
        for icon_name in ICON_NAMES:
            orig_cp = code_map.get(icon_name)
            if orig_cp is None or orig_cp not in src_cmap:
                continue
            src_gname = src_cmap[orig_cp]
            dst_gname = copy_glyph_recursive(src_glyf, src_hmtx, src_gname, weight_key)
            assigned_cp = pua_base + offset
            offset += 1
            new_cmap[assigned_cp] = dst_gname
            css_rules[weight_key].append((icon_name, assigned_cp))

        src_font.close()

    base_font.setGlyphOrder(new_glyph_order)
    base_glyf.glyphs = new_glyf_glyphs
    base_glyf.glyphOrder = new_glyph_order
    base_font["hmtx"].metrics = new_hmtx
    base_font["maxp"].numGlyphs = len(new_glyph_order)

    for table in base_font["cmap"].tables:
        if table.isUnicode():
            table.cmap = dict(new_cmap)

    out_woff2 = FONTS_OUT / "phosphor-subset.woff2"
    base_font.flavor = "woff2"
    base_font.save(str(out_woff2))
    base_font.close()

    lines = [
        "/* Auto-generated by tools/build-assets-step1.py - Self-hosted Phosphor Subset (ADR D-28) */",
        "@layer tokens {",
        "  @font-face {",
        '    font-family: "Phosphor Dravit";',
        '    src: url("../../../fonts/phosphor-subset.woff2") format("woff2");',
        "    font-weight: 400;",
        "    font-style: normal;",
        "    font-display: block;",
        "  }",
        "}",
        "",
        "@layer components {",
        "  .ph,",
        "  .ph-bold,",
        "  .ph-fill {",
        '    font-family: "Phosphor Dravit", sans-serif;',
        "    font-style: normal;",
        "    font-weight: 400;",
        "    font-variant: normal;",
        "    text-transform: none;",
        "    line-height: 1;",
        "    letter-spacing: 0;",
        "    display: inline-flex;",
        "    align-items: center;",
        "    justify-content: center;",
        "    flex-shrink: 0;",
        "    vertical-align: middle;",
        "    -webkit-font-smoothing: antialiased;",
        "    -moz-osx-font-smoothing: grayscale;",
        "  }",
    ]

    for icon_name, cp in css_rules["regular"]:
        lines.extend([
            "",
            f"  .ph.ph-{icon_name}::before {{",
            f'    content: "\\{cp:04x}";',
            "  }",
        ])
    for icon_name, cp in css_rules["bold"]:
        lines.extend([
            "",
            f"  .ph-bold.ph-{icon_name}::before {{",
            f'    content: "\\{cp:04x}";',
            "  }",
        ])
    for icon_name, cp in css_rules["fill"]:
        lines.extend([
            "",
            f"  .ph-fill.ph-{icon_name}::before {{",
            f'    content: "\\{cp:04x}";',
            "  }",
        ])

    lines.append("}")
    lines.append("")
    ICONS_CSS_OUT.write_text("\n".join(lines), encoding="utf-8")

    return out_woff2.stat().st_size, len(css_rules["regular"])


def optimize_logos_and_placeholders() -> dict[str, int]:
    sizes: dict[str, int] = {}
    for variant in ["logo-navy", "logo-ivory"]:
        src_png = LAYOUT_DIR / "assets" / "img" / f"{variant}.png"
        if not src_png.exists():
            continue
        with Image.open(src_png) as img:
            rgba = img.convert("RGBA")
            bbox = rgba.getbbox()
            if bbox:
                pad_x = int((bbox[2] - bbox[0]) * 0.03)
                pad_y = int((bbox[3] - bbox[1]) * 0.06)
                crop_box = (
                    max(0, bbox[0] - pad_x),
                    max(0, bbox[1] - pad_y),
                    min(rgba.width, bbox[2] + pad_x),
                    min(rgba.height, bbox[3] + pad_y),
                )
                rgba = rgba.crop(crop_box)

            ratio = rgba.width / rgba.height
            h_1x = 44
            w_1x = round(h_1x * ratio)
            h_2x = 88
            w_2x = round(h_2x * ratio)

            im_1x = rgba.resize((w_1x, h_1x), Image.Resampling.LANCZOS)
            im_2x = rgba.resize((w_2x, h_2x), Image.Resampling.LANCZOS)

            p_1x = IMAGES_OUT / f"{variant}@1x.webp"
            p_2x = IMAGES_OUT / f"{variant}@2x.webp"
            p_png = IMAGES_OUT / f"{variant}.png"

            im_1x.save(p_1x, "WEBP", quality=86, method=6)
            im_2x.save(p_2x, "WEBP", quality=86, method=6)
            im_2x.save(p_png, "PNG", optimize=True)

            sizes[p_1x.name] = p_1x.stat().st_size
            sizes[p_2x.name] = p_2x.stat().st_size
            sizes[p_png.name] = p_png.stat().st_size

    PLACEHOLDERS_OUT.mkdir(parents=True, exist_ok=True)
    for svg_file in (LAYOUT_DIR / "assets" / "img").glob("*.svg"):
        shutil.copy2(svg_file, PLACEHOLDERS_OUT / svg_file.name)

    return sizes


def main() -> None:
    FONTS_OUT.mkdir(parents=True, exist_ok=True)
    IMAGES_OUT.mkdir(parents=True, exist_ok=True)
    ICONS_CSS_OUT.parent.mkdir(parents=True, exist_ok=True)

    unicodes = parse_unicode_ranges()
    qs_size = build_quicksand_woff2(unicodes)
    anton_size = build_anton_woff2(unicodes)
    phos_size, icon_count = build_phosphor_subset()
    logo_sizes = optimize_logos_and_placeholders()

    print(f"quicksand-vf-vi.woff2: {qs_size:,} bytes ({qs_size / 1024:.1f} KB)")
    print(f"anton-vi.woff2:        {anton_size:,} bytes ({anton_size / 1024:.1f} KB)")
    print(f"phosphor-subset.woff2: {phos_size:,} bytes ({phos_size / 1024:.1f} KB) [{icon_count} icons x 3 weights]")
    for k, v in logo_sizes.items():
        print(f"{k:22s}: {v:,} bytes ({v / 1024:.1f} KB)")


if __name__ == "__main__":
    main()
