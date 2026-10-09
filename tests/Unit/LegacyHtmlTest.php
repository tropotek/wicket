<?php

namespace Tests\Unit;

use App\Import\LegacyHtml;
use PHPUnit\Framework\TestCase;

class LegacyHtmlTest extends TestCase
{
    public function test_page_links_become_root_relative_wiki_links(): void
    {
        $html = (new LegacyHtml(['Old_Name' => 'Old_Name-page']))->rewrite(
            '<p><a href="page://Wiki_How_To" title="How">a</a> <a class="x" href="page://Old_Name#top">b</a> <a href="page://My%20Page">c</a></p>');

        $this->assertSame('<p><a href="/Wiki_How_To" title="How" class="wk-page">a</a> '
            .'<a class="x wk-page" href="/Old_Name-page#top">b</a> <a href="/My%20Page" class="wk-page">c</a></p>', $html);
    }

    public function test_html_without_legacy_links_is_returned_untouched(): void
    {
        $legacy = new LegacyHtml([]);

        $this->assertSame('<p>Plain <a href="https://x.test">x</a>', $legacy->rewrite('<p>Plain <a href="https://x.test">x</a>'));
        $this->assertSame('', $legacy->rewrite(''));
    }
}
