/*
 * Rento admin image manager (vehicle forms and changeimage1-5.php).
 * Each [data-a-drop] slot wraps one file input (img1 ... img5):
 *  - drag and drop or click to choose, with a preview
 *  - photos larger than MAX_EDGE px or MAX_BYTES are resized and re-encoded as JPEG
 *    in the browser, so a form with five phone photos stays under the server's 8 MB
 *    post limit; the server still validates every file (admin/includes/upload.php)
 *  - blocks submit while a photo is still being processed or when the total is too big
 */
(function () {
  'use strict';

  var MAX_EDGE = 1920;              // longest side after resizing
  var MAX_BYTES = 900 * 1024;       // smaller files that already fit are sent as they are
  var QUALITY = 0.85;
  var FORM_LIMIT = 7.5 * 1024 * 1024; // leave room under post_max_size (8 MB)
  var TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  function formatBytes(n) {
    return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
  }

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { resolve({ img: img, url: url }); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('unreadable')); };
      img.src = url;
    });
  }

  // Returns { file, width, height, resized } — the original file when it already fits.
  function shrink(file) {
    return loadImage(file).then(function (loaded) {
      var img = loaded.img;
      var w = img.naturalWidth, h = img.naturalHeight;
      URL.revokeObjectURL(loaded.url);
      var scale = Math.min(1, MAX_EDGE / Math.max(w, h));
      if (scale === 1 && file.size <= MAX_BYTES) {
        return { file: file, width: w, height: h, resized: false };
      }
      var cw = Math.round(w * scale), ch = Math.round(h * scale);
      var canvas = document.createElement('canvas');
      canvas.width = cw;
      canvas.height = ch;
      var ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff'; // PNG transparency becomes white in the JPEG
      ctx.fillRect(0, 0, cw, ch);
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(img, 0, 0, cw, ch);
      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          if (!blob || blob.size >= file.size) {
            resolve({ file: file, width: w, height: h, resized: false });
            return;
          }
          var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
          resolve({ file: new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }), width: cw, height: ch, resized: true });
        }, 'image/jpeg', QUALITY);
      });
    });
  }

  function setFiles(input, file) {
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      return true;
    } catch (e) {
      return false; // very old browser: the original file is uploaded
    }
  }

  function initSlot(slot) {
    var input = slot.querySelector('[data-a-drop-input]');
    var preview = slot.querySelector('.a-drop__preview');
    var info = slot.querySelector('[data-a-drop-info]');
    var status = slot.querySelector('[data-a-drop-status]');
    var hadImage = slot.classList.contains('has-image');
    var busy = false;
    // The browser's own "required" bubble would point at the hidden input; the submit
    // check below does the same job visibly. Without JS the attribute stays in place.
    if (input.required) {
      input.required = false;
      input.dataset.required = '1';
    }

    function setStatus(text, variant) {
      status.textContent = text;
      status.className = 'a-badge' + (variant ? ' a-badge--' + variant : '');
    }

    function showPreview(file) {
      var img = preview.querySelector('img');
      if (!img) {
        img = document.createElement('img');
        img.alt = 'New image preview';
        preview.innerHTML = '';
        preview.appendChild(img);
      }
      if (img.dataset.objectUrl) URL.revokeObjectURL(img.dataset.objectUrl);
      img.dataset.objectUrl = URL.createObjectURL(file);
      img.src = img.dataset.objectUrl;
      img.alt = 'New image preview';
    }

    function handle(file) {
      if (!file) return;
      if (TYPES.indexOf(file.type) === -1) {
        input.value = '';
        setStatus('Not an image', 'danger');
        info.textContent = 'Choose a JPG, PNG or WebP photo.';
        return;
      }
      busy = true;
      slot.dataset.busy = '1';
      setStatus('Processing…', 'info');
      info.textContent = 'Preparing ' + file.name + '…';
      shrink(file).then(function (result) {
        var replaced = result.resized ? setFiles(input, result.file) : true;
        if (!replaced) result = { file: file, width: result.width, height: result.height, resized: false };
        showPreview(result.file);
        slot.classList.add('has-image', 'is-changed');
        setStatus(hadImage ? 'Will be replaced' : 'New', 'primary');
        info.textContent = (result.resized ? formatBytes(file.size) + ' → ' : '') + formatBytes(result.file.size)
          + ' · ' + result.width + '×' + result.height + (result.resized ? ' (resized)' : '');
        slot.dataset.bytes = result.file.size;
      }).catch(function () {
        input.value = '';
        setStatus('Unreadable', 'danger');
        info.textContent = 'This file could not be read as an image.';
        delete slot.dataset.bytes;
      }).then(function () {
        busy = false;
        delete slot.dataset.busy;
      });
    }

    input.addEventListener('change', function () {
      // Files set by our own DataTransfer also fire change in some browsers; skip those.
      if (busy) return;
      if (input.files[0]) handle(input.files[0]);
    });

    ['dragenter', 'dragover'].forEach(function (type) {
      slot.addEventListener(type, function (event) {
        event.preventDefault();
        slot.classList.add('is-over');
      });
    });
    ['dragleave', 'drop'].forEach(function (type) {
      slot.addEventListener(type, function (event) {
        event.preventDefault();
        if (type === 'dragleave' && slot.contains(event.relatedTarget)) return;
        slot.classList.remove('is-over');
      });
    });
    slot.addEventListener('drop', function (event) {
      var file = event.dataTransfer && event.dataTransfer.files[0];
      if (!file) return;
      if (!setFiles(input, file)) return;
      handle(file);
    });
  }

  var slots = document.querySelectorAll('[data-a-drop]');
  slots.forEach(initSlot);

  // Check the whole form before it is sent.
  var forms = new Set();
  slots.forEach(function (slot) { if (slot.closest('form')) forms.add(slot.closest('form')); });
  forms.forEach(function (form) {
    form.addEventListener('submit', function (event) {
      var formSlots = form.querySelectorAll('[data-a-drop]');
      var busy = Array.prototype.some.call(formSlots, function (s) { return s.dataset.busy; });
      if (busy) {
        event.preventDefault();
        event.stopImmediatePropagation();
        alertIn(form, 'warning', 'Photos are still being prepared. Please wait a moment and save again.');
        return;
      }
      var missing = Array.prototype.find.call(formSlots, function (s) {
        var input = s.querySelector('[data-a-drop-input]');
        return input.dataset.required && !input.files.length;
      });
      if (missing) {
        event.preventDefault();
        event.stopImmediatePropagation();
        setTimeout(function () { missing.scrollIntoView({ block: 'center' }); missing.querySelector('[data-a-drop-input]').focus(); }, 0);
        missing.querySelector('[data-a-drop-status]').className = 'a-badge a-badge--danger';
        missing.querySelector('[data-a-drop-status]').textContent = 'Required';
        alertIn(form, 'error', 'Images 1 to 4 are required.');
        return;
      }
      var total = Array.prototype.reduce.call(formSlots, function (sum, s) { return sum + (+s.dataset.bytes || 0); }, 0);
      if (total > FORM_LIMIT) {
        event.preventDefault();
        event.stopImmediatePropagation();
        alertIn(form, 'error', 'These images add up to ' + formatBytes(total) + ', over the ' + formatBytes(FORM_LIMIT) + ' the server accepts in one save. Save fewer images at a time.');
      }
    }, true);
  });

  // A notice at the top of the form (the toast area is for server messages).
  function alertIn(form, type, text) {
    var box = form.querySelector('[data-a-form-alert]');
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('data-a-form-alert', '');
      box.setAttribute('role', 'alert');
      form.insertBefore(box, form.firstChild);
    }
    box.className = 'a-notice a-notice--' + type + ' mb-3';
    box.textContent = text;
    box.scrollIntoView({ block: 'nearest' });
  }
})();
