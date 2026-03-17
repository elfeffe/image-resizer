/**
 * Image Resizer - Lazy Loading System
 * Handles lazy loading for images with BlurHash placeholders
 */

class ImageLazyLoader {
    constructor() {
        this.observers = new Map();
        this.init();
    }

    init() {
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.scanForImages());
        } else {
            this.scanForImages();
        }

        // Re-scan after dynamic content loads (Livewire, AJAX, etc.)
        this.setupMutationObserver();
        this.setupLivewireSupport();
    }

    scanForImages() {
        const lazyImages = document.querySelectorAll('img[data-src]:not([data-lazy-processed])');
        
        if (lazyImages.length === 0) return;

        // Check for IntersectionObserver support
        if (!('IntersectionObserver' in window)) {
            // Fallback: load all images immediately
            lazyImages.forEach(img => this.loadImageImmediately(img));
            return;
        }

        // Create observer if not exists
        if (!this.observers.has('main')) {
            this.observers.set('main', new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observers.get('main').unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '50px 0px', // Start loading 50px before image enters viewport
                threshold: 0.01
            }));
        }

        const observer = this.observers.get('main');
        
        lazyImages.forEach(img => {
            img.setAttribute('data-lazy-processed', 'true');
            observer.observe(img);
        });
    }

    loadImage(img) {
        const picture = img.closest('picture');
        
        // Load sources first if inside picture element
        if (picture) {
            const sources = picture.querySelectorAll('source[data-srcset]');
            sources.forEach(source => {
                if (source.dataset.srcset) {
                    source.srcset = source.dataset.srcset;
                    source.removeAttribute('data-srcset');
                }
            });
        }
        
        // Load the img element
        if (img.dataset.src) {
            // Preload the image to ensure smooth transition
            const tempImg = new Image();
            tempImg.onload = () => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            };
            tempImg.onerror = () => {
                // Fallback: still set src even if preload failed
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            };
            tempImg.src = img.dataset.src;
        }
        
        if (img.dataset.srcset) {
            img.srcset = img.dataset.srcset;
            img.removeAttribute('data-srcset');
        }
    }

    loadImageImmediately(img) {
        const picture = img.closest('picture');
        
        // Load sources first if inside picture element
        if (picture) {
            const sources = picture.querySelectorAll('source[data-srcset]');
            sources.forEach(source => {
                if (source.dataset.srcset) {
                    source.srcset = source.dataset.srcset;
                    source.removeAttribute('data-srcset');
                }
            });
        }
        
        // Load the img element
        if (img.dataset.src) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        }
        
        if (img.dataset.srcset) {
            img.srcset = img.dataset.srcset;
            img.removeAttribute('data-srcset');
        }

        img.setAttribute('data-lazy-processed', 'true');
    }

    setupMutationObserver() {
        // Watch for dynamically added content
        const mutationObserver = new MutationObserver((mutations) => {
            let shouldScan = false;
            
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check if the added node contains lazy images
                            if (node.matches && node.matches('img[data-src]')) {
                                shouldScan = true;
                            } else if (node.querySelector && node.querySelector('img[data-src]')) {
                                shouldScan = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldScan) {
                // Debounce scanning
                clearTimeout(this.scanTimeout);
                this.scanTimeout = setTimeout(() => this.scanForImages(), 100);
            }
        });
        
        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    setupLivewireSupport() {
        // Support for Livewire navigation and updates
        if (typeof window.Livewire !== 'undefined') {
            document.addEventListener('livewire:navigated', () => {
                setTimeout(() => this.scanForImages(), 100);
            });
            
            document.addEventListener('livewire:updated', () => {
                setTimeout(() => this.scanForImages(), 100);
            });
        }

        // Support for legacy Livewire events
        document.addEventListener('livewire:load', () => {
            setTimeout(() => this.scanForImages(), 100);
        });
    }

    // Public method to manually trigger scanning
    static scan() {
        if (window.imageLazyLoader) {
            window.imageLazyLoader.scanForImages();
        }
    }

    // Public method to force load a specific image
    static loadImage(imgElement) {
        if (window.imageLazyLoader && imgElement) {
            window.imageLazyLoader.loadImage(imgElement);
        }
    }
}

// Auto-initialize when script loads
if (typeof window !== 'undefined') {
    window.imageLazyLoader = new ImageLazyLoader();
    
    // Expose public methods
    window.ImageLazyLoader = ImageLazyLoader;
} 