// Import CSS
import '../css/image-resizer.css';


// Simple immediate image loader - no lazy loading, no intersection observer
function initializeImages() {
  const images = document.querySelectorAll('img[data-blurhash-container]');
  
  images.forEach((img, index) => {
    const canvasId = img.getAttribute('data-blurhash-container');
    
    // Function to hide BlurHash
    const hideBlurHash = () => {
      if (canvasId) {
        const canvas = document.getElementById(canvasId);
        if (canvas) {
          canvas.style.transition = 'opacity 0.3s ease';
          canvas.style.opacity = '0';
          
          // Remove canvas after fade
          setTimeout(() => {
            if (canvas.parentNode) {
              canvas.remove();
            }
          }, 300);
        }
      }
    };
    
    // Check if already loaded
    if (img.complete && img.naturalHeight > 0) {
      hideBlurHash();
    } else {
      // Listen for load event
      img.addEventListener('load', hideBlurHash, { once: true });
      
      // Fallback in case load event doesn't fire
      setTimeout(() => {
        if (img.complete && img.naturalHeight > 0) {
          hideBlurHash();
        }
      }, 1000);
    }
  });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', initializeImages);

// Also initialize if DOM is already ready
if (document.readyState !== 'loading') {
  initializeImages();
}

// Reinitialize on Livewire updates
if (window.Livewire) {
  document.addEventListener('livewire:navigated', () => {
    setTimeout(initializeImages, 100);
  });
  
  document.addEventListener('livewire:updated', () => {
    setTimeout(initializeImages, 100);
  });
} 