<?php

namespace App\Html;

use App\Html\Sanitizer\EmbedSanitizer;
use App\Html\Sanitizer\LocalUrlSanitizer;
use App\Html\Sanitizer\StyleSanitizer;
use App\Support\SiteHosts;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The allow-list for stored page HTML: what the editor produces, nothing that runs code or
 * pulls in other sites (bar video embeds from wicket.embed_hosts). Links and images on this site
 * are stored as paths (see SiteHosts). Idempotent.
 */
class Sanitizer
{
    private HtmlSanitizer $sanitizer;

    /** @param  list<string>  $embedHosts */
    public function __construct(array $embedHosts, SiteHosts $siteHosts)
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('wk-secret', ['data-id', 'contenteditable'])
            ->allowElement('iframe', ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'frameborder', 'loading'])
            ->allowAttribute('class', '*')
            ->allowAttribute('style', '*')
            ->allowAttribute('title', '*')
            ->allowAttribute('rel', ['a'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withAttributeSanitizer(new StyleSanitizer)
            ->withAttributeSanitizer(new EmbedSanitizer(array_map('strtolower', $embedHosts)))
            ->withAttributeSanitizer(new LocalUrlSanitizer($siteHosts))
            ->withMaxInputLength(-1);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function clean(?string $html): string
    {
        return trim((string) $html) === '' ? '' : $this->sanitizer->sanitize($html);
    }
}
