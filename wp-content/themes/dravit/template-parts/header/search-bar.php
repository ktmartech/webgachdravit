<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$search_action = MegaMenuRenderer::internal_url('/');
$all_gach_url  = MegaMenuRenderer::collection_url('gach');
$query_val     = function_exists('get_search_query') ? (string) get_search_query() : '';
?>
<div class="drv-search" data-drv-search>
    <div class="drv-search-backdrop" data-drv-search-backdrop hidden></div>
    <form class="drv-search__form" action="<?php echo esc_url($search_action); ?>" method="get" role="search">
        <i class="ph ph-magnifying-glass drv-search__icon" aria-hidden="true"></i>
        <label for="global-search-input" class="u-sr-only">Tìm mẫu gạch, mã SKU hoặc kích thước</label>
        <input id="global-search-input"
               name="s"
               type="search"
               value="<?php echo esc_attr($query_val); ?>"
               class="drv-search__input"
               placeholder="Tìm mẫu gạch, mã SKU (DRV-8801), kích thước 80x80..."
               autocomplete="off">
        <button type="submit" class="drv-search__submit" aria-label="Tìm kiếm">
            <span>Tìm</span>
        </button>
    </form>

    <div class="drv-search-drop" hidden aria-label="Gợi ý tìm kiếm tức thì">
        <div class="drv-search-drop__quick" data-search-quick>
            <span class="drv-search-drop__quick-label">
                <i class="ph ph-sparkle" aria-hidden="true"></i>
                <span>Từ khóa phổ biến:</span>
            </span>
            <button type="button" class="drv-search-tag" data-drv-action="chip-insert" data-drv-target="#global-search-input" data-drv-chip-value="80x80">80x80</button>
            <button type="button" class="drv-search-tag" data-drv-action="chip-insert" data-drv-target="#global-search-input" data-drv-chip-value="Marble">Vân đá Marble</button>
            <button type="button" class="drv-search-tag" data-drv-action="chip-insert" data-drv-target="#global-search-input" data-drv-chip-value="Terrazzo">Terrazzo</button>
            <button type="button" class="drv-search-tag" data-drv-action="chip-insert" data-drv-target="#global-search-input" data-drv-chip-value="60x120">Khổ lớn 60x120</button>
            <button type="button" class="drv-search-tag" data-drv-action="chip-insert" data-drv-target="#global-search-input" data-drv-chip-value="R10">Chống trượt R10</button>
        </div>
        <div class="drv-search-drop__grid">
            <div class="drv-search-drop__col drv-search-drop__col--silo">
                <div class="drv-search-drop__col-head">
                    <span class="drv-mega__heading" data-search-silo-heading>Danh mục &amp; Kích thước</span>
                </div>
                <ul class="drv-search-silo-list" data-search-silos></ul>
            </div>
            <div class="drv-search-drop__col">
                <div class="drv-search-drop__col-head">
                    <span class="drv-mega__heading" data-search-prod-heading>Sản phẩm khớp mã SKU / tên</span>
                    <a href="<?php echo esc_url($all_gach_url); ?>" class="drv-link u-text-xs" data-search-view-all>Xem tất cả danh mục</a>
                </div>
                <ul class="drv-search-prod-list" data-search-products></ul>
            </div>
        </div>
    </div>
</div>

