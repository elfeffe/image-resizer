<?php

declare(strict_types=1);

return [

    /*
     * Queue used for image metadata and placeholder generation jobs.
     */
    'queue' => env('IMAGERESIZER_QUEUE', 'default'),

    'storage' => [
        /*
         * Disk used to store resized images. Defaults to the package-managed
         * `image_resizer` disk registered by the service provider.
         */
        'disk' => env('IMAGERESIZER_STORAGE_DISK', 'image_resizer'),

        /*
         * Definition for the package-managed `image_resizer` disk. `local` keeps
         * files in storage/app/public/image_resizer (historical behaviour). Use
         * `s3` for any S3-compatible provider (Backblaze B2, Cloudflare R2,
         * AWS S3) by setting the credentials below.
         */
        'driver' => env('IMAGERESIZER_STORAGE_DRIVER', 'local'),

        /*
         * Key prefix inside the bucket (s3 only). Keeps image-resizer objects
         * separate from other packages sharing the same bucket.
         */
        'root' => env('IMAGERESIZER_STORAGE_ROOT', 'image_resizer'),

        /*
         * Public base URL of the bucket, used by the s3 disk definition.
         */
        'url' => env('IMAGERESIZER_STORAGE_URL'),

        'visibility' => env('IMAGERESIZER_STORAGE_VISIBILITY', 'public'),

        's3' => [
            'key' => env('IMAGERESIZER_STORAGE_KEY'),
            'secret' => env('IMAGERESIZER_STORAGE_SECRET'),
            'region' => env('IMAGERESIZER_STORAGE_REGION'),
            'bucket' => env('IMAGERESIZER_STORAGE_BUCKET'),
            'endpoint' => env('IMAGERESIZER_STORAGE_ENDPOINT'),
            'use_path_style_endpoint' => env('IMAGERESIZER_STORAGE_PATH_STYLE', false),
        ],
    ],

    'serve' => [
        /*
         * Public base URL used to build resized-image URLs: a CDN pull-zone
         * URL, a CNAME, or the bare bucket file URL. When empty, URLs stay
         * relative to the app route (historical behaviour).
         */
        'url' => env('IMAGERESIZER_SERVE_URL'),

        /*
         * cdn_origin: URLs point at serve.url and the CDN origin-pulls the app.
         * redirect:   URLs stay on the app route and the app 301s to serve.url.
         */
        'mode' => env('IMAGERESIZER_SERVE_MODE', 'cdn_origin'),
    ],

    /*
     * How resizes are encoded. Metadata (EXIF, ICC) is always stripped once the orientation
     * has been applied. `quality` is for JPEG and WebP. AVIF is offered first to browsers that
     * take it (about a third smaller than WebP for the same look) when `avif` is on: it costs
     * more CPU per resize, so it is off by default; `avif_speed` is libheif's 0 (slowest, best)
     * to 10 (fastest) and `avif_quality` its own scale, where 60 looks like WebP at 78.
     */
    'encode' => [
        'quality' => (int) env('IMAGERESIZER_QUALITY', 78),
        'avif' => (bool) env('IMAGERESIZER_AVIF', false),
        'avif_quality' => (int) env('IMAGERESIZER_AVIF_QUALITY', 60),
        'avif_speed' => (int) env('IMAGERESIZER_AVIF_SPEED', 7),
    ],

    /*
     * Cached resizes nobody has asked for in this many days are deleted by `image-resizer:prune`,
     * scheduled daily at 04:40. 0 turns the schedule off; the command still runs by hand.
     */
    'prune' => [
        'days' => (int) env('IMAGERESIZER_PRUNE_DAYS', 30),
    ],

    /*
     * Signed URLs. `/image_resizer/{id}/…` takes any media id and asks for no login, so a counter loop lists everything a project
     * stores. A project that signs has every URL its models build (HasImageResizer) end in `?s=`, an HMAC-SHA256 of the path's
     * segments; the route answers 403 to any other request before it reads the database or a disk, and keeps its cache under a folder
     * only the app can name. Off by default so a project that writes these URLs by hand keeps working until it uses the builders.
     * The key is `signature_key`, else `app.key`: rotating it invalidates every URL already handed out, in pages, feeds and emails.
     */
    'signed' => (bool) env('IMAGERESIZER_SIGNED', false),

    'signature_key' => env('IMAGERESIZER_SIGNATURE_KEY'),

    /*
     * `type=original` redirects to the media's own file, whatever the media is and whoever asks (in a project that signs, whoever
     * holds a signed URL). Off by default; no builder in this package makes such a URL.
     */
    'allow_original' => (bool) env('IMAGERESIZER_ALLOW_ORIGINAL', false),

    /*
     * The largest width or height the route serves, in pixels (larger is a 422). `resize` and `fit` never go above the source's own size.
     */
    'max_dimension' => (int) env('IMAGERESIZER_MAX_DIMENSION', 4096),

    /*
     * Requests a minute per address (the named limiter `image-resizer`), served or refused: a page of thumbnails asks for one each.
     * 0 turns it off. Behind a proxy or a CDN the address is the proxy's unless the host trusts it: raise this, or trust the proxy.
     */
    'throttle' => (int) env('IMAGERESIZER_THROTTLE', 120),
];
