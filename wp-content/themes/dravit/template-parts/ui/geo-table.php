<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$title   = trim((string) ($args['title'] ?? ''));
$caption = trim((string) ($args['caption'] ?? $title));
$headers = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : [];
$rows    = isset($args['rows']) && is_array($args['rows']) ? $args['rows'] : [];
$bullets = isset($args['bullets']) && is_array($args['bullets']) ? $args['bullets'] : [];
$badge   = isset($args['badge']) && is_string($args['badge']) ? trim($args['badge']) : 'Kiểm duyệt kỹ thuật';
$note    = isset($args['note']) && is_string($args['note']) ? trim($args['note']) : '';

if ($rows === [] && $bullets === []) {
    return;
}
?>
<aside class="drv-geo-box" aria-label="<?php echo esc_attr($title !== '' ? $title : 'Bảng dữ kiện kỹ thuật'); ?>">
    <?php if ($title !== '') : ?>
        <div class="l-cluster l-cluster--between">
            <strong class="u-text-sm u-uppercase"><?php echo esc_html($title); ?></strong>
            <?php if ($badge !== '') : ?>
                <?php drv_ui('badge', ['text' => $badge, 'variant' => 'moss']); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($bullets !== []) : ?>
        <ul class="drv-geo-box__list u-text-sm">
            <?php foreach ($bullets as $bullet) : ?>
                <li><?php echo wp_kses_post((string) $bullet); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($rows !== []) : ?>
        <div class="drv-geo-table-wrap">
            <table class="drv-spec-table drv-geo-table">
                <?php if ($caption !== '') : ?>
                    <caption class="u-sr-only"><?php echo esc_html($caption); ?></caption>
                <?php endif; ?>
                <?php if ($headers !== []) : ?>
                    <thead>
                        <tr>
                            <?php foreach ($headers as $th) : ?>
                                <th scope="col"><?php echo esc_html((string) $th); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                <?php endif; ?>
                <tbody>
                    <?php foreach ($rows as $row) :
                        if (!is_array($row)) {
                            continue;
                        }
                        $cells = array_values($row);
                        ?>
                        <tr>
                            <?php foreach ($cells as $c_idx => $cell) : ?>
                                <?php if ($c_idx === 0) : ?>
                                    <th scope="row"><?php echo esc_html((string) $cell); ?></th>
                                <?php else : ?>
                                    <td><?php echo wp_kses_post((string) $cell); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($note !== '') : ?>
        <p class="u-text-xs u-text-muted"><?php echo esc_html($note); ?></p>
    <?php endif; ?>
</aside>
