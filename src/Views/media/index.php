<?php
/**
 * Media library — admin page.
 *
 * Paginated grid of all media (images, videos, embeds) with upload zone,
 * type filter tabs, inline metadata editing, and delete.
 *
 * @var string                         $pageTitle
 * @var \Pubvana\Media\Models\Media[] $media
 * @var int                            $total
 * @var int                            $page
 * @var int                            $perPage
 * @var string|null                    $typeFilter
 */
?>

<!-- Upload zone -->
<div class="card mb-3">
    <div class="card-body">
        <div id="media-upload-zone" class="border border-dashed rounded p-4 text-center" style="cursor:pointer;">
            <i class="ti ti-cloud-upload" style="font-size:2rem;"></i>
            <p class="mb-0 mt-2">Drop files here or click to upload</p>
            <input type="file" id="media-file-input" class="d-none" multiple
                   accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime">
        </div>
    </div>
</div>

<!-- Filter tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link <?= $typeFilter === null ? 'active' : '' ?>"
                   href="/admin/media">All</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $typeFilter === 'image' ? 'active' : '' ?>"
                   href="/admin/media?type=image">Images</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $typeFilter === 'video' ? 'active' : '' ?>"
                   href="/admin/media?type=video">Videos</a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <?php if (empty($media)): ?>
            <p class="text-secondary text-center py-4">No media found.</p>
        <?php else: ?>
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3" id="media-grid">
                <?php foreach ($media as $item): ?>
                    <div class="col" data-media-id="<?= (int) $item->id ?>">
                        <div class="card card-sm media-card" role="button"
                             data-media='<?= htmlspecialchars(json_encode([
                                 'id'             => (int) $item->id,
                                 'type'           => $item->type,
                                 'filename'       => $item->filename,
                                 'path'           => $item->path,
                                 'alt_text'       => $item->alt_text,
                                 'title'          => $item->title,
                                 'embed_url'      => $item->embed_url,
                                 'embed_provider' => $item->embed_provider,
                                 'poster_path'    => $item->poster_path,
                             ]), ENT_QUOTES) ?>'>
                            <?php if ($item->type === 'image' && $item->path): ?>
                                <?php
                                    $dir  = dirname($item->path);
                                    $name = pathinfo($item->path, PATHINFO_FILENAME);
                                    $thumbUrl = '/' . $dir . '/thumbs/' . $name . '.webp';
                                ?>
                                <img src="<?= htmlspecialchars($thumbUrl) ?>"
                                     alt="<?= htmlspecialchars($item->alt_text ?? '') ?>"
                                     class="card-img-top" loading="lazy">
                            <?php elseif ($item->type === 'video'): ?>
                                <?php if ($item->poster_path): ?>
                                    <img src="/<?= htmlspecialchars($item->poster_path) ?>"
                                         alt="<?= htmlspecialchars($item->title ?? 'Video') ?>"
                                         class="card-img-top" loading="lazy">
                                <?php else: ?>
                                    <div class="card-img-top bg-dark d-flex align-items-center justify-content-center" style="height:120px;">
                                        <i class="ti ti-video text-white" style="font-size:2rem;"></i>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($item->type === 'embed'): ?>
                                <div class="card-img-top bg-azure-lt d-flex align-items-center justify-content-center" style="height:120px;">
                                    <i class="ti ti-brand-<?= htmlspecialchars($item->embed_provider ?? 'youtube') ?>" style="font-size:2rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body p-2">
                                <small class="text-truncate d-block text-secondary">
                                    <?= htmlspecialchars($item->filename) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php $totalPages = (int) ceil($total / $perPage); ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                <a class="page-link"
                                   href="/admin/media?page=<?= $p ?><?= $typeFilter ? '&type=' . $typeFilter : '' ?>">
                                    <?= $p ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Detail sidebar (shown when a media card is clicked) -->
<div id="media-detail" class="offcanvas offcanvas-end" tabindex="-1">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Media Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="media-detail-body">
        <!-- Populated by JS -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const grid = document.getElementById('media-grid');
    const uploadZone = document.getElementById('media-upload-zone');
    const fileInput = document.getElementById('media-file-input');

    // ── Upload ──────────────────────────────────────────────

    uploadZone.addEventListener('click', () => fileInput.click());

    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('border-primary');
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('border-primary');
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('border-primary');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
        fileInput.value = '';
    });

    function handleFiles(files) {
        const fileArray = Array.from(files);
        let completed = 0;
        let lastData = null;

        fileArray.forEach(file => {
            const isVideo = file.type.startsWith('video/');
            const endpoint = isVideo ? '/admin/media/upload/video' : '/admin/media/upload/image';

            const fd = new FormData();
            fd.append('file', file);
            fd.append('_csrf_token', csrfToken);

            fetch(endpoint, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    completed++;
                    lastData = data;

                    if (completed === fileArray.length) {
                        // Single image upload → open editor
                        if (fileArray.length === 1 && data.type === 'image') {
                            window.location.href = '/admin/media/' + data.id + '/editor';
                        } else {
                            location.reload();
                        }
                    }
                })
                .catch(() => alert('Upload failed.'));
        });
    }

    // ── Detail sidebar ──────────────────────────────────────

    if (grid) {
        grid.addEventListener('click', function (e) {
            const card = e.target.closest('.media-card');
            if (!card) return;

            const media = JSON.parse(card.dataset.media);
            showDetail(media);
        });
    }

    function showDetail(media) {
        const body = document.getElementById('media-detail-body');
        let preview = '';

        if (media.type === 'image' && media.path) {
            preview = `<img src="/${media.path}" class="img-fluid rounded mb-3" alt="">`;
        } else if (media.type === 'video' && media.poster_path) {
            preview = `<img src="/${media.poster_path}" class="img-fluid rounded mb-3" alt="">`;
        } else if (media.type === 'video') {
            preview = `<div class="bg-dark rounded d-flex align-items-center justify-content-center mb-3" style="height:200px;">
                <i class="ti ti-video text-white" style="font-size:3rem;"></i>
            </div>`;
        } else if (media.type === 'embed') {
            preview = `<div class="bg-azure-lt rounded d-flex align-items-center justify-content-center mb-3" style="height:200px;">
                <i class="ti ti-brand-${media.embed_provider || 'youtube'}" style="font-size:3rem;"></i>
            </div>`;
        }

        body.innerHTML = `
            ${preview}
            <div class="mb-3">
                <label class="form-label">Alt Text</label>
                <input type="text" class="form-control" id="detail-alt" value="${media.alt_text || ''}">
            </div>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" id="detail-title" value="${media.title || ''}">
            </div>
            <div class="mb-3">
                <small class="text-secondary">${media.filename}</small>
            </div>
            ${media.type === 'image' ? `
                <div class="mb-3">
                    <a href="/admin/media/${media.id}/editor" class="btn btn-outline-primary w-100">
                        <i class="ti ti-photo-edit"></i> Edit Image
                    </a>
                </div>
            ` : ''}
            ${media.type === 'video' ? `
                <div class="mb-3">
                    <label class="form-label">Upload Poster</label>
                    <input type="file" class="form-control" id="detail-poster" accept="image/*">
                </div>
            ` : ''}
            <div class="d-flex gap-2">
                <button class="btn btn-primary" id="detail-save" data-id="${media.id}">Save</button>
                <button class="btn btn-outline-danger" id="detail-delete" data-id="${media.id}">Delete</button>
            </div>
        `;

        // Save metadata on blur
        document.getElementById('detail-save').addEventListener('click', function () {
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append('alt_text', document.getElementById('detail-alt').value);
            fd.append('title', document.getElementById('detail-title').value);
            fd.append('_csrf_token', csrfToken);

            fetch(`/admin/media/${id}/update`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.error) { alert(data.error); return; }
                });
        });

        // Delete
        document.getElementById('detail-delete').addEventListener('click', function () {
            if (!confirm('Delete this media? This cannot be undone.')) return;
            const id = this.dataset.id;
            const fd = new FormData();
            fd.append('_csrf_token', csrfToken);

            fetch(`/admin/media/${id}/delete`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.error) { alert(data.error); return; }
                    document.querySelector(`[data-media-id="${id}"]`)?.remove();
                    tabler.Offcanvas.getInstance(document.getElementById('media-detail'))?.hide();
                });
        });

        // Poster upload
        const posterInput = document.getElementById('detail-poster');
        if (posterInput) {
            posterInput.addEventListener('change', function () {
                const fd = new FormData();
                fd.append('file', this.files[0]);
                fd.append('_csrf_token', csrfToken);

                fetch(`/admin/media/${media.id}/poster`, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) { alert(data.error); return; }
                        location.reload();
                    });
            });
        }

        new tabler.Offcanvas(document.getElementById('media-detail')).show();
    }
});
</script>
