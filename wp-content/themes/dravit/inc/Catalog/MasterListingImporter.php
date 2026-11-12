<?php
declare(strict_types=1);

namespace Dravit\Catalog;

use Dravit\Core\Config;

defined('ABSPATH') || exit;

/**
 * Bộ nạp hàng loạt file Master Product Listing 48 cột (`.xlsx` hoặc `.csv`).
 * Hỗ trợ đọc trực tiếp `.xlsx` qua `ZipArchive` + `SimpleXMLElement` không cần thư viện ngoài,
 * tự dò dòng tiêu đề, đồng bộ 14 `pa_*`, 28 Smart Collections, `PrimaryCategory`, `drv_ai_profile` và ghép ảnh theo mã SKU.
 */
final class MasterListingImporter {
    private const IMG_ROLES = ['room', 'face', 'light', 'grout', 'showroom', 'edge'];

    /**
     * Đọc và import file `.xlsx` hoặc `.csv` vào WooCommerce.
     *
     * @return array{
     *     total_rows: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     items: array<int, array{
     *         sku: string,
     *         product_id: int,
     *         action: string,
     *         post_title: string,
     *         primary_cat: string,
     *         collections: array<int, string>
     *     }>
     * }
     */
    public function importFile(string $file_path, string $images_dir = '', bool $dry_run = false, string $original_name = ''): array {
        if (!is_readable($file_path)) {
            throw new \RuntimeException('Không đọc được file nguồn Master Listing.');
        }

        $ext_source = $original_name !== '' ? $original_name : $file_path;
        $ext        = strtolower(pathinfo($ext_source, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = $this->sniffIsZip($file_path) ? 'xlsx' : 'csv';
        }

        $matrix  = ($ext === 'xlsx') ? $this->readXlsxMatrix($file_path) : $this->readCsvMatrix($file_path);
        $records = $this->extractKeyedRecords($matrix);

        if (!$dry_run) {
            (new Seeder())->run();
        }

        $collections_cfg = Config::get('collections');
        $created         = 0;
        $updated         = 0;
        $skipped         = 0;
        $items           = [];

        foreach ($records as $raw_row) {
            $parsed = MasterListingParser::parseRow($raw_row);
            if (!$parsed['is_valid_sku']) {
                $skipped++;
                continue;
            }

            if ($dry_run) {
                $w_mm    = (int) ($parsed['meta']['drv_piece_w_mm'] ?? 0);
                $h_mm    = (int) ($parsed['meta']['drv_piece_h_mm'] ?? 0);
                $reg_m2  = (int) ($parsed['meta']['drv_price_m2'] ?? 0);
                $sale_m2 = (int) ($parsed['meta']['drv_sale_price_m2'] ?? 0);
                $matched = RuleMatcher::matchCollections($collections_cfg, [
                    'line'  => (string) ($parsed['meta']['drv_line'] ?? 'gach'),
                    'attrs' => $parsed['taxonomies'],
                    'flags' => [
                        'on_sale'      => ($sale_m2 > 0 && $sale_m2 < $reg_m2),
                        'large_format' => AttributeDeriver::isLargeFormat($w_mm, $h_mm),
                    ],
                ]);
                $manual_slug = $parsed['primary_cat_slug'] !== '' ? $parsed['primary_cat_slug'] : null;
                $prim_slug   = PrimaryCategory::resolvePrimarySlug($matched, $collections_cfg, $manual_slug, $manual_slug !== null) ?? '';

                $items[] = [
                    'sku'         => $parsed['sku'],
                    'product_id'  => 0,
                    'action'      => 'dry-run',
                    'post_title'  => $parsed['post_title'],
                    'primary_cat' => $prim_slug,
                    'collections' => $matched,
                ];
                continue;
            }

            $upserted = $this->upsertParsedRow($parsed, $images_dir);
            if ($upserted['action'] === 'created') {
                $created++;
            } else {
                $updated++;
            }
            $items[] = $upserted;
        }

        return [
            'total_rows' => count($records),
            'created'    => $created,
            'updated'    => $updated,
            'skipped'    => $skipped,
            'items'      => $items,
        ];
    }

    /**
     * Đọc ma trận dòng/cột từ file `.xlsx` và trích xuất danh sách bản ghi đã ghép khóa cột.
     *
     * @return array<int, array<string, string>>
     */
    public function readRecordsFromFile(string $file_path): array {
        $ext    = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $matrix = ($ext === 'xlsx' || $this->sniffIsZip($file_path))
            ? $this->readXlsxMatrix($file_path)
            : $this->readCsvMatrix($file_path);

        return $this->extractKeyedRecords($matrix);
    }

    /**
     * @param array{
     *     is_valid_sku: bool,
     *     sku: string,
     *     post_title: string,
     *     post_name: string,
     *     post_excerpt: string,
     *     primary_cat_slug: string,
     *     images: array<int, string>,
     *     meta: array<string, mixed>,
     *     taxonomies: array<string, array<int, string>>
     * } $parsed
     * @return array{
     *     sku: string,
     *     product_id: int,
     *     action: string,
     *     post_title: string,
     *     primary_cat: string,
     *     collections: array<int, string>
     * }
     */
    public function upsertParsedRow(array $parsed, string $images_dir = ''): array {
        $sku         = $parsed['sku'];
        $existing_id = function_exists('wc_get_product_id_by_sku') ? (int) wc_get_product_id_by_sku($sku) : 0;
        $existing    = ($existing_id > 0 && function_exists('wc_get_product')) ? wc_get_product($existing_id) : false;
        $is_update   = ($existing instanceof \WC_Product_Simple);
        $product     = $is_update ? $existing : new \WC_Product_Simple();

        $product->set_sku($sku);
        if ($parsed['post_title'] !== '') {
            $product->set_name($parsed['post_title']);
        }
        if ($parsed['post_name'] !== '') {
            $product->set_slug($parsed['post_name']);
        }
        if ($parsed['post_excerpt'] !== '') {
            $product->set_short_description($parsed['post_excerpt']);
        }
        $product->set_status('publish');
        $product->set_catalog_visibility('visible');
        if (!$is_update) {
            $product->set_stock_status('instock');
        }

        // Gán toàn bộ meta nguyên tử & hồ sơ AI `drv_ai_profile`.
        foreach ($parsed['meta'] as $meta_key => $meta_val) {
            if (is_array($meta_val)) {
                $product->update_meta_data($meta_key, $meta_val);
            } else {
                $product->update_meta_data($meta_key, (string) $meta_val);
            }
        }

        // Thiết lập Danh mục chính (`rank_math_primary_product_cat`) trước khi save để `PrimaryCategory` bảo toàn.
        if ($parsed['primary_cat_slug'] !== '' && taxonomy_exists('product_cat')) {
            $prim_term = get_term_by('slug', $parsed['primary_cat_slug'], 'product_cat');
            if ($prim_term instanceof \WP_Term) {
                $product->update_meta_data('rank_math_primary_product_cat', (string) $prim_term->term_id);
                $product->update_meta_data('drv_primary_auto', '0');
            } else {
                $product->update_meta_data('drv_primary_auto', '1');
            }
        } else {
            $product->update_meta_data('drv_primary_auto', '1');
        }

        // Gắn thuộc tính `pa_*` lên đối tượng `WC_Product`.
        $this->attachTaxonomyAttributes($product, $parsed['taxonomies']);

        // Khớp ảnh local theo mã SKU nếu truyền `--images-dir`.
        if ($images_dir !== '' && is_dir($images_dir)) {
            $this->attachImagesFromDirectory($product, $sku, $parsed['post_title'], $images_dir);
        }

        $saved_id = (int) $product->save();

        $assigned_cats = $saved_id > 0 ? wp_get_object_terms($saved_id, 'product_cat', ['fields' => 'slugs']) : [];
        $cat_slugs     = is_array($assigned_cats) ? array_values(array_map('strval', $assigned_cats)) : [];

        $prim_tid    = $saved_id > 0 ? (int) get_post_meta($saved_id, 'rank_math_primary_product_cat', true) : 0;
        $prim_obj    = ($prim_tid > 0 && taxonomy_exists('product_cat')) ? get_term($prim_tid, 'product_cat') : null;
        $actual_prim = ($prim_obj instanceof \WP_Term) ? $prim_obj->slug : '';

        return [
            'sku'         => $sku,
            'product_id'  => $saved_id,
            'action'      => $is_update ? 'updated' : 'created',
            'post_title'  => $product->get_name(),
            'primary_cat' => $actual_prim,
            'collections' => $cat_slugs,
        ];
    }

    /**
     * Gắn mảng thuộc tính `pa_*` vào `\WC_Product` (tự tạo term nếu chưa tồn tại).
     *
     * @param array<string, array<int, string>> $taxonomies
     */
    public function attachTaxonomyAttributes(\WC_Product $product, array $taxonomies): void {
        if ($taxonomies === [] || !function_exists('wc_attribute_taxonomy_id_by_name')) {
            return;
        }

        $existing_attrs = $product->get_attributes();
        if (!is_array($existing_attrs)) {
            $existing_attrs = [];
        }

        $pos = count($existing_attrs);
        foreach ($taxonomies as $tax => $slugs) {
            if (!taxonomy_exists($tax) || $slugs === []) {
                continue;
            }

            $tids = [];
            foreach ($slugs as $slug) {
                $t = get_term_by('slug', $slug, $tax);
                if ($t instanceof \WP_Term) {
                    $tids[] = (int) $t->term_id;
                } else {
                    $ins = wp_insert_term($slug, $tax, ['slug' => $slug]);
                    if (!is_wp_error($ins) && isset($ins['term_id'])) {
                        $tids[] = (int) $ins['term_id'];
                    }
                }
            }

            if ($tids === []) {
                continue;
            }

            $attr_id = wc_attribute_taxonomy_id_by_name($tax);
            if ($attr_id <= 0) {
                continue;
            }

            $wc_attr = new \WC_Product_Attribute();
            $wc_attr->set_id($attr_id);
            $wc_attr->set_name($tax);
            $wc_attr->set_options($tids);
            $wc_attr->set_position($pos++);
            $wc_attr->set_visible(true);
            $wc_attr->set_variation(false);
            $existing_attrs[$tax] = $wc_attr;
        }

        $product->set_attributes($existing_attrs);
    }

    /**
     * Đọc bảng đầu tiên (hoặc sheet `1_Master_Listing_San_Pham`) từ file `.xlsx`.
     *
     * @return array<int, array<int, string>>
     */
    private function readXlsxMatrix(string $file_path): array {
        if (!class_exists('\ZipArchive') || !class_exists('\SimpleXMLElement')) {
            throw new \RuntimeException('Môi trường PHP thiếu ZipArchive hoặc SimpleXMLElement để đọc file .xlsx.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($file_path) !== true) {
            throw new \RuntimeException('Không thể giải nén file .xlsx Master Listing.');
        }

        try {
            $shared_strings = [];
            $shared_xml     = $zip->getFromName('xl/sharedStrings.xml');
            if (is_string($shared_xml) && $shared_xml !== '') {
                $sst = new \SimpleXMLElement($shared_xml, LIBXML_NONET | LIBXML_NOCDATA);
                foreach ($sst->si as $si) {
                    if (isset($si->t)) {
                        $shared_strings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $buf = '';
                        foreach ($si->r as $run) {
                            $buf .= (string) ($run->t ?? '');
                        }
                        $shared_strings[] = $buf;
                    } else {
                        $shared_strings[] = '';
                    }
                }
            }

            $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (!is_string($sheet_xml) || $sheet_xml === '') {
                throw new \RuntimeException('Không tìm thấy xl/worksheets/sheet1.xml trong file .xlsx.');
            }

            $sheet = new \SimpleXMLElement($sheet_xml, LIBXML_NONET | LIBXML_NOCDATA);
            $rows  = [];

            if (!isset($sheet->sheetData->row)) {
                return [];
            }

            foreach ($sheet->sheetData->row as $row_el) {
                $row_vals = [];
                $max_col  = -1;

                foreach ($row_el->c as $cell) {
                    $ref     = (string) ($cell['r'] ?? '');
                    $col_idx = $this->colRefToIndex($ref);
                    $type    = (string) ($cell['t'] ?? '');
                    $val     = '';

                    if ($type === 's' && isset($cell->v)) {
                        $s_idx = (int) $cell->v;
                        $val   = $shared_strings[$s_idx] ?? '';
                    } elseif ($type === 'inlineStr' && isset($cell->is)) {
                        if (isset($cell->is->t)) {
                            $val = (string) $cell->is->t;
                        } elseif (isset($cell->is->r)) {
                            foreach ($cell->is->r as $run) {
                                $val .= (string) ($run->t ?? '');
                            }
                        }
                    } elseif (isset($cell->v) && (string) $cell->v !== '') {
                        $val = (string) $cell->v;
                    } elseif (isset($cell->f)) {
                        $formula = (string) $cell->f;
                        // Giữ lại công thức HYPERLINK để `MasterListingParser::extractUrl()` lấy được link Google Photos
                        if (stripos($formula, 'HYPERLINK') !== false) {
                            $val = $formula;
                        }
                    }

                    $row_vals[$col_idx] = $val;
                    if ($col_idx > $max_col) {
                        $max_col = $col_idx;
                    }
                }

                if ($max_col >= 0) {
                    $dense = [];
                    for ($i = 0; $i <= $max_col; $i++) {
                        $dense[$i] = $row_vals[$i] ?? '';
                    }
                    $rows[] = $dense;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * Đọc ma trận dòng/cột từ file `.csv` (tự nhận diện dấu phân cách `,`, `;`, `\t`).
     *
     * @return array<int, array<int, string>>
     */
    private function readCsvMatrix(string $file_path): array {
        $handle = fopen($file_path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Không thể mở file CSV Master Listing.');
        }

        try {
            $sample = '';
            for ($i = 0; $i < 6 && !feof($handle); $i++) {
                $line = fgets($handle);
                if (is_string($line)) {
                    $sample .= $line;
                }
            }
            rewind($handle);

            $delimiter = $this->detectCsvDelimiter($sample);
            $rows      = [];

            while (($data = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if ($data === [null]) {
                    continue;
                }
                $rows[] = array_map(static fn($cell): string => is_string($cell) ? $cell : '', $data);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function detectCsvDelimiter(string $sample): string {
        $candidates = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
        arsort($candidates);
        $best = array_key_first($candidates);
        return is_string($best) && $candidates[$best] > 0 ? $best : ',';
    }

    /**
     * Dò dòng tiêu đề chuẩn nhất (ưu tiên dòng khóa kỹ thuật `sku` / `meta:...`, fallback dòng nhãn Tiếng Việt `Mã SKU (*)`).
     *
     * @param array<int, array<int, string>> $matrix
     * @return array<int, array<string, string>>
     */
    private function extractKeyedRecords(array $matrix): array {
        if ($matrix === []) {
            return [];
        }

        $header_idx = 0;
        $best_score = -1;
        $scan_limit = min(15, count($matrix));

        for ($r = 0; $r < $scan_limit; $r++) {
            $score = 0;
            foreach ($matrix[$r] as $cell) {
                $trim = trim($cell);
                if ($trim === '') {
                    continue;
                }
                $norm = MasterListingParser::normalizeHeaderKey($trim);
                if ($norm === 'sku') {
                    $score += 10;
                }
                if (str_starts_with($trim, 'meta:') || str_starts_with($trim, 'attribute:')) {
                    $score += 3;
                } elseif ($norm !== $trim) {
                    $score += 1;
                }
            }

            if ($score > $best_score) {
                $best_score = $score;
                $header_idx = $r;
            }
        }

        $headers = $matrix[$header_idx];
        $records = [];
        $total   = count($matrix);

        for ($r = $header_idx + 1; $r < $total; $r++) {
            $row   = $matrix[$r];
            $assoc = [];
            $non_empty = false;

            foreach ($headers as $col_idx => $col_name) {
                $key = trim($col_name);
                if ($key === '') {
                    continue;
                }
                $val = $row[$col_idx] ?? '';
                if (trim($val) !== '') {
                    $non_empty = true;
                }
                $assoc[$key] = $val;
            }

            if ($non_empty) {
                $records[] = $assoc;
            }
        }

        return $records;
    }

    private function colRefToIndex(string $cell_ref): int {
        if (preg_match('/^([A-Z]+)/i', $cell_ref, $m) !== 1) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $index   = 0;
        $len     = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }
        return max(0, $index - 1);
    }

    private function sniffIsZip(string $file_path): bool {
        $fh = fopen($file_path, 'rb');
        if ($fh === false) {
            return false;
        }
        $magic = fread($fh, 4);
        fclose($fh);
        return $magic === "PK\x03\x04";
    }

    /**
     * Quét thư mục ảnh local theo quy ước `{SKU}.{jpg,jpeg,png,webp,avif}` và `{SKU}-1..6.{...}`,
     * nạp idempotent vào Media Library và gắn vai trò `drv_img_role`.
     */
    private function attachImagesFromDirectory(\WC_Product $product, string $sku, string $title, string $images_dir): void {
        $dir  = rtrim($images_dir, '/\\');
        $exts = ['webp', 'avif', 'jpg', 'jpeg', 'png'];
        $matched_files = [];

        // 1. Ảnh chính `{SKU}.{ext}` hoặc `{SKU}-1.{ext}`
        foreach ($exts as $ext) {
            $direct = $dir . DIRECTORY_SEPARATOR . $sku . '.' . $ext;
            if (is_file($direct)) {
                $matched_files[] = $direct;
                break;
            }
        }

        for ($idx = 1; $idx <= 6; $idx++) {
            foreach ($exts as $ext) {
                $candidate = $dir . DIRECTORY_SEPARATOR . $sku . '-' . $idx . '.' . $ext;
                if (is_file($candidate) && !in_array($candidate, $matched_files, true)) {
                    $matched_files[] = $candidate;
                    break;
                }
            }
        }

        if ($matched_files === []) {
            return;
        }

        $attachment_ids = [];
        foreach ($matched_files as $pos => $src_file) {
            $role   = self::IMG_ROLES[$pos] ?? 'face';
            $att_id = $this->ensureLocalAttachment($src_file, $title !== '' ? $title : $sku, $role);
            if ($att_id > 0) {
                $attachment_ids[] = $att_id;
            }
        }

        if ($attachment_ids !== []) {
            $product->set_image_id($attachment_ids[0]);
            if (count($attachment_ids) > 1) {
                $product->set_gallery_image_ids(array_slice($attachment_ids, 1));
            }
        }
    }

    private function ensureLocalAttachment(string $src_file, string $title, string $role): int {
        $hash = sha1_file($src_file);
        if (!is_string($hash)) {
            return 0;
        }

        $existing = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_drv_import_sha1',
            'meta_value'     => $hash,
        ]);

        if (is_array($existing) && isset($existing[0]) && (int) $existing[0] > 0) {
            $att_id = (int) $existing[0];
            update_post_meta($att_id, 'drv_img_role', $role);
            return $att_id;
        }

        $filename = wp_basename($src_file);
        $contents = file_get_contents($src_file);
        if (!is_string($contents)) {
            return 0;
        }

        $upload = wp_upload_bits($filename, null, $contents);
        if (!empty($upload['error']) || empty($upload['file'])) {
            return 0;
        }

        $filetype = wp_check_filetype($upload['file'], null);
        $att_id   = wp_insert_attachment([
            'guid'           => (string) ($upload['url'] ?? ''),
            'post_mime_type' => (string) ($filetype['type'] ?? 'image/jpeg'),
            'post_title'     => $title,
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file']);

        if (is_wp_error($att_id) || $att_id <= 0) {
            return 0;
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $meta = wp_generate_attachment_metadata($att_id, $upload['file']);
        wp_update_attachment_metadata($att_id, $meta);
        update_post_meta($att_id, '_wp_attachment_image_alt', $title);
        update_post_meta($att_id, 'drv_img_role', $role);
        update_post_meta($att_id, '_drv_import_sha1', $hash);

        return (int) $att_id;
    }
}
