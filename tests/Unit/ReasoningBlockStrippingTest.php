<?php

namespace Tests\Unit;

use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Tests\TestCase;

/**
 * 推理模型的思考块一旦进入正文，会成为文章开头并被截进 SEO 描述。
 */
class ReasoningBlockStrippingTest extends TestCase
{
    public function test_paired_think_block_is_removed(): void
    {
        $content = "<think>\nLet me plan the structure first.\n</think>\n\n## How withdrawals work\n\nBody text.";

        $this->assertSame(
            "## How withdrawals work\n\nBody text.",
            OpenAiRuntimeProvider::normalizeGeneratedText($content),
        );
    }

    public function test_dangling_close_tag_keeps_only_the_body(): void
    {
        $content = "The user wants an article about payouts.\nI should avoid inventing numbers.\n</think>\n## Payouts\n\nBody.";

        $this->assertSame("## Payouts\n\nBody.", OpenAiRuntimeProvider::normalizeGeneratedText($content));
    }

    public function test_dangling_open_tag_keeps_text_before_it(): void
    {
        $content = "## Payouts\n\nBody text.\n\n<think>should I add more?";

        $this->assertSame("## Payouts\n\nBody text.", OpenAiRuntimeProvider::normalizeGeneratedText($content));
    }

    public function test_open_tag_at_start_does_not_wipe_everything(): void
    {
        // 整段都在思考块里且无闭合标签时，宁可保留原文也不要产出空文章。
        $content = "<think>only reasoning here";

        $this->assertNotSame('', OpenAiRuntimeProvider::normalizeGeneratedText($content));
    }

    public function test_alternate_tag_names_are_handled(): void
    {
        $content = "<reasoning>plan</reasoning>\n## Title\n\nBody.";

        $this->assertSame("## Title\n\nBody.", OpenAiRuntimeProvider::normalizeGeneratedText($content));
    }

    public function test_normal_content_is_untouched(): void
    {
        $content = "## How to withdraw\n\nOpen the app and tap Withdraw.";

        $this->assertSame($content, OpenAiRuntimeProvider::normalizeGeneratedText($content));
    }
}
