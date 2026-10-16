<?php

declare(strict_types=1);

namespace Elfeffe\ImageResizer\Support;

use RuntimeException;

/**
 * Who may ask the resizer for what: whether a project signs its URLs and how, which types and sizes the route serves, and whether
 * `type=original` is served at all. One place, because the URL builder (HasImageResizer), the route (ImageResizerController) and a
 * host's tests must agree on every byte of it.
 */
final class Access
{
    /**
     * The types the controller implements. `resize` scales down keeping the ratio, `fit` crops to the box, `null` re-encodes the file at
     * its own size (what getMediaHtml() asks for without a type) and `original` redirects to the media's own URL, which is refused
     * unless `image-resizer.allow_original` is on. Both `resize` and `fit` stop at the source's size. `crop` was never implemented: two
     * Neoteo themes still ask for it and have always been served the file at its own size, as for `null`, so it stays accepted.
     */
    public const array TYPES = ['resize', 'fit', 'null', 'crop', 'original'];

    /** Whether the project signs its resizer URLs (`image-resizer.signed`). */
    public static function signed(): bool
    {
        return (bool) config('image-resizer.signed', false);
    }

    public static function allowsOriginal(): bool
    {
        return (bool) config('image-resizer.allow_original', false);
    }

    /** The largest width or height the route serves (`image-resizer.max_dimension`, 4096 by default). */
    public static function maxDimension(): int
    {
        return max(1, (int) config('image-resizer.max_dimension', 4096));
    }

    /** A numeric size above the limit comes back as the limit; anything else (`null`, a word) as it was. */
    public static function clamp(mixed $dimension): mixed
    {
        return is_numeric($dimension) && (int) $dimension > self::maxDimension() ? self::maxDimension() : $dimension;
    }

    /**
     * The signature of one URL: HMAC-SHA256, in hex, of `{id}|{w}|{h}|{type}|{file}` as they stand in the URL path (`file` is
     * `{name}.{ext}`), keyed by `image-resizer.signature_key` or else `app.key`. The file is signed with the rest: `ext` picks the
     * encoder, and an object-storage resize is kept under a key that carries the name, so a signature that left them out would open
     * as many variants as its holder cared to name.
     */
    public static function signature(int|float|string $id, int|float|string $width, int|float|string $height, string $type, string $file): string
    {
        return hash_hmac('sha256', implode('|', [$id, $width, $height, $type, $file]), self::key());
    }

    /** Whether `$given` (the `s` of a request) is the signature of that URL. */
    public static function accepts(mixed $given, int|float|string $id, int|float|string $width, int|float|string $height, string $type, string $file): bool
    {
        return is_string($given) && hash_equals(self::signature($id, $width, $height, $type, $file), $given);
    }

    /**
     * The folder a media's resizes are cached in: its id, or in a project that signs a token only this app can derive from the id.
     * A cached resize is a plain file, and the id alone names it for whoever knows the size: `/storage/image_resizer/{id}/…` when the
     * disk is linked into `public`, the Apache and Nginx fast paths, a public bucket. None of them can check a signature, so a
     * project that signs keeps its cache where only the app finds it. The signature itself is never part of a path: every signed URL
     * of a variant shares one file.
     */
    public static function folder(int|string $id): string
    {
        return self::signed() ? substr(hash_hmac('sha256', 'folder|'.$id, self::key()), 0, 32) : (string) $id;
    }

    private static function key(): string
    {
        $key = (string) (config('image-resizer.signature_key') ?: config('app.key'));

        // An empty key signs nothing: anyone could compute the signature. Failing is the only safe answer.
        if ($key === '') {
            throw new RuntimeException('image-resizer: signed URLs need image-resizer.signature_key or app.key, and neither is set.');
        }

        return $key;
    }
}
