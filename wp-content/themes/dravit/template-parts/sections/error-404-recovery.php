<?php
declare(strict_types=1);

use Dravit\Seo\InternalLinks;
use Dravit\Seo\UrlNormalizer;

defined('ABSPATH') || exit;

/**
 * Khối điều hướng cứu hộ cho `404.php` và trạng thái tìm kiếm rỗng `search.php` (`drv_section('error-404-recovery')`, docs/07 §8.2, docs/16 §1.1).
 *
 * @var array<string, mixed> $args
 */
$eyebrow      = trim((string) ($args['eyebrow'] ?? 'Gợi ý tra cứu nhanh'));
$heading      = trim((string) ($args['heading'] ?? 'Không tìm thấy nội dung khớp yêu cầu'));
$message      = trim((string) ($args['message'] ?? 'Đường dẫn có thể đã thay đổi hoặc mã gạch bạn tìm hiện chưa cập nhật lên hệ thống trực tuyến.'));
$is_h1        = !empty($args['is_h1']);
$tag_name     = $is_h1 ? 'h1' : 'h2';
$search_value = function_exists('get_search_query') ? trim((string) get_search_query()) : '';

$silo_slugs = drv_setting('catalog.404_silos', ['gach-lat-nen', 'gach-phong-khach', 'gach-van-da', 'gach-80x80']);
$silo_slugs = is_array($silo_slugs) ? $silo_slugs : ['gach-lat-nen', 'gach-phong-khach', 'gach-van-da', 'gach-80x80'];
$silo_tiles = InternalLinks::getSiloTiles('', $silo_slugs, 4);

$keywords = drv_setting('search.popular_keywords', ['80x80', 'Vân đá Marble', 'Terrazzo', '60x120', 'R10']);
$keywords = is_array($keywords) ? $keywords : [];

$gach_url = UrlNormalizer::untrailingslashUrl(home_url('/gach'));
$boq_url  = UrlNormalizer::untrailingslashUrl(home_url('/danh-sach-bao-gia'));
$zalo     = drv_clean_phone((string) drv_setting('contact.zalo', ''));
$hotline  = (string) drv_setting('contact.hotline', '');
?>
<section class="l-section drv-recovery">
    <div class="l-container l-stack l-stack--lg">
        <div class="drv-recovery__hero l-stack l-stack--md">
            <div class="l-stack l-stack--xs">
                <?php if ($eyebrow !== '') : ?>
                    <span class="drv-sec-head__eyebrow"><?php echo esc_html($eyebrow); ?></span>
                <?php endif; ?>
                <<?php echo esc_attr($tag_name); ?> class="drv-page-title"><?php echo esc_html($heading); ?></<?php echo esc_attr($tag_name); ?>>
                <p class="u-text-muted"><?php echo esc_html($message); ?></p>
            </div>

            <form class="drv-recovery__search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <input type="hidden" name="post_type" value="product">
                <label class="u-sr-only" for="drv-recovery-search-input">Tìm mẫu gạch theo mã SKU, kích thước hoặc vân gạch</label>
                <div class="drv-recovery__search-box">
                    <?php drv_ui('icon', ['name' => 'magnifying-glass', 'size' => 'sm']); ?>
                    <input id="drv-recovery-search-input"
                           type="search"
                           class="drv-recovery__search-input"
                           name="s"
                           value="<?php echo esc_attr($search_value); ?>"
                           placeholder="Nhập mã SKU (VD: DRV-8801), kích thước 80x80, Terrazzo..."
                           autocomplete="off">
                    <button type="submit" class="drv-btn drv-btn--primary">
                        <span>Tìm mẫu gạch</span>
                    </button>
                </div>
            </form>

            <?php if ($keywords !== []) : ?>
                <div class="l-cluster l-cluster--sm">
                    <span class="u-text-xs u-text-muted">Thử tìm nhanh:</span>
                    <?php foreach ($keywords as $kw) :
                        $kw_str = trim((string) $kw);
                        if ($kw_str === '') {
                            continue;
                        }
                        $kw_url = add_query_arg([
                            's'         => $kw_str,
                            'post_type' => 'product',
                        ], home_url('/'));
                        ?>
                        <a href="<?php echo esc_url($kw_url); ?>" class="drv-chip"><?php echo esc_html($kw_str); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($silo_tiles !== []) : ?>
            <div class="l-stack l-stack--sm">
                <h2 class="u-text-base">Danh mục gạch chủ lực tại kho</h2>
                <div class="l-grid l-grid--auto-sm">
                    <?php foreach ($silo_tiles as $tile) : ?>
                        <?php drv_ui('sub-silo-tile', $tile); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="l-cluster l-cluster--md">
            <a href="<?php echo esc_url($gach_url); ?>" class="drv-btn drv-btn--primary">
                <?php drv_ui('icon', ['name' => 'squares-four', 'size' => 'sm']); ?>
                <span>Xem toàn bộ kho gạch</span>
            </a>
            <a href="<?php echo esc_url($boq_url); ?>" class="drv-btn drv-btn--outline">
                <?php drv_ui('icon', ['name' => 'clipboard-text', 'size' => 'sm']); ?>
                <span>Lập danh sách báo giá</span>
            </a>
            <?php if ($zalo !== '') : ?>
                <a href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" target="_blank" rel="noopener noreferrer" class="drv-btn drv-btn--ghost">
                    <?php drv_ui('icon', ['name' => 'chat-circle-dots', 'size' => 'sm']); ?>
                    <span>Gửi ảnh mẫu qua Zalo (<?php echo esc_html($hotline); ?>)</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
