<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Leads\AntiSpamGuard;
use Dravit\Leads\LeadValidator;
use PHPUnit\Framework\TestCase;

/**
 * Step 8 · Q-8 / Q-9: chống spam 5 lớp (Origin, Honeypot, Nonce tươi, Time-trap HMAC, Rate-limit IP)
 * và validate trường Lead hợp nhất (docs/17 §4.1).
 */
final class AntiSpamGuardTest extends TestCase {
    private const KEY = 'test-secret';

    /** @var array<string, mixed> */
    private array $transients = [];

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->transients = [];
        Functions\stubs([
            'home_url'      => static fn (string $path = ''): string => 'https://gachdravit.vn' . $path,
            'wp_parse_url'  => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'get_transient' => fn (string $key): mixed => $this->transients[$key] ?? false,
            'set_transient' => function (string $key, mixed $value): bool {
                $this->transients[$key] = $value;
                return true;
            },
        ]);
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_time_trap_requires_signed_ts_older_than_3_seconds(): void {
        $now = 1_762_854_000;
        $sig = AntiSpamGuard::signTs($now - 10, self::KEY);

        self::assertNull(AntiSpamGuard::checkTimeTrap($now - 10, $sig, $now, self::KEY));
        self::assertSame('drv_too_fast', AntiSpamGuard::checkTimeTrap($now - 1, AntiSpamGuard::signTs($now - 1, self::KEY), $now, self::KEY));
        self::assertSame('drv_bad_token', AntiSpamGuard::checkTimeTrap($now - 10, 'forged', $now, self::KEY), 'Client không tự ký được mốc thời gian');
        self::assertSame('drv_bad_token', AntiSpamGuard::checkTimeTrap($now - 20, $sig, $now, self::KEY), 'Đổi ts làm sai chữ ký');
        self::assertSame('drv_token_expired', AntiSpamGuard::checkTimeTrap($now - 90000, AntiSpamGuard::signTs($now - 90000, self::KEY), $now, self::KEY));
    }

    public function test_honeypot_and_origin_layers(): void {
        self::assertTrue(AntiSpamGuard::isHoneypotTripped('http://spam.example'));
        self::assertFalse(AntiSpamGuard::isHoneypotTripped(''));
        self::assertFalse(AntiSpamGuard::isHoneypotTripped('   '));

        self::assertNull(AntiSpamGuard::verifyStrictOrigin('https://gachdravit.vn', ''));
        self::assertNull(AntiSpamGuard::verifyStrictOrigin('', 'https://GACHDRAVIT.vn/lien-he'));
        self::assertInstanceOf(\WP_Error::class, AntiSpamGuard::verifyStrictOrigin('https://evil.example', ''));
        self::assertInstanceOf(\WP_Error::class, AntiSpamGuard::verifyStrictOrigin('', ''), 'Thiếu cả Origin lẫn Referer bị chặn');
    }

    public function test_classic_fallback_fakes_success_for_honeypot(): void {
        $result = AntiSpamGuard::verifyClassic(['drv_website' => 'bot'], 'https://gachdravit.vn', '');
        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame(AntiSpamGuard::CODE_HONEYPOT, $result->get_error_code());
    }

    public function test_rate_limit_blocks_sixth_submission_with_429(): void {
        $hash = AntiSpamGuard::hashIp('203.0.113.9', 'salt');
        for ($i = 0; $i < 5; $i++) {
            self::assertNull(AntiSpamGuard::consumeRateLimit($hash));
        }
        $blocked = AntiSpamGuard::consumeRateLimit($hash);
        self::assertInstanceOf(\WP_Error::class, $blocked);
        self::assertSame('drv_rate_limited', $blocked->get_error_code());
        self::assertSame(429, $blocked->get_error_data()['status']);
        self::assertGreaterThan(0, $blocked->get_error_data()['retry_after']);
        self::assertStringContainsString('15 phút', $blocked->get_error_message());

        self::assertNull(AntiSpamGuard::consumeRateLimit(AntiSpamGuard::hashIp('198.51.100.1', 'salt')), 'IP khác không bị ảnh hưởng');
        self::assertNotSame('203.0.113.9', $hash, 'Không lưu IP thô');
    }

    public function test_validator_normalizes_phone_and_requires_consent(): void {
        self::assertSame('0909385880', LeadValidator::normalizePhone('0909 385 880'));
        self::assertSame('0909385880', LeadValidator::normalizePhone('+84 909.385.880'));
        self::assertSame('', LeadValidator::normalizePhone('0209385880'));
        self::assertSame('', LeadValidator::normalizePhone('12345'));

        $bad = LeadValidator::validate(['name' => '1', 'phone' => '123', 'email' => 'x@', 'consent' => false], 'contact', ['HCM']);
        self::assertSame(['name', 'phone', 'email', 'message', 'consent'], array_keys($bad['errors']));

        $ok = LeadValidator::validate([
            'name' => 'Nguyễn Thanh Tùng', 'phone' => '0909 385 880', 'province' => 'hcm', 'role' => 'hacker',
            'message' => 'Cần báo giá 85 m²', 'consent' => '1', 'area_m2' => '32,5',
        ], 'contact_page', ['HCM']);
        self::assertSame([], $ok['errors']);
        self::assertSame('contact', $ok['data']['source']);
        self::assertSame('HCM', $ok['data']['province']);
        self::assertSame('chu-nha', $ok['data']['role']);
        self::assertSame(32.5, $ok['data']['area_m2']);
    }

    public function test_source_specific_required_fields(): void {
        $base = ['name' => 'Trần Minh', 'phone' => '0918668899', 'consent' => true];
        self::assertArrayHasKey('province', LeadValidator::validate($base, 'boq', ['HCM'])['errors']);
        self::assertArrayHasKey('product_id', LeadValidator::validate($base, 'pdp', ['HCM'])['errors']);
        self::assertArrayHasKey('preferred_time', LeadValidator::validate($base, 'showroom_visit', ['HCM'])['errors']);
        self::assertArrayHasKey('post_id', LeadValidator::validate($base, 'blog_inline', ['HCM'])['errors']);
        self::assertSame([], LeadValidator::validate($base, 'quote_modal', ['HCM'])['errors']);
        self::assertArrayHasKey('source', LeadValidator::validate($base, 'unknown', [])['errors']);
        self::assertSame(['utm_source' => 'zalo'], LeadValidator::sanitizeUtm(['utm_source' => 'zalo', 'evil' => 'x']));
    }
}
