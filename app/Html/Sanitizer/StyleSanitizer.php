<?php

namespace App\Html\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps the inline styles the editor produces (alignment, sizes, spacing, borders, colours) and
 * drops every other declaration, plus any value that could load a resource or run code.
 */
class StyleSanitizer implements AttributeSanitizerInterface
{
    private const PROPERTIES = [
        'text-align', 'vertical-align', 'float', 'clear',
        'width', 'height', 'max-width', 'min-width', 'max-height', 'min-height',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-width', 'border-style', 'border-color', 'border-collapse', 'border-spacing', 'border-radius',
        'color', 'background-color',
        'font-weight', 'font-style', 'font-size', 'font-family', 'line-height',
        'text-decoration', 'text-indent', 'list-style-type', 'white-space',
    ];

    /** Anything that can fetch, script or escape the property parser. */
    private const UNSAFE = '/url\s*\(|expression|javascript:|@import|behaviou?r|\\\\|<|>|\/\*/i';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $kept = [];
        foreach (explode(';', $value) as $declaration) {
            [$property, $val] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $val = trim($val);

            if ($val !== '' && in_array($property, self::PROPERTIES, true) && ! preg_match(self::UNSAFE, $val)) {
                $kept[] = "{$property}: {$val}";
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
