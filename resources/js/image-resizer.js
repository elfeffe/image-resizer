// Import CSS
import '../css/image-resizer.css';

// BlurHash placeholders.
//
// The placeholder view emits one <canvas data-blurhash="…"> per image; this
// file paints them all with a single decoder. Decoding at 32 pixels on the
// long side and letting CSS scale the canvas is the way BlurHash is meant to
// be used: it looks identical to a full-size decode and costs a fraction of
// a millisecond per image, which matters when a Livewire update swaps two
// dozen cards at once.

const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:=?@[]^_{|}~';
const PAINT_SIZE = 32;

const decode83 = (str) => {
  let value = 0;
  for (let i = 0; i < str.length; i++) {
    value = value * 83 + CHARS.indexOf(str[i]);
  }
  return value;
};
const srgbToLinear = (value) => {
  const v = value / 255;
  return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
};
const linearToSrgb = (value) => {
  const v = Math.max(0, Math.min(1, value));
  return Math.round(v <= 0.0031308 ? v * 12.92 * 255 : (1.055 * Math.pow(v, 1 / 2.4) - 0.055) * 255);
};
const signPow = (value, exp) => Math.sign(value) * Math.pow(Math.abs(value), exp);

function decodeBlurHash(hash, width, height) {
  const sizeFlag = decode83(hash[0]);
  const numY = Math.floor(sizeFlag / 9) + 1;
  const numX = (sizeFlag % 9) + 1;
  const maximumValue = (decode83(hash[1]) + 1) / 166;
  const colors = new Array(numX * numY);

  for (let i = 0; i < colors.length; i++) {
    if (i === 0) {
      const value = decode83(hash.substring(2, 6));
      colors[i] = [srgbToLinear((value >> 16) & 255), srgbToLinear((value >> 8) & 255), srgbToLinear(value & 255)];
    } else {
      const value = decode83(hash.substring(4 + i * 2, 6 + i * 2));
      colors[i] = [
        signPow((Math.floor(value / 361) - 9) / 9, 2) * maximumValue,
        signPow(((Math.floor(value / 19) % 19) - 9) / 9, 2) * maximumValue,
        signPow(((value % 19) - 9) / 9, 2) * maximumValue,
      ];
    }
  }

  const pixels = new Uint8ClampedArray(width * height * 4);

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      let r = 0;
      let g = 0;
      let b = 0;
      for (let j = 0; j < numY; j++) {
        const basisY = Math.cos((Math.PI * y * j) / height);
        for (let i = 0; i < numX; i++) {
          const basis = Math.cos((Math.PI * x * i) / width) * basisY;
          const color = colors[i + j * numX];
          r += color[0] * basis;
          g += color[1] * basis;
          b += color[2] * basis;
        }
      }
      const offset = (y * width + x) * 4;
      pixels[offset] = linearToSrgb(r);
      pixels[offset + 1] = linearToSrgb(g);
      pixels[offset + 2] = linearToSrgb(b);
      pixels[offset + 3] = 255;
    }
  }

  return pixels;
}

function paintBlurHash(canvas) {
  if (canvas.dataset.blurhashPainted) {
    return;
  }

  const hash = canvas.dataset.blurhash;

  if (!hash || hash.length < 6) {
    return;
  }

  // Keep the box's ratio at placeholder resolution; CSS stretches it back.
  const ratio = (canvas.width || 1) / (canvas.height || 1);
  const width = ratio >= 1 ? PAINT_SIZE : Math.max(4, Math.round(PAINT_SIZE * ratio));
  const height = ratio >= 1 ? Math.max(4, Math.round(PAINT_SIZE / ratio)) : PAINT_SIZE;

  try {
    const pixels = decodeBlurHash(hash, width, height);
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').putImageData(new ImageData(pixels, width, height), 0, 0);
    canvas.dataset.blurhashPainted = '1';
  } catch (error) {
    // The LQIP colour behind the canvas stays; say why the blur did not.
    console.warn('image-resizer: could not paint blurhash', hash, error);
  }
}

function paintAll(root = document) {
  root.querySelectorAll('canvas[data-blurhash]:not([data-blurhash-painted])').forEach(paintBlurHash);
}

// Once an image has pixels, its placeholder (canvas or colour block) goes.
function releasePlaceholder(img) {
  const container = img.closest('[data-image-container]');
  const byId = img.getAttribute('data-blurhash-container');
  const placeholder = (byId && document.getElementById(byId))
    || container?.querySelector('.blurhash-canvas, .image-resizer-blurhash-bg');

  if (placeholder) {
    placeholder.remove();
  }
}

// A source that fails to load leaves the box painted in the LQIP colour
// rather than a broken-image glyph.
function blankFailedImage(img) {
  const container = img.closest('[data-image-container]');

  if (!container) {
    return;
  }

  const colour = getComputedStyle(container).getPropertyValue('--lqip-color').trim() || '#f0f0f0';
  const width = img.getAttribute('width') || 1;
  const height = img.getAttribute('height') || width;

  img.removeAttribute('srcset');
  img.closest('picture')?.querySelectorAll('source').forEach((source) => source.remove());
  img.src = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='${width}' height='${height}'%3E%3Crect width='100%25' height='100%25' fill='${encodeURIComponent(colour)}'/%3E%3C/svg%3E`;
}

function isResizerImage(target) {
  return target instanceof HTMLImageElement
    && (target.closest('[data-image-container]') !== null || target.hasAttribute('data-blurhash-container'));
}

// Images that finished loading before this script ran never fire `load`
// again; sweep them.
function releaseLoadedPlaceholders() {
  document.querySelectorAll('[data-image-container] img, img[data-blurhash-container]').forEach((img) => {
    if (img.complete && img.naturalWidth > 0) {
      releasePlaceholder(img);
    }
  });
}

let scheduled = false;

function schedulePaint() {
  if (scheduled) {
    return;
  }
  scheduled = true;
  // A task, not an animation frame: a background tab never gets frames, and
  // the canvas must already be painted when it becomes visible.
  setTimeout(() => {
    scheduled = false;
    paintAll();
    releaseLoadedPlaceholders();
  }, 0);
}

function start() {
  // Idempotent: a second evaluation (a navigate swap that re-ran body
  // scripts, a duplicated tag) must not double the listeners and observer.
  if (window.__imageResizerStarted) {
    schedulePaint();
    return;
  }
  window.__imageResizerStarted = true;

  schedulePaint();

  // load/error do not bubble; capture them once for every image, present or
  // future, instead of an inline handler per image.
  document.addEventListener('load', (event) => {
    if (isResizerImage(event.target)) {
      releasePlaceholder(event.target);
    }
  }, true);
  document.addEventListener('error', (event) => {
    if (isResizerImage(event.target)) {
      blankFailedImage(event.target);
    }
  }, true);

  // Content that arrives later (Livewire updates, wire:navigate, lazy
  // islands, infinite scroll) gets painted the same way.
  new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      for (const node of mutation.addedNodes) {
        if (node.nodeType === 1 && (node.matches('canvas[data-blurhash]') || node.querySelector('canvas[data-blurhash]'))) {
          schedulePaint();
          return;
        }
      }
    }
  }).observe(document.documentElement, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start);
} else {
  start();
}
