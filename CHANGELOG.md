# Changelog

All notable changes to `image-resizer` will be documented in this file.

## Unreleased

- a model can define `imageResizerWarmUrls(Media $media): array` to have the resizes its views ask for made in a queued job (`WarmResizesJob`) right after the image is stored, from one read of the original, so the first view finds them cached; `image-resizer:warm` queues it for existing media

## 1.0.0 - 202X-XX-XX

- initial release
