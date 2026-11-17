<?php
declare(strict_types=1);

namespace Dravit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Dravit\Content\AuthorProfile;
use Dravit\Content\ContentFilters;
use Dravit\Content\Shortcodes;
use Dravit\Core\Settings;
use Dravit\Seo\FaqResolver;
use Dravit\Seo\SchemaGraph;
use PHPUnit\Framework\TestCase;

final class BlogShortcodeRenderTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        Settings::flush_cache();
        FaqResolver::resetRegisteredItems();

        Functions\stubs([
            'esc_html'          => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_attr'          => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_url'           => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
            'esc_url_raw'       => static fn (mixed $v): string => trim((string) $v),
            'wp_strip_all_tags' => static fn (string $s): string => strip_tags($s),
            'wp_kses'           => static fn (string $s): string => $s,
            'wp_parse_url'      => static fn (string $url, int $component = -1): mixed => parse_url($url, $component),
            'home_url'          => static fn (string $path = ''): string => 'https://gachdravit.vn' . ($path !== '' && $path !== '/' ? '/' . ltrim($path, '/') : ''),
            'get_option'        => static fn (): array => [],
        ]);
    }

    protected function tearDown(): void {
        FaqResolver::resetRegisteredItems();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_editorial_shortcodes_render_clean_html_without_howto_or_microdata(): void {
        $takeaways = Shortcodes::renderTakeaways(['title' => 'Kết luận nhanh'], "Chọn R10 cho sàn tắm\nXương Porcelain E <= 0,5%");
        self::assertStringContainsString('drv-key-takeaways', $takeaways);
        self::assertStringContainsString('Chọn R10 cho sàn tắm', $takeaways);

        $callout = Shortcodes::renderCallout(['type' => 'warning', 'title' => 'Lưu ý mạch ron'], 'Không lát khít dưới 1,5 mm.');
        self::assertStringContainsString('drv-callout--warning', $callout);
        self::assertStringContainsString('Lưu ý mạch ron', $callout);

        $prosCons = Shortcodes::renderProsCons([
            'pros' => 'Ít đường mạch ron|Dễ vệ sinh',
            'cons' => 'Cần nền cán phẳng chuẩn',
        ]);
        self::assertStringContainsString('drv-pros-cons__col--pros', $prosCons);
        self::assertStringContainsString('drv-pros-cons__col--cons', $prosCons);

        $formula = Shortcodes::renderFormula([
            'title' => 'Quy đổi thùng gạch 80x80',
            'cells' => 'Số thùng::CEIL(S x 1,05 / 1,92)::3 viên = 1,92 m²',
        ]);
        self::assertStringContainsString('drv-formula-box', $formula);
        self::assertStringContainsString('CEIL(S x 1,05 / 1,92)', $formula);

        $steps = Shortcodes::renderSteps([
            'items' => 'Chuẩn bị cốt nền::Bước 1::Cán phẳng sai số <= 2 mm | Trải keo C2::Bước 2::Kéo bay răng cưa 8 mm',
        ]);
        self::assertStringContainsString('drv-howto-grid', $steps);
        self::assertStringNotContainsString('HowTo', $steps);
        self::assertStringNotContainsString('itemscope', $steps);

        $faq = Shortcodes::renderFaq([
            'items' => 'Gạch 80x80 mấy viên 1 thùng?::Quy cách chuẩn 3 viên = 1,92 m².',
        ]);
        self::assertStringContainsString('drv-faq-group', $faq);
        self::assertStringNotContainsString('itemscope', $faq);
        self::assertCount(1, FaqResolver::getRegisteredItems());
    }

    public function test_ai_card_shortcode_respects_feature_flag_and_content_filters_wrap_tables(): void {
        // Mặc định `features.ai_enabled = true` -> render card.
        $enabledHtml = Shortcodes::renderAiCard(['prompts' => 'Tìm gạch nhà tắm R10']);
        self::assertStringContainsString('drv-blog-ai-card', $enabledHtml);
        self::assertStringContainsString('data-drv-prompt="Tìm gạch nhà tắm R10"', $enabledHtml);

        // Khi tắt `features.ai_enabled = false` -> trả chuỗi rỗng sạch (`D-25` / `D-32`).
        Settings::flush_cache();
        Functions\when('get_option')->justReturn(['features' => ['ai_enabled' => false]]);
        self::assertSame('', Shortcodes::renderAiCard(['prompts' => 'Tìm gạch nhà tắm R10']));

        // `ContentFilters`: gỡ microdata `STRIP-20` & bọc `<table>` bằng `.drv-geo-table-wrap` + `scope="col"`.
        $dirtyHtml = '<div itemscope itemtype="https://schema.org/FAQPage"><p itemprop="name">Test</p></div>'
            . '<table><thead><tr><th>Cấp R</th><th>Khu vực</th></tr></thead><tbody><tr><td>R10</td><td>Nhà tắm</td></tr></tbody></table>';

        $clean = ContentFilters::stripMicrodataAttributes($dirtyHtml);
        $clean = ContentFilters::wrapStandaloneTables($clean);

        self::assertStringNotContainsString('itemscope', $clean);
        self::assertStringNotContainsString('itemtype', $clean);
        self::assertStringNotContainsString('itemprop', $clean);
        self::assertStringContainsString('drv-geo-table-wrap', $clean);
        self::assertStringContainsString('<th scope="col">Cấp R</th>', $clean);
    }

    public function test_faq_resolver_extracts_shortcodes_from_raw_post_content_and_schema_honors_eeat_fallback(): void {
        $rawPost = <<<'POST'
<h2>Câu hỏi thường gặp</h2>
[drv_faq open_first="1"]
[drv_faq_item q="Nhà tắm lát R9 được không?"]Không nên vì R9 trơn khi có nước xà phòng, cần tối thiểu R10.[/drv_faq_item]
[drv_faq_item q="1 thùng 60x60 bao nhiêu m²?"]4 viên tương đương 1,44 m².[/drv_faq_item]
[/drv_faq]
POST;

        $extracted = FaqResolver::extractFromPostContent($rawPost);
        self::assertCount(2, $extracted);
        self::assertSame('Nhà tắm lát R9 được không?', $extracted[0]['q']);
        self::assertStringContainsString('tối thiểu R10', $extracted[0]['a']);

        // `O-13`: User admin hoặc thiếu `job_title` -> fallback về tổ chức `Ban Kỹ thuật DRAVIT` (`#organization`).
        $orgProfile = AuthorProfile::buildProfileFromData([
            'id'        => 1,
            'name'      => 'admin',
            'job_title' => '',
        ], 'https://gachdravit.vn');
        self::assertTrue($orgProfile['is_organization']);
        self::assertSame('Ban Kỹ thuật DRAVIT', $orgProfile['name']);

        $orgNode = SchemaGraph::buildAuthorOrReviewerSchemaNode($orgProfile, 'https://gachdravit.vn');
        self::assertSame(['@id' => 'https://gachdravit.vn#organization'], $orgNode);

        // Chuyên gia đã xác minh -> xuất `Person` trỏ về `/gioi-thieu#person-{id}`.
        $expertProfile = AuthorProfile::buildProfileFromData([
            'id'        => 7,
            'name'      => 'Trần Quốc Bảo',
            'job_title' => 'Kỹ sư Vật liệu Xây dựng',
            'same_as'   => 'https://linkedin.com/in/tran-quoc-bao',
        ], 'https://gachdravit.vn');
        self::assertFalse($expertProfile['is_organization']);
        $expertNode = SchemaGraph::buildAuthorOrReviewerSchemaNode($expertProfile, 'https://gachdravit.vn');
        self::assertSame('Person', $expertNode['@type']);
        self::assertSame('https://gachdravit.vn/gioi-thieu#person-7', $expertNode['url']);

        // `M-15`: `sanitizeBlogEntities` gỡ `HowTo` + `Person` rác khi author là Organization, gắn `reviewedBy` & `mentions`.
        $mentions = SchemaGraph::buildBlogMentions(['gach-nha-tam', 'gach-80x80'], 'https://gachdravit.vn');
        $graph    = SchemaGraph::sanitizeBlogEntities(
            [
                'webpage' => ['@type' => 'WebPage', 'url' => 'https://gachdravit.vn/cam-nang/test'],
                'person'  => ['@type' => 'Person', 'name' => 'admin'],
                'howto'   => ['@type' => 'HowTo', 'name' => 'Deprecated'],
                'article' => ['@type' => 'Article', 'headline' => 'Tiêu chuẩn R10'],
            ],
            '',
            'Kỹ sư Vật liệu Xây dựng',
            $orgNode,
            $expertNode,
            '2026-03-15',
            $mentions
        );

        self::assertArrayNotHasKey('howto', $graph);
        self::assertArrayNotHasKey('person', $graph);
        self::assertSame('BlogPosting', $graph['article']['@type']);
        self::assertSame(['@id' => 'https://gachdravit.vn#organization'], $graph['article']['author']);
        self::assertSame('Trần Quốc Bảo', $graph['article']['reviewedBy']['name']);
        self::assertSame('2026-03-15', $graph['webpage']['lastReviewed']);
        self::assertCount(2, $graph['article']['mentions']);
        self::assertSame('https://gachdravit.vn/gach-nha-tam', $graph['article']['mentions'][0]['url']);
    }
}
