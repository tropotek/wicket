<?php

namespace App\Html;

use Dom\HTMLDocument;
use Illuminate\Contracts\Container\Container;

/**
 * Runs the configured passes (config wicket.html_passes, in order) over stored page HTML before
 * display. The HTML is parsed once with PHP's HTML5 parser and serialised once at the end.
 */
class Pipeline
{
    /** @param  list<class-string<HtmlPass>>  $passes */
    public function __construct(private Container $container, private array $passes) {}

    public function render(string $html, RenderContext $context = new RenderContext): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, LIBXML_NOERROR, 'UTF-8');

        foreach ($this->passes as $pass) {
            $this->container->make($pass)->apply($doc, $context);
        }

        return $doc->body->innerHTML;
    }
}
