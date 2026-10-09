<?php

namespace App\Html\Sanitizer;

use App\Support\SiteHosts;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/** Links and images on this site (any of its hosts) are stored as root-relative paths. */
class LocalUrlSanitizer implements AttributeSanitizerInterface
{
    public function __construct(private SiteHosts $hosts) {}

    public function getSupportedElements(): ?array
    {
        return ['a', 'img'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['href', 'src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $this->hosts->localise($value);
    }
}
