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

    public function test_plain_output_is_untouched(): void
    {
        $titles = $this->parse("How to Play Indian Rummy\nRummy Rules for Beginners");

        $this->assertSame(['How to Play Indian Rummy', 'Rummy Rules for Beginners'], $titles);
    }
}
