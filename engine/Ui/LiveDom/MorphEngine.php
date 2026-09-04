<?php
declare(strict_types=1);

namespace Oshim\Ui\LiveDom;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Pure PHP DOM Diffing & Morphing Engine.
 * Computes atomic structural diffs and patch instructions between HTML revisions
 * with zero external dependencies.
 */
class MorphEngine
{
    public const OP_REPLACE_ROOT   = 'REPLACE_ROOT';
    public const OP_UPDATE_TEXT    = 'UPDATE_TEXT';
    public const OP_SET_ATTR       = 'SET_ATTR';
    public const OP_REMOVE_ATTR    = 'REMOVE_ATTR';
    public const OP_REPLACE_NODE   = 'REPLACE_NODE';
    public const OP_INSERT_CHILD   = 'INSERT_CHILD';
    public const OP_REMOVE_CHILD   = 'REMOVE_CHILD';
    public const OP_MORPH_INNER    = 'MORPH_INNER';

    /**
     * Compute differences between old and new HTML strings.
     *
     * @return array{
     *     has_changes: bool,
     *     old_hash: string,
     *     new_hash: string,
     *     patches: array<array{op: string, path?: string, key?: string, value?: mixed, html?: string, text?: string, index?: int}>,
     *     html: string
     * }
     */
    public function diff(string $oldHtml, string $newHtml, ?string $rootId = null): array
    {
        $oldHtml = trim($oldHtml);
        $newHtml = trim($newHtml);

        $oldHash = md5($oldHtml);
        $newHash = md5($newHtml);

        if ($oldHash === $newHash) {
            return [
                'has_changes' => false,
                'old_hash'    => $oldHash,
                'new_hash'    => $newHash,
                'patches'     => [],
                'html'        => $newHtml,
            ];
        }

        $oldTag = $this->extractTag($oldHtml);
        $newTag = $this->extractTag($newHtml);

        $patches = [];

        // If root tags differ or one is empty, replace root
        if ($oldTag === '' || $newTag === '' || strtolower($oldTag) !== strtolower($newTag)) {
            $patches[] = [
                'op'   => self::OP_REPLACE_ROOT,
                'html' => $newHtml,
                'id'   => $rootId,
            ];

            return [
                'has_changes' => true,
                'old_hash'    => $oldHash,
                'new_hash'    => $newHash,
                'patches'     => $patches,
                'html'        => $newHtml,
            ];
        }

        // Try fine-grained DOM diffing if DOMDocument is available
        if (class_exists(DOMDocument::class)) {
            try {
                $docOld = $this->createDomDocument($oldHtml);
                $docNew = $this->createDomDocument($newHtml);

                $rootOld = $this->findFirstElement($docOld);
                $rootNew = $this->findFirstElement($docNew);

                if ($rootOld !== null && $rootNew !== null) {
                    $this->diffNodes($rootOld, $rootNew, '', $patches);

                    return [
                        'has_changes' => true,
                        'old_hash'    => $oldHash,
                        'new_hash'    => $newHash,
                        'patches'     => $patches,
                        'html'        => $newHtml,
                    ];
                }
            } catch (\Throwable) {
                // Fall back to attribute + inner morphing
            }
        }

        // Fast attribute and inner HTML diff fallback
        $oldAttrs = $this->extractAttributes($oldHtml);
        $newAttrs = $this->extractAttributes($newHtml);

        foreach ($newAttrs as $k => $v) {
            if (!array_key_exists($k, $oldAttrs) || $oldAttrs[$k] !== $v) {
                $patches[] = [
                    'op'    => self::OP_SET_ATTR,
                    'key'   => $k,
                    'value' => $v,
                    'path'  => '',
                ];
            }
        }

        foreach ($oldAttrs as $k => $v) {
            if (!array_key_exists($k, $newAttrs)) {
                $patches[] = [
                    'op'   => self::OP_REMOVE_ATTR,
                    'key'  => $k,
                    'path' => '',
                ];
            }
        }

        $oldInner = $this->extractInnerHtml($oldHtml);
        $newInner = $this->extractInnerHtml($newHtml);

        if ($oldInner !== $newInner) {
            $patches[] = [
                'op'   => self::OP_MORPH_INNER,
                'html' => $newInner,
                'path' => '',
            ];
        }

        return [
            'has_changes' => true,
            'old_hash'    => $oldHash,
            'new_hash'    => $newHash,
            'patches'     => $patches,
            'html'        => $newHtml,
        ];
    }

    /**
     * Recursive tree diff between two DOMNodes.
     */
    protected function diffNodes(DOMNode $oldNode, DOMNode $newNode, string $path, array &$patches): void
    {
        // 1. If different node types, replace node
        if ($oldNode->nodeType !== $newNode->nodeType) {
            $patches[] = [
                'op'   => self::OP_REPLACE_NODE,
                'path' => $path,
                'html' => $this->nodeToHtml($newNode),
            ];
            return;
        }

        // 2. Text node diff
        if ($oldNode->nodeType === XML_TEXT_NODE) {
            if ($oldNode->nodeValue !== $newNode->nodeValue) {
                $patches[] = [
                    'op'   => self::OP_UPDATE_TEXT,
                    'path' => $path,
                    'text' => $newNode->nodeValue ?? '',
                ];
            }
            return;
        }

        // 3. Element node diff
        if ($oldNode instanceof DOMElement && $newNode instanceof DOMElement) {
            if (strtolower($oldNode->tagName) !== strtolower($newNode->tagName)) {
                $patches[] = [
                    'op'   => self::OP_REPLACE_NODE,
                    'path' => $path,
                    'html' => $this->nodeToHtml($newNode),
                ];
                return;
            }

            // Morph attributes
            $oldAttrs = [];
            foreach ($oldNode->attributes as $attr) {
                $oldAttrs[$attr->name] = $attr->value;
            }

            $newAttrs = [];
            foreach ($newNode->attributes as $attr) {
                $newAttrs[$attr->name] = $attr->value;
            }

            // Added or modified attributes
            foreach ($newAttrs as $k => $v) {
                if (!array_key_exists($k, $oldAttrs) || $oldAttrs[$k] !== $v) {
                    $patches[] = [
                        'op'    => self::OP_SET_ATTR,
                        'path'  => $path,
                        'key'   => $k,
                        'value' => $v,
                    ];
                }
            }

            // Removed attributes
            foreach ($oldAttrs as $k => $v) {
                if (!array_key_exists($k, $newAttrs)) {
                    $patches[] = [
                        'op'   => self::OP_REMOVE_ATTR,
                        'path' => $path,
                        'key'  => $k,
                    ];
                }
            }

            // Morph child nodes
            $this->diffChildren($oldNode, $newNode, $path, $patches);
        }
    }

    /**
     * Diff children of two elements, respecting keys if present.
     */
    protected function diffChildren(DOMElement $oldNode, DOMElement $newNode, string $parentPath, array &$patches): void
    {
        $oldChildren = $this->getCleanChildNodes($oldNode);
        $newChildren = $this->getCleanChildNodes($newNode);

        $oldLen = count($oldChildren);
        $newLen = count($newChildren);
        $commonLen = min($oldLen, $newLen);

        for ($i = 0; $i < $commonLen; $i++) {
            $childPath = $parentPath === '' ? (string)$i : "{$parentPath}.{$i}";
            $this->diffNodes($oldChildren[$i], $newChildren[$i], $childPath, $patches);
        }

        // New children added
        if ($newLen > $oldLen) {
            for ($i = $oldLen; $i < $newLen; $i++) {
                $patches[] = [
                    'op'    => self::OP_INSERT_CHILD,
                    'path'  => $parentPath,
                    'index' => $i,
                    'html'  => $this->nodeToHtml($newChildren[$i]),
                ];
            }
        }

        // Extra old children removed
        if ($oldLen > $newLen) {
            for ($i = $oldLen - 1; $i >= $newLen; $i--) {
                $patches[] = [
                    'op'    => self::OP_REMOVE_CHILD,
                    'path'  => $parentPath,
                    'index' => $i,
                ];
            }
        }
    }

    /**
     * Filter out empty whitespace text nodes for cleaner diffs.
     *
     * @return array<DOMNode>
     */
    protected function getCleanChildNodes(DOMNode $node): array
    {
        $clean = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue ?? '') === '') {
                continue;
            }
            $clean[] = $child;
        }
        return $clean;
    }

    protected function nodeToHtml(DOMNode $node): string
    {
        $doc = $node->ownerDocument ?? new DOMDocument();
        return (string)$doc->saveHTML($node);
    }

    protected function createDomDocument(string $html): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $encoded = mb_encode_numericentity($html, [0x80, 0x10ffff, 0, 0x1fffff], 'UTF-8');
        $doc->loadHTML("<!DOCTYPE html><html><body>{$encoded}</body></html>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc;
    }

    protected function findFirstElement(DOMDocument $doc): ?DOMElement
    {
        $body = $doc->getElementsByTagName('body')->item(0);
        if ($body !== null) {
            foreach ($body->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    return $child;
                }
            }
        }

        foreach ($doc->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    public function extractTag(string $html): string
    {
        if (preg_match('/^<([a-zA-Z0-9\-]+)/', trim($html), $m)) {
            return strtolower($m[1]);
        }
        return '';
    }

    public function extractAttributes(string $html): array
    {
        $attrs = [];
        if (preg_match('/^<[a-zA-Z0-9\-]+\s+([^>]+)>/s', trim($html), $m)) {
            $attrStr = $m[1];
            if (preg_match_all('/([a-zA-Z0-9\-:_]+)(?:="([^"]*)"|(?:\s+|$))/s', $attrStr, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $key = $match[1];
                    $val = $match[2] ?? '';
                    $attrs[$key] = $val;
                }
            }
        }
        return $attrs;
    }

    public function extractInnerHtml(string $html): string
    {
        $html = trim($html);
        $tag = $this->extractTag($html);
        if ($tag === '') {
            return $html;
        }

        $stripped = preg_replace('/^<[a-zA-Z0-9\-]+[^>]*>/', '', $html);
        $stripped = preg_replace('/<\/' . preg_quote($tag, '/') . '>\s*$/', '', (string)$stripped);
        return trim((string)$stripped);
    }
}
