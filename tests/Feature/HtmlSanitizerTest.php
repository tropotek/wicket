<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Html\Sanitizer;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    private function clean(string $html): string
    {
        return app(Sanitizer::class)->clean($html);
    }

    public function test_malicious_payloads_are_neutralised(): void
    {
        $payloads = [
            '<script>alert(1)</script>' => 'alert',
            '<img src="/x.png" onerror="alert(1)">' => 'onerror',
            '<a href="javascript:alert(1)">x</a>' => 'javascript:',
            '<a href="JaVaScRiPt:alert(1)">x</a>' => 'alert',
            '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>' => 'data:',
            '<img src="data:image/png;base64,iVBORw0KGgo=">' => 'data:',
            '<svg onload="alert(1)"><circle r="1"/></svg>' => 'onload',
            '<object data="x.swf"></object>' => 'object',
            '<embed src="x.swf">' => 'embed',
            '<base href="https://evil.example/">' => 'base',
            '<meta http-equiv="refresh" content="0;url=https://evil.example">' => 'refresh',
            '<link rel="stylesheet" href="https://evil.example/x.css">' => 'evil',
            '<form action="https://evil.example"><input name="p"></form>' => 'evil',
            '<iframe src="https://evil.example/phish"></iframe>' => 'evil',
            '<iframe src="http://www.youtube.com/embed/x"></iframe>' => 'youtube',
            '<iframe srcdoc="&lt;script&gt;alert(1)&lt;/script&gt;"></iframe>' => 'srcdoc',
            '<p style="background: url(https://evil.example/t.png)">x</p>' => 'evil',
            '<p style="width: expression(alert(1))">x</p>' => 'expression',
            '<p style="position: fixed; top: 0">x</p>' => 'position',
            '<p style="color: red; background-image: url(x)">x</p>' => 'background-image',
            '<div onclick="steal()">x</div>' => 'steal',
            '<wk-secret data-id="1" onmouseover="x()">n</wk-secret>' => 'onmouseover',
        ];

        foreach ($payloads as $html => $mustNotContain) {
            $this->assertStringNotContainsStringIgnoringCase($mustNotContain, $this->clean($html), $html);
        }
    }

    public function test_wiki_content_survives(): void
    {
        $keep = [
            '<p style="text-align: center">Centred</p>',
            '<table class="table" style="width: 100%; border-collapse: collapse"><tbody><tr><td colspan="2">a</td></tr></tbody></table>',
            '<p>Pay with <wk-secret data-id="12" contenteditable="false">Bank</wk-secret> today</p>',
            '<pre class="language-php"><code>&lt;?php echo 1;</code></pre>',
            '<p><a href="/faq" class="wk-page">FAQ</a> <a href="https://example.org" target="_blank" rel="noopener">ext</a> <a href="mailto:a@example.org">mail</a></p>',
            '<figure class="image"><img src="/data/media/uploads/2026/10/a.png" alt="A" width="300" height="200"><figcaption>Caption</figcaption></figure>',
            '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" allowfullscreen></iframe>',
            '<h2 id="setup">Setup</h2><ul><li><strong>bold</strong> <em>em</em> <s>gone</s></li></ul>',
            '<p><span style="color: #e03e2d; font-weight: bold">red</span></p>',
        ];

        foreach ($keep as $html) {
            // The sanitiser entity-encodes "@" in attributes and self-closes void elements; both read back identically.
            $this->assertSame($html, str_replace(['&#64;', ' />'], ['@', '>'], $this->clean($html)), $html);
        }
    }

    public function test_sanitising_is_idempotent(): void
    {
        $messy = '<p style="text-align:center;position:absolute">x<script>y</script><wk-secret data-id="3">s</wk-secret></p><img src="/a.png" onerror="z">';
        $once = $this->clean($messy);

        $this->assertSame($once, $this->clean($once));
    }

    public function test_page_form_stores_clean_html_without_spurious_revisions(): void
    {
        $editor = User::factory()->editor()->create();
        $page = Page::factory()->for($editor, 'author')->create(['slug' => 'doc', 'visibility' => Visibility::Public]);
        $form = ['title' => 'Doc', 'slug' => 'doc', 'visibility' => 'public', 'publish' => '1', 'html' => '<p>Hi<script>alert(1)</script></p>'];

        $this->actingAs($editor)->put(route('pages.update', $page), $form);
        $this->assertSame('<p>Hi</p>', $page->latestContent()->first()->html);

        $this->actingAs($editor)->put(route('pages.update', $page), $form);
        $this->assertSame(1, $page->contents()->count());
    }

    public function test_content_written_directly_is_cleaned(): void
    {
        $page = Page::factory()->create();
        $content = $page->contents()->create(['html' => '<p onclick="x()">Imported</p><script>bad()</script>']);

        $this->assertSame('<p>Imported</p>', $content->fresh()->html);
    }
}
