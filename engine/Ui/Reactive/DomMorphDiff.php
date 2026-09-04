<?php
declare(strict_types=1);

namespace Oshim\Ui\Reactive;

/**
 * Pure PHP Virtual DOM Morph & Diff Engine for reactive client hydration.
 */
class DomMorphDiff
{
    /**
     * Compute differences between old and new HTML outputs to produce atomic morph instructions.
     *
     * @return array{
     *     has_changes: bool,
     *     old_hash: string,
     *     new_hash: string,
     *     patches: array<array{type: string, target?: string, content?: string}>
     * }
     */
    public static function diff(string $oldHtml, string $newHtml): array
    {
        $oldHtml = trim($oldHtml);
        $newHtml = trim($newHtml);

        $oldHash = md5($oldHtml);
        $newHash = md5($newHtml);

        if ($oldHash === $newHash) {
            return [
                'has_changes' => false,
                'old_hash' => $oldHash,
                'new_hash' => $newHash,
                'patches' => [],
            ];
        }

        $patches = [];

        // Check for quick root-level replacement
        if (self::extractTag($oldHtml) !== self::extractTag($newHtml)) {
            $patches[] = [
                'type' => 'REPLACE_ROOT',
                'content' => $newHtml,
            ];
        } else {
            // Attribute / Text morphing check
            $oldAttrs = self::extractAttributes($oldHtml);
            $newAttrs = self::extractAttributes($newHtml);

            if ($oldAttrs !== $newAttrs) {
                $patches[] = [
                    'type' => 'UPDATE_ATTRIBUTES',
                    'attributes' => $newAttrs,
                ];
            }

            $patches[] = [
                'type' => 'MORPH_INNER_HTML',
                'content' => self::extractInnerHtml($newHtml),
            ];
        }

        return [
            'has_changes' => true,
            'old_hash' => $oldHash,
            'new_hash' => $newHash,
            'patches' => $patches,
        ];
    }

    private static function extractTag(string $html): string
    {
        if (preg_match('/^<([a-zA-Z0-9\-]+)/', $html, $m)) {
            return strtolower($m[1]);
        }
        return '';
    }

    private static function extractAttributes(string $html): array
    {
        $attrs = [];
        if (preg_match('/^<[a-zA-Z0-9\-]+\s+([^>]+)>/', $html, $m)) {
            $attrStr = $m[1];
            if (preg_match_all('/([a-zA-Z0-9\-:]+)="([^"]*)"/', $attrStr, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $attrs[$match[1]] = $match[2];
                }
            }
        }
        return $attrs;
    }

    private static function extractInnerHtml(string $html): string
    {
        $tag = self::extractTag($html);
        if ($tag === '') {
            return $html;
        }

        // Strip outer opening and closing tags
        $stripped = preg_replace('/^<[a-zA-Z0-9\-]+[^>]*>/', '', $html);
        $stripped = preg_replace('/<\/' . preg_quote($tag, '/') . '>$/', '', (string)$stripped);
        return trim((string)$stripped);
    }
}
