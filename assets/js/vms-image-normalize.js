(() => {
  if (window.BVMGR_IMAGE_NORMALIZE) {
    return;
  }

  function formatBytes(bytes) {
    const size = Number(bytes || 0);
    if (!(size > 0)) {
      return '0 B';
    }
    if (size < 1024) {
      return `${size} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = size / 1024;
    let index = 0;
    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }

    const decimals = value >= 100 ? 0 : (value >= 10 ? 1 : 2);
    return `${value.toFixed(decimals)} ${units[index]}`;
  }

  function replaceExtension(filename, nextExtension) {
    const safeName = String(filename || 'upload').trim() || 'upload';
    const base = safeName.replace(/\.[^.]+$/, '');
    return `${base}.${nextExtension}`;
  }

  function getFileExtension(file) {
    const name = typeof file === 'string' ? file : String(file && file.name ? file.name : '');
    const match = name.toLowerCase().match(/\.([a-z0-9]+)$/);
    return match ? match[1] : '';
  }

  function isHeicLike(file) {
    const mimeType = String(file && file.type ? file.type : '').toLowerCase();
    const extension = getFileExtension(file);
    return mimeType === 'image/heic'
      || mimeType === 'image/heif'
      || extension === 'heic'
      || extension === 'heics'
      || extension === 'heif'
      || extension === 'heifs';
  }

  async function loadImageSource(file) {
    if (typeof window.createImageBitmap === 'function') {
      try {
        const bitmap = await window.createImageBitmap(file, { imageOrientation: 'from-image' });
        return {
          source: bitmap,
          width: bitmap.width,
          height: bitmap.height,
          cleanup: () => {
            if (typeof bitmap.close === 'function') {
              bitmap.close();
            }
          }
        };
      } catch (_error) {
        try {
          const bitmap = await window.createImageBitmap(file);
          return {
            source: bitmap,
            width: bitmap.width,
            height: bitmap.height,
            cleanup: () => {
              if (typeof bitmap.close === 'function') {
                bitmap.close();
              }
            }
          };
        } catch (_ignored) {
          /* fall through */
        }
      }
    }

    const objectUrl = window.URL.createObjectURL(file);
    return new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => {
        resolve({
          source: image,
          width: image.naturalWidth || image.width,
          height: image.naturalHeight || image.height,
          cleanup: () => window.URL.revokeObjectURL(objectUrl)
        });
      };
      image.onerror = () => {
        window.URL.revokeObjectURL(objectUrl);
        reject(new Error('image_load_failed'));
      };
      image.src = objectUrl;
    });
  }

  function canvasToBlob(canvas, mimeType, quality) {
    return new Promise((resolve, reject) => {
      if (typeof canvas.toBlob !== 'function') {
        reject(new Error('canvas_blob_unsupported'));
        return;
      }

      canvas.toBlob((blob) => {
        if (blob) {
          resolve(blob);
          return;
        }
        reject(new Error('canvas_blob_failed'));
      }, mimeType, quality);
    });
  }

  async function normalizeImageUpload(file, options = {}) {
    const loaded = await loadImageSource(file);
    try {
      const maxDimension = Math.max(600, Number(options.maxDimension || 2200));
      const quality = Math.max(0.6, Math.min(0.92, Number(options.quality || 0.86)));
      const longestSide = Math.max(Number(loaded.width || 0), Number(loaded.height || 0), 1);
      const scale = Math.min(1, maxDimension / longestSide);
      const targetWidth = Math.max(1, Math.round((Number(loaded.width || 1)) * scale));
      const targetHeight = Math.max(1, Math.round((Number(loaded.height || 1)) * scale));
      const canvas = document.createElement('canvas');
      canvas.width = targetWidth;
      canvas.height = targetHeight;

      const context = canvas.getContext('2d', { alpha: false });
      if (!context) {
        throw new Error('canvas_context_failed');
      }

      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, targetWidth, targetHeight);
      context.drawImage(loaded.source, 0, 0, targetWidth, targetHeight);

      const blob = await canvasToBlob(canvas, 'image/jpeg', quality);
      return {
        blob,
        filename: replaceExtension(file.name, 'jpg'),
        mimeType: 'image/jpeg',
        originalSize: Number(file.size || 0),
        outputSize: Number(blob.size || 0),
        width: targetWidth,
        height: targetHeight
      };
    } finally {
      if (loaded && typeof loaded.cleanup === 'function') {
        loaded.cleanup();
      }
    }
  }

  window.BVMGR_IMAGE_NORMALIZE = {
    formatBytes,
    getFileExtension,
    isHeicLike,
    normalizeImageUpload
  };
})();
