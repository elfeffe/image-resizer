<?php

namespace Elfeffe\ImageResizer\Http\Controllers;

use Illuminate\Http\Request;
use Image;
use Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImageResizerController extends Controller
{
    private $media;

    public function show(Request $request)
    {
        $this->media = Media::find($request->img);

        if (!$this->media) {
            return;
        }

        if ($request->type === 'original') {
            return redirect($this->media->getFullUrl());
        }

        $mime = match ($request->ext) {
            'png' =>
            'image/png',
            'webp' =>
            'image/webp',
            default => 'image/jpg',
        };

        try {
            $response = Response::make($this->getCanvas($request))->header('Content-Type', $mime);

            $response->header("pragma", "public");
            $response->header("Cache-Control", " public, max-age=2628000");
            $response->header("Cached", 'no');
            $response->header("Web-Server", 'no');
            $response->header('Connection', 'Keep-alive');

            // output
            return $response;

        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    public function getCanvas($request)
    {
        $file = $request->img . '/' . $request->w . 'x' . $request->h . '/' . $request->type . '.' . $request->ext;
        $request->h = $request->h === 'null' ? null : $request->h;

        $image = Image::make($this->media->getPath());

        if ($request->type === 'resize') {
            $image->resize($request->w, $request->h, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        if ($request->type === 'fit') {
            $image->fit($request->w, $request->h, function ($constraint) {
                //$constraint->upsize();
            });
        }

        $canvas = Image::canvas($image->width(), $image->height());

        //$canvas->fill('#ffffff');

        $canvas->insert($image, 'center');
        $canvas->interlace();
        $canvas->getCore()->stripImage();

        $quality = 88;

        if ($request->w < 150) {
            $quality = 93;
        }

        $canvas->encode($request->ext, $quality);

        //src/ImageResizerServiceProvider.php
        Storage::disk('image_resizer')->put($file, $canvas);

        return $canvas;
    }
}
