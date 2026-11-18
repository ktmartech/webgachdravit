<?php
declare(strict_types=1);

namespace Dravit\Content;

use Dravit\Catalog\AttributeDeriver;
use Dravit\Core\Config;
use Dravit\Core\Integrations\FluentFormsBridge;
use Dravit\Core\ServiceProviderInterface;
use Dravit\Home\HomeData;
use Dravit\Seo\FaqResolver;
use Dravit\Seo\UrlNormalizer;
use Dravit\Units\Calc;
use Dravit\Units\PriceState;

defined('ABSPATH') || exit;

/**
 * Hệ thống 12 Shortcodes của Cẩm nang DRAVIT (Phương án A1, ADR D-20, D-24, D-31, D-32, D-36, docs/20 §3–§4):
 * - 6 Shortcodes trình bày kỹ thuật (thay Gutenberg Patterns theo D-36):
 *   `[drv_takeaways]`, `[drv_callout]`, `[drv_pros_cons]`, `[drv_formula]`, `[drv_steps]`, `[drv_faq]` (+ `[drv_faq_item]`).
 * - 6 Shortcodes thương mại động (cấm gõ cứng giá/tồn kho, gắn LiteSpeed tag `drv_catalog`):
 *   `[drv_products]`, `[drv_shop_the_look]`, `[drv_price_table]`, `[drv_lead_form]`, `[drv_related_posts]`, `[drv_ai_card]`.
 */
final class Shortcodes implements ServiceProviderInterface {
    /** @var array<int, array{q: string, a: string}> */
    private static array $pending_faq_items = [];

    /** @var array<int, string> */
    private const TAGS = [
        'drv_takeaways',
        'drv_callout',
        'drv_pros_cons',
        'drv_formula',
        'drv_steps',
        'drv_faq',
        'drv_faq_item',
        'drv_products',
        'drv_shop_the_look',
        'drv_price_table',
        'drv_lead_form',
        'drv_related_posts',
        'drv_ai_card',
        'drv_info',
    ];

    public function register(): void {
        if (function_exists('add_filter')) {
            add_filter('no_texturize_shortcodes', [self::class, 'excludeFromTexturize']);
        }

        if (!function_exists('add_shortcode')) {
            return;
        }

        // Nhóm A: 6 mẫu trình bày nội dung chuẩn GEO/kỹ thuật (D-36).
        add_shortcode('drv_takeaways', [self::class, 'renderTakeaways']);
        add_shortcode('drv_callout', [self::class, 'renderCallout']);
        add_shortcode('drv_pros_cons', [self::class, 'renderProsCons']);
        add_shortcode('drv_formula', [self::class, 'renderFormula']);
        add_shortcode('drv_steps', [self::class, 'renderSteps']);
        add_shortcode('drv_faq', [self::class, 'renderFaq']);
        add_shortcode('drv_faq_item', [self::class, 'renderFaqItem']);

        // Nhóm B: 6 khối thương mại lấy dữ liệu sống từ WooCommerce / `drv_lead`.
        add_shortcode('drv_products', [self::class, 'renderProducts']);
        add_shortcode('drv_shop_the_look', [self::class, 'renderShopTheLook']);
        add_shortcode('drv_price_table', [self::class, 'renderPriceTable']);
        add_shortcode('drv_lead_form', [self::class, 'renderLeadForm']);
        add_shortcode('drv_related_posts', [self::class, 'renderRelatedPosts']);
        add_shortcode('drv_ai_card', [self::class, 'renderAiCard']);

        // Nhóm C: thông tin thương hiệu động từ `drv_settings` (trang Chính sách, Hướng dẫn, Hợp tác).
        add_shortcode('drv_info', [self::class, 'renderInfo']);
    }

    /**
     * Ngăn `wptexturize` biến dấu ngoặc kép trong thuộc tính shortcode thành `&#8221;`.
     *
     * @param array<int, string> $shortcodes
     * @return array<int, string>
     */
    public static function excludeFromTexturize(array $shortcodes): array {
        return array_values(array_unique(array_merge($shortcodes, self::TAGS)));
    }

    // =========================================================================
    // NHÓM A — 6 SHORTCODES TRÌNH BÀY NỘI DUNG CHUẨN GEO / KỸ THUẬT (D-36)
    // =========================================================================

    /**
     * 1. Hộp Tóm tắt nhanh chuẩn GEO `[drv_takeaways title="..." items="Ý 1|Ý 2"]...[/drv_takeaways]`.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderTakeaways(array|string $atts = [], ?string $content = null): string {
        $a     = self::parseAtts($atts, [
            'title' => 'Tóm tắt nhanh cho gia chủ & thầu thợ',
            'items' => '',
        ]);
        $title = trim((string) $a['title']);
        $lines = self::splitPipeOrLines((string) $a['items']);

        if ($lines === [] && $content !== null && trim($content) !== '') {
            if (preg_match_all('/<li\b[^>]*>(.*?)<\/li>/isu', $content, $m) > 0) {
                foreach ($m[1] as $li_html) {
                    $clean_li = trim((string) $li_html);
                    if ($clean_li !== '') {
                        $lines[] = $clean_li;
                    }
                }
            } else {
                $lines = self::splitPipeOrLines($content);
            }
        }

        if ($lines === []) {
            return '';
        }

        ob_start();
        ?>
        <aside class="drv-key-takeaways" aria-label="<?php echo esc_attr($title); ?>">
            <div class="drv-key-takeaways__top">
                <div class="drv-key-takeaways__head">
                    <i class="ph-fill ph-bookmarks-simple drv-icon" aria-hidden="true"></i>
                    <span><?php echo esc_html($title); ?></span>
                </div>
            </div>
            <ul class="drv-key-takeaways__list">
                <?php foreach ($lines as $line) : ?>
                    <li class="drv-key-takeaways__item">
                        <i class="ph-bold ph-check-square drv-icon--xs" aria-hidden="true"></i>
                        <div><?php echo self::sanitizeInlineHtml($line); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 2. Hộp ghi chú / mẹo / cảnh báo kỹ thuật `[drv_callout type="note|tip|warning" title="..."]...[/drv_callout]`.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderCallout(array|string $atts = [], ?string $content = null): string {
        $a    = self::parseAtts($atts, [
            'type'  => 'note',
            'title' => '',
            'text'  => '',
        ]);
        $type = strtolower(trim((string) $a['type']));
        if (!in_array($type, ['note', 'tip', 'warning'], true)) {
            $type = 'note';
        }

        $defaults = [
            'note'    => ['title' => 'Ghi chú kỹ thuật từ Kho DRAVIT', 'icon' => 'info'],
            'tip'     => ['title' => 'Mẹo thi công chuẩn tại công trình', 'icon' => 'lightbulb'],
            'warning' => ['title' => 'Cảnh báo sai lầm cần tránh khi ốp lát', 'icon' => 'warning'],
        ];

        $title = trim((string) $a['title']) !== '' ? trim((string) $a['title']) : $defaults[$type]['title'];
        $icon  = $defaults[$type]['icon'];
        $body  = trim((string) ($content ?? '')) !== '' ? trim((string) $content) : trim((string) $a['text']);

        if ($body === '') {
            return '';
        }

        ob_start();
        ?>
        <aside class="drv-callout drv-callout--<?php echo esc_attr($type); ?>" aria-label="<?php echo esc_attr($title); ?>">
            <div class="drv-callout__head">
                <i class="ph-fill ph-<?php echo esc_attr($icon); ?> drv-icon--xs" aria-hidden="true"></i>
                <span><?php echo esc_html($title); ?></span>
            </div>
            <div><?php echo self::sanitizeRichHtml($body); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 3. Bảng đối chiếu Ưu điểm & Hạn chế 2 cột `[drv_pros_cons pros="...|..." cons="...|..."]`.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderProsCons(array|string $atts = []): string {
        $a          = self::parseAtts($atts, [
            'pros_title' => 'Ưu điểm thực tế',
            'cons_title' => 'Điểm cần lưu ý khi thi công',
            'pros'       => '',
            'cons'       => '',
        ]);
        $pros_items = self::splitPipeOrLines((string) $a['pros']);
        $cons_items = self::splitPipeOrLines((string) $a['cons']);

        if ($pros_items === [] && $cons_items === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="drv-pros-cons" role="region" aria-label="Đối chiếu ưu điểm và điểm cần lưu ý">
            <div class="drv-pros-cons__col drv-pros-cons__col--pros">
                <div class="drv-pros-cons__head">
                    <i class="ph-fill ph-check-circle drv-icon--xs" aria-hidden="true"></i>
                    <span><?php echo esc_html(trim((string) $a['pros_title'])); ?></span>
                </div>
                <ul class="drv-pros-cons__list">
                    <?php foreach ($pros_items as $p_item) : ?>
                        <li>
                            <i class="ph-bold ph-check u-text-moss" aria-hidden="true"></i>
                            <span><?php echo self::sanitizeInlineHtml($p_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="drv-pros-cons__col drv-pros-cons__col--cons">
                <div class="drv-pros-cons__head">
                    <i class="ph-fill ph-warning-circle drv-icon--xs" aria-hidden="true"></i>
                    <span><?php echo esc_html(trim((string) $a['cons_title'])); ?></span>
                </div>
                <ul class="drv-pros-cons__list">
                    <?php foreach ($cons_items as $c_item) : ?>
                        <li>
                            <i class="ph-bold ph-warning u-text-amber" aria-hidden="true"></i>
                            <span><?php echo self::sanitizeInlineHtml($c_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 4. Hộp công thức định mức vật tư `[drv_formula title="..." sub="..." cells="Nhãn::Công thức::Ghi chú | ..."]`.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderFormula(array|string $atts = [], ?string $content = null): string {
        $a     = self::parseAtts($atts, [
            'title'   => 'Công thức quy đổi số thùng gạch chuẩn',
            'sub'     => '',
            'eq'      => '',
            'example' => '',
            'cells'   => '',
        ]);
        $title = trim((string) $a['title']);
        $sub   = trim((string) $a['sub']);
        $cells = [];

        $raw_cells = self::splitPipeOrLines((string) $a['cells']);
        foreach ($raw_cells as $raw_cell) {
            $parts = array_map('trim', explode('::', $raw_cell));
            if (count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '') {
                $cells[] = [
                    'lbl' => $parts[0],
                    'eq'  => $parts[1],
                    'sub' => $parts[2] ?? '',
                ];
            }
        }

        $eq      = trim((string) $a['eq']);
        $example = trim((string) $a['example']);
        if ($example === '' && $content !== null && trim($content) !== '') {
            $example = trim($content);
        }

        if ($cells === [] && $eq === '' && $example === '') {
            return '';
        }

        ob_start();
        ?>
        <div class="drv-formula-box" role="region" aria-label="<?php echo esc_attr($title); ?>">
            <div class="drv-formula-box__head">
                <span>
                    <i class="ph-fill ph-function drv-icon--xs u-text-moss" aria-hidden="true"></i>
                    <?php echo esc_html($title); ?>
                </span>
                <?php if ($sub !== '') : ?>
                    <span class="u-text-xs u-text-muted u-tabular"><?php echo esc_html($sub); ?></span>
                <?php endif; ?>
            </div>

            <?php if ($cells !== []) : ?>
                <div class="drv-formula-box__grid">
                    <?php foreach ($cells as $cell) : ?>
                        <div class="drv-formula-cell">
                            <span class="drv-formula-cell__lbl"><?php echo esc_html($cell['lbl']); ?></span>
                            <span class="drv-formula-cell__eq"><?php echo esc_html($cell['eq']); ?></span>
                            <?php if ($cell['sub'] !== '') : ?>
                                <span class="drv-formula-cell__sub"><?php echo esc_html($cell['sub']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="drv-formula-box__single">
                    <?php if ($eq !== '') : ?>
                        <p class="drv-formula-cell__eq"><?php echo esc_html($eq); ?></p>
                    <?php endif; ?>
                    <?php if ($example !== '') : ?>
                        <div class="drv-formula-box__example"><?php echo self::sanitizeInlineHtml($example); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 5. Khối quy trình thi công trực quan `[drv_steps items="Tiêu đề::Badge::Mô tả | ..."]` (KHÔNG sinh HowTo Schema theo 06 §4).
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderSteps(array|string $atts = []): string {
        $a         = self::parseAtts($atts, [
            'label' => 'Quy trình thi công chuẩn kỹ thuật',
            'items' => '',
        ]);
        $raw_steps = self::splitPipeOrLines((string) $a['items']);
        $steps     = [];

        foreach ($raw_steps as $raw_step) {
            $parts = array_map('trim', explode('::', $raw_step));
            if (count($parts) === 2) {
                $steps[] = ['title' => $parts[0], 'badge' => '', 'desc' => $parts[1]];
            } elseif (count($parts) >= 3) {
                $steps[] = ['title' => $parts[0], 'badge' => $parts[1], 'desc' => $parts[2]];
            }
        }

        if ($steps === []) {
            return '';
        }

        ob_start();
        ?>
        <div class="drv-howto-grid" role="list" aria-label="<?php echo esc_attr(trim((string) $a['label'])); ?>">
            <?php foreach ($steps as $idx => $step) : ?>
                <div class="drv-howto-step" role="listitem">
                    <div class="drv-howto-step__top">
                        <span class="drv-howto-step__num u-tabular"><?php echo esc_html(sprintf('%02d', $idx + 1)); ?></span>
                        <?php if ($step['badge'] !== '') : ?>
                            <?php drv_ui('badge', ['text' => $step['badge'], 'variant' => 'outline']); ?>
                        <?php endif; ?>
                    </div>
                    <p class="drv-howto-step__title"><?php echo esc_html($step['title']); ?></p>
                    <p class="drv-howto-step__desc"><?php echo self::sanitizeInlineHtml($step['desc']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 6. Khối FAQ bài viết `[drv_faq items="Câu hỏi?::Trả lời | ..."][drv_faq_item q="..."]...[/drv_faq_item][/drv_faq]`.
     * Tự động đồng bộ sang `FaqResolver` cho JSON-LD `FAQPage` (0 `itemscope` microdata).
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderFaq(array|string $atts = [], ?string $content = null): string {
        $a          = self::parseAtts($atts, [
            'items'      => '',
            'open_first' => '1',
        ]);
        $open_first = ((string) $a['open_first'] !== '0');

        self::$pending_faq_items = [];
        if ($content !== null && trim($content) !== '' && function_exists('do_shortcode')) {
            do_shortcode($content);
        }

        $pairs = self::$pending_faq_items;
        self::$pending_faq_items = [];

        foreach (self::splitPipeOrLines((string) $a['items']) as $raw_pair) {
            $parts = array_map('trim', explode('::', $raw_pair, 2));
            if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $pairs[] = ['q' => $parts[0], 'a' => $parts[1]];
            }
        }

        $valid = FaqResolver::parseFaqItems($pairs);
        if ($valid === []) {
            return '';
        }

        if (FaqResolver::getRegisteredItems() === []) {
            FaqResolver::registerItems($valid);
        }

        ob_start();
        ?>
        <div class="drv-faq-group">
            <?php foreach ($valid as $idx => $item) : ?>
                <details class="drv-faq-item"<?php echo ($open_first && $idx === 0) ? ' open' : ''; ?>>
                    <summary class="drv-faq-summary">
                        <span><?php echo esc_html($item['q']); ?></span>
                        <i class="ph-bold ph-caret-down drv-icon--xs" aria-hidden="true"></i>
                    </summary>
                    <div class="drv-faq-answer">
                        <p><?php echo self::sanitizeInlineHtml($item['a']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Shortcode con `[drv_faq_item q="Câu hỏi?"]Câu trả lời...[/drv_faq_item]`.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderFaqItem(array|string $atts = [], ?string $content = null): string {
        $a = self::parseAtts($atts, ['q' => '', 'a' => '']);
        $q = trim((string) $a['q']);
        $ans = trim((string) ($content ?? '')) !== '' ? trim((string) $content) : trim((string) $a['a']);

        if ($q !== '' && $ans !== '') {
            self::$pending_faq_items[] = ['q' => $q, 'a' => $ans];
        }

        return '';
    }

    // =========================================================================
    // NHÓM B — 6 SHORTCODES THƯƠNG MẠI ĐỘNG (`docs/20 §4.1–4.6`)
    // =========================================================================

    /**
     * 4.1 `[drv_products collection="gach-terrazzo" skus="" limit="4" layout="grid|carousel" title="..."]`
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderProducts(array|string $atts = []): string {
        if (!drv_is_woo_active()) {
            return '';
        }

        $a          = self::parseAtts($atts, [
            'collection' => '',
            'skus'       => '',
            'limit'      => '4',
            'layout'     => 'grid',
            'title'      => '',
        ]);
        $collection = strtolower(trim((string) $a['collection']));
        $skus       = self::parseSkuList((string) $a['skus']);
        $limit      = min(8, max(2, abs((int) $a['limit'])));
        $layout     = in_array(strtolower(trim((string) $a['layout'])), ['grid', 'carousel'], true)
            ? strtolower(trim((string) $a['layout']))
            : 'grid';

        $products = self::resolveProducts($skus, $collection, $limit);
        if ($products === []) {
            return '';
        }

        if (function_exists('do_action')) {
            do_action('litespeed_tag_add', 'drv_catalog');
        }

        $collections_cfg = Config::get('collections');
        $has_valid_col   = ($collection !== '' && isset($collections_cfg[$collection]));
        $col_name        = $has_valid_col ? (string) ($collections_cfg[$collection]['name'] ?? $collection) : 'Gạch ốp lát DRAVIT';
        $col_url         = $has_valid_col ? HomeData::siteUrl('/' . $collection) : HomeData::siteUrl('/gach');
        $col_count       = $has_valid_col ? HomeData::collectionCount($collection, count($products)) : 0;

        $title = trim((string) $a['title']);
        if ($title === '') {
            $title = $has_valid_col
                ? sprintf('Mẫu %s tiêu biểu sẵn kho DRAVIT', $col_name)
                : 'Mẫu gạch tiêu biểu tại kho DRAVIT';
        }

        $boq_url = HomeData::siteUrl('/danh-sach-bao-gia');

        ob_start();
        ?>
        <section class="drv-product-embed-box" data-drv-carousel-wrap aria-label="<?php echo esc_attr($title); ?>">
            <div class="drv-product-embed-box__head">
                <div class="l-cluster l-cluster--xs">
                    <i class="ph-fill ph-squares-four drv-icon u-text-moss" aria-hidden="true"></i>
                    <strong class="u-text-sm u-text-navy"><?php echo esc_html($title); ?></strong>
                </div>

                <?php if ($layout === 'carousel' || count($products) > 3) : ?>
                    <div class="drv-product-embed-box__nav" data-carousel-nav aria-label="Điều hướng danh sách mẫu gạch">
                        <button type="button"
                                class="drv-carousel-nav-btn"
                                data-drv-action="scroll-carousel"
                                data-drv-dir="prev"
                                data-carousel-prev
                                aria-label="Xem mẫu trước">
                            <i class="ph-bold ph-caret-left" aria-hidden="true"></i>
                        </button>
                        <button type="button"
                                class="drv-carousel-nav-btn"
                                data-drv-action="scroll-carousel"
                                data-drv-dir="next"
                                data-carousel-next
                                aria-label="Xem mẫu tiếp theo">
                            <i class="ph-bold ph-caret-right" aria-hidden="true"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="drv-product-embed-box__grid<?php echo $layout === 'grid' ? ' is-static-grid' : ''; ?>" data-drv-carousel>
                <?php foreach ($products as $product) : ?>
                    <?php
                    drv_ui('product-card', [
                        'product'     => $product,
                        'context'     => 'embed',
                        'heading_tag' => 'h4',
                    ]);
                    ?>
                <?php endforeach; ?>
            </div>

            <div class="drv-product-embed-box__foot">
                <span class="u-text-xs u-text-muted">
                    <i class="ph-fill ph-stack drv-icon--xs u-text-moss" aria-hidden="true"></i>
                    Chiết khấu dự kiến <strong>5%</strong> từ 30 m² và <strong>8%</strong> từ 100 m² trên Bảng dự toán BOQ.
                </span>
                <div class="l-cluster l-cluster--xs">
                    <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                        <i class="ph ph-clipboard-text drv-icon--xs" aria-hidden="true"></i>
                        <span>Lập dự toán BOQ</span>
                    </a>
                    <?php if ($has_valid_col) : ?>
                        <a href="<?php echo esc_url($col_url); ?>" class="drv-btn drv-btn--primary drv-btn--sm">
                            <span>
                                <?php
                                echo esc_html(
                                    $col_count > 0
                                        ? sprintf('Xem trọn bộ %d+ mẫu %s', $col_count, $col_name)
                                        : sprintf('Xem trọn bộ %s', $col_name)
                                );
                                ?>
                            </span>
                            <i class="ph-bold ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 4.2 `[drv_shop_the_look sku="DRV-TRZ60,DRV-3612T" role="Gạch lát sàn trong ảnh" caption="..."]`
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderShopTheLook(array|string $atts = []): string {
        if (!drv_is_woo_active()) {
            return '';
        }

        $a       = self::parseAtts($atts, [
            'sku'     => '',
            'role'    => 'Gạch sử dụng trong ảnh',
            'caption' => '',
        ]);
        $skus    = array_slice(self::parseSkuList((string) $a['sku']), 0, 2);
        $roles   = array_map('trim', explode('|', (string) $a['role']));
        $caption = trim((string) $a['caption']);

        if ($skus === []) {
            return '';
        }

        $products = self::resolveProducts($skus, '', 2);
        if ($products === []) {
            return '';
        }

        if (function_exists('do_action')) {
            do_action('litespeed_tag_add', 'drv_catalog');
        }

        $items = [];
        foreach ($products as $idx => $prod) {
            $role_lbl = $roles[$idx] ?? ($roles[0] !== '' ? $roles[0] : 'Gạch sử dụng trong ảnh');
            $items[]  = [
                'product' => $prod,
                'role'    => $role_lbl,
            ];
        }

        return drv_ui_html('shop-the-look', [
            'items'   => $items,
            'caption' => $caption,
        ]);
    }

    /**
     * 4.3 `[drv_price_table skus="..." collection="" limit="8" caption="..."]`
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderPriceTable(array|string $atts = []): string {
        if (!drv_is_woo_active()) {
            return '';
        }

        $a          = self::parseAtts($atts, [
            'skus'       => '',
            'collection' => '',
            'limit'      => '8',
            'caption'    => 'Bảng quy cách đóng gói và đơn giá niêm yết cập nhật trực tiếp từ kho DRAVIT',
        ]);
        $skus       = self::parseSkuList((string) $a['skus']);
        $collection = strtolower(trim((string) $a['collection']));
        $limit      = min(12, max(1, abs((int) $a['limit'])));
        $caption    = trim((string) $a['caption']);

        $products = self::resolveProducts($skus, $collection, $limit);
        if ($products === []) {
            return '';
        }

        if (function_exists('do_action')) {
            do_action('litespeed_tag_add', 'drv_catalog');
        }

        $sync_date = function_exists('wp_date') ? (string) wp_date('d/m/Y') : gmdate('d/m/Y');
        $boq_url   = HomeData::siteUrl('/danh-sach-bao-gia');

        ob_start();
        ?>
        <div class="drv-price-table-block">
            <div class="drv-geo-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr($caption); ?>">
                <div class="drv-table-scroll-hint">
                    <i class="ph-bold ph-arrows-out-line-horizontal drv-icon--xs" aria-hidden="true"></i>
                    <span>Vuốt ngang bảng để xem quy cách thùng và thêm nhanh vào Báo giá</span>
                </div>
                <table class="drv-geo-table">
                    <?php if ($caption !== '') : ?>
                        <caption><?php echo esc_html($caption); ?></caption>
                    <?php endif; ?>
                    <thead>
                        <tr>
                            <th scope="col">Mã &amp; Tên mẫu gạch</th>
                            <th scope="col">Kích thước (cm)</th>
                            <th scope="col">Bề mặt / Chống trượt R</th>
                            <th scope="col">Quy cách thùng</th>
                            <th scope="col">Đơn giá niêm yết</th>
                            <th scope="col">Dự toán BOQ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product) : ?>
                            <?php $row = self::formatPriceTableRow($product); ?>
                            <tr>
                                <th scope="row">
                                    <div class="drv-price-table__name-cell">
                                        <?php if ($row['swatch_url'] !== '') : ?>
                                            <img src="<?php echo esc_url($row['swatch_url']); ?>"
                                                 alt=""
                                                 width="48"
                                                 height="48"
                                                 loading="lazy"
                                                 class="drv-price-table__swatch">
                                        <?php else : ?>
                                            <span class="drv-price-table__swatch drv-price-table__swatch--placeholder" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="drv-price-table__name-info">
                                            <?php if ($row['role_label'] !== '') : ?>
                                                <span class="drv-price-table__role"><?php echo esc_html(mb_strtoupper($row['role_label'])); ?></span>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url($row['url']); ?>" class="drv-price-table__link">
                                                <?php echo esc_html($row['name']); ?>
                                            </a>
                                            <?php if ($row['sku'] !== '') : ?>
                                                <span class="drv-price-table__sku u-tabular"><?php echo esc_html($row['sku']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </th>
                                <td class="u-tabular"><?php echo esc_html($row['size_cm']); ?></td>
                                <td><?php echo esc_html($row['surface_r']); ?></td>
                                <td class="u-tabular"><?php echo esc_html($row['pack_spec']); ?></td>
                                <td class="u-tabular">
                                    <?php if ($row['is_quote']) : ?>
                                        <span class="u-bold u-text-navy">Liên hệ báo giá</span>
                                    <?php else : ?>
                                        <strong><?php echo esc_html($row['price_m2_text']); ?></strong>
                                        <span class="drv-price-table__sub"><?php echo esc_html($row['price_piece_text']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button"
                                            class="drv-btn drv-btn--xs drv-btn--outline"
                                            data-drv-action="quote-add"
                                            data-product-id="<?php echo esc_attr((string) $row['id']); ?>"
                                            data-sku="<?php echo esc_attr($row['sku']); ?>"
                                            data-drv-area="15">
                                        <i class="ph-bold ph-plus drv-icon--xs" aria-hidden="true"></i>
                                        <span>Báo giá</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="drv-price-table__footnote u-text-xs u-text-muted">
                <span>Đơn giá đồng bộ trực tiếp từ kho ngày <strong class="u-tabular"><?php echo esc_html($sync_date); ?></strong>.</span>
                <a href="<?php echo esc_url($boq_url); ?>">Mở công cụ tính chẵn thùng theo diện tích phòng</a>
            </p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 4.4 `[drv_lead_form type="sample|consult" title="..." sku=""]`
     * Kết nối Dual-Mode `FluentFormsBridge` (`blog_inline`) hoặc Native Form chống spam 5 lớp (`D-30`, `D-31`).
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderLeadForm(array|string $atts = []): string {
        $a    = self::parseAtts($atts, [
            'type'  => 'sample',
            'title' => '',
            'sku'   => '',
        ]);
        $type = strtolower(trim((string) $a['type']));
        if (!in_array($type, ['sample', 'consult'], true)) {
            $type = 'sample';
        }

        // Quy tắc 20 §4.4: Nếu tính năng gửi mẫu thật tắt -> tự chuyển sang chế độ tư vấn mặt bằng `consult`.
        if ($type === 'sample' && !(bool) drv_setting('features.sample_order_enabled', true)) {
            $type = 'consult';
        }

        $is_sample = ($type === 'sample');
        $title     = trim((string) $a['title']);
        if ($title === '') {
            $title = $is_sample
                ? 'Đăng ký nhận mẫu gạch thật đối chiếu tại công trình'
                : 'Gửi diện tích mặt bằng để kỹ thuật viên tính số thùng chuẩn';
        }

        $sku        = trim((string) $a['sku']);
        $product_id = 0;
        if ($sku !== '' && function_exists('wc_get_product_id_by_sku')) {
            $product_id = max(0, (int) wc_get_product_id_by_sku($sku));
        }

        $post_id  = function_exists('get_the_ID') ? max(0, (int) get_the_ID()) : 0;
        $sla_text = (string) drv_setting('order.sla_text', '');
        $source   = $is_sample ? 'sample' : 'blog_inline';
        $uid_sfx  = substr(md5($source . '|' . $post_id . '|' . $sku . '|' . $title), 0, 6);

        ob_start();
        ?>
        <section class="drv-inline-lead-box" aria-label="<?php echo esc_attr($title); ?>">
            <div class="drv-inline-lead-content">
                <?php
                drv_ui('badge', [
                    'text'    => $is_sample ? 'Đối chiếu mẫu thật tại công trình' : 'Hỗ trợ kỹ thuật dự toán',
                    'variant' => 'outline',
                ]);
                ?>
                <p class="drv-inline-lead-box__title"><?php echo esc_html($title); ?></p>
                <p class="drv-inline-lead-box__desc">
                    <?php
                    echo esc_html(
                        $is_sample
                            ? 'So trực tiếp độ nhám chống trượt R và sắc độ vân gạch dưới ánh đèn thực tế trước khi chốt khối lượng.'
                            : 'Để lại thông tin mặt bằng cần ốp lát, kỹ thuật viên DRAVIT sẽ quy đổi số thùng chẵn và kiểm tra tồn kho cùng mã lô (Lot).'
                    );
                    ?>
                </p>
                <div class="drv-inline-lead-perks">
                    <span><i class="ph-bold ph-check drv-icon--xs" aria-hidden="true"></i> Kiểm soát xuất kho cùng mã lô (Lot)</span>
                    <span><i class="ph-bold ph-check drv-icon--xs" aria-hidden="true"></i> Báo giá minh bạch theo m² &amp; thùng</span>
                </div>
            </div>

            <div class="drv-inline-lead-form">
                <?php
                $ff_context = array_filter([
                    'drv_product_id' => $product_id > 0 ? (string) $product_id : '',
                    'drv_sku'        => $sku,
                    'drv_post_id'    => $post_id > 0 ? (string) $post_id : '',
                    'drv_lead_mode'  => $type,
                ]);

                if (!FluentFormsBridge::render_slot('blog_inline', $ff_context)) {
                    if (function_exists('get_template_part')) {
                        get_template_part('template-parts/leads/native-form', null, [
                            'source'              => $source,
                            'id_prefix'           => 'blog-lead-' . $uid_sfx,
                            'fields'              => $is_sample ? ['message'] : ['province', 'area', 'message'],
                            'product_id'          => $product_id,
                            'post_id'             => $post_id,
                            'submit_label'        => 'Gửi yêu cầu báo giá',
                            'message_label'       => $is_sample ? 'Mã gạch hoặc địa chỉ nhận mẫu' : 'Hạng mục & diện tích cần tính',
                            'message_placeholder' => $sku !== ''
                                ? sprintf('Cần đối chiếu mẫu %s cho sàn phòng khách...', $sku)
                                : 'Ví dụ: Cần mẫu gạch lát nhà tắm R10 và tính số thùng cho sàn 28 m²...',
                            'full'                => true,
                        ]);
                    }
                }
                ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 4.5 `[drv_related_posts ids="142,158" limit="2" label="Xem thêm hướng dẫn liên quan"]`
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderRelatedPosts(array|string $atts = []): string {
        if (!function_exists('get_posts')) {
            return '';
        }

        $a       = self::parseAtts($atts, [
            'ids'   => '',
            'limit' => '2',
            'label' => 'Xem thêm hướng dẫn kỹ thuật liên quan',
        ]);
        $limit   = min(3, max(1, abs((int) $a['limit'])));
        $label   = trim((string) $a['label']);
        $cur_id  = function_exists('get_the_ID') ? max(0, (int) get_the_ID()) : 0;
        $raw_ids = array_values(array_filter(
            array_map('intval', preg_split('/[\s,]+/', trim((string) $a['ids'])) ?: []),
            static fn (int $id): bool => $id > 0 && $id !== $cur_id
        ));
        if ($raw_ids === [] && $cur_id > 0) {
            $raw_ids = PostMeta::getRelatedPostIds($cur_id);
        }

        $posts = [];
        if ($raw_ids !== []) {
            $queried = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'post__in'            => array_slice($raw_ids, 0, $limit),
                'orderby'             => 'post__in',
                'posts_per_page'      => $limit,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);
            if (is_array($queried)) {
                $posts = $queried;
            }
        } else {
            $query_args = [
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $limit,
                'post__not_in'        => $cur_id > 0 ? [$cur_id] : [],
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ];
            if ($cur_id > 0 && function_exists('wp_get_post_categories')) {
                $cats = wp_get_post_categories($cur_id);
                if (is_array($cats) && $cats !== []) {
                    $query_args['category__in'] = array_map('intval', $cats);
                }
            }
            $queried = get_posts($query_args);
            if ((!is_array($queried) || $queried === []) && isset($query_args['category__in'])) {
                unset($query_args['category__in']);
                $queried = get_posts($query_args);
            }
            if (is_array($queried)) {
                $posts = $queried;
            }
        }

        if ($posts === []) {
            return '';
        }

        $blog_url = HomeData::siteUrl('/cam-nang');

        ob_start();
        ?>
        <aside class="drv-inline-related" aria-label="<?php echo esc_attr($label); ?>">
            <div class="drv-inline-related__head">
                <div class="l-cluster l-cluster--xs">
                    <i class="ph-fill ph-book-open-text drv-icon u-text-moss" aria-hidden="true"></i>
                    <strong class="u-text-sm u-text-navy"><?php echo esc_html($label); ?></strong>
                </div>
                <a href="<?php echo esc_url($blog_url); ?>" class="drv-inline-related__more">
                    <span>Cẩm nang kỹ thuật</span>
                    <i class="ph-bold ph-arrow-right drv-icon--xs" aria-hidden="true"></i>
                </a>
            </div>
            <div class="drv-inline-related__list">
                <?php foreach ($posts as $p) :
                    if (!$p instanceof \WP_Post || $p->post_name === 'hello-world') {
                        continue;
                    }
                    $card = BlogSetup::mapPostCard($p);
                    ?>
                    <a href="<?php echo esc_url($card['url']); ?>" class="drv-inline-related__item">
                        <i class="ph ph-file-text drv-icon u-text-moss" aria-hidden="true"></i>
                        <div class="drv-inline-related__body">
                            <span class="drv-inline-related__meta"><?php echo esc_html($card['cat_name'] . ' · ' . $card['reading_label']); ?></span>
                            <strong class="drv-inline-related__title"><?php echo esc_html($card['title']); ?></strong>
                        </div>
                        <span class="drv-inline-related__arrow" aria-hidden="true">
                            <i class="ph-bold ph-arrow-up-right"></i>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * 4.6 `[drv_ai_card prompts="Câu 1|Câu 2" title="..."]` — Khóa cứng theo cờ `features.ai_enabled` (`D-25` / `D-32`).
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderAiCard(array|string $atts = []): string {
        if (!(bool) drv_setting('features.ai_enabled', false)) {
            return '';
        }

        $a       = self::parseAtts($atts, [
            'title'   => 'Cần chọn mẫu gạch và tính số thùng theo diện tích thực tế?',
            'prompts' => '',
        ]);
        $title   = trim((string) $a['title']);
        $prompts = self::splitPipeOrLines((string) $a['prompts']);

        if ($prompts === [] && function_exists('get_the_ID')) {
            $post_id = (int) get_the_ID();
            if ($post_id > 0) {
                $prompts = PostMeta::getAiPrompts($post_id);
            }
        }

        if ($prompts === []) {
            $prompts = [
                'Nhà tắm 6 m² nên lát gạch 30x60 hay 60x60 chống trượt R10?',
                'Tính giúp số thùng gạch 80x80 cho phòng khách 32 m² (hao hụt 5%)',
            ];
        }

        $ai_url = HomeData::siteUrl('/tro-ly-ai-tim-gach');

        ob_start();
        ?>
        <div class="drv-blog-ai-card" role="region" aria-label="Trợ lý AI tư vấn chọn gạch theo thông số">
            <div class="drv-blog-ai-card__intro">
                <span class="drv-blog-ai-card__status">
                    <span class="drv-blog-ai-card__icon" aria-hidden="true">
                        <i class="ph-fill ph-robot"></i>
                    </span>
                    <span class="drv-blog-ai-card__live"><span class="drv-blog-ai-card__dot" aria-hidden="true"></span>Trợ lý AI · Trực tuyến</span>
                </span>
                <p class="drv-blog-ai-card__title"><?php echo esc_html($title); ?></p>
                <p class="drv-blog-ai-card__desc">Lọc đúng chỉ số chống trượt R theo không gian và quy đổi ngay số thùng chẵn từ dữ liệu kho DRAVIT.</p>
                <div class="drv-blog-ai-card__foot">
                    <a href="<?php echo esc_url($ai_url); ?>" class="drv-btn drv-btn--moss">
                        <i class="ph-fill ph-sparkle drv-icon--xs" aria-hidden="true"></i>
                        <span>Mở công cụ Trợ lý tìm gạch</span>
                    </a>
                </div>
            </div>

            <div class="drv-blog-ai-card__ask">
                <span class="drv-blog-ai-card__ask-label">Hỏi thử ngay</span>
                <div class="drv-blog-ai-card__prompts" role="group" aria-label="Câu hỏi gợi ý nhanh">
                    <?php foreach (array_slice($prompts, 0, 3) as $prompt) : ?>
                        <button type="button"
                                class="drv-blog-ai-card__prompt"
                                data-drv-action="ai-finder-open"
                                data-drv-prompt="<?php echo esc_attr($prompt); ?>">
                            <i class="ph ph-chat-circle-text drv-icon--xs" aria-hidden="true"></i>
                            <span><?php echo esc_html($prompt); ?></span>
                            <i class="ph-bold ph-arrow-up-right drv-icon--xs drv-blog-ai-card__go" aria-hidden="true"></i>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

// =========================================================================
    // NHÓM C — `[drv_info]`: THÔNG TIN THƯƠNG HIỆU ĐỘNG TỪ `drv_settings`
    // =========================================================================

    /**
     * Danh sách khóa được phép đọc. Sửa ở Admin → Cài đặt Hệ thống DRAVIT là mọi trang tự đồng bộ.
     *
     * @return array<string, string> khóa => mô tả
     */
    public static function infoKeys(): array {
        return [
            'brand'               => 'Tên thương hiệu',
            'tagline'             => 'Khẩu hiệu',
            'legal_name'          => 'Tên pháp nhân',
            'tax_id'              => 'Mã số thuế',
            'address'             => 'Địa chỉ trụ sở',
            'email'               => 'Email CSKH',
            'project_email'       => 'Email phòng dự án B2B',
            'hotline'             => 'Hotline',
            'zalo'                => 'Số Zalo',
            'hours'               => 'Khung giờ làm việc',
            'showroom_name'       => 'Tên Showroom chính',
            'showroom_address'    => 'Địa chỉ Showroom',
            'showroom_hours'      => 'Giờ mở cửa Showroom',
            'sla'                 => 'Thời gian phản hồi (SLA)',
            'transfer_due_days'   => 'Số ngày giữ đơn chờ chuyển khoản',
            'auto_cancel_hours'   => 'Số giờ tự hủy đơn chưa thanh toán',
            'waste_pct'           => 'Tỷ lệ hao hụt mặc định (%)',
            'bank_name'           => 'Tên ngân hàng',
            'bank_account_name'   => 'Chủ tài khoản',
            'bank_branch'         => 'Chi nhánh ngân hàng',
        ];
    }

    /**
     * Giá trị thô của 1 khóa thông tin thương hiệu.
     */
    public static function infoValue(string $key): string {
        $rooms = drv_setting('showrooms', []);
        $room  = (is_array($rooms) && isset($rooms[0]) && is_array($rooms[0])) ? $rooms[0] : [];

        return match ($key) {
            'brand'             => (string) drv_setting('company.brand_name', 'DRAVIT'),
            'tagline'           => (string) drv_setting('company.tagline', ''),
            'legal_name'        => (string) drv_setting('company.legal_name', ''),
            'tax_id'            => (string) drv_setting('company.tax_id', ''),
            'address'           => (string) drv_setting('company.address', ''),
            'email'             => (string) drv_setting('contact.email', (string) drv_setting('company.email', '')),
            'project_email'     => (string) drv_setting('contact.project_email', ''),
            'hotline'           => (string) drv_setting('contact.hotline', ''),
            'zalo'              => (string) drv_setting('contact.zalo', ''),
            'hours'             => (string) drv_setting('contact.working_hours', ''),
            'showroom_name'     => (string) ($room['name'] ?? ''),
            'showroom_address'  => (string) ($room['address'] ?? ''),
            'showroom_hours'    => (string) ($room['hours'] ?? ''),
            'sla'               => (string) drv_setting('order.sla_text', ''),
            'transfer_due_days' => (string) drv_setting('order.transfer_due_days', '3'),
            'auto_cancel_hours' => (string) drv_setting('order.auto_cancel_hours', '72'),
            'waste_pct'         => (string) drv_setting('order.default_waste_pct', '5'),
            'bank_name'         => (string) drv_setting('bank.bank_name', ''),
            'bank_account_name' => (string) drv_setting('bank.account_name', ''),
            'bank_branch'       => (string) drv_setting('bank.branch', ''),
            default             => '',
        };
    }

    /**
     * `[drv_info key="hotline" link="tel"]` → in giá trị cấu hình, tùy chọn bọc liên kết `tel` | `mail` | `zalo`.
     *
     * @param array<string, mixed>|string $atts
     */
    public static function renderInfo(array|string $atts = []): string {
        $a   = self::parseAtts($atts, ['key' => '', 'link' => '', 'fallback' => '']);
        $key = trim((string) $a['key']);
        if (!isset(self::infoKeys()[$key])) {
            return '';
        }

        $value = trim(self::infoValue($key));
        if ($value === '') {
            return esc_html((string) $a['fallback']);
        }

        $link = trim((string) $a['link']);
        $text = esc_html($value);

        if ($link === 'tel') {
            return '<a href="' . esc_url('tel:' . drv_clean_phone($value)) . '">' . $text . '</a>';
        }
        if ($link === 'mail') {
            return '<a href="' . esc_url('mailto:' . $value) . '">' . $text . '</a>';
        }
        if ($link === 'zalo') {
            return '<a href="' . esc_url('https://zalo.me/' . drv_clean_phone($value)) . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
        }

        return $text;
    }

    // =========================================================================
    // HELPERS THUẦN & TRUY VẤN SẢN PHẨM CHO SHORTCODES
    // =========================================================================

    /**
     * Đóng gói 1 dòng thông số sản phẩm cho `[drv_price_table]` (hàm thuần trên `\WC_Product`, hỗ trợ test `priced` vs `quote_only`).
     *
     * @return array{
     *   id: int,
     *   sku: string,
     *   name: string,
     *   url: string,
     *   size_cm: string,
     *   surface_r: string,
     *   pack_spec: string,
     *   is_quote: bool,
     *   price_m2_text: string,
     *   price_piece_text: string
     * }
     */
    public static function formatPriceTableRow(\WC_Product $product): array {
        $id       = $product->get_id();
        $sku      = (string) $product->get_sku();
        $name     = $product->get_name();
        $raw_link = function_exists('get_permalink') && $id > 0 ? (string) get_permalink($id) : ('/san-pham/' . $product->get_slug());
        $url      = UrlNormalizer::untrailingslashUrl($raw_link);

        $state    = PriceState::resolve($product);
        $is_quote = ($state === PriceState::QUOTE_ONLY);
        $unit_cfg = PriceState::readUnitConfig($product);

        $w_mm    = max(0, (int) $product->get_meta('drv_piece_w_mm', true));
        $h_mm    = max(0, (int) $product->get_meta('drv_piece_h_mm', true));
        $size_cm = ($w_mm > 0 && $h_mm > 0) ? AttributeDeriver::deriveSizeLabel($w_mm, $h_mm) : '-';

        $surface = self::firstTermLabel($id, 'pa_be-mat');
        $dcof    = self::firstTermLabel($id, 'pa_dcof');
        $surf_r  = implode(' · ', array_values(array_filter([$surface, $dcof])));
        if ($surf_r === '') {
            $surf_r = '-';
        }

        $ppb        = $unit_cfg['pieces_per_box'];
        $unit_label = $unit_cfg['unit_label'];
        $area_piece = Calc::pieceAreaM2($w_mm, $h_mm);
        $m2_box     = (float) $product->get_meta('drv_m2_per_box', true);
        if ($m2_box <= 0.0 && $area_piece > 0.0) {
            $m2_box = round($area_piece * $ppb, 4);
        }
        $kg_box = max(0.0, (float) $product->get_meta('drv_kg_per_box', true));

        $pack_parts = [sprintf('%d %s', $ppb, $unit_label)];
        if ($m2_box > 0.0) {
            $pack_parts[0] .= ' = ' . drv_format_decimal($m2_box, 2) . ' m²';
        }
        if ($kg_box > 0.0) {
            $pack_parts[] = '(' . drv_format_decimal($kg_box, 1) . ' kg)';
        }
        $pack_spec = implode(' ', $pack_parts);

        $reg_m2   = max(0, (int) $product->get_meta('drv_price_m2', true));
        $sale_m2  = max(0, (int) $product->get_meta('drv_sale_price_m2', true));
        $eff_m2   = ($state === PriceState::SALE && $sale_m2 > 0 && $sale_m2 < $reg_m2) ? $sale_m2 : $reg_m2;
        $piece_vn = $is_quote ? 0 : (int) round((float) $product->get_price());

        $img_id     = $product->get_image_id();
        $swatch_url = $img_id > 0 && function_exists('wp_get_attachment_image_url')
            ? (string) wp_get_attachment_image_url($img_id, 'thumbnail')
            : '';
        if ($swatch_url === '') {
            $swatch_url = DRV_URI . '/assets/images/placeholders/tile-marble-calacatta.svg';
        }

        // Lấy role (loại gạch) từ category đầu tiên để hiển thị label nhỏ
        $term_ids   = $product->get_category_ids();
        $role_label = '';
        if (!empty($term_ids) && function_exists('get_term')) {
            $term = get_term((int) $term_ids[0], 'product_cat');
            if ($term instanceof \WP_Term) {
                $role_label = $term->name;
            }
        }

        return [
            'id'               => $id,
            'sku'              => $sku,
            'name'             => $name,
            'url'              => $url,
            'swatch_url'       => $swatch_url,
            'role_label'       => $role_label,
            'size_cm'          => $size_cm,
            'surface_r'        => $surf_r,
            'pack_spec'        => $pack_spec,
            'is_quote'         => $is_quote || $eff_m2 <= 0,
            'price_m2_text'    => $eff_m2 > 0 ? (drv_format_vnd($eff_m2) . '/m²') : 'Liên hệ báo giá',
            'price_piece_text' => $piece_vn > 0 ? sprintf('≈ %s/%s', drv_format_vnd($piece_vn), $unit_label) : '',
        ];
    }

    /**
     * Truy vấn danh sách `\WC_Product` theo thứ tự mã SKU chỉ định hoặc theo Smart Collection.
     *
     * @param array<int, string> $skus
     * @return array<int, \WC_Product>
     */
    private static function resolveProducts(array $skus, string $collection, int $limit): array {
        $products = [];

        if ($skus !== [] && function_exists('wc_get_product_id_by_sku') && function_exists('wc_get_product')) {
            foreach ($skus as $sku) {
                $pid = (int) wc_get_product_id_by_sku($sku);
                if ($pid <= 0) {
                    continue;
                }
                $prod = wc_get_product($pid);
                if ($prod instanceof \WC_Product && $prod->get_status() === 'publish') {
                    $products[] = $prod;
                }
                if (count($products) >= $limit) {
                    break;
                }
            }
        }

        if ($products === [] && function_exists('wc_get_products')) {
            $query = [
                'status'  => 'publish',
                'limit'   => $limit,
                'orderby' => 'menu_order',
                'order'   => 'ASC',
            ];
            $collections_cfg = Config::get('collections');
            if ($collection !== '' && isset($collections_cfg[$collection])) {
                $query['category'] = [$collection];
            }
            $queried = wc_get_products($query);
            if (is_array($queried)) {
                foreach ($queried as $item) {
                    if ($item instanceof \WC_Product) {
                        $products[] = $item;
                    }
                }
            }
        }

        return array_slice($products, 0, $limit);
    }

    private static function firstTermLabel(int $product_id, string $taxonomy): string {
        if ($product_id <= 0 || !function_exists('get_the_terms')) {
            return '';
        }

        $terms = get_the_terms($product_id, $taxonomy);
        if (!is_array($terms) || !isset($terms[0]) || !$terms[0] instanceof \WP_Term) {
            return '';
        }

        if (function_exists('get_term_meta')) {
            $short = trim((string) get_term_meta($terms[0]->term_id, 'drv_short_label', true));
            if ($short !== '') {
                return $short;
            }
        }

        return $terms[0]->name;
    }

    /**
     * @param array<string, mixed>|string $atts
     * @param array<string, string>       $defaults
     * @return array<string, mixed>
     */
    private static function parseAtts(array|string $atts, array $defaults): array {
        $raw = is_array($atts) ? $atts : [];
        if (function_exists('shortcode_atts')) {
            return shortcode_atts($defaults, $raw);
        }

        return array_merge($defaults, $raw);
    }

    /**
     * @return array<int, string>
     */
    private static function parseSkuList(string $raw): array {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,|]+/', trim($raw)) ?: [];
        $skus  = [];
        foreach ($parts as $part) {
            $clean = trim((string) preg_replace('/[^A-Za-z0-9\-_]/', '', $part));
            if ($clean !== '' && !in_array($clean, $skus, true)) {
                $skus[] = $clean;
            }
        }

        return $skus;
    }

    /**
     * @return array<int, string>
     */
    private static function splitPipeOrLines(string $raw): array {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $parts = str_contains($trimmed, '|')
            ? explode('|', $trimmed)
            : (preg_split('/[\r\n]+/', $trimmed) ?: []);

        $out = [];
        foreach ($parts as $part) {
            $clean = trim($part);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }

        return $out;
    }

    private static function sanitizeInlineHtml(string $html): string {
        if (function_exists('wp_kses')) {
            return wp_kses($html, [
                'strong' => [],
                'b'      => [],
                'em'     => [],
                'code'   => [],
                'br'     => [],
                'a'      => [
                    'href'  => true,
                    'title' => true,
                    'class' => true,
                ],
            ]);
        }

        return strip_tags($html, '<strong><b><em><code><br><a>');
    }

    private static function sanitizeRichHtml(string $html): string {
        if (function_exists('wp_kses_post')) {
            return wp_kses_post(function_exists('wpautop') ? wpautop($html) : $html);
        }

        return strip_tags($html, '<p><strong><b><em><code><br><a><ul><ol><li>');
    }
}
