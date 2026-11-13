<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$title = trim((string) ($args['title'] ?? ''));
if ($title === '') {
    return;
}

$eyebrow      = isset($args['eyebrow']) && is_string($args['eyebrow']) ? trim($args['eyebrow']) : '';
$desc         = isset($args['desc']) && is_string($args['desc']) ? trim($args['desc']) : '';
$raw_tag      = (string) ($args['heading_tag'] ?? 'h2');
$heading_tag  = in_array($raw_tag, ['h1', 'h2', 'h3'], true) ? $raw_tag : 'h2';
$action_label = isset($args['action_label']) && is_string($args['action_label']) ? trim($args['action_label']) : '';
$action_url   = isset($args['action_url']) && is_string($args['action_url']) ? trim($args['action_url']) : '';
$action_style = isset($args['action_style']) && is_string($args['action_style']) ? trim($args['action_style']) : 'link';
$reveal       = isset($args['reveal']) && is_string($args['reveal']) ? trim($args['reveal']) : '';
$badge_text   = isset($args['badge_text']) && is_string($args['badge_text']) ? trim($args['badge_text']) : '';
$badge_var    = (string) ($args['badge_variant'] ?? 'showroom');
?>
<div class="drv-sec-head"<?php echo $reveal !== '' ? ' data-drv-reveal="' . esc_attr($reveal) . '"' : ''; ?>>
    <div class="l-stack l-stack--xs">
        <?php if ($eyebrow !== '') : ?>
            <span class="drv-sec-head__eyebrow"><?php echo esc_html($eyebrow); ?></span>
        <?php endif; ?>
        <<?php echo esc_attr($heading_tag); ?>><?php echo esc_html($title); ?></<?php echo esc_attr($heading_tag); ?>>
        <?php if ($desc !== '') : ?>
            <p class="drv-sec-head__desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($action_label !== '' && $action_url !== '') : ?>
        <?php if ($action_style === 'more-btn') : ?>
            <a href="<?php echo esc_url($action_url); ?>" class="drv-more-btn">
                <span><?php echo esc_html($action_label); ?></span>
                <?php drv_ui('icon', ['name' => 'caret-right', 'weight' => 'bold', 'size' => 'xs']); ?>
            </a>
        <?php else : ?>
            <a href="<?php echo esc_url($action_url); ?>" class="drv-link">
                <span><?php echo esc_html($action_label); ?></span>
                <?php drv_ui('icon', ['name' => 'arrow-right', 'size' => 'xs']); ?>
            </a>
        <?php endif; ?>
    <?php elseif ($badge_text !== '') : ?>
        <?php drv_ui('badge', ['text' => $badge_text, 'variant' => $badge_var]); ?>
    <?php endif; ?>
</div>
