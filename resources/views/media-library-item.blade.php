@if($item && $media)
{{ $item->getMediaHtml($media, $width, $height, $type, ['title' => $name, 'alt' => $name], $name) }}
@else
    <span class="text-gray-500 bg-gray-100 px-3 py-1 rounded">{{ $name ?: 'Media not found' }}</span>
@endif
