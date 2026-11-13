<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Section: Trust Strip (`template-parts/sections/trust-strip.php`, `S-3`, `docs/07 §3.2`).
 * Lưới ron 1px (`--bw`) chia 4 cam kết kỹ thuật & thương mại cốt lõi.
 *
 * @var array<string, mixed> $args
 */

$surface = (string) ($args['surface'] ?? 'white');
$sec_cls = 'l-section l-section--tight';
if ($surface === 'white') {
    $sec_cls .= ' l-section--white';
} elseif ($surface === 'champagne') {
    $sec_cls .= ' l-section--champagne';
}

$pillars = [
    [
        'icon'  => 'shield-check',
        'title' => 'Thông số minh bạch',
        'desc'  => 'Xương Porcelain BIa ≤ 0,5%, ghi rõ chuẩn nhám R9 - R12 trên từng mã.',
    ],
    [
        'icon'  => 'calculator',
        'title' => 'Giá kép rõ ràng',
        'desc'  => 'Niêm yết song song đ/m² và đ/viên, tự quy đổi chẵn thùng cộng hao hụt.',
    ],
    [
        'icon'  => 'lightbulb',
        'title' => 'Mẫu thật tại Showroom',
        'desc'  => 'Đối chiếu vân thật trực tiếp dưới đèn ấm 3000K và đèn trắng 6500K.',
    ],
    [
        'icon'  => 'package',
        'title' => 'Đồng bộ lô màu công trình',
        'desc'  => 'Giữ hàng cùng mã màu Shade & cỡ Caliber, xuất hóa đơn VAT đầy đủ.',
    ],
];
?>
<section class="<?php echo esc_attr($sec_cls); ?>" id="home-trust" aria-labelledby="home-trust-title">
    <div class="l-container">
        <h2 id="home-trust-title" class="u-sr-only">Bảo chứng kỹ thuật và thương mại DRAVIT</h2>
        <div class="l-grout-grid l-grout-grid--4 drv-trust-strip" data-drv-stagger>
            <?php foreach ($pillars as $pillar) : ?>
                <article class="drv-trust-item">
                    <span class="drv-trust-item__icon" aria-hidden="true">
                        <?php drv_ui('icon', ['name' => $pillar['icon']]); ?>
                    </span>
                    <div class="drv-trust-item__body">
                        <h3 class="drv-trust-item__title"><?php echo esc_html($pillar['title']); ?></h3>
                        <p class="drv-trust-item__desc"><?php echo esc_html($pillar['desc']); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

