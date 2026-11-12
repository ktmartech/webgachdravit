<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/** @var array<string, mixed> $args */
$id           = sanitize_html_class((string) ($args['id'] ?? 'drv-field-' . wp_rand(100, 9999)));
$name         = (string) ($args['name'] ?? $id);
$label        = (string) ($args['label'] ?? '');
$type         = (string) ($args['type'] ?? 'text');
$value        = $args['value'] ?? '';
$required     = !empty($args['required']);
$hint         = isset($args['hint']) && is_string($args['hint']) ? trim($args['hint']) : '';
$error        = isset($args['error']) && is_string($args['error']) ? trim($args['error']) : '';
$placeholder  = (string) ($args['placeholder'] ?? '');
$options      = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
$autocomplete = isset($args['autocomplete']) && is_string($args['autocomplete']) ? $args['autocomplete'] : '';
$inputmode    = isset($args['inputmode']) && is_string($args['inputmode']) ? $args['inputmode'] : '';
$raw_attrs    = isset($args['attrs']) && is_array($args['attrs']) ? $args['attrs'] : [];

$error_id = $id . '-error';
$hint_id  = $id . '-hint';
$desc_ids = [];
if ($hint !== '') {
    $desc_ids[] = $hint_id;
}
if ($error !== '') {
    $desc_ids[] = $error_id;
}

$extra_attr_str = '';
foreach ($raw_attrs as $k => $v) {
    $safe_k = (string) preg_replace('/[^a-zA-Z0-9_:-]/', '', (string) $k);
    if ($safe_k === '' || $v === false || $v === null) {
        continue;
    }
    $extra_attr_str .= $v === true ? ' ' . $safe_k : sprintf(' %s="%s"', $safe_k, esc_attr((string) $v));
}

if ($type === 'checkbox') : ?>
    <label class="drv-check" for="<?php echo esc_attr($id); ?>">
        <input id="<?php echo esc_attr($id); ?>"
               name="<?php echo esc_attr($name); ?>"
               type="checkbox"
               value="<?php echo esc_attr((string) ($value !== '' ? $value : '1')); ?>"
               <?php echo !empty($args['checked']) ? 'checked' : ''; ?>
               <?php echo $required ? 'required' : ''; ?>
               <?php echo $extra_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <span><?php echo esc_html($label); ?></span>
    </label>
    <?php
    return;
endif;
?>
<div class="drv-field<?php echo $error !== '' ? ' is-invalid' : ''; ?>">
    <?php if ($label !== '') : ?>
        <label for="<?php echo esc_attr($id); ?>" class="drv-label">
            <?php echo esc_html($label); ?>
            <?php if ($required) : ?>
                <span class="drv-label__req" aria-hidden="true">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>

    <?php if ($type === 'select') : ?>
        <select id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr($name); ?>"
                class="drv-select"
                <?php echo $required ? 'required' : ''; ?>
                <?php echo $error !== '' ? 'aria-invalid="true"' : ''; ?>
                <?php echo $desc_ids !== [] ? 'aria-describedby="' . esc_attr(implode(' ', $desc_ids)) . '"' : ''; ?>
                <?php echo $extra_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php foreach ($options as $opt_val => $opt_label) : ?>
                <option value="<?php echo esc_attr((string) $opt_val); ?>" <?php echo ((string) $value === (string) $opt_val) ? 'selected' : ''; ?>>
                    <?php echo esc_html((string) $opt_label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php elseif ($type === 'textarea') : ?>
        <textarea id="<?php echo esc_attr($id); ?>"
                  name="<?php echo esc_attr($name); ?>"
                  class="drv-textarea"
                  placeholder="<?php echo esc_attr($placeholder); ?>"
                  <?php echo $required ? 'required' : ''; ?>
                  <?php echo $error !== '' ? 'aria-invalid="true"' : ''; ?>
                  <?php echo $desc_ids !== [] ? 'aria-describedby="' . esc_attr(implode(' ', $desc_ids)) . '"' : ''; ?>
                  <?php echo $extra_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea((string) $value); ?></textarea>
    <?php else : ?>
        <input id="<?php echo esc_attr($id); ?>"
               name="<?php echo esc_attr($name); ?>"
               type="<?php echo esc_attr($type); ?>"
               value="<?php echo esc_attr((string) $value); ?>"
               class="drv-input"
               <?php echo $placeholder !== '' ? 'placeholder="' . esc_attr($placeholder) . '"' : ''; ?>
               <?php echo $autocomplete !== '' ? 'autocomplete="' . esc_attr($autocomplete) . '"' : ''; ?>
               <?php echo $inputmode !== '' ? 'inputmode="' . esc_attr($inputmode) . '"' : ''; ?>
               <?php echo $required ? 'required' : ''; ?>
               <?php echo $error !== '' ? 'aria-invalid="true"' : ''; ?>
               <?php echo $desc_ids !== [] ? 'aria-describedby="' . esc_attr(implode(' ', $desc_ids)) . '"' : ''; ?>
               <?php echo $extra_attr_str; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
    <?php endif; ?>

    <?php if ($hint !== '') : ?>
        <span id="<?php echo esc_attr($hint_id); ?>" class="drv-label__hint u-text-xs u-text-muted"><?php echo esc_html($hint); ?></span>
    <?php endif; ?>

    <span id="<?php echo esc_attr($error_id); ?>" class="drv-field__error" role="alert" <?php echo $error === '' ? 'hidden' : ''; ?>>
        <?php echo esc_html($error); ?>
    </span>
</div>

