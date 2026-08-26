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
];
