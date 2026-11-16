<?php
declare(strict_types=1);

namespace Dravit\Quote;

use Dravit\Core\ServiceProviderInterface;
use Dravit\Leads\AntiSpamGuard;
use Dravit\Leads\LeadController;
use Dravit\Leads\LeadNotifier;
use Dravit\Leads\LeadRepository;
use Dravit\Leads\LeadValidator;

defined('ABSPATH') || exit;

/**
 * `POST /wp-json/dravit/v1/quote/submit`: tiếp nhận phiếu BOQ đa phòng từ `/danh-sach-bao-gia` (docs/15 §2.5, docs/17 §4.3).
 */
final class QuoteController implements ServiceProviderInterface {
    public const NONCE_ACTION = 'drv_quote_submit';

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('dravit/v1', '/quote/submit', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handle_submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_submit(\WP_REST_Request $request): \WP_REST_Response {
        LeadController::noCache();
        $payload = $request->get_json_params();
        $payload = is_array($payload) ? $payload : [];
        $meta    = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        $guard = AntiSpamGuard::verify($request, self::NONCE_ACTION, $meta);
        if ($guard instanceof \WP_Error) {
            return $guard->get_error_code() === AntiSpamGuard::CODE_HONEYPOT
                ? LeadController::fakeSuccess('BG')
                : LeadController::errorResponse($guard);
        }

        $result = self::process($payload, new BoqService(), [
            'page_url' => (string) ($meta['page_url'] ?? ''),
            'utm'      => $meta['utm'] ?? [],
            'ip_hash'  => $guard,
        ]);

        if ($result instanceof \WP_Error) {
            return LeadController::errorResponse($result);
        }

        $resp = new \WP_REST_Response($result, 201);
        $resp->header('Cache-Control', 'no-store');
        return $resp;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>|\WP_Error
     */
    public static function process(array $payload, BoqService $service, array $context = []): array|\WP_Error {
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : (is_array($payload['customer'] ?? null) ? $payload['customer'] : []);
        $boq     = is_array($payload['boq'] ?? null) ? $payload['boq'] : $payload;

        $checked = LeadValidator::validate($contact, 'boq', LeadController::provinceCodes());
        $input   = BoqService::normalizeInput(
            $boq,
            (array) drv_setting('order.waste_options', [0, 5, 10]),
            (array) drv_setting('quote.roles', []),
            (int) drv_setting('quote.max_items', 30),
            (int) drv_setting('quote.max_rooms', 12)
        );

        $errors = $checked['errors'] + $input['errors'];
        if ($errors !== []) {
            return LeadController::validationError($errors);
        }

        $computed = $service->compute($input['rooms'], $input['waste_pct'], (array) drv_setting('quote.volume_tiers', []), []);
        if ($computed['errors'] !== []) {
            return LeadController::validationError($computed['errors'], 'Bảng dự toán có mẫu không còn khả dụng.');
        }

        $data   = $checked['data'];
        $totals = $computed['totals'];
        if ($data['project'] === '' && $input['project'] !== '') {
            $data['project'] = $input['project'];
        }
        $data['area_m2'] = (float) $totals['total_m2'];

        $created = LeadRepository::create('boq', $data, [
            '_drv_boq'              => [
                'waste_pct' => $input['waste_pct'],
                'project'   => $data['project'],
                'rooms'     => $computed['rooms'],
            ],
            '_drv_total_m2'         => $totals['total_m2'],
            '_drv_total_boxes'      => $totals['total_boxes'],
            '_drv_total_kg'         => $totals['total_kg'],
            '_drv_subtotal_vnd'     => $totals['subtotal_vnd'],
            '_drv_discount_rate'    => $totals['discount_rate'],
            '_drv_discount_est_vnd' => $totals['discount_est_vnd'],
            '_drv_grand_est_vnd'    => $totals['grand_est_vnd'],
            '_drv_has_quote_items'  => $totals['has_quote_items'] ? '1' : '0',
            '_drv_page_url'         => LeadController::sameSiteUrl((string) ($context['page_url'] ?? '')),
            '_drv_utm'              => LeadValidator::sanitizeUtm($context['utm'] ?? []),
            '_drv_ip_hash'          => (string) ($context['ip_hash'] ?? ''),
            '_drv_channel'          => 'rest',
        ]);

        if ($created instanceof \WP_Error) {
            return $created;
        }

        LeadNotifier::send($created['id']);
        do_action('drv/lead/created', $created['id'], $data + [
            'lead_type' => 'boq',
            'code'      => $created['code'],
            'boq'       => $computed,
        ]);

        return [
            'ok'          => true,
            'lead_id'     => $created['id'],
            'quote_code'  => $created['code'],
            'sla_message' => sprintf(
                'Chuyên viên DRAVIT sẽ kiểm tra mã lô tồn kho và liên hệ số %s. %s.',
                (string) $data['phone'],
                (string) drv_setting('order.sla_text', 'phản hồi trong 30 phút giờ làm việc')
            ),
            'summary'     => $totals,
        ];
    }
}
