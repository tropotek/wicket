<?php

namespace Tests\Feature;

use App\Html\PageLinks;
use Tests\TestCase;

class PageLinksTest extends TestCase
{
    public function test_recognises_page_links(): void
    {
        $links = app(PageLinks::class);
        $own = 'http://'.request()->getHost();

        $cases = [
            '/getting-started' => 'getting-started',
            '/getting-started/' => 'getting-started',
            '/getting-started?x=1#part' => 'getting-started',
            '/Wiki_How_To' => 'Wiki_How_To',
            $own.'/faq' => 'faq',
            '/data/media/x.png' => null,
            '/pages/faq/edit' => null,
            '/admin' => null,
            '/login' => null,
            '/' => null,
            'faq' => null,
            '//other.example/faq' => null,
            'https://other.example/faq' => null,
            'mailto:faq@example.org' => null,
            '#faq' => null,
            'javascript:alert(1)' => null,
        ];

        foreach ($cases as $href => $slug) {
            $this->assertSame($slug, $links->slug($href), $href);
        }
    }

    public function test_slugs_in_html_are_distinct(): void
    {
        $html = '<p><a href="/a">A</a> <a href="/a#x">A again</a> <a href="/b">B</a> <a href="https://example.org/c">ext</a></p>';

        $this->assertSame(['a', 'b'], app(PageLinks::class)->slugsIn($html));
    }
}
