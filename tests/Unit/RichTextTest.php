<?php

namespace Tests\Unit;

use App\Support\RichText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    #[Test]
    public function it_strips_dangerous_markup(): void
    {
        $html = RichText::sanitize('<p onclick="alert(1)">Hello <script>alert(1)</script><strong>World</strong></p><a href="javascript:alert(1)">x</a>');

        $this->assertStringContainsString('<p>', $html);
        $this->assertStringContainsString('<strong>World</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<a', $html);
    }

    #[Test]
    public function it_keeps_allowed_alignment_styles(): void
    {
        $html = RichText::sanitize('<p style="text-align: center; color: red;">Aligned</p>');

        $this->assertStringContainsString('text-align: center;', $html);
        $this->assertStringNotContainsString('color', $html);
    }

    #[Test]
    public function it_detects_blank_editor_content(): void
    {
        $this->assertTrue(RichText::isBlank('<p></p>'));
        $this->assertTrue(RichText::isBlank('<p><br></p>'));
        $this->assertFalse(RichText::isBlank('<p>Item</p>'));
    }

    #[Test]
    public function it_converts_to_plain_text_and_from_plain(): void
    {
        $this->assertSame('Hello World', RichText::toPlainText('<p>Hello <strong>World</strong></p>'));
        $this->assertSame('<p>Sample</p>', RichText::fromPlain('Sample'));
    }
}
