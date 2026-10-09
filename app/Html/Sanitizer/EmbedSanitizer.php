<?php

namespace App\Html\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/** An <iframe> may only show https pages from the configured embed hosts (video players). */
class EmbedSanitizer implements AttributeSanitizerInterface
{
    /** @param  list<string>  $hosts */
    public function __construct(private array $hosts) {}

    public function getSupportedElements(): ?array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $parts = parse_url(trim($value));

        if (($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), $this->hosts, true)) {
            return null;
        }

        return $value;
    }
}
