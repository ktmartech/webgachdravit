<?php
declare(strict_types=1);

namespace Dravit\Advisor\Contracts;

defined('ABSPATH') || exit;

/**
 * Hợp đồng tìm kiếm ứng viên sản phẩm đóng kín (ADR D-32, D-47).
 */
interface CatalogSearchInterface {
    /**
     * Trả về toàn bộ gói snapshot (`version`, `built_at`, `count`, `items`).
     *
     * @return array<string, mixed>
     */
    public function getSnapshot(): array;

    /**
     * Trả về danh sách SKU đã chuẩn hóa để `QueryEngine` lọc cứng & chấm điểm trong RAM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCandidates(): array;

    /**
     * Phiên bản snapshot hiện hành để gắn vào cache key & log đối soát.
     */
    public function getVersion(): string;

    /**
     * Tái tạo lại toàn bộ snapshot từ WooCommerce.
     *
     * @return array<string, mixed>
     */
    public function rebuild(): array;
}

