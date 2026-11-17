<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Dravit\Woo\AccountIdentity;
use PHPUnit\Framework\TestCase;

/**
 * `/tai-khoan`: đăng nhập bằng SĐT hoặc email, kiểm tra form đăng ký, quy tắc mật khẩu.
 */
final class AccountIdentityTest extends TestCase {
    public function test_classify_phone_variants_to_normalized_vn_number(): void {
        foreach (['0912345678', '0912 345 678', '0912.345.678', '+84 912 345 678', '84912345678', '(091) 234-5678'] as $raw) {
            self::assertSame(['type' => 'phone', 'value' => '0912345678'], AccountIdentity::classify($raw), $raw);
        }
    }

    public function test_classify_email_login_and_empty(): void {
        self::assertSame(['type' => 'email', 'value' => 'an.nguyen@gmail.com'], AccountIdentity::classify('  An.Nguyen@Gmail.com '));
        self::assertSame('login', AccountIdentity::classify('admin_dravit')['type']);
        // Số không đúng đầu số VN → giữ nguyên làm tên đăng nhập, không đoán.
        self::assertSame(['type' => 'login', 'value' => '0281234567'], AccountIdentity::classify('0281234567'));
        self::assertSame('empty', AccountIdentity::classify('   ')['type']);
    }

    public function test_valid_registration_has_no_errors(): void {
        self::assertSame([], AccountIdentity::validateRegistration([
            'name'     => 'Nguyễn Văn An',
            'phone'    => '0912 345 678',
            'email'    => 'an@congty.vn',
            'password' => 'gachdep2026',
            'honeypot' => '',
        ]));
    }

    public function test_registration_errors_per_field(): void {
        $errors = AccountIdentity::validateRegistration([
            'name'     => '',
            'phone'    => '0281234567',
            'email'    => 'khong-phai-email',
            'password' => 'abc',
        ]);
        self::assertSame(['name', 'phone', 'email', 'password'], array_keys($errors));
        self::assertStringContainsString('10 số', $errors['phone']);

        $long = AccountIdentity::validateRegistration(['name' => str_repeat('Ă', 61), 'phone' => '0912345678', 'email' => 'a@b.vn', 'password' => 'abcd1234']);
        self::assertSame(['name'], array_keys($long));
    }

    public function test_honeypot_blocks_without_field_details(): void {
        $errors = AccountIdentity::validateRegistration([
            'name'     => 'Bot',
            'phone'    => '0912345678',
            'email'    => 'bot@spam.io',
            'password' => 'abcd1234',
            'honeypot' => 'https://spam.example',
        ]);
        self::assertSame(['form'], array_keys($errors));
    }

    public function test_password_rules(): void {
        self::assertNotSame('', AccountIdentity::passwordError(''));
        self::assertStringContainsString('8', AccountIdentity::passwordError('ab12'));
        self::assertStringContainsString('chữ và số', AccountIdentity::passwordError('12345678'));
        self::assertStringContainsString('chữ và số', AccountIdentity::passwordError('abcdefgh'));
        self::assertSame('', AccountIdentity::passwordError('gạchđẹp99'));
    }

    public function test_generic_login_error_does_not_reveal_account(): void {
        $msg = AccountIdentity::genericLoginError();
        self::assertStringNotContainsString('chưa được đăng ký', $msg);
        self::assertStringContainsString('mật khẩu chưa đúng', $msg);
    }
}
