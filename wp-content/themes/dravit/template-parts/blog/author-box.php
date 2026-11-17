<?php
declare(strict_types=1);

use Dravit\Home\HomeData;

defined('ABSPATH') || exit;

/**
 * Hộp Tác giả & Kỹ sư Thẩm định E-E-A-T (mockup `08-blog-single.html`). Không Gravatar ngoài, không nhân sự giả (`O-13`).
 * Đơn vị (Ban Kỹ thuật) dùng ô biểu tượng thay cho logo để không bị cắt ảnh; chuyên gia thật dùng ảnh tròn.
 *
 * @var array<string, mixed> $args
 */
$author   = isset($args['author']) && is_array($args['author']) ? $args['author'] : [];
$reviewer = isset($args['reviewer']) && is_array($args['reviewer']) && !empty($args['reviewer']['name']) ? $args['reviewer'] : null;

if ($author === []) {
    return;
}

$zalo      = drv_clean_phone((string) drv_setting('contact.zalo', ''));
$about_url = HomeData::siteUrl('/gioi-thieu');

$persons = [
    [
        'person' => $author,
        'badge'  => !empty($author['is_organization']) ? 'Đơn vị biên soạn' : 'Tác giả biên soạn',
        'icon'   => 'ph-pen-nib',
        'is_org' => !empty($author['is_organization']),
    ],
];
if ($reviewer !== null) {
    $persons[] = ['person' => $reviewer, 'badge' => 'Thẩm định thực thi', 'icon' => 'ph-seal-check', 'is_org' => false];
}
?>
<section class="drv-author-box" id="author-credentials" aria-label="Hội đồng biên soạn và kiểm duyệt kỹ thuật">
    <div class="drv-author-box__head">
        <span class="drv-author-box__title">
            <i class="ph-fill ph-shield-check drv-icon--sm u-text-moss" aria-hidden="true"></i>
            Biên soạn &amp; thẩm định kỹ thuật
        </span>
        <ul class="drv-author-box__standards" aria-label="Tiêu chuẩn đối chiếu">
            <li>ISO 13006</li>
            <li>TCVN 7745</li>
            <li>DIN 51130</li>
        </ul>
    </div>

    <div class="drv-author-box__grid">
        <?php foreach ($persons as $row) :
            $p      = $row['person'];
            $name   = (string) ($p['name'] ?? '');
            $role   = (string) ($p['job_title'] ?? '');
            $bio    = (string) ($p['credentials'] ?? '');
            $avatar = (string) ($p['avatar_url'] ?? '');
            $url    = (string) ($p['url'] ?? '');
            ?>
            <div class="drv-author-box__person">
                <?php if (!$row['is_org'] && $avatar !== '') : ?>
                    <img src="<?php echo esc_url($avatar); ?>" width="64" height="64" loading="lazy" decoding="async" alt="<?php echo esc_attr($name); ?>" class="drv-author-box__avatar">
                <?php else : ?>
                    <span class="drv-author-box__avatar drv-author-box__avatar--tile" aria-hidden="true">
                        <i class="ph-fill ph-shield-check"></i>
                    </span>
                <?php endif; ?>
                <div class="drv-author-box__content">
                    <span class="drv-author-box__badge">
                        <i class="ph-fill <?php echo esc_attr($row['icon']); ?> drv-icon--xs" aria-hidden="true"></i>
                        <span><?php echo esc_html($row['badge']); ?></span>
                    </span>
                    <strong class="drv-author-box__name">
                        <?php if ($url !== '') : ?>
                            <a href="<?php echo esc_url($url); ?>" class="drv-author-box__link"><?php echo esc_html($name); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($name); ?>
                        <?php endif; ?>
                    </strong>
                    <?php if ($role !== '') : ?>
                        <span class="drv-author-box__role-line"><?php echo esc_html($role); ?></span>
                    <?php endif; ?>
                    <?php if ($bio !== '') : ?>
                        <p class="drv-author-box__bio-text"><?php echo esc_html($bio); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="drv-author-box__foot">
        <span class="drv-author-box__promise">
            <i class="ph-fill ph-check-circle drv-icon--sm u-text-moss" aria-hidden="true"></i>
            <span>Cam kết xuất kho 100% cùng một lô sản xuất (Shade &amp; Caliber) kèm chứng chỉ chất lượng.</span>
        </span>
        <div class="drv-author-box__actions">
            <a href="<?php echo esc_url($about_url); ?>" class="drv-btn drv-btn--outline drv-btn--sm">
                <span>Hồ sơ năng lực DRAVIT</span>
            </a>
            <?php if ($zalo !== '') : ?>
                <a href="<?php echo esc_url('https://zalo.me/' . $zalo); ?>" target="_blank" rel="noopener noreferrer" class="drv-btn drv-btn--moss drv-btn--sm">
                    <i class="ph ph-chat-circle-dots drv-icon--xs" aria-hidden="true"></i>
                    <span>Hỏi Kỹ sư qua Zalo</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
