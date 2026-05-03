<?php
/**
 * Jodit editor init with media integration — rendered by MediaService::joditInit().
 *
 * Initialises Jodit on the given selector with:
 * - Custom "Media Library" toolbar button that opens an offcanvas browser
 * - Drag/drop image upload routed through the media package
 * - Image and video browse/upload in the offcanvas
 * - Jodit's built-in video button left intact for YouTube/Vimeo embeds
 *
 * @var string $selector CSS selector for the textarea
 * @var string $joditId  Unique ID for this instance
 * @var array  $config   Merged Jodit config (height, buttons)
 */
?>

<!-- Jodit Media Offcanvas -->
<div id="<?= $joditId ?>-offcanvas" class="offcanvas offcanvas-end" data-jodit-media="1" tabindex="-1" style="width:450px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Media Library</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="<?= $joditId ?>-upload-zone" class="border border-dashed rounded p-3 text-center mb-3" style="cursor:pointer;">
            <i class="ti ti-cloud-upload" style="font-size:1.5rem;"></i>
            <p class="mb-0 mt-1 small">Drop file or click to upload</p>
            <input type="file" class="d-none"
                   accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime">
        </div>
        <div class="row row-cols-3 g-2" id="<?= $joditId ?>-grid">
            <div class="text-center text-secondary py-4 w-100">Loading...</div>
        </div>
        <div class="text-center mt-3" id="<?= $joditId ?>-load-more" style="display:none;">
            <button type="button" class="btn btn-outline-secondary btn-sm">Load more</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
(function() {
    var csrfToken    = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var offcanvasEl  = document.getElementById('<?= $joditId ?>-offcanvas');
    var grid         = document.getElementById('<?= $joditId ?>-grid');
    var loadMoreWrap = document.getElementById('<?= $joditId ?>-load-more');
    var uploadZone   = document.getElementById('<?= $joditId ?>-upload-zone');
    var uploadInput  = uploadZone.querySelector('input[type="file"]');
    var page   = 1;
    var total   = 0;
    var loaded = false;

    if (offcanvasEl && offcanvasEl.parentElement !== document.body) {
        document.body.appendChild(offcanvasEl);
    }

    offcanvasEl.addEventListener('show.bs.offcanvas', function() {
        offcanvasEl.style.zIndex = '1085';
        setTimeout(function() {
            var backdrops = document.querySelectorAll('.offcanvas-backdrop');
            var backdrop = backdrops[backdrops.length - 1];
            if (backdrop) {
                backdrop.setAttribute('data-jodit-media-backdrop', '1');
                backdrop.style.zIndex = '1080';
            }
        }, 0);
    });

    offcanvasEl.addEventListener('hidden.bs.offcanvas', function() {
        offcanvasEl.style.zIndex = '';
        var activeModal = document.querySelector('.modal.show');
        if (activeModal) {
            activeModal.focus();
        }
    });

    // ── Upload ─────────────────────────────────────────────

    uploadZone.addEventListener('click', function(e) {
        if (e.target.closest('input')) return;
        uploadInput.click();
    });
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('border-primary');
    });
    uploadZone.addEventListener('dragleave', function() {
        uploadZone.classList.remove('border-primary');
    });
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('border-primary');
        if (e.dataTransfer.files.length) uploadFile(e.dataTransfer.files[0]);
    });
    uploadInput.addEventListener('change', function() {
        if (uploadInput.files.length) uploadFile(uploadInput.files[0]);
        uploadInput.value = '';
    });

    function uploadFile(file) {
        var isVideo  = file.type.startsWith('video/');
        var endpoint = isVideo ? '/admin/media/upload/video' : '/admin/media/upload/image';

        var fd = new FormData();
        fd.append('file', file);
        fd.append('_csrf_token', csrfToken);

        fetch(endpoint, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) { alert(data.error); return; }
                page = 1;
                loaded = true;
                loadMedia(1);
            })
            .catch(function() { alert('Upload failed.'); });
    }

    // ── Browse grid ────────────────────────────────────────

    function loadMedia(pg) {
        fetch('/admin/media/json?page=' + pg)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (pg === 1) grid.innerHTML = '';
                total = data.total;

                if (data.items.length === 0 && pg === 1) {
                    grid.innerHTML = '<div class="text-center text-secondary py-4 w-100">No media found.</div>';
                    loadMoreWrap.style.display = 'none';
                    return;
                }

                data.items.forEach(function(item) {
                    var thumb = '';

                    if (item.type === 'image' && item.thumb_url) {
                        thumb = '<img src="' + item.thumb_url + '" class="card-img-top" loading="lazy"'
                              + ' style="height:90px; object-fit:cover;"'
                              + ' alt="' + (item.filename || '') + '">';
                    } else if (item.type === 'video') {
                        if (item.poster_url) {
                            thumb = '<img src="' + item.poster_url + '" class="card-img-top" loading="lazy"'
                                  + ' style="height:90px; object-fit:cover;"'
                                  + ' alt="' + (item.filename || '') + '">';
                        } else {
                            thumb = '<div class="card-img-top bg-dark d-flex align-items-center justify-content-center" style="height:90px;">'
                                  + '<i class="ti ti-video text-white" style="font-size:1.5rem;"></i></div>';
                        }
                    } else {
                        return; // skip embeds — those use Jodit's built-in video button
                    }

                    var col = document.createElement('div');
                    col.className = 'col';
                    col.innerHTML = '<div class="card card-sm jodit-media-item"'
                        + ' data-type="' + item.type + '"'
                        + ' data-url="' + (item.medium_url || item.url || '') + '"'
                        + ' data-medium-url="' + (item.medium_url || item.url || '') + '"'
                        + ' data-thumb-url="' + (item.thumb_url || item.url || '') + '"'
                        + ' data-original="' + (item.url || '') + '"'
                        + ' data-poster="' + (item.poster_url || '') + '"'
                        + ' data-alt="' + (item.alt_text || '') + '">'
                        + thumb + '</div>';
                    if (item.type === 'image') {
                        col.innerHTML = '<div class="card card-sm jodit-media-item"'
                            + ' data-type="' + item.type + '"'
                            + ' data-url="' + (item.medium_url || item.url || '') + '"'
                            + ' data-medium-url="' + (item.medium_url || item.url || '') + '"'
                            + ' data-thumb-url="' + (item.thumb_url || item.url || '') + '"'
                            + ' data-original="' + (item.url || '') + '"'
                            + ' data-poster="' + (item.poster_url || '') + '"'
                            + ' data-alt="' + (item.alt_text || '') + '">'
                            + thumb
                            + '<div class="card-body p-2">'
                            + '<div class="btn-group btn-group-sm w-100" role="group">'
                            + '<button type="button" class="btn btn-outline-secondary jodit-size-choice" data-size="small">S</button>'
                            + '<button type="button" class="btn btn-outline-secondary jodit-size-choice" data-size="medium">M</button>'
                            + '<button type="button" class="btn btn-outline-secondary jodit-size-choice" data-size="large">L</button>'
                            + '</div></div></div>';
                    }
                    grid.appendChild(col);
                });

                var totalLoaded = pg * data.per_page;
                loadMoreWrap.style.display = totalLoaded < total ? '' : 'none';
            });
    }

    offcanvasEl.addEventListener('show.bs.offcanvas', function() {
        if (!loaded) {
            loadMedia(1);
            loaded = true;
        }
    });

    loadMoreWrap.querySelector('button').addEventListener('click', function() {
        page++;
        loadMedia(page);
    });

    // ── Jodit init ─────────────────────────────────────────

    if (typeof Jodit === 'undefined') return;

    Jodit.defaultOptions.controls.image = {
        tooltip: 'Media Library',
        exec: function() {
            new tabler.Offcanvas(offcanvasEl).show();
        }
    };

    var editor = Jodit.make('<?= $selector ?>', {
        height: <?= (int) $config['height'] ?>,
        buttons: '<?= $config['buttons'] ?>',
        uploader: {
            url: '/admin/media/upload/image',
            filesVariableName: 'file',
            headers: { 'X-CSRF-Token': csrfToken },
            data: { '_csrf_token': csrfToken },
            isSuccess: function(resp) { return !resp.error; },
            process: function(resp) {
                return {
                    files: [resp.medium_url || resp.url],
                    path: '',
                    baseurl: '',
                    error: resp.error ? 1 : 0,
                    msg: resp.error || ''
                };
            },
            defaultHandlerSuccess: function(data) {
                if (data.files && data.files.length) {
                    editor.s.insertImage(data.files[0]);
                }
            }
        }
    });

    // ── Insert from offcanvas ──────────────────────────────

    grid.addEventListener('click', function(e) {
        var choice = e.target.closest('.jodit-size-choice');
        var item = e.target.closest('.jodit-media-item');
        if (!item) return;

        var type     = item.dataset.type;
        var size     = choice ? choice.dataset.size : 'large';
        var url      = size === 'small'
            ? (item.dataset.thumbUrl || item.dataset.url)
            : (size === 'large' ? item.dataset.original : (item.dataset.mediumUrl || item.dataset.url));
        var original = item.dataset.original;
        var alt      = item.dataset.alt;
        var poster   = item.dataset.poster;

        if (type === 'image') {
            editor.s.insertHTML('<img src="' + url + '" alt="' + alt + '">');
        } else if (type === 'video') {
            var html = '<video controls src="' + original + '"';
            if (poster) html += ' poster="' + poster + '"';
            html += '></video>';
            editor.s.insertHTML(html);
        }

        tabler.Offcanvas.getInstance(offcanvasEl)?.hide();
    });
})();
});
</script>
