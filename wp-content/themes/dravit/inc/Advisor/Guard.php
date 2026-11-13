<?php
declare(strict_types=1);

namespace Dravit\Advisor;

use Dravit\Core\Config;
use Dravit\Core\RestSecurity;

defined('ABSPATH') || exit;

/**
 * Lớp bảo vệ cổng vào REST cho Trợ lý AI Tìm Gạch (`docs/13` §4, §11; `docs/15` §2.5):
 * - Feature flag `drv_settings.features.ai_enabled`
 * - Kiểm tra cùng Origin
 * - Xác thực Nonce tươi (`drv_advisor_submit`)
 * - Chống bot Honeypot (`drv_website`)
 * - Giới hạn độ dài tin nhắn (`<= 500` ký tự)
 * - Rate-limit `10 requests / 10 phút / IP`
 */
final class Guard {
    /**
     * Kiểm tra điều kiện bảo mật cho `POST /dravit/v1/advisor`.
     *
     * @param array<string, mixed> $body
     */
    public static function checkAdvisorRequest(\WP_REST_Request $request, array $body): ?\WP_Error {
        $rules    = Config::get('ai-rules');
        $messages = isset($rules['messages']) && is_array($rules['messages']) ? $rules['messages'] : [];
        $fallback = function_exists('home_url') ? untrailingslashit(home_url('/gach')) : '/gach';

        // 1. Cờ bật/tắt tính năng AI (`D-25`)
        if (!(bool) drv_setting('features.ai_enabled', false)) {
            return new \WP_Error(
                'drv_feature_disabled',
                (string) ($messages['feature_disabled'] ?? 'Trợ lý AI tìm gạch hiện đang tạm đóng.'),
                ['status' => 404, 'fallback_url' => $fallback]
            );
        }

        // 2. Kiểm tra Origin cùng tên miền
        $originErr = RestSecurity::verifyOrigin($request);
        if ($originErr instanceof \WP_Error) {
            return $originErr;
        }

        // 3. Xác thực Nonce tươi (cho phép cả X-DRV-Nonce từ /dravit/v1/nonce hoặc X-WP-Nonce chuẩn)
        if (!self::verifyNonce($request, $body)) {
            return new \WP_Error(
                'drv_bad_nonce',
                'Phiên bảo mật đã hết hạn, vui lòng thử gửi lại.',
                ['status' => 403, 'fallback_url' => $fallback]
            );
        }

        // 4. Honeypot chống bot tự động (`drv_website` phải rỗng)
        $honeypot = isset($body['drv_website']) ? trim((string) $body['drv_website']) : '';
        if ($honeypot !== '') {
            return new \WP_Error(
                'drv_spam_detected',
                'Yêu cầu không hợp lệ.',
                ['status' => 400, 'fallback_url' => $fallback]
            );
        }

        // 5. Giới hạn độ dài câu hỏi (tối đa 500 ký tự - L6)
        $maxChars = max(100, (int) ($rules['max_message_chars'] ?? 500));
        $message  = isset($body['message']) && is_string($body['message']) ? trim($body['message']) : '';
        if (mb_strlen($message, 'UTF-8') > $maxChars) {
            return new \WP_Error(
                'drv_message_too_long',
                sprintf('Tin nhắn tối đa %d ký tự, vui lòng rút gọn yêu cầu.', $maxChars),
                ['status' => 400, 'fallback_url' => $fallback]
            );
        }

        // 6. Rate-limit theo IP (`10 requests / 600s`)
        $rlLimit  = max(1, (int) ($rules['rate_limit']['limit'] ?? 10));
        $rlWindow = max(60, (int) ($rules['rate_limit']['window'] ?? 600));
        $rateErr  = RestSecurity::checkRateLimit('advisor_query', $rlLimit, $rlWindow);
        if ($rateErr instanceof \WP_Error) {
            return new \WP_Error(
                'drv_rate_limited',
                (string) ($messages['rate_limited'] ?? 'Bạn đã gửi quá nhiều yêu cầu liên tiếp.'),
                ['status' => 429, 'fallback_url' => $fallback]
            );
        }

        return null;
    }

    /**
     * Kiểm tra điều kiện bảo mật cho `POST /dravit/v1/advisor/feedback`.
     *
     * @param array<string, mixed> $body
     */
    public static function checkFeedbackRequest(\WP_REST_Request $request, array $body): ?\WP_Error {
        if (!(bool) drv_setting('features.ai_enabled', false)) {
            return new \WP_Error('drv_feature_disabled', 'Tính năng tạm đóng.', ['status' => 404]);
        }

        $originErr = RestSecurity::verifyOrigin($request);
        if ($originErr instanceof \WP_Error) {
            return $originErr;
        }

        if (!self::verifyNonce($request, $body)) {
            return new \WP_Error('drv_bad_nonce', 'Phiên bảo mật hết hạn.', ['status' => 403]);
        }

        $rateErr = RestSecurity::checkRateLimit('advisor_feedback', 20, 600);
        if ($rateErr instanceof \WP_Error) {
            return $rateErr;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function verifyNonce(\WP_REST_Request $request, array $body): bool {
        if (!function_exists('wp_verify_nonce')) {
            return true;
        }

        $drvNonce = trim((string) $request->get_header('x_drv_nonce'));
        if ($drvNonce === '' && isset($body['nonce']) && is_string($body['nonce'])) {
            $drvNonce = trim($body['nonce']);
        }
        if ($drvNonce !== '' && wp_verify_nonce($drvNonce, 'drv_advisor_submit') !== false) {
            return true;
        }

        $wpNonce = trim((string) $request->get_header('x_wp_nonce'));
        if ($wpNonce !== '' && wp_verify_nonce($wpNonce, 'wp_rest') !== false) {
            return true;
        }

        return false;
    }
}
