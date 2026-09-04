<?php
declare(strict_types=1);

namespace Oshim\Ui\Signals;

/**
 * Binds Signals to DOM Nodes and generates atomic micro-patches.
 */
class SignalDomBinder
{
    /**
     * Wrap content with a signal binding attribute.
     */
    public static function bindText(Signal|Computed $signal, string $tag = 'span', array $attributes = []): string
    {
        $attrStr = '';
        foreach ($attributes as $k => $v) {
            $attrStr .= sprintf(' %s="%s"', htmlspecialchars($k), htmlspecialchars((string)$v));
        }

        $value = htmlspecialchars((string)$signal->get(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return sprintf(
            '<%s data-oshim-sig="%s"%s>%s</%s>',
            $tag,
            $signal->getId(),
            $attrStr,
            $value,
            $tag
        );
    }

    /**
     * Generate pinpoint atomic mutation patch for client runtime.
     *
     * @return array{
     *     type: string,
     *     signal_id: string,
     *     value: mixed
     * }
     */
    public static function createPatch(Signal|Computed $signal): array
    {
        return [
            'type' => 'PATCH_SIGNAL_VALUE',
            'signal_id' => $signal->getId(),
            'value' => $signal->get(),
        ];
    }
}
