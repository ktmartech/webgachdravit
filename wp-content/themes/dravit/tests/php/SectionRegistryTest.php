<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Core\SectionRegistry;
use PHPUnit\Framework\TestCase;

final class SectionRegistryTest extends TestCase {
    private string $tempSectionFile = '';
    private ?string $originalHeroContent = null;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        SectionRegistry::reset_cache();

        Functions\stubs([
            'esc_html'     => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'sanitize_key' => static fn (mixed $v): string => (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
        ]);

        $dir = DRV_DIR . '/template-parts/sections';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->tempSectionFile = $dir . '/hero-advisor.php';
        $this->originalHeroContent = is_file($this->tempSectionFile)
            ? (string) file_get_contents($this->tempSectionFile)
            : null;

        file_put_contents(
            $this->tempSectionFile,
            "<?php\ndeclare(strict_types=1);\n/** @var array<string, mixed> \$args */\necho '<section data-test-hero=\"' . esc_html((string) (\$args['variant'] ?? '')) . '\">' . esc_html((string) (\$args['eyebrow'] ?? '')) . '</section>';\n"
        );
    }

    protected function tearDown(): void {
        if ($this->tempSectionFile !== '') {
            if ($this->originalHeroContent !== null) {
                file_put_contents($this->tempSectionFile, $this->originalHeroContent);
            } elseif (is_file($this->tempSectionFile)) {
                @unlink($this->tempSectionFile);
            }
        }
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_merges_defaults_and_respects_enabled_flag(): void {
        ob_start();
        drv_section('hero-advisor', ['eyebrow' => 'Tư vấn chọn gạch AI']);
        $rendered = (string) ob_get_clean();

        self::assertStringContainsString('data-test-hero="home"', $rendered);
        self::assertStringContainsString('Tư vấn chọn gạch AI', $rendered);

        ob_start();
        drv_section('hero-advisor', ['enabled' => false, 'eyebrow' => 'Bị tắt']);
        $disabledHtml = (string) ob_get_clean();

        self::assertSame('', $disabledHtml);
    }

    public function test_render_stack_renders_multiple_configured_sections(): void {
        ob_start();
        SectionRegistry::render_stack([
            ['section' => 'hero-advisor', 'props' => ['variant' => 'landing', 'eyebrow' => 'Khối 1']],
            ['section' => 'hero-advisor', 'props' => ['enabled' => false, 'eyebrow' => 'Khối ẩn']],
            ['section' => 'hero-advisor', 'props' => ['variant' => 'compact', 'eyebrow' => 'Khối 2']],
        ]);
        $html = (string) ob_get_clean();

        self::assertStringContainsString('data-test-hero="landing"', $html);
        self::assertStringContainsString('data-test-hero="compact"', $html);
        self::assertStringNotContainsString('Khối ẩn', $html);
    }
}

