<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Danh mục 16 module Section tái sử dụng toàn hệ thống (Phương án S-3, ADR D-34).
 *
 * Mỗi mục khai báo:
 * - defaults: giá trị mặc định an toàn cho mảng $props.
 * - style:    entry CSS chuyên biệt nạp theo nhu cầu (nếu có).
 * - script:   entry JS module chuyên biệt nạp theo nhu cầu (nếu có).
 *
 * @return array<string, array{defaults: array<string, mixed>, style?: string, script?: string}>
 */
return [
    'hero-advisor'          => ['defaults' => ['variant' => 'home', 'show_search' => true]],
    'trust-strip'           => ['defaults' => ['variant' => 'default']],
    'space-bento'           => ['defaults' => ['columns' => 4]],
    'silo-tiles-bar'        => ['defaults' => ['items' => []]],
    'product-explorer-tabs' => ['defaults' => ['tabs' => []]],
    'product-grid-block'    => ['defaults' => ['columns' => 4, 'limit' => 8]],
    'size-r-guide'          => ['defaults' => []],
    'showroom-showcase'     => ['defaults' => ['show_booking_cta' => true]],
    'geo-spec-section'      => ['defaults' => ['rows' => []]],
    'faq-section'           => ['defaults' => ['items' => []]],
    'lead-capture-section'  => ['defaults' => ['form_slot' => 'contact_page', 'lead_type' => 'contact']],
    'editorial-guides'      => ['defaults' => ['limit' => 3]],
    'ai-finder-workspace'   => ['defaults' => [], 'script' => 'assets/src/js/chunks/ai-finder.js'],
    'boq-workspace'         => ['defaults' => [], 'script' => 'assets/src/js/chunks/quote.js'],
    'contact-hub'           => ['defaults' => []],
    'error-404-recovery'    => ['defaults' => []],
];

