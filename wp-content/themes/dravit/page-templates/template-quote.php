<?php
/**
 * Template Name: Danh sách Báo giá BOQ
 *
 * Trang `/danh-sach-bao-gia` (docs/17, `04.1-quote-cart.html`): HTML SSR chỉ là khung an toàn cache,
 * toàn bộ phòng / hạng mục đọc từ `localStorage['drv_quote_v2']` và hydrate giá tươi qua REST.
 * Robots `noindex, follow` + canonical sạch do `Seo\RobotsMatrix` / `Seo\Canonical` đảm nhiệm.
 *
 * @package Dravit
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();
?>
<main id="main-content" class="drv-page drv-page--quote">
    <?php drv_section('boq-workspace', ['title' => get_the_title() ?: 'Bảng báo giá gạch theo từng phòng']); ?>
</main>
<?php
get_footer();
