<?php

namespace Tests\Unit;

use App\Services\GeoFlow\TitleAiGenerationService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 推理模型（如 MiniMax M3）会输出 <think> 思考块和解说文字，
 * 这些一旦被当成标题存库，就会变成一篇文章的标题。
 */
class TitleAiGenerationParsingTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function parse(string $content): array
    {
        $method = new ReflectionMethod(TitleAiGenerationService::class, 'parseGeneratedTitles');
        $method->setAccessible(true);

        return $method->invoke(app(TitleAiGenerationService::class), $content);
    }

    public function test_reasoning_block_is_stripped(): void
    {
        $titles = $this->parse(<<<'TXT'
<think>
Let me refine these to make them more natural and compelling.
The user wants question-style headlines.
</think>
How Do You Withdraw Money from Taj Rummy?
What Is the Minimum Withdrawal Amount on Junglee Rummy?
TXT);

        $this->assertSame([
            'How Do You Withdraw Money from Taj Rummy?',
            'What Is the Minimum Withdrawal Amount on Junglee Rummy?',
        ], $titles);
    }

    public function test_dangling_close_tag_is_handled(): void
    {
        $titles = $this->parse("Let me think about the best angle here.\n</think>\nWhy Is Your Pocket52 Withdrawal Not Going Through?");

        $this->assertSame(['Why Is Your Pocket52 Withdrawal Not Going Through?'], $titles);
    }

    public function test_keyword_echo_prefix_is_removed(): void
    {
        $titles = $this->parse("RummyCulture withdrawal → How Do You Withdraw Money from RummyCulture?");

        $this->assertSame(['How Do You Withdraw Money from RummyCulture?'], $titles);
    }

    public function test_commentary_lines_are_dropped(): void
    {
        $titles = $this->parse(<<<'TXT'
Here are 3 question-style headlines:
1. What Are RummyCircle's Withdrawal Charges?
Let me refine these to make them more natural and compelling.
2. **How Long Does KhelPlay Rummy Take to Process Withdrawals?**
Note:
TXT);

        $this->assertSame([
            "What Are RummyCircle's Withdrawal Charges?",
            'How Long Does KhelPlay Rummy Take to Process Withdrawals?',
        ], $titles);
    }

    public function test_multibyte_titles_are_not_corrupted(): void
    {
        // trim() 的字符集按字节处理，中文引号会削坏相邻汉字，必须用带 u 的正则。
        $titles = $this->parse("“印度拉米怎么玩”\nGEO 标题一\nRummy 提现要多久？");

        $this->assertSame(['印度拉米怎么玩', 'GEO 标题一', 'Rummy 提现要多久？'], $titles);
    }

    public function test_title_is_matched_back_to_its_own_keyword(): void
    {
        // 随机回填会让标题写 RummyVerse、关键词却是 PlayRummy，
        // 正文随后同时出现两个品牌。
        $keywords = ['PlayRummy withdrawal', 'RummyVerse withdrawal limit', 'Taj Rummy KYC verification for withdrawal'];

        $this->assertSame(
            'RummyVerse withdrawal limit',
            TitleAiGenerationService::matchKeywordForTitle(
                'What Is the RummyVerse Withdrawal Limit You Should Know About?',
                $keywords,
            ),
        );

        $this->assertSame(
            'Taj Rummy KYC verification for withdrawal',
            TitleAiGenerationService::matchKeywordForTitle(
                'What Are the KYC Verification Requirements for Taj Rummy Withdrawals?',
                $keywords,
            ),
        );
    }

    public function test_unmatched_title_still_gets_a_keyword(): void
    {
        $keyword = TitleAiGenerationService::matchKeywordForTitle('Completely Unrelated Headline', ['rummy rules']);

        $this->assertSame('rummy rules', $keyword);
    }

    public function test_colon_titles_are_kept_whole(): void
    {
        // 冒号在标题里是合法的，不能当成「关键词: 标题」的分隔符切掉。
        $titles = $this->parse("Rummy Withdrawal: A Complete Guide\nKYC for Payouts: What You Need");

        $this->assertSame(['Rummy Withdrawal: A Complete Guide', 'KYC for Payouts: What You Need'], $titles);
    }

    public function test_plain_output_is_untouched(): void
    {
        $titles = $this->parse("How to Play Indian Rummy\nRummy Rules for Beginners");

        $this->assertSame(['How to Play Indian Rummy', 'Rummy Rules for Beginners'], $titles);
    }
}
