document.addEventListener('DOMContentLoaded', function () {

    // Markdown preview tabs
    var tabBtns = document.querySelectorAll('.tab-btn');
    var textarea = document.getElementById('description');
    var preview = document.getElementById('md-preview');

    if (tabBtns.length && textarea && preview) {
        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                tabBtns.forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                if (this.dataset.tab === 'preview') {
                    textarea.style.display = 'none';
                    preview.style.display = 'block';
                    preview.innerHTML = renderMarkdown(textarea.value);
                } else {
                    textarea.style.display = '';
                    preview.style.display = 'none';
                }
            });
        });
    }

    function renderMarkdown(text) {
        var html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Tables
        html = html.replace(/^(\|.+\|)\n(\|[\s:|\-]+\|)\n((?:\|.+\|\n?)+)/gm, function(m, hdr, sep, body) {
            var ths = hdr.split('|').filter(function(s){return s.trim()});
            var seps = sep.split('|').filter(function(s){return s.trim()});
            var aligns = seps.map(function(s) {
                s = s.trim();
                if (s[0]===':' && s[s.length-1]===':') return 'center';
                if (s[s.length-1]===':') return 'right';
                return 'left';
            });
            var t = '<table class="md-table"><thead><tr>';
            ths.forEach(function(h,i){ t += '<th style="text-align:'+(aligns[i]||'left')+'">'+h.trim()+'</th>'; });
            t += '</tr></thead><tbody>';
            body.trim().split('\n').forEach(function(row) {
                var cells = row.split('|').filter(function(s){return s.trim()});
                t += '<tr>';
                cells.forEach(function(c,i){ t += '<td style="text-align:'+(aligns[i]||'left')+'">'+c.trim()+'</td>'; });
                t += '</tr>';
            });
            t += '</tbody></table>';
            return t;
        });

        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
        html = html.replace(/`(.+?)`/g, '<code>$1</code>');
        html = html.replace(/^- (.+)$/gm, '<li>$1</li>');
        html = html.replace(/(<li>.*?<\/li>\n?)+/g, '<ul>$&</ul>');
        html = html.replace(/\n{2,}/g, '</p><p>');
        html = '<p>' + html + '</p>';
        html = html.replace(/<p><\/p>/g, '');
        html = html.replace(/<p>\s*(<h[1-3]>)/g, '$1');
        html = html.replace(/(<\/h[1-3]>)\s*<\/p>/g, '$1');
        html = html.replace(/<p>\s*(<ul>)/g, '$1');
        html = html.replace(/(<\/ul>)\s*<\/p>/g, '$1');
        html = html.replace(/<p>\s*(<table)/g, '$1');
        html = html.replace(/(<\/table>)\s*<\/p>/g, '$1');
        return html;
    }

    // Admin item list — tag filter + search
    var adminSearch = document.getElementById('admin-search');
    var adminPills = document.querySelectorAll('#admin-tag-filters .tag-pill');
    var adminCards = document.querySelectorAll('#admin-item-list .admin-item-card');
    var adminEmpty = document.getElementById('admin-filter-empty');

    if (adminSearch && adminCards.length) {
        var adminActiveTags = [];
        var adminAllBtn = document.querySelector('#admin-tag-filters .tag-pill[data-tag=""]');
        function adminFilter() {
            var q = adminSearch.value.toLowerCase().trim();
            var visible = 0;
            adminCards.forEach(function (card) {
                var matchTag = !adminActiveTags.length || adminActiveTags.some(function(t) {
                    return (',' + card.dataset.tags + ',').indexOf(',' + t + ',') !== -1;
                });
                var matchSearch = !q || card.dataset.search.indexOf(q) !== -1;
                var show = matchTag && matchSearch;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (adminEmpty) adminEmpty.style.display = visible ? 'none' : '';
        }
        adminSearch.addEventListener('input', adminFilter);
        adminPills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                var tag = this.dataset.tag;
                if (!tag) {
                    adminActiveTags = [];
                    adminPills.forEach(function(p) { p.classList.remove('active'); });
                    adminAllBtn.classList.add('active');
                } else {
                    adminAllBtn.classList.remove('active');
                    var idx = adminActiveTags.indexOf(tag);
                    if (idx !== -1) { adminActiveTags.splice(idx, 1); this.classList.remove('active'); }
                    else { adminActiveTags.push(tag); this.classList.add('active'); }
                    if (!adminActiveTags.length) adminAllBtn.classList.add('active');
                }
                adminFilter();
            });
        });
    }

    // Tag filter expand/collapse
    var atf = document.getElementById('admin-tag-filters');
    var atb = document.getElementById('admin-tag-expand');
    if (atf && atb) {
        if (atf.scrollHeight > atf.clientHeight + 2) atb.style.display = '';
        atb.addEventListener('click', function() {
            atf.classList.toggle('expanded');
            atb.textContent = atf.classList.contains('expanded') ? 'Less' : '…';
        });
    }

    document.querySelectorAll('time.local-time').forEach(function (el) {
        var d = new Date(el.getAttribute('datetime'));
        if (!isNaN(d)) el.textContent = d.toLocaleString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    });
    document.querySelectorAll('time.local-date').forEach(function (el) {
        var d = new Date(el.getAttribute('datetime'));
        if (!isNaN(d)) el.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
    });

    // Multi-image slot management
    var slotsContainer = document.getElementById('image-slots');
    var hiddenContainer = document.getElementById('slot-hidden-inputs');
    var fileInput = document.getElementById('image-upload');
    var addBtn = document.getElementById('btn-add-image');
    var editorDiv = document.getElementById('image-editor');
    var canvas = document.getElementById('editor-canvas');
    var altInput = document.getElementById('slot-alt');

    if (!slotsContainer || !canvas) return;

    var ctx = canvas.getContext('2d');
    var imageSlots = [];
    var activeSlotIndex = -1;
    var originalImage = null;
    var currentImage = null;
    var cropStart = null;
    var cropEnd = null;
    var isDragging = false;
    var editorDirty = false;

    if (typeof initialImages !== 'undefined' && initialImages.length) {
        var alts = (typeof initialAlts !== 'undefined') ? initialAlts : [];
        initialImages.forEach(function (src, i) {
            imageSlots.push({ src: src, data: '', dirty: false, alt: alts[i] || '' });
        });
    }

    if (altInput) {
        altInput.addEventListener('input', function () {
            if (activeSlotIndex >= 0) {
                imageSlots[activeSlotIndex].alt = this.value;
            }
        });
    }

    renderThumbnails();

    function renderThumbnails() {
        slotsContainer.innerHTML = '';
        imageSlots.forEach(function (slot, i) {
            var thumb = document.createElement('div');
            thumb.className = 'slot-thumb' + (i === activeSlotIndex ? ' active' : '');

            var img = document.createElement('img');
            img.src = slot.data || (slot.src + '?v=' + Date.now());
            img.alt = slot.alt || ('Image ' + (i + 1));
            thumb.appendChild(img);

            var controls = document.createElement('div');
            controls.className = 'slot-controls';

            if (i > 0) {
                var leftBtn = document.createElement('button');
                leftBtn.type = 'button';
                leftBtn.textContent = '←';
                leftBtn.title = 'Move left';
                leftBtn.addEventListener('click', function (e) { e.stopPropagation(); moveSlot(i, -1); });
                controls.appendChild(leftBtn);
            }
            if (i < imageSlots.length - 1) {
                var rightBtn = document.createElement('button');
                rightBtn.type = 'button';
                rightBtn.textContent = '→';
                rightBtn.title = 'Move right';
                rightBtn.addEventListener('click', function (e) { e.stopPropagation(); moveSlot(i, 1); });
                controls.appendChild(rightBtn);
            }

            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'slot-delete';
            delBtn.textContent = '×';
            delBtn.title = 'Remove image';
            delBtn.addEventListener('click', function (e) { e.stopPropagation(); removeSlot(i); });
            controls.appendChild(delBtn);

            thumb.appendChild(controls);
            thumb.addEventListener('click', function () { selectSlot(i); });
            slotsContainer.appendChild(thumb);
        });
        syncHiddenInputs();
    }

    function syncHiddenInputs() {
        hiddenContainer.innerHTML = '';
        imageSlots.forEach(function (slot) {
            var pathInput = document.createElement('input');
            pathInput.type = 'hidden';
            pathInput.name = 'slot_paths[]';
            pathInput.value = slot.data ? '' : slot.src;
            hiddenContainer.appendChild(pathInput);

            var dataInput = document.createElement('input');
            dataInput.type = 'hidden';
            dataInput.name = 'slot_data[]';
            dataInput.value = slot.data || '';
            hiddenContainer.appendChild(dataInput);

            var altHidden = document.createElement('input');
            altHidden.type = 'hidden';
            altHidden.name = 'slot_alts[]';
            altHidden.value = slot.alt || '';
            hiddenContainer.appendChild(altHidden);
        });
    }

    function selectSlot(i) {
        if (i < 0 || i >= imageSlots.length) return;
        saveAltFromInput();
        applyDirtyToActiveSlot();
        activeSlotIndex = i;
        var slot = imageSlots[i];
        if (altInput) altInput.value = slot.alt || '';
        var src = slot.data || slot.src;
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            originalImage = img;
            currentImage = img;
            editorDirty = false;
            drawImage(img);
            editorDiv.style.display = 'block';
        };
        img.src = src + (slot.data ? '' : '?v=' + Date.now());
        renderThumbnails();
    }

    function saveAltFromInput() {
        if (altInput && activeSlotIndex >= 0) {
            imageSlots[activeSlotIndex].alt = altInput.value;
        }
    }

    function removeSlot(i) {
        imageSlots.splice(i, 1);
        if (activeSlotIndex === i) {
            activeSlotIndex = -1;
            editorDiv.style.display = 'none';
            originalImage = null;
            currentImage = null;
            if (altInput) altInput.value = '';
        } else if (activeSlotIndex > i) {
            activeSlotIndex--;
        }
        renderThumbnails();
    }

    function moveSlot(i, dir) {
        var j = i + dir;
        if (j < 0 || j >= imageSlots.length) return;
        var tmp = imageSlots[i];
        imageSlots[i] = imageSlots[j];
        imageSlots[j] = tmp;
        if (activeSlotIndex === i) activeSlotIndex = j;
        else if (activeSlotIndex === j) activeSlotIndex = i;
        renderThumbnails();
    }

    function applyDirtyToActiveSlot() {
        if (activeSlotIndex < 0 || !editorDirty || !currentImage) return;
        var tmpCanvas = document.createElement('canvas');
        tmpCanvas.width = currentImage.width;
        tmpCanvas.height = currentImage.height;
        var tmpCtx = tmpCanvas.getContext('2d');
        tmpCtx.fillStyle = '#ffffff';
        tmpCtx.fillRect(0, 0, tmpCanvas.width, tmpCanvas.height);
        tmpCtx.drawImage(currentImage, 0, 0);
        imageSlots[activeSlotIndex].data = tmpCanvas.toDataURL('image/jpeg', 0.90);
        imageSlots[activeSlotIndex].dirty = false;
        editorDirty = false;
    }

    addBtn.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            imageSlots.push({ src: '', data: e.target.result, dirty: false, alt: '' });
            renderThumbnails();
            selectSlot(imageSlots.length - 1);
        };
        reader.readAsDataURL(file);
        this.value = '';
    });

    // Canvas drawing
    function drawImage(img) {
        var maxW = 800;
        var scale = img.width > maxW ? maxW / img.width : 1;
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        cropStart = null;
        cropEnd = null;
    }

    function drawCropOverlay() {
        if (!cropStart || !cropEnd || !currentImage) return;
        var maxW = 800;
        var scale = currentImage.width > maxW ? maxW / currentImage.width : 1;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(currentImage, 0, 0, canvas.width, canvas.height);

        var x = Math.min(cropStart.x, cropEnd.x);
        var y = Math.min(cropStart.y, cropEnd.y);
        var w = Math.abs(cropEnd.x - cropStart.x);
        var h = Math.abs(cropEnd.y - cropStart.y);

        ctx.fillStyle = 'rgba(0,0,0,0.4)';
        ctx.fillRect(0, 0, canvas.width, y);
        ctx.fillRect(0, y + h, canvas.width, canvas.height - y - h);
        ctx.fillRect(0, y, x, h);
        ctx.fillRect(x + w, y, canvas.width - x - w, h);

        ctx.strokeStyle = '#2a6041';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, w, h);
    }

    canvas.addEventListener('mousedown', function (e) {
        var rect = canvas.getBoundingClientRect();
        cropStart = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        cropEnd = null;
        isDragging = true;
    });

    canvas.addEventListener('mousemove', function (e) {
        if (!isDragging) return;
        var rect = canvas.getBoundingClientRect();
        cropEnd = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        drawCropOverlay();
    });

    canvas.addEventListener('mouseup', function () { isDragging = false; });

    document.getElementById('btn-crop').addEventListener('click', function () {
        if (!cropStart || !cropEnd || !currentImage) return;
        var maxW = 800;
        var scale = currentImage.width > maxW ? maxW / currentImage.width : 1;
        var x = Math.min(cropStart.x, cropEnd.x) / scale;
        var y = Math.min(cropStart.y, cropEnd.y) / scale;
        var w = Math.abs(cropEnd.x - cropStart.x) / scale;
        var h = Math.abs(cropEnd.y - cropStart.y) / scale;
        if (w < 10 || h < 10) return;

        var tmpCanvas = document.createElement('canvas');
        tmpCanvas.width = w;
        tmpCanvas.height = h;
        tmpCanvas.getContext('2d').drawImage(currentImage, x, y, w, h, 0, 0, w, h);

        var img = new Image();
        img.onload = function () {
            currentImage = img;
            drawImage(img);
            editorDirty = true;
        };
        img.src = tmpCanvas.toDataURL('image/png');
    });

    function rotateImage(degrees) {
        if (!currentImage) return;
        var tmpCanvas = document.createElement('canvas');
        var rad = (degrees * Math.PI) / 180;
        if (degrees === 90 || degrees === -90) {
            tmpCanvas.width = currentImage.height;
            tmpCanvas.height = currentImage.width;
        } else {
            tmpCanvas.width = currentImage.width;
            tmpCanvas.height = currentImage.height;
        }
        var tmpCtx = tmpCanvas.getContext('2d');
        tmpCtx.translate(tmpCanvas.width / 2, tmpCanvas.height / 2);
        tmpCtx.rotate(rad);
        tmpCtx.drawImage(currentImage, -currentImage.width / 2, -currentImage.height / 2);

        var img = new Image();
        img.onload = function () {
            currentImage = img;
            drawImage(img);
            editorDirty = true;
        };
        img.src = tmpCanvas.toDataURL('image/png');
    }

    document.getElementById('btn-rotate-left').addEventListener('click', function () { rotateImage(-90); });
    document.getElementById('btn-rotate-right').addEventListener('click', function () { rotateImage(90); });

    var bgRawImageData = null;
    var bgOriginalImageData = null;
    var bgPreviewActive = false;
    var thresholdPanel = document.getElementById('bg-threshold-panel');
    var thresholdSlider = document.getElementById('bg-threshold');
    var thresholdVal = document.getElementById('bg-threshold-val');
    var featherCheck = document.getElementById('bg-feather');

    function bgApplyThreshold() {
        if (!bgRawImageData || !bgOriginalImageData) return;
        var thresh = parseInt(thresholdSlider.value);
        var soft = featherCheck.checked;
        var raw = bgRawImageData.data;
        var orig = bgOriginalImageData.data;
        var out = new ImageData(bgRawImageData.width, bgRawImageData.height);
        var d = out.data;
        for (var i = 0; i < raw.length; i += 4) {
            var alpha = raw[i + 3];
            var a;
            if (soft) {
                if (alpha <= thresh * 0.5) a = 0;
                else if (alpha >= thresh) a = alpha;
                else a = Math.round(alpha * ((alpha - thresh * 0.5) / (thresh * 0.5)));
            } else {
                a = alpha >= thresh ? 255 : 0;
            }
            d[i] = orig[i];
            d[i + 1] = orig[i + 1];
            d[i + 2] = orig[i + 2];
            d[i + 3] = a;
        }
        var tmpCanvas = document.createElement('canvas');
        tmpCanvas.width = out.width;
        tmpCanvas.height = out.height;
        tmpCanvas.getContext('2d').putImageData(out, 0, 0);

        var maxW = 800;
        var scale = out.width > maxW ? maxW / out.width : 1;
        canvas.width = out.width * scale;
        canvas.height = out.height * scale;
        drawCheckerboard(ctx, canvas.width, canvas.height);
        ctx.drawImage(tmpCanvas, 0, 0, canvas.width, canvas.height);
    }

    function drawCheckerboard(context, w, h) {
        var size = 8;
        for (var y = 0; y < h; y += size) {
            for (var x = 0; x < w; x += size) {
                context.fillStyle = ((x / size + y / size) & 1) ? '#ccc' : '#fff';
                context.fillRect(x, y, size, size);
            }
        }
    }

    function bgEnterPreview() {
        bgPreviewActive = true;
        thresholdPanel.style.display = '';
        document.querySelector('.editor-controls').style.display = 'none';
    }

    function bgExitPreview() {
        bgPreviewActive = false;
        bgRawImageData = null;
        bgOriginalImageData = null;
        thresholdPanel.style.display = 'none';
        document.querySelector('.editor-controls').style.display = '';
    }

    if (thresholdSlider) {
        thresholdSlider.addEventListener('input', function () {
            thresholdVal.textContent = this.value;
            bgApplyThreshold();
        });
    }
    if (featherCheck) {
        featherCheck.addEventListener('change', function () { bgApplyThreshold(); });
    }

    document.getElementById('btn-bg-accept').addEventListener('click', function () {
        if (!bgRawImageData) return;
        bgApplyThreshold();
        var tmpCanvas = document.createElement('canvas');
        tmpCanvas.width = bgRawImageData.width;
        tmpCanvas.height = bgRawImageData.height;
        var tmpCtx = tmpCanvas.getContext('2d');

        var thresh = parseInt(thresholdSlider.value);
        var soft = featherCheck.checked;
        var raw = bgRawImageData.data;
        var orig = bgOriginalImageData.data;
        var out = tmpCtx.createImageData(tmpCanvas.width, tmpCanvas.height);
        var d = out.data;
        for (var i = 0; i < raw.length; i += 4) {
            var alpha = raw[i + 3];
            var a;
            if (soft) {
                if (alpha <= thresh * 0.5) a = 0;
                else if (alpha >= thresh) a = alpha;
                else a = Math.round(alpha * ((alpha - thresh * 0.5) / (thresh * 0.5)));
            } else {
                a = alpha >= thresh ? 255 : 0;
            }
            d[i] = orig[i];
            d[i + 1] = orig[i + 1];
            d[i + 2] = orig[i + 2];
            d[i + 3] = a;
        }
        tmpCtx.putImageData(out, 0, 0);

        var img = new Image();
        img.onload = function () {
            currentImage = img;
            editorDirty = true;
            bgExitPreview();
            drawImage(img);
        };
        img.src = tmpCanvas.toDataURL('image/png');
    });

    document.getElementById('btn-bg-cancel').addEventListener('click', function () {
        bgExitPreview();
        if (currentImage) drawImage(currentImage);
    });

    document.getElementById('btn-remove-bg').addEventListener('click', async function () {
        if (!currentImage) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Loading model...';
        try {
            const { removeBackground } = await import(
                'https://cdn.jsdelivr.net/npm/@imgly/background-removal@1.7.0/+esm'
            );
            btn.textContent = 'Processing...';
            var srcCanvas = document.createElement('canvas');
            srcCanvas.width = currentImage.width;
            srcCanvas.height = currentImage.height;
            var srcCtx = srcCanvas.getContext('2d');
            srcCtx.drawImage(currentImage, 0, 0);
            bgOriginalImageData = srcCtx.getImageData(0, 0, srcCanvas.width, srcCanvas.height);

            var blob = await new Promise(function (r) { srcCanvas.toBlob(r, 'image/png'); });
            var resultBlob = await removeBackground(blob, {
                model: 'isnet_quint8',
                output: { format: 'image/png' },
                progress: function (key, current, total) {
                    btn.textContent = key.startsWith('fetch')
                        ? 'Downloading... ' + Math.round(current / total * 100) + '%'
                        : 'Processing...';
                }
            });
            var url = URL.createObjectURL(resultBlob);
            var rawImg = new Image();
            rawImg.onload = function () {
                var rawCanvas = document.createElement('canvas');
                rawCanvas.width = rawImg.width;
                rawCanvas.height = rawImg.height;
                var rawCtx = rawCanvas.getContext('2d');
                rawCtx.drawImage(rawImg, 0, 0);
                bgRawImageData = rawCtx.getImageData(0, 0, rawCanvas.width, rawCanvas.height);
                URL.revokeObjectURL(url);

                thresholdSlider.value = 128;
                thresholdVal.textContent = '128';
                featherCheck.checked = true;
                bgEnterPreview();
                bgApplyThreshold();
            };
            rawImg.src = url;
        } catch (e) {
            console.error('Background removal failed:', e);
            alert('Background removal failed: ' + e.message);
        } finally {
            btn.textContent = 'Remove BG';
            btn.disabled = false;
        }
    });

    document.getElementById('btn-resize').addEventListener('click', function () {
        if (!currentImage) return;
        var maxSize = parseInt(document.getElementById('resize-max').value) || 1200;
        var ratio = Math.min(maxSize / currentImage.width, maxSize / currentImage.height, 1);
        if (ratio >= 1) return;

        var tmpCanvas = document.createElement('canvas');
        tmpCanvas.width = currentImage.width * ratio;
        tmpCanvas.height = currentImage.height * ratio;
        tmpCanvas.getContext('2d').drawImage(currentImage, 0, 0, tmpCanvas.width, tmpCanvas.height);

        var img = new Image();
        img.onload = function () {
            currentImage = img;
            drawImage(img);
            editorDirty = true;
        };
        img.src = tmpCanvas.toDataURL('image/png');
    });

    document.getElementById('btn-reset').addEventListener('click', function () {
        if (!originalImage) return;
        currentImage = originalImage;
        drawImage(originalImage);
        editorDirty = false;
    });

    document.getElementById('btn-apply').addEventListener('click', function () {
        if (!currentImage || activeSlotIndex < 0) return;
        applyDirtyToActiveSlot();
        renderThumbnails();
        this.textContent = 'Applied!';
        var self = this;
        setTimeout(function () { self.textContent = 'Apply to slot'; }, 1500);
    });

    var form = document.getElementById('item-form');
    if (form) {
        form.addEventListener('submit', function () {
            saveAltFromInput();
            applyDirtyToActiveSlot();
            syncHiddenInputs();
        });
    }

});

// ── Geolocation for the item form ───────────────────────────────────
// Deliberately a SEPARATE DOMContentLoaded listener: the handler above returns
// early when the image-editor elements are absent, which has previously killed
// unrelated code appended to its end. Keeping this independent means it runs on
// every admin page that has the field.
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('geolocate-btn');
    var latEl = document.getElementById('latitude');
    var lonEl = document.getElementById('longitude');
    var precEl = document.getElementById('location_precision');
    var msg = document.getElementById('geolocate-msg');
    if (!btn || !latEl || !lonEl) return;

    function say(text, isError) {
        if (!msg) return;
        msg.textContent = text;
        msg.style.display = text ? 'block' : 'none';
        msg.style.color = isError ? 'var(--danger, #c0392b)' : 'var(--muted)';
    }

    if (!navigator.geolocation) {
        btn.disabled = true;
        btn.title = 'This browser does not expose a geolocation API';
        return;
    }

    btn.addEventListener('click', function () {
        var original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Locating…';
        say('');

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                // Store full precision; the published value is rounded server-side
                // to whatever precision the seller picked.
                latEl.value = pos.coords.latitude.toFixed(6);
                lonEl.value = pos.coords.longitude.toFixed(6);
                // Only nudge the precision away from "no location" — never
                // overwrite a choice the admin has already made.
                if (precEl && precEl.value === 'none') precEl.value = '100m';
                btn.disabled = false;
                btn.textContent = original;
                say('Location filled in. Adjust the precision or edit by hand if you prefer.');
            },
            function (err) {
                btn.disabled = false;
                btn.textContent = original;
                var reason = 'Could not get your location.';
                if (err && err.code === 1) reason = 'Permission denied — enter coordinates by hand instead.';
                else if (err && err.code === 2) reason = 'Position unavailable — enter coordinates by hand instead.';
                else if (err && err.code === 3) reason = 'Timed out — try again, or enter coordinates by hand.';
                say(reason, true);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
        );
    });
});
