(function () {
  'use strict';

  var sheet = document.querySelector('[data-flyer-sheet]');
  var stage = document.querySelector('[data-flyer-stage]');
  var status = document.querySelector('[data-flyer-status]');
  var printButtons = Array.prototype.slice.call(document.querySelectorAll('[data-flyer-print]'));
  var downloadButtons = Array.prototype.slice.call(document.querySelectorAll('[data-flyer-download]'));
  var errorBox = document.querySelector('[data-flyer-error]');

  if (!sheet || !stage || !printButtons.length || !downloadButtons.length) {
    return;
  }

  var orientation = sheet.getAttribute('data-orientation') === 'landscape' ? 'landscape' : 'portrait';
  var pagePixels = orientation === 'landscape' ? { width: 1056, height: 816 } : { width: 816, height: 1056 };
  var pagePoints = orientation === 'landscape' ? { width: 792, height: 612 } : { width: 612, height: 792 };
  var readyError = null;

  function setActionDisabled(disabled) {
    printButtons.concat(downloadButtons).forEach(function (button) {
      button.disabled = disabled;
    });
  }

  function setStatus(message, isError) {
    if (status) {
      status.textContent = message;
      status.style.color = isError ? '#8b1f16' : '';
    }
  }

  function scalePreview() {
    if (window.matchMedia && window.matchMedia('print').matches) {
      return;
    }
    var available = Math.max(280, Math.min(window.innerWidth - 24, 1180));
    var scale = Math.min(1, available / pagePixels.width);
    sheet.style.setProperty('--sheet-scale', String(scale));
    stage.style.width = Math.round(pagePixels.width * scale) + 'px';
    stage.style.height = Math.round(pagePixels.height * scale) + 'px';
  }

  function imageReady(image) {
    if (image.complete && image.naturalWidth > 0) {
      return Promise.resolve();
    }
    if (image.complete) {
      return Promise.reject(new Error('An image required for this flyer could not be loaded.'));
    }
    return new Promise(function (resolve, reject) {
      image.addEventListener('load', function () {
        if (image.naturalWidth > 0) {
          resolve();
        } else {
          reject(new Error('An image required for this flyer could not be loaded.'));
        }
      }, { once: true });
      image.addEventListener('error', function () {
        reject(new Error('An image required for this flyer could not be loaded.'));
      }, { once: true });
    });
  }

  function regionOverflows(element) {
    return !!element && (element.scrollHeight > element.clientHeight + 1 || element.scrollWidth > element.clientWidth + 1);
  }

  function contentOverflows() {
    var panel = sheet.querySelector('.offer-panel');
    var art = sheet.querySelector('.art-panel');
    var brand = sheet.querySelector('.brand-card');
    if (regionOverflows(panel)) {
      return true;
    }
    if (brand && art && window.getComputedStyle(art).display !== 'none') {
      var brandRect = brand.getBoundingClientRect();
      var artRect = art.getBoundingClientRect();
      return brandRect.bottom > artRect.bottom - 4 || brandRect.right > artRect.right - 4;
    }
    return false;
  }

  function fitBoundedText() {
    var candidates = Array.prototype.slice.call(sheet.querySelectorAll('[data-flyer-fit]'));
    var changed = true;
    var passes = 0;
    if (orientation === 'landscape') {
      if (contentOverflows()) {
        throw new Error('The landscape offer does not fit at a readable size. Shorten the heading or business name, or choose portrait.');
      }
      return;
    }
    while (contentOverflows() && changed && passes < 24) {
      changed = false;
      candidates.forEach(function (element) {
        var computed = window.getComputedStyle(element);
        var current = parseFloat(computed.fontSize || '0');
        var minimum = parseFloat(element.getAttribute('data-min-font') || '9');
        if (current > minimum) {
          element.style.fontSize = Math.max(minimum, current - 1) + 'px';
          changed = true;
        }
      });
      passes += 1;
    }
    if (contentOverflows()) {
      throw new Error('The flyer text does not fit at a readable size. Shorten the heading or business name, or choose a roomier composition.');
    }
  }

  function prepare() {
    setActionDisabled(true);
    scalePreview();
    var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
    var imagesReady = Array.prototype.slice.call(sheet.querySelectorAll('[data-flyer-asset]')).map(imageReady);
    return Promise.all([fontsReady].concat(imagesReady)).then(function () {
      fitBoundedText();
      setActionDisabled(false);
      setStatus(orientation === 'landscape' ? 'Full flyer and Landscape Letter offer-only versions are ready to print or download.' : 'The portrait full flyer is ready to print or download.', false);
      return true;
    }).catch(function (error) {
      readyError = error instanceof Error ? error : new Error(String(error));
      sheet.classList.add('has-error');
      if (errorBox) {
        errorBox.textContent = readyError.message;
      }
      setStatus(readyError.message, true);
      throw readyError;
    });
  }

  function blobToDataUrl(blob) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(String(reader.result || '')); };
      reader.onerror = function () { reject(new Error('An image could not be prepared for the PDF.')); };
      reader.readAsDataURL(blob);
    });
  }

  function inlineImage(image) {
    var src = image.getAttribute('src') || '';
    if (src.indexOf('data:') === 0) {
      return Promise.resolve();
    }
    return window.fetch(src, { credentials: 'same-origin', cache: 'force-cache' }).then(function (response) {
      if (!response.ok) {
        throw new Error('An image could not be loaded for the PDF.');
      }
      return response.blob();
    }).then(blobToDataUrl).then(function (dataUrl) {
      image.setAttribute('src', dataUrl);
    });
  }

  function elementBox(element, rootBox) {
    var box = element.getBoundingClientRect();
    return { x: box.left - rootBox.left, y: box.top - rootBox.top, width: box.width, height: box.height };
  }

  function paintBox(context, element, rootBox) {
    var box = elementBox(element, rootBox);
    if (box.width <= 0 || box.height <= 0) {
      return;
    }
    var computed = window.getComputedStyle(element);
    var fill = computed.backgroundColor;
    if ((!fill || fill === 'rgba(0, 0, 0, 0)') && element.classList.contains('art-panel')) {
      fill = '#e7f0ed';
    }
    var radius = Math.max(0, parseFloat(computed.borderTopLeftRadius || '0'));
    context.beginPath();
    if (context.roundRect) {
      context.roundRect(box.x, box.y, box.width, box.height, radius);
    } else {
      context.rect(box.x, box.y, box.width, box.height);
    }
    if (fill && fill !== 'rgba(0, 0, 0, 0)') {
      context.fillStyle = fill;
      context.fill();
    }
    var borderWidth = parseFloat(computed.borderTopWidth || '0');
    if (borderWidth > 0 && computed.borderTopColor !== 'rgba(0, 0, 0, 0)') {
      context.lineWidth = borderWidth;
      context.strokeStyle = computed.borderTopColor;
      context.stroke();
    }
  }

  function paintImage(context, image, rootBox) {
    var box = elementBox(image, rootBox);
    if (box.width <= 0 || box.height <= 0) {
      return;
    }
    var naturalWidth = image.naturalWidth || box.width;
    var naturalHeight = image.naturalHeight || box.height;
    var scale = Math.min(box.width / naturalWidth, box.height / naturalHeight);
    var width = naturalWidth * scale;
    var height = naturalHeight * scale;
    context.save();
    context.imageSmoothingEnabled = !image.classList.contains('qr');
    context.drawImage(image, box.x + ((box.width - width) / 2), box.y + ((box.height - height) / 2), width, height);
    context.restore();
  }

  function wrappedLines(context, text, maximumWidth) {
    var words = text.split(/\s+/).filter(Boolean);
    var lines = [];
    var line = '';
    words.forEach(function (word) {
      var candidate = line ? line + ' ' + word : word;
      if (context.measureText(candidate).width <= maximumWidth) {
        line = candidate;
        return;
      }
      if (line) {
        lines.push(line);
        line = '';
      }
      if (context.measureText(word).width <= maximumWidth) {
        line = word;
        return;
      }
      Array.from(word).forEach(function (character) {
        var characterCandidate = line + character;
        if (line && context.measureText(characterCandidate).width > maximumWidth) {
          lines.push(line);
          line = character;
        } else {
          line = characterCandidate;
        }
      });
    });
    if (line) {
      lines.push(line);
    }
    return lines;
  }

  function paintText(context, element, rootBox) {
    var text = String(element.textContent || '').replace(/\s+/g, ' ').trim();
    if (!text) {
      return;
    }
    var box = elementBox(element, rootBox);
    var computed = window.getComputedStyle(element);
    var fontSize = parseFloat(computed.fontSize || '12');
    var fontWeight = element.matches('.offer, .scan') ? '700' : computed.fontWeight;
    var fontFamily = computed.fontFamily || 'Arial, sans-serif';
    var lineHeight = parseFloat(computed.lineHeight || '0') || fontSize * 1.2;
    var lines;
    context.save();
    context.font = fontWeight + ' ' + fontSize + 'px ' + fontFamily;
    context.fillStyle = computed.color || '#17202a';
    context.textBaseline = 'top';
    lines = wrappedLines(context, text, Math.max(4, box.width));
    var textHeight = lines.length * lineHeight;
    var minimumSize = orientation === 'landscape' ? fontSize : Math.max(7, fontSize * 0.72);
    while (textHeight > box.height + 1 && fontSize > minimumSize) {
      var ratio = lineHeight / fontSize;
      fontSize = Math.max(minimumSize, fontSize - 0.5);
      lineHeight = fontSize * ratio;
      context.font = fontWeight + ' ' + fontSize + 'px ' + fontFamily;
      lines = wrappedLines(context, text, Math.max(4, box.width));
      textHeight = lines.length * lineHeight;
    }
    var y = box.y;
    lines.forEach(function (line) {
      var x = box.x;
      if (computed.textAlign === 'center') {
        x += box.width / 2;
        context.textAlign = 'center';
      } else if (computed.textAlign === 'right') {
        x += box.width;
        context.textAlign = 'right';
      } else {
        context.textAlign = 'left';
      }
      context.fillText(line, x, y);
      y += lineHeight;
    });
    context.restore();
  }

  function renderCanvas(offerOnly) {
    var clone = sheet.cloneNode(true);
    clone.style.setProperty('--sheet-scale', '1');
    clone.style.transform = 'none';
    clone.style.position = 'relative';
    clone.style.inset = 'auto';
    clone.classList.remove('has-error');
    if (offerOnly) {
      clone.classList.add('flyer-offer-only');
      var artPanel = clone.querySelector('.art-panel');
      if (artPanel) {
        artPanel.remove();
      }
    }
    var cloneError = clone.querySelector('[data-flyer-error]');
    if (cloneError) {
      cloneError.remove();
    }
    clone.style.left = '-20000px';
    clone.style.top = '0';
    clone.style.zIndex = '-1';
    document.body.appendChild(clone);
    var images = Array.prototype.slice.call(clone.querySelectorAll('img'));
    var imagePromises = images.map(inlineImage);
    return Promise.all(imagePromises).then(function () {
      return Promise.all(images.map(imageReady));
    }).then(function () {
      var canvas = document.createElement('canvas');
      canvas.width = pagePixels.width * 2;
      canvas.height = pagePixels.height * 2;
      var context = canvas.getContext('2d');
      var rootBox = clone.getBoundingClientRect();
      context.scale(2, 2);
      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, pagePixels.width, pagePixels.height);
      Array.prototype.slice.call(clone.querySelectorAll('.composition, .art-panel, .no-art')).forEach(function (element) {
        paintBox(context, element, rootBox);
      });
      images.filter(function (image) { return image.classList.contains('artwork'); }).forEach(function (image) {
        paintImage(context, image, rootBox);
      });
      Array.prototype.slice.call(clone.querySelectorAll('.brand-card, .offer-panel, .qr-wrap, .details')).forEach(function (element) {
        paintBox(context, element, rootBox);
      });
      images.filter(function (image) { return !image.classList.contains('artwork'); }).forEach(function (image) {
        paintImage(context, image, rootBox);
      });
      Array.prototype.slice.call(clone.querySelectorAll('.no-art strong, .heading, .subheading, .venue, .offer, .business, .scan, .details li, .note')).forEach(function (element) {
        paintText(context, element, rootBox);
      });
      clone.remove();
      return canvas;
    }).catch(function (error) {
      clone.remove();
      throw error;
    });
  }

  function base64Bytes(dataUrl) {
    var encoded = String(dataUrl).split(',')[1] || '';
    var binary = window.atob(encoded);
    var bytes = new Uint8Array(binary.length);
    for (var index = 0; index < binary.length; index += 1) {
      bytes[index] = binary.charCodeAt(index);
    }
    return bytes;
  }

  function concatBytes(parts) {
    var length = parts.reduce(function (sum, part) { return sum + part.length; }, 0);
    var result = new Uint8Array(length);
    var offset = 0;
    parts.forEach(function (part) {
      result.set(part, offset);
      offset += part.length;
    });
    return result;
  }

  function pdfFromCanvas(canvas) {
    var encoder = new TextEncoder();
    var jpeg = base64Bytes(canvas.toDataURL('image/jpeg', 0.98));
    var content = 'q\n' + pagePoints.width + ' 0 0 ' + pagePoints.height + ' 0 0 cm\n/Im0 Do\nQ\n';
    var objects = [
      encoder.encode('<< /Type /Catalog /Pages 2 0 R >>'),
      encoder.encode('<< /Type /Pages /Kids [3 0 R] /Count 1 >>'),
      encoder.encode('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pagePoints.width + ' ' + pagePoints.height + '] /Resources << /XObject << /Im0 5 0 R >> >> /Contents 4 0 R >>'),
      encoder.encode('<< /Length ' + content.length + ' >>\nstream\n' + content + 'endstream'),
      concatBytes([encoder.encode('<< /Type /XObject /Subtype /Image /Width ' + canvas.width + ' /Height ' + canvas.height + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpeg.length + ' >>\nstream\n'), jpeg, encoder.encode('\nendstream')])
    ];
    var parts = [encoder.encode('%PDF-1.4\n%âãÏÓ\n')];
    var offsets = [0];
    var position = parts[0].length;
    objects.forEach(function (object, index) {
      offsets[index + 1] = position;
      var prefix = encoder.encode((index + 1) + ' 0 obj\n');
      var suffix = encoder.encode('\nendobj\n');
      parts.push(prefix, object, suffix);
      position += prefix.length + object.length + suffix.length;
    });
    var xrefOffset = position;
    var xref = 'xref\n0 ' + (objects.length + 1) + '\n0000000000 65535 f \n';
    for (var objectNumber = 1; objectNumber <= objects.length; objectNumber += 1) {
      xref += String(offsets[objectNumber]).padStart(10, '0') + ' 00000 n \n';
    }
    xref += 'trailer\n<< /Size ' + (objects.length + 1) + ' /Root 1 0 R >>\nstartxref\n' + xrefOffset + '\n%%EOF\n';
    parts.push(encoder.encode(xref));
    return new Blob(parts, { type: 'application/pdf' });
  }

  var ready = prepare();
  window.backstageOutreachFlyerReady = ready;

  function clearPrintVariant() {
    sheet.classList.remove('flyer-offer-only');
  }

  printButtons.forEach(function (printButton) {
    printButton.addEventListener('click', function () {
      var offerOnly = printButton.getAttribute('data-flyer-print') === 'offer';
      ready.then(function () {
        sheet.classList.toggle('flyer-offer-only', offerOnly);
        window.print();
        window.setTimeout(clearPrintVariant, 0);
      }).catch(function () {});
    });
  });

  downloadButtons.forEach(function (downloadButton) {
    downloadButton.addEventListener('click', function () {
      var offerOnly = downloadButton.getAttribute('data-flyer-download') === 'offer';
      if (readyError) {
        setStatus(readyError.message, true);
        return;
      }
      setActionDisabled(true);
      setStatus(offerOnly ? 'Creating the one-page offer-only PDF…' : 'Creating the one-page full-flyer PDF…', false);
      ready.then(function () { return renderCanvas(offerOnly); }).then(function (canvas) {
        var blob = pdfFromCanvas(canvas);
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = sheet.getAttribute(offerOnly ? 'data-offer-pdf-filename' : 'data-pdf-filename') || 'admission-offer.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        setStatus(offerOnly ? 'Offer-only PDF downloaded.' : 'Full-flyer PDF downloaded.', false);
        setActionDisabled(false);
      }).catch(function (error) {
        var message = error && error.message ? error.message : 'The PDF could not be created. Please try printing the flyer instead.';
        setStatus(message, true);
        setActionDisabled(false);
      });
    });
  });

  window.addEventListener('resize', scalePreview);
  window.addEventListener('beforeprint', function () {
    sheet.style.setProperty('--sheet-scale', '1');
  });
  window.addEventListener('afterprint', function () {
    clearPrintVariant();
    scalePreview();
  });
}());
