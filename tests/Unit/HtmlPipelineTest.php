<?php

namespace Tests\Unit;

use App\Html\HtmlPass;
use App\Html\Pipeline;
use App\Html\RenderContext;
use Dom\HTMLDocument;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class HtmlPipelineTest extends TestCase
{
    private function pipeline(array $passes): Pipeline
    {
        return new Pipeline(new Container, $passes);
    }

    public function test_passes_run_in_order_on_one_document(): void
    {
        $html = $this->pipeline([AppendA::class, AppendB::class])->render('<p>start</p>');

        $this->assertSame('<p>start</p><p>A</p><p>B</p>', $html);
    }

    public function test_empty_html_skips_parsing(): void
    {
        $this->assertSame('', $this->pipeline([AppendA::class])->render(''));
        $this->assertSame('', $this->pipeline([AppendA::class])->render("  \n "));
    }

    public function test_round_trip_keeps_custom_elements_entities_and_utf8(): void
    {
        $html = '<p>Café&nbsp;<wk-secret data-id="3" contenteditable="false">Bank</wk-secret> 🔒</p>'
            .'<table><tbody><tr><td>a</td></tr></tbody></table><pre><code class="language-php">&lt;?php</code></pre>';

        $this->assertSame($html, $this->pipeline([])->render($html));
    }

    public function test_passes_receive_the_context(): void
    {
        $context = new RenderContext;
        $html = $this->pipeline([RecordContext::class])->render('<p>x</p>', $context);

        $this->assertSame($context, RecordContext::$seen);
        $this->assertSame('<p>x</p>', $html);
    }
}

class AppendA implements HtmlPass
{
    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        $p = $doc->createElement('p');
        $p->textContent = 'A';
        $doc->body->append($p);
    }
}

class AppendB implements HtmlPass
{
    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        // Runs after AppendA: sees its paragraph.
        $p = $doc->createElement('p');
        $p->textContent = $doc->body->lastElementChild->textContent === 'A' ? 'B' : 'wrong order';
        $doc->body->append($p);
    }
}

class RecordContext implements HtmlPass
{
    public static ?RenderContext $seen = null;

    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        self::$seen = $context;
    }
}
