<?php
declare(strict_types=1);

use Dravit\Core\Menu\MegaMenuRenderer;

defined('ABSPATH') || exit;

$search_action = MegaMenuRenderer::internal_url('/');
$all_gach_url  = MegaMenuRenderer::collection_url('gach');
$query_val     = function_exists('get_search_query') ? (string) get_search_query() : '';
$popular_kws   = drv_setting('search.popular_keywords', ['80x80', 'Vân đá Marble', 'Terrazzo', 'Khổ lớn 60x120', 'Chống trượt R10']);
$popular_kws   = is_array($popular_kws) ? $popular_kws : [];
?>
<div class="drv-search" data-drv-search>
    <div class="drv-search-backdrop" data-drv-search-backdrop hidden></div>
    <form class="drv-search__form" action="<?php echo esc_url($search_action); ?>" method="get" role="search">
        <input type="hidden" name="post_type" value="product">
        <i class="ph ph-magnifying-glass drv-search__icon" aria-hidden="true"></i>
        <label for="global-search-input" class="u-sr-only">Tìm mẫu gạch, mã SKU hoặc kích thước</label>
        <input id="global-search-input"
               name="s"
               type="search"
               value="<?php echo esc_attr($query_val); ?>"
               class="drv-search__input"
               placeholder="Tìm mẫu gạch, mã SKU (DRV-8801), kích thước 80x80..."
               autocomplete="off"
               role="combobox"
               aria-autocomplete="list"
               aria-expanded="false"
               aria-controls="drv-search-panel"
               aria-activedescendant=""
               data-search-input>
        <button type="button"
                class="drv-search__clear"
                data-search-clear
                aria-label="Xóa từ khóa tìm kiếm"
                <?php echo $query_val === '' ? 'hidden' : ''; ?>>
            <i class="ph ph-x" aria-hidden="true"></i>
        </button>
        <button type="submit" class="drv-search__submit" aria-label="Tìm kiếm">
            <span>Tìm</span>
        </button>
    </form>

    <div id="drv-search-panel"
         class="drv-search-drop"
         role="listbox"
         aria-label="Gợi ý tìm kiếm tức thì"
         data-search-panel
         hidden>
        <?php if ($popular_kws !== []) : ?>
            <div class="drv-search-drop__quick" data-search-quick>
                <span class="drv-search-drop__quick-label">
                    <i class="ph ph-sparkle" aria-hidden="true"></i>
                    <span>Từ khóa phổ biến:</span>
                </span>
                <?php foreach ($popular_kws as $kw) :
                    $kw_text = trim((string) $kw);
                    if ($kw_text === '') {
                        continue;
                    }
                    ?>
                    <button type="button"
                            class="drv-search-tag"
                            data-drv-action="chip-insert"
                            data-drv-target="#global-search-input"
                            data-drv-chip-value="<?php echo esc_attr($kw_text); ?>">
                        <?php echo esc_html($kw_text); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="drv-search-drop__grid">
            <div class="drv-search-drop__col drv-search-drop__col--silo">
                <div class="drv-search-drop__col-head">
                    <span class="drv-mega__heading" data-search-silo-heading>Danh mục phổ biến</span>
                </div>
                <ul class="drv-search-silo-list" role="presentation" data-search-silos></ul>
            </div>
            <div class="drv-search-drop__col">
                <div class="drv-search-drop__col-head">
                    <span class="drv-mega__heading" data-search-prod-heading>Sản phẩm tìm kiếm nhiều</span>
                    <a href="<?php echo esc_url($all_gach_url); ?>" class="drv-link u-text-xs" data-search-view-all>Xem toàn bộ kho</a>
                </div>
                <ul class="drv-search-prod-list" role="presentation" data-search-products></ul>
            </div>
        </div>
        <p class="u-sr-only" aria-live="polite" data-search-status></p>
    </div>
</div>
