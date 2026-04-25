<?php
/**
 * Reusable media image picker — rendered by MediaService::picker().
 *
 * Displays a clickable thumbnail preview. Clicking opens an offcanvas
 * with the media library grid (images only). Selecting an image updates
 * the hidden input and preview, then closes the offcanvas.
 *
 * @var string $inputName    Form input name (e.g. 'avatar')
 * @var string $currentValue Current image path (relative, e.g. 'uploads/2026/04/abc.png')
 * @var string $pickerId     Unique ID for this picker instance
 */
$hasImage = !empty($currentValue);
$previewSrc = $hasImage ? '/' . ltrim($currentValue, '/') : '';
?>
<div class="media-picker" id="<?= $pickerId ?>">
    <div class="media-picker-preview border rounded p-2 d-inline-block" role="button"
         style="cursor:pointer; min-width:80px; min-height:80px;"
         data-bs-toggle="offcanvas" data-bs-target="#<?= $pickerId ?>-offcanvas">
        <?php if ($hasImage): ?>
            <img src="<?= htmlspecialchars($previewSrc) ?>" alt="Selected image"
                 class="rounded" style="max-width:120px; max-height:120px; object-fit:cover;">
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center text-secondary"
                 style="width:80px; height:80px;">
                <i class="ti ti-photo-plus" style="font-size:2rem;"></i>
            </div>
        <?php endif; ?>
    </div>
    <input type="hidden" name="<?= htmlspecialchars($inputName) ?>" value="<?= htmlspecialchars($currentValue) ?>">
    <?php if ($hasImage): ?>
        <button type="button" class="btn btn-ghost-danger btn-sm mt-1 media-picker-clear">
            <i class="ti ti-x"></i> Remove
        </button>
    <?php endif; ?>
</div>

<!-- Offcanvas picker -->
<div id="<?= $pickerId ?>-offcanvas" class="offcanvas offcanvas-end" tabindex="-1" style="width:450px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Select Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="row row-cols-3 g-2" id="<?= $pickerId ?>-grid">
            <div class="text-center text-secondary py-4 w-100">Loading...</div>
        </div>
        <div class="text-center mt-3" id="<?= $pickerId ?>-load-more" style="display:none;">
            <button type="button" class="btn btn-outline-secondary btn-sm">Load more</button>
        </div>
    </div>
</div>

<script>
(function() {
    const picker = document.getElementById('<?= $pickerId ?>');
    const grid = document.getElementById('<?= $pickerId ?>-grid');
    const loadMoreWrap = document.getElementById('<?= $pickerId ?>-load-more');
    const offcanvasEl = document.getElementById('<?= $pickerId ?>-offcanvas');
    const hiddenInput = picker.querySelector('input[type="hidden"]');
    const preview = picker.querySelector('.media-picker-preview');
    const clearBtn = picker.querySelector('.media-picker-clear');
    let page = 1;
    let total = 0;
    let loaded = false;

    function loadImages(pg) {
        fetch('/admin/media/json?type=image&page=' + pg)
            .then(r => r.json())
            .then(data => {
                if (pg === 1) grid.innerHTML = '';
                total = data.total;

                if (data.items.length === 0 && pg === 1) {
                    grid.innerHTML = '<div class="text-center text-secondary py-4 w-100">No images found.</div>';
                    loadMoreWrap.style.display = 'none';
                    return;
                }

                data.items.forEach(item => {
                    if (item.type !== 'image' || !item.thumb_url) return;
                    const col = document.createElement('div');
                    col.className = 'col';
                    col.innerHTML = `<div class="card card-sm media-picker-item" role="button"
                                          data-path="${item.path}" style="cursor:pointer;">
                        <img src="${item.thumb_url}" class="card-img-top" loading="lazy"
                             style="height:90px; object-fit:cover;"
                             alt="${item.filename || ''}">
                    </div>`;
                    grid.appendChild(col);
                });

                const totalLoaded = pg * data.per_page;
                loadMoreWrap.style.display = totalLoaded < total ? '' : 'none';
            });
    }

    // Load on first open
    offcanvasEl.addEventListener('show.bs.offcanvas', function() {
        if (!loaded) {
            loadImages(1);
            loaded = true;
        }
    });

    // Load more
    loadMoreWrap.querySelector('button').addEventListener('click', function() {
        page++;
        loadImages(page);
    });

    // Select image
    grid.addEventListener('click', function(e) {
        const item = e.target.closest('.media-picker-item');
        if (!item) return;

        const path = item.dataset.path;
        hiddenInput.value = path;

        // Update preview
        preview.innerHTML = `<img src="/${path}" alt="Selected image"
            class="rounded" style="max-width:120px; max-height:120px; object-fit:cover;">`;

        // Ensure remove button exists
        if (!picker.querySelector('.media-picker-clear')) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-ghost-danger btn-sm mt-1 media-picker-clear';
            btn.innerHTML = '<i class="ti ti-x"></i> Remove';
            btn.addEventListener('click', clearImage);
            preview.insertAdjacentElement('afterend', btn);
        }

        // Close offcanvas
        tabler.Offcanvas.getInstance(offcanvasEl)?.hide();
    });

    // Clear / remove
    function clearImage() {
        hiddenInput.value = '';
        preview.innerHTML = `<div class="d-flex align-items-center justify-content-center text-secondary"
            style="width:80px; height:80px;">
            <i class="ti ti-photo-plus" style="font-size:2rem;"></i>
        </div>`;
        const btn = picker.querySelector('.media-picker-clear');
        if (btn) btn.remove();
    }

    if (clearBtn) clearBtn.addEventListener('click', clearImage);
})();
</script>
