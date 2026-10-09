<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use App\Support\PdfExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private ?string $mediaFile = null;

    protected function tearDown(): void
    {
        if ($this->mediaFile && is_file($this->mediaFile)) {
            unlink($this->mediaFile);
        }

        parent::tearDown();
    }

    private function page(string $html, array $attrs = []): Page
    {
        $page = Page::factory()->create($attrs + ['slug' => 'guide', 'title' => 'Guide', 'visibility' => Visibility::Public]);
        $page->contents()->create(['html' => $html]);

        return $page;
    }

    public function test_returns_an_inline_pdf(): void
    {
        $this->page('<h2>Intro</h2><p>Hello <strong>PDF</strong> — ünïcödé ✓</p><table><tr><td>a</td></tr></table>');

        $response = $this->get('/pages/guide/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="guide.pdf"');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_access_matches_the_page_view(): void
    {
        $author = User::factory()->editor()->create();
        $page = $this->page('<p>x</p>', ['visibility' => Visibility::Private, 'author_id' => $author->id]);

        $this->get('/pages/guide/pdf')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->editor()->create())->get('/pages/guide/pdf')->assertForbidden();
        $this->actingAs($author)->get('/pages/guide/pdf')->assertOk();

        $page->delete();
        $this->actingAs($author)->get('/pages/guide/pdf')->assertNotFound();
    }

    public function test_secrets_print_as_names_or_hidden_never_values(): void
    {
        $secret = Secret::factory()->create(['name' => 'Bank', 'password' => 'pw-never-printed', 'visibility' => Visibility::Members]);
        $page = $this->page('<p>Use <wk-secret data-id="'.$secret->id.'">Bank</wk-secret></p>');
        $export = app(PdfExport::class);

        $forMember = $export->html($page, User::factory()->create());
        $this->assertStringContainsString('[Secret: Bank]', $forMember);
        $this->assertStringNotContainsString('pw-never-printed', $forMember);
        $this->assertStringNotContainsString('secretCard', $forMember);

        $forGuest = $export->html($page, null);
        $this->assertStringContainsString('[Hidden]', $forGuest);
        $this->assertStringNotContainsString('Bank', $forGuest);
    }

    public function test_images_iframes_and_links_are_made_print_safe(): void
    {
        @mkdir(base_path('data/media'), 0775, true);
        $this->mediaFile = base_path('data/media/pdf-test-image.png');
        file_put_contents($this->mediaFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        $page = $this->page(
            '<p><img src="/data/media/pdf-test-image.png" alt="Local">'
            .'<img src="https://tracker.example/pixel.png" alt="Remote">'
            .'<img src="/data/media/missing.png">'
            .'<img src="/data/media/../../.env" alt="Escape"></p>'
            .'<iframe src="https://www.youtube.com/embed/abc"></iframe>'
            .'<p><a href="/faq">FAQ</a> <a href="https://example.org">ext</a></p>'
        );

        $html = app(PdfExport::class)->html($page, null);

        $this->assertStringContainsString('src="'.realpath($this->mediaFile).'"', $html);
        $this->assertStringContainsString('[Remote]', $html);
        $this->assertStringNotContainsString('tracker.example', $html);
        $this->assertStringContainsString('[image]', $html);
        $this->assertStringContainsString('[Escape]', $html);
        $this->assertStringNotContainsString('.env', $html);
        $this->assertStringContainsString('[Video: https://www.youtube.com/embed/abc]', $html);
        $this->assertStringContainsString('href="'.url('/faq').'"', $html);
        $this->assertStringContainsString('href="https://example.org"', $html);

        $this->get('/pages/guide/pdf')->assertOk();
    }

    public function test_page_view_has_the_pdf_button(): void
    {
        $this->page('<p>x</p>');

        $this->get('/guide')->assertSee(route('pages.pdf', 'guide'));
    }
}
