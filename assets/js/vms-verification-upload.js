(() => {
  const config = window.vmsVerificationUpload || {};
  const imageTools = window.VmsImageNormalize || {};
  const forms = Array.from(document.querySelectorAll('.vms-verification-form[data-vms-photo-upload="1"]'));
  if (!forms.length) {
    return;
  }

  const formatBytes = typeof imageTools.formatBytes === 'function'
    ? imageTools.formatBytes
    : ((bytes) => `${Number(bytes || 0)} B`);

  function setBusy(form, busy) {
    const fields = form.querySelectorAll('input, select, textarea, button');
    fields.forEach((field) => {
      if (!(field instanceof HTMLElement)) {
        return;
      }
      if (busy) {
        field.setAttribute('disabled', 'disabled');
      } else if (!field.hasAttribute('data-vms-locked')) {
        field.removeAttribute('disabled');
      }
    });
  }

  function setStatus(form, message, tone = 'working') {
    const statusEl = form.querySelector('[data-vms-verify-upload-status]');
    if (!(statusEl instanceof HTMLElement)) {
      return;
    }

    statusEl.hidden = !message;
    statusEl.textContent = String(message || '').trim();
    statusEl.classList.remove('is-error', 'is-working', 'is-done');
    if (!message) {
      return;
    }
    if (tone === 'error') {
      statusEl.classList.add('is-error');
    } else if (tone === 'done') {
      statusEl.classList.add('is-done');
    } else {
      statusEl.classList.add('is-working');
    }
  }

  function setDebug(form, message, forceVisible = false) {
    const debugEl = form.querySelector('[data-vms-verify-upload-debug]');
    if (!(debugEl instanceof HTMLElement)) {
      return;
    }

    const text = String(message || '').trim();
    const shouldShow = !!text && (forceVisible || !!config.debug);
    debugEl.hidden = !shouldShow;
    debugEl.textContent = shouldShow ? text : '';
  }

  function normalizeError(payload) {
    if (!payload || typeof payload !== 'object') {
      return { code: '', message: '' };
    }

    if (payload.error && typeof payload.error === 'object') {
      return {
        code: String(payload.error.code || '').trim(),
        message: String(payload.error.message || '').trim()
      };
    }

    return {
      code: String(payload.code || '').trim(),
      message: String(payload.message || '').trim()
    };
  }

  function configuredMessage(code, fallback) {
    if (config.messages && typeof config.messages === 'object') {
      const value = config.messages[code];
      if (value) {
        return String(value);
      }
    }
    return fallback;
  }

  function getFileExtension(file) {
    if (typeof imageTools.getFileExtension === 'function') {
      return imageTools.getFileExtension(file);
    }
    const name = String(file && file.name ? file.name : '').toLowerCase();
    const match = name.match(/\.([a-z0-9]+)$/);
    return match ? match[1] : '';
  }

  function isHeicLike(file) {
    if (typeof imageTools.isHeicLike === 'function') {
      return imageTools.isHeicLike(file);
    }
    return false;
  }

  function isPdfFile(file) {
    const mimeType = String(file && file.type ? file.type : '').toLowerCase();
    const extension = getFileExtension(file);
    return mimeType === 'application/pdf' || extension === 'pdf';
  }

  function isSupportedImageFile(file) {
    const mimeType = String(file && file.type ? file.type : '').toLowerCase();
    const extension = getFileExtension(file);
    if (isHeicLike(file)) {
      return false;
    }
    return mimeType === 'image/jpeg'
      || mimeType === 'image/png'
      || mimeType === 'image/webp'
      || extension === 'jpg'
      || extension === 'jpeg'
      || extension === 'png'
      || extension === 'webp';
  }

  function validateSelectedFile(file) {
    if (!file) {
      return 'file_missing';
    }
    if (isHeicLike(file)) {
      return 'heic_not_supported';
    }
    if (isSupportedImageFile(file) || isPdfFile(file)) {
      return '';
    }
    return 'file_type_not_allowed';
  }

  function buildDebugText(originalSize, outputSize) {
    return `Original ${formatBytes(originalSize)} -> ${formatBytes(outputSize)}`;
  }

  function resolveSubmitUrl(form) {
    if (!(form instanceof HTMLFormElement)) {
      return window.location.href;
    }

    const attr = String(form.getAttribute('action') || '').trim();
    return attr || window.location.href;
  }

  async function submitWithAjax(form, formData, preparedUpload) {
    formData.set('response_mode', 'json');
    if (preparedUpload && preparedUpload.blob) {
      formData.delete('proof_file');
      formData.append('proof_file', preparedUpload.blob, preparedUpload.filename);
    }

    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', resolveSubmitUrl(form), true);
      xhr.responseType = 'json';
      xhr.timeout = 45000;
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('loadstart', () => {
        setStatus(form, 'Uploading proof');
      });

      xhr.upload.addEventListener('load', () => {
        setStatus(form, 'Saving request');
      });

      xhr.onerror = () => reject({ code: 'network_error', message: 'Could not upload proof right now.' });
      xhr.ontimeout = () => reject({ code: 'network_timeout', message: 'Network timeout' });
      xhr.onload = () => {
        const payload = xhr.response && typeof xhr.response === 'object'
          ? xhr.response
          : (() => {
            try {
              return JSON.parse(xhr.responseText || '{}');
            } catch (_error) {
              return null;
            }
          })();

        if (xhr.status >= 200 && xhr.status < 300 && payload && payload.ok) {
          resolve(payload.data || {});
          return;
        }

        reject(payload || { code: 'save_failed', message: configuredMessage('save_failed', 'Could not save upload.') });
      };

      xhr.send(formData);
    });
  }

  function uploadErrorMessage(payload, selectedFile) {
    const normalized = normalizeError(payload);
    const code = normalized.code;

    if (code) {
      return configuredMessage(code, normalized.message || 'Could not upload proof.');
    }

    if (selectedFile && isPdfFile(selectedFile) && Number(selectedFile.size || 0) > Number(config.maxUploadBytes || 0)) {
      return configuredMessage('pdf_too_large', normalized.message || 'PDF too large.');
    }

    return normalized.message || configuredMessage('save_failed', 'Could not upload proof.');
  }

  function largeFileCode(file) {
    return isPdfFile(file) ? 'pdf_too_large' : 'file_too_large';
  }

  forms.forEach((form) => {
    let busy = false;

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (busy) {
        return;
      }

      const fileInput = form.querySelector('input[name="proof_file"]');
      const selectedFile = fileInput instanceof HTMLInputElement && fileInput.files && fileInput.files[0]
        ? fileInput.files[0]
        : null;
      const selectionCode = validateSelectedFile(selectedFile);
      if (selectionCode) {
        setStatus(form, configuredMessage(selectionCode, 'Please choose a supported proof file.'), 'error');
        setDebug(form, selectionCode === 'heic_not_supported' ? 'Try a screenshot or export the image as JPG/PNG first.' : '', true);
        return;
      }

      if (!selectedFile) {
        setStatus(form, configuredMessage('file_missing', 'Please choose a proof file before submitting.'), 'error');
        return;
      }

      if (Number(selectedFile.size || 0) > Number(config.maxUploadBytes || 0)) {
        const code = largeFileCode(selectedFile);
        setStatus(form, configuredMessage(code, 'This file is too large.'), 'error');
        setDebug(form, `Current limit: ${config.maxUploadLabel || formatBytes(config.maxUploadBytes || 0)}`, true);
        return;
      }

      const formData = new FormData(form);
      busy = true;
      setBusy(form, true);
      setDebug(form, '');

      try {
        let preparedUpload = {
          blob: selectedFile,
          filename: selectedFile.name,
          mimeType: selectedFile.type,
          originalSize: Number(selectedFile.size || 0),
          outputSize: Number(selectedFile.size || 0)
        };

        if (isSupportedImageFile(selectedFile)) {
          setStatus(form, 'Preparing image');
          if (Number(selectedFile.size || 0) > Number(config.warnUploadBytes || 0)) {
            setDebug(form, 'Large image detected. Normalizing before upload.', true);
          }

          try {
            if (typeof imageTools.normalizeImageUpload !== 'function') {
              throw new Error('image_normalize_unavailable');
            }

            setStatus(form, 'Normalizing image');
            preparedUpload = await imageTools.normalizeImageUpload(selectedFile, {
              maxDimension: config.maxDimension,
              quality: config.quality
            });

            if (Number(preparedUpload.outputSize || 0) > Number(config.maxUploadBytes || 0)) {
              throw { code: 'file_too_large' };
            }

            setDebug(form, buildDebugText(preparedUpload.originalSize, preparedUpload.outputSize));
          } catch (error) {
            const normalized = normalizeError(error);
            if (normalized.code === 'file_too_large') {
              throw { code: 'file_too_large' };
            }

            preparedUpload = {
              blob: selectedFile,
              filename: selectedFile.name,
              mimeType: selectedFile.type,
              originalSize: Number(selectedFile.size || 0),
              outputSize: Number(selectedFile.size || 0)
            };
            setDebug(form, 'Could not normalize in this browser. Uploading the original image for server-side processing.', true);
          }
        } else if (config.debug) {
          setStatus(form, 'Preparing file');
          setDebug(form, `PDF size ${formatBytes(selectedFile.size || 0)}`);
        }

        const response = await submitWithAjax(form, formData, preparedUpload);
        setStatus(form, 'Done', 'done');
        window.setTimeout(() => {
          if (response && response.redirect) {
            window.location.assign(String(response.redirect));
            return;
          }
          window.location.reload();
        }, 120);
      } catch (error) {
        const message = uploadErrorMessage(error, selectedFile);
        const code = normalizeError(error).code;
        setStatus(form, message, 'error');
        if (code === 'file_too_large' || code === 'pdf_too_large') {
          setDebug(form, `Current limit: ${config.maxUploadLabel || formatBytes(config.maxUploadBytes || 0)}`, true);
        } else if (code === 'network_timeout') {
          setDebug(form, 'Please try again on a stable connection.', true);
        } else if (code === 'image_processing_failed') {
          setDebug(form, 'A screenshot usually works well for credential proof uploads.', true);
        }
        setBusy(form, false);
        busy = false;
      }
    });
  });
})();
