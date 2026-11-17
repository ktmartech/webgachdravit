#!/usr/bin/env python3
"""
Step 9 CSS slicer (ADR D-15, D-26, D-30): cắt CSS Giỏ hàng & Checkout từ
docs/layout/assets/css/pages.css (§4 / §4B) sang `pages/cart.css` và `pages/checkout.css`.

- Bỏ khối FBT vật tư phụ (D-26), thanh demo prototype, topbar checkout (đã có ở header-mega.css).
- Đổi HEX cứng sang token, bỏ `!important`, đổi media query sang range syntax.
- Bọc toàn bộ trong `@layer woo` (16 §3: template WooCommerce dùng layer `woo`).

Chạy lại an toàn (deterministic): `python tools/port-css-step9.py && npx stylelint "assets/src/css/pages/*-port.css" --fix`.
"""
from __future__ import annotations

import re
from pathlib import Path

THEME_DIR = Path(__file__).resolve().parent.parent
PUBLIC_DIR = THEME_DIR.parent.parent.parent
PAGES_CSS = PUBLIC_DIR / "docs" / "layout" / "assets" / "css" / "pages.css"
OUT_DIR = THEME_DIR / "assets" / "src" / "css" / "pages"

HEX_MAP = {
    "#083a4f": "var(--c-navy-900)",
    "#0b192c": "var(--c-text-main)",
    "#cbd5e1": "var(--c-navy-300)",
    "#f8fafc": "var(--c-ivory)",
    "#ffffff": "var(--c-white)",
    "#fff": "var(--c-white)",
    "#c0392b": "var(--c-sale)",
    "#5eead4": "var(--c-teal-300, var(--c-teal-500))",
    "#f0fdf9": "var(--c-teal-50)",
    "#f0fdfa": "var(--c-teal-50)",
    "#f7faf8": "var(--c-ivory)",
    "#fafbfc": "var(--c-ivory)",
    "#fbfcfe": "var(--c-ivory)",
    "#64748b": "var(--c-navy-500)",
}

# (start_marker, end_marker_exclusive) — cắt theo comment section của mockup, không phụ thuộc số dòng.
CART_SLICES = [
    ("/* 2-Column Main Cart Layout", "/* Frequently Bought Together (FBT) Bundle Block */"),
    ("/* Cart Guarantees 3-Card Grid */", "/* Printable Quote Sheet Table inside Dialog */"),
]
CHECKOUT_SLICES = [
    ("/* 2-Column Checkout Layout", "/* ========================================================================\n     4C. ORDER RECEIVED"),
]
ORDER_SLICES = [
    ("/* ========================================================================\n     4C. ORDER RECEIVED", "/* ========================================================================\n     5. EDITORIAL"),
]

# Khối bị loại bỏ bên trong slice (selector prefix → bỏ cả rule).
DROP_SELECTORS = (
    ".drv-cart-demo-bar",
    ".drv-checkout-topbar",
    ".drv-fbt-",
    # STRIP-22 (16 §6, 18 §4.3): không che watermark lên ảnh QR thật ở trạng thái Chờ xác nhận.
    ".drv-qr-watermark",
)


def slice_between(text: str, start: str, end: str) -> str:
    s = text.index(start)
    e = text.index(end, s)
    return text[s:e]


def map_hex(css: str) -> str:
    def repl(m: re.Match[str]) -> str:
        key = m.group(0).lower()
        return HEX_MAP.get(key, m.group(0))

    # `var(--x, #hex)` fallback → bỏ fallback.
    css = re.sub(r"var\((--[a-z0-9-]+),\s*#[0-9a-fA-F]{3,6}\)", r"var(\1)", css)
    return re.sub(r"#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b", repl, css)


def modern_media(css: str) -> str:
    css = re.sub(r"\(min-width:\s*([0-9.]+px)\)", r"(width >= \1)", css)
    css = re.sub(r"\(max-width:\s*([0-9.]+px)\)", r"(width <= \1)", css)
    return css


def drop_rules(css: str) -> str:
    """Bỏ các rule có selector bắt đầu bằng prefix cấm (đếm ngoặc để xóa trọn khối)."""
    out: list[str] = []
    i = 0
    n = len(css)
    while i < n:
        brace = css.find("{", i)
        if brace == -1:
            out.append(css[i:])
            break
        # Phần trước `{` gồm whitespace/comment + selector.
        head = css[i:brace]
        close_prev = head.rfind("}")
        if close_prev != -1:
            out.append(css[i : i + close_prev + 1])
            i = i + close_prev + 1
            continue
        selector = head.strip().split("*/")[-1].strip()
        depth = 0
        j = brace
        while j < n:
            if css[j] == "{":
                depth += 1
            elif css[j] == "}":
                depth -= 1
                if depth == 0:
                    break
            j += 1
        block = css[i : j + 1]
        if selector.startswith("@media"):
            inner = css[brace + 1 : j]
            cleaned = drop_rules(inner)
            if cleaned.strip():
                out.append(head + "{" + cleaned + "}")
        elif any(p in selector for p in DROP_SELECTORS):
            pass
        else:
            out.append(block)
        i = j + 1
    return "".join(out)


def build(slices: list[tuple[str, str]], src: str, header: str) -> str:
    parts = [slice_between(src, a, b) for a, b in slices]
    body = "\n".join(parts)
    body = body.replace(" !important", "")
    body = map_hex(body)
    body = modern_media(body)
    body = drop_rules(body)
    body = body.replace("drvDrawerSlideDown", "drv-cart-drawer-slide-down")
    body = re.sub(r"\n{3,}", "\n\n", body)
    return f"{header}\n@layer woo {{\n  {body.strip()}\n}}\n"


def main() -> None:
    src = PAGES_CSS.read_text(encoding="utf-8")

    cart_header = (
        "/* ==========================================================================\n"
        "   CART (`assets/src/css/pages/cart.css`) — port docs/layout/04-cart.html (pages.css §4)\n"
        "   Sinh bởi tools/port-css-step9.py · Bỏ FBT vật tư phụ (D-26) & thanh demo prototype.\n"
        "   Phần mở rộng theme nằm trong `cart-theme.css`.\n"
        "   ========================================================================== */\n"
    )
    checkout_header = (
        "/* ==========================================================================\n"
        "   CHECKOUT VN (`assets/src/css/pages/checkout.css`) — port docs/layout/05-checkout.html (pages.css §4B)\n"
        "   Sinh bởi tools/port-css-step9.py · Topbar dùng header-mega.css. Phần mở rộng: `checkout-theme.css`.\n"
        "   ========================================================================== */\n"
    )

    (OUT_DIR / "cart-port.css").write_text(build(CART_SLICES, src, cart_header), encoding="utf-8")
    order_header = (
        "/* ==========================================================================\n"
        "   ORDER RECEIVED (`assets/src/css/pages/order.css`) — port docs/layout/06-order-received.html (pages.css §4C)\n"
        "   Sinh bởi tools/port-css-step9.py · Bỏ watermark QR (STRIP-22). Phần mở rộng: `order-theme.css`.\n"
        "   ========================================================================== */\n"
    )

    (OUT_DIR / "checkout-port.css").write_text(build(CHECKOUT_SLICES, src, checkout_header), encoding="utf-8")
    (OUT_DIR / "order-port.css").write_text(build(ORDER_SLICES, src, order_header), encoding="utf-8")
    print("Wrote cart-port.css, checkout-port.css & order-port.css")


if __name__ == "__main__":
    main()
