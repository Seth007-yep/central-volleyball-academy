<?php

declare(strict_types=1);

namespace Cva\Support;

/**
 * Small HTML output helper for values inserted into page text or attributes.
 *
 * Escape when rendering, rather than trying to guess which input is safe when
 * it is stored. This does not replace validation or a rich-text sanitizer.
 */
final class Html
{
    /** Convert a string to safe UTF-8 text for an HTML text/attribute context. */
    public static function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
