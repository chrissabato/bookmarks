<?php
require __DIR__ . '/db.php';
init_schema();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bookmarks Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
  <style>
    .sortable-ghost { opacity: 0.4; }
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing; }
    [contenteditable]:focus { outline: none; background: rgba(255,255,255,0.05); border-radius: 3px; }
    .link-row:hover .del-btn { opacity: 1; }
  </style>
</head>
<body class="bg-slate-950 text-slate-300 min-h-screen">

<!-- New Page Set Dialog -->
<dialog id="new-set-dialog"
        class="bg-slate-800 border border-slate-700 rounded-lg p-6 text-slate-300 w-full max-w-sm backdrop:bg-slate-950/80">
  <h2 class="text-lg font-semibold text-white mb-4">New Page Set</h2>
  <div class="flex flex-col gap-3">
    <div>
      <label class="text-xs text-slate-500 uppercase tracking-wider">Title</label>
      <input id="new-title" type="text" placeholder="e.g. Press Box"
             class="mt-1 w-full bg-slate-900 border border-slate-700 text-white rounded px-3 py-2 text-sm focus:outline-none focus:border-slate-500">
    </div>
    <div>
      <label class="text-xs text-slate-500 uppercase tracking-wider">Slug</label>
      <input id="new-slug" type="text" placeholder="e.g. press-box"
             class="mt-1 w-full bg-slate-900 border border-slate-700 text-white rounded px-3 py-2 text-sm font-mono focus:outline-none focus:border-slate-500">
      <p class="text-xs text-slate-500 mt-1">Lowercase letters, numbers, hyphens only</p>
    </div>
    <div class="flex gap-2 justify-end mt-2">
      <button onclick="document.getElementById('new-set-dialog').close()"
              class="px-4 py-1.5 text-sm rounded border border-slate-700 hover:border-slate-500 transition-colors">Cancel</button>
      <button onclick="createPageSet()"
              class="px-4 py-1.5 text-sm rounded bg-white text-black font-medium hover:bg-slate-200 transition-colors">Create</button>
    </div>
  </div>
</dialog>

<div class="max-w-5xl mx-auto px-4 py-4">

  <!-- Nav -->
  <div class="border-b border-slate-700 pb-3 mb-4">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-3">
      <h1 class="text-xl font-bold text-white uppercase tracking-wide">Bookmarks Admin</h1>
      <div class="flex items-center gap-2">
        <button onclick="toggleSetManager()"
                id="manage-btn"
                class="text-xs px-3 py-1.5 rounded border border-slate-700 hover:border-slate-500 text-slate-400 hover:text-white transition-colors">
          Manage Sets
        </button>
        <a id="view-link" href="index.php" target="_blank"
           class="text-xs px-3 py-1.5 rounded border border-slate-700 hover:border-slate-500 text-slate-400 hover:text-white transition-colors">
          View &rarr;
        </a>
      </div>
    </div>
    <!-- Page set tabs -->
    <div id="set-tabs" class="flex flex-wrap gap-1.5"></div>
  </div>

  <!-- Page Set Manager (collapsible) -->
  <div id="set-manager" class="hidden bg-slate-800 border border-slate-700 rounded-lg mb-4 p-4">
    <div class="flex items-center justify-between mb-3">
      <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Page Sets</span>
      <button onclick="document.getElementById('new-set-dialog').showModal()"
              class="text-xs px-2 py-1 rounded border border-dashed border-slate-700 hover:border-slate-500 text-slate-500 hover:text-slate-300 transition-colors">
        + New Set
      </button>
    </div>
    <div id="set-list" class="flex flex-col gap-1"></div>
  </div>

  <!-- Category area -->
  <div id="category-list" class="flex flex-col gap-3"></div>
  <button onclick="addCategory()"
          class="w-full mt-2 border border-dashed border-slate-700 hover:border-slate-600 text-slate-500 hover:text-slate-400 rounded-lg py-2.5 text-sm transition-colors">
    + Add Category
  </button>

</div>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
const state = {
    pageSets: [],
    activeId: null,
    categories: [],   // [{id, name, position, bookmarks:[{id,label,url,position}]}]
};

// ── API helpers ───────────────────────────────────────────────────────────────
async function api(action, params = {}, method = 'GET') {
    const url = 'api.php?action=' + encodeURIComponent(action);
    let res;
    if (method === 'GET') {
        const qs = new URLSearchParams(params).toString();
        res = await fetch(url + (qs ? '&' + qs : ''));
    } else {
        const body = new URLSearchParams(params);
        res = await fetch(url, { method: 'POST', body });
    }
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    return data;
}

const GET  = (action, p) => api(action, p, 'GET');
const POST = (action, p) => api(action, p, 'POST');

// ── Favicon ───────────────────────────────────────────────────────────────────
function faviconUrl(url) {
    try {
        const norm = url.startsWith('//') ? 'https:' + url : url;
        if (norm.startsWith('javascript:')) return '';
        const host = new URL(norm).hostname;
        if (!host) return '';
        return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(host)}&sz=32`;
    } catch { return ''; }
}

// ── Init ──────────────────────────────────────────────────────────────────────
async function init() {
    state.pageSets = await GET('page_sets.list');
    if (state.pageSets.length) {
        state.activeId = state.pageSets[0].id;
    }
    renderTabs();
    renderSetManager();
    if (state.activeId) await loadPageSet(state.activeId);
}

// ── Page set tabs ─────────────────────────────────────────────────────────────
function renderTabs() {
    const container = document.getElementById('set-tabs');
    container.innerHTML = '';
    state.pageSets.forEach(ps => {
        const btn = document.createElement('button');
        btn.className = `px-3 py-1 rounded text-sm font-medium transition-colors ${
            ps.id === state.activeId
                ? 'bg-white text-black'
                : 'text-slate-400 hover:text-white hover:bg-slate-900'
        }`;
        btn.textContent = ps.title;
        btn.addEventListener('click', () => switchSet(ps.id));
        container.appendChild(btn);
    });

    // Update view link
    const active = state.pageSets.find(p => p.id === state.activeId);
    const viewLink = document.getElementById('view-link');
    if (active) viewLink.href = `index.php?set=${encodeURIComponent(active.slug)}`;
}

async function switchSet(id) {
    state.activeId = id;
    renderTabs();
    await loadPageSet(id);
}

// ── Page set manager ──────────────────────────────────────────────────────────
function toggleSetManager() {
    const el = document.getElementById('set-manager');
    el.classList.toggle('hidden');
}

function renderSetManager() {
    const list = document.getElementById('set-list');
    list.innerHTML = '';
    state.pageSets.forEach(ps => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 py-1 group';
        row.dataset.id = ps.id;

        const handle = document.createElement('span');
        handle.className = 'drag-handle text-slate-600 hover:text-slate-400 text-sm select-none';
        handle.textContent = '⠿';

        const title = document.createElement('span');
        title.className = 'flex-1 text-sm text-slate-300';
        title.contentEditable = 'true';
        title.textContent = ps.title;
        title.addEventListener('blur', async () => {
            const newTitle = title.textContent.trim();
            if (newTitle && newTitle !== ps.title) {
                await POST('page_sets.update', { id: ps.id, title: newTitle });
                ps.title = newTitle;
                renderTabs();
            } else {
                title.textContent = ps.title;
            }
        });
        title.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); title.blur(); } });

        const slugBadge = document.createElement('span');
        slugBadge.className = 'text-xs text-slate-500 font-mono';
        slugBadge.textContent = ps.slug;

        const delBtn = document.createElement('button');
        delBtn.className = 'del-btn opacity-0 group-hover:opacity-100 text-slate-600 hover:text-red-400 text-sm px-1 transition-colors';
        delBtn.textContent = '✕';
        delBtn.addEventListener('click', async () => {
            if (!confirm(`Delete page set "${ps.title}"? All categories and bookmarks in it will be deleted.`)) return;
            await POST('page_sets.delete', { id: ps.id });
            state.pageSets = state.pageSets.filter(p => p.id !== ps.id);
            if (state.activeId === ps.id) {
                state.activeId = state.pageSets[0]?.id ?? null;
                if (state.activeId) await loadPageSet(state.activeId);
                else { state.categories = []; renderCategories(); }
            }
            renderTabs();
            renderSetManager();
        });

        row.append(handle, title, slugBadge, delBtn);
        list.appendChild(row);
    });

    // SortableJS for page set reorder
    if (list._sortable) list._sortable.destroy();
    list._sortable = Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: async () => {
            const ids = [...list.querySelectorAll('[data-id]')].map(el => parseInt(el.dataset.id));
            await POST('page_sets.reorder', { ids: JSON.stringify(ids) });
            ids.forEach((id, pos) => { const ps = state.pageSets.find(p => p.id === id); if (ps) ps.position = pos; });
            state.pageSets.sort((a, b) => a.position - b.position);
            renderTabs();
        }
    });
}

// ── New page set ──────────────────────────────────────────────────────────────
document.getElementById('new-title').addEventListener('input', function () {
    const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    document.getElementById('new-slug').value = slug;
});

async function createPageSet() {
    const title = document.getElementById('new-title').value.trim();
    const slug  = document.getElementById('new-slug').value.trim();
    if (!title || !slug) { alert('Title and slug are required.'); return; }
    if (!/^[a-z0-9_-]+$/.test(slug)) { alert('Slug must be lowercase letters, numbers, or hyphens.'); return; }
    try {
        const ps = await POST('page_sets.create', { title, slug });
        state.pageSets.push(ps);
        state.activeId = ps.id;
        state.categories = [];
        document.getElementById('new-set-dialog').close();
        document.getElementById('new-title').value = '';
        document.getElementById('new-slug').value = '';
        renderTabs();
        renderSetManager();
        renderCategories();
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

// ── Load page set ─────────────────────────────────────────────────────────────
async function loadPageSet(id) {
    state.categories = await GET('categories.list', { page_set_id: id });
    renderCategories();
}

// ── Render categories ─────────────────────────────────────────────────────────
function renderCategories() {
    const container = document.getElementById('category-list');
    container.innerHTML = '';
    state.categories.forEach(cat => container.appendChild(buildCategoryCard(cat)));
    initCategorySortable(container);
}

function buildCategoryCard(cat) {
    const card = el('div', 'bg-slate-800 border border-slate-700 rounded-lg');
    card.dataset.id = cat.id;

    // Header
    const header = el('div', 'flex items-center gap-2 px-3 py-2 border-b border-slate-700');

    const handle = el('span', 'drag-handle text-slate-600 hover:text-slate-400 text-sm select-none');
    handle.textContent = '⠿';

    const nameEl = el('div', 'flex-1 text-sm font-semibold text-white uppercase tracking-wide');
    nameEl.contentEditable = 'true';
    nameEl.textContent = cat.name;
    nameEl.addEventListener('blur', async () => {
        const newName = nameEl.textContent.trim();
        if (newName && newName !== cat.name) {
            await POST('categories.update', { id: cat.id, name: newName });
            cat.name = newName;
        } else {
            nameEl.textContent = cat.name;
        }
    });
    nameEl.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); nameEl.blur(); } });

    const delBtn = el('button', 'del-btn opacity-0 text-slate-600 hover:text-red-400 text-sm px-1 transition-colors');
    delBtn.textContent = '✕';
    delBtn.addEventListener('click', async () => {
        if (!confirm(`Delete category "${cat.name}"?`)) return;
        await POST('categories.delete', { id: cat.id });
        state.categories = state.categories.filter(c => c.id !== cat.id);
        card.remove();
    });
    header.addEventListener('mouseenter', () => delBtn.style.opacity = '1');
    header.addEventListener('mouseleave', () => delBtn.style.opacity = '0');

    header.append(handle, nameEl, delBtn);

    // Bookmarks
    const bmList = el('div', 'p-2 flex flex-col gap-1 bookmark-list');
    bmList.dataset.catId = cat.id;
    cat.bookmarks.forEach(bm => bmList.appendChild(buildBookmarkRow(bm, cat)));

    // Add link button
    const addBtn = el('button', 'w-full mt-1 border border-dashed border-slate-700 hover:border-slate-600 text-slate-500 hover:text-slate-400 rounded py-1 text-xs transition-colors');
    addBtn.textContent = '+ Add Link';
    addBtn.addEventListener('click', () => appendNewBookmarkRow(bmList, cat));

    const body = el('div', 'flex flex-col');
    body.append(bmList, el('div', 'px-2 pb-2'));
    body.lastChild.appendChild(addBtn);

    card.append(header, body);
    initBookmarkSortable(bmList);
    return card;
}

function buildBookmarkRow(bm, cat) {
    const row = el('div', 'link-row flex items-center gap-1.5 group/row');
    row.dataset.id = bm.id;

    const handle = el('span', 'drag-handle text-slate-600 hover:text-slate-400 text-xs select-none shrink-0');
    handle.textContent = '⠿';

    // Icon cell
    const iconCell = buildIconCell(bm);

    const labelInput = el('input', 'w-32 shrink-0 bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded px-2 py-1 focus:outline-none focus:border-slate-400 focus:bg-slate-800');
    labelInput.value = bm.label;
    labelInput.placeholder = 'Label';
    labelInput.addEventListener('change', async () => {
        await POST('bookmarks.update', { id: bm.id, label: labelInput.value });
        bm.label = labelInput.value;
    });

    const urlInput = el('input', 'flex-1 min-w-0 bg-slate-900 border border-slate-700 text-slate-500 text-xs rounded px-2 py-1 font-mono focus:outline-none focus:border-slate-400 focus:bg-slate-800');
    urlInput.value = bm.url;
    urlInput.placeholder = 'URL';
    urlInput.addEventListener('change', async () => {
        await POST('bookmarks.update', { id: bm.id, url: urlInput.value });
        bm.url = urlInput.value;
        if (!bm.icon) renderIconDisplay(iconCell.querySelector('button'), bm);
    });

    const delBtn = el('button', 'del-btn opacity-0 group-hover/row:opacity-100 text-slate-600 hover:text-red-400 text-xs px-1 shrink-0 transition-colors');
    delBtn.textContent = '✕';
    delBtn.addEventListener('click', async () => {
        await POST('bookmarks.delete', { id: bm.id });
        cat.bookmarks = cat.bookmarks.filter(b => b.id !== bm.id);
        row.remove();
    });

    row.append(handle, iconCell, labelInput, urlInput, delBtn);
    return row;
}

// ── Icon cell ─────────────────────────────────────────────────────────────────
function parseIcon(iconClass) {
    if (!iconClass) return { name: '', weight: 'regular' };
    const parts = iconClass.trim().split(/\s+/);
    const namePart = parts.find(p => p.startsWith('ph-') && !['ph-fill','ph-bold','ph-light','ph-thin','ph-duotone'].includes(p));
    const name = namePart ? namePart.slice(3) : '';
    const weight = parts.includes('ph-fill') ? 'fill' : parts.includes('ph-bold') ? 'bold' : 'regular';
    return { name, weight };
}

function buildIconClass(name, weight) {
    if (!name) return '';
    return (weight === 'regular' ? 'ph' : `ph-${weight}`) + ` ph-${name}`;
}

function renderIconDisplay(btn, bm) {
    btn.innerHTML = '';
    if (bm.icon) {
        const i = document.createElement('i');
        i.className = bm.icon + ' text-indigo-400 text-sm';
        btn.appendChild(i);
    } else {
        const img = document.createElement('img');
        img.src = faviconUrl(bm.url);
        img.width = 16; img.height = 16;
        img.className = 'opacity-60';
        img.onerror = () => { img.style.display = 'none'; };
        btn.appendChild(img);
    }
}

function buildIconCell(bm) {
    const wrap = el('div', 'relative shrink-0');
    const btn = el('button', 'w-6 h-6 flex items-center justify-center rounded hover:bg-slate-700 transition-colors');
    btn.title = 'Set icon';
    renderIconDisplay(btn, bm);

    let popover = null;

    function closePopover() {
        if (popover) { popover.remove(); popover = null; }
    }

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (popover) { closePopover(); return; }

        const { name: currentName, weight: currentWeight } = parseIcon(bm.icon);
        let selectedWeight = currentWeight;

        popover = el('div', 'absolute left-0 top-8 z-50 bg-slate-800 border border-slate-600 rounded-lg p-3 shadow-2xl w-56');

        // Preview
        const previewRow = el('div', 'flex items-center gap-2 mb-2');
        const preview = el('i', (bm.icon || '') + ' text-indigo-400 text-lg w-6 text-center');
        const previewLabel = el('span', 'text-xs text-slate-500 truncate');
        previewLabel.textContent = currentName || 'No icon set';
        previewRow.append(preview, previewLabel);

        // Name input
        const input = el('input', 'w-full bg-slate-900 border border-slate-700 text-slate-200 text-sm rounded px-2 py-1.5 font-mono focus:outline-none focus:border-slate-500 mb-2');
        input.placeholder = 'e.g. video-camera';
        input.value = currentName;

        // Weight buttons
        const weights = ['regular', 'fill', 'bold'];
        const weightRow = el('div', 'flex gap-1 mb-2');
        weights.forEach(w => {
            const wb = el('button', `flex-1 text-xs rounded py-1 transition-colors border ${w === selectedWeight ? 'bg-white text-black border-white' : 'bg-slate-950 border-slate-700 text-slate-400 hover:border-slate-500 hover:text-white'}`);
            wb.textContent = w;
            wb.dataset.weight = w;
            wb.addEventListener('click', () => {
                selectedWeight = w;
                weightRow.querySelectorAll('button').forEach(b => {
                    const active = b.dataset.weight === w;
                    b.className = `flex-1 text-xs rounded py-1 transition-colors border ${active ? 'bg-white text-black border-white' : 'bg-slate-950 border-slate-700 text-slate-400 hover:border-slate-500 hover:text-white'}`;
                });
                preview.className = buildIconClass(input.value.trim(), selectedWeight) + ' text-indigo-400 text-lg w-6 text-center';
            });
            weightRow.appendChild(wb);
        });

        input.addEventListener('input', () => {
            const cls = buildIconClass(input.value.trim(), selectedWeight);
            preview.className = cls + ' text-indigo-400 text-lg w-6 text-center';
            previewLabel.textContent = input.value.trim() || 'No icon set';
        });

        // Action buttons
        const actionRow = el('div', 'flex gap-1.5 mb-2');
        const setBtn = el('button', 'flex-1 bg-white text-black text-xs font-medium rounded px-2 py-1 hover:bg-slate-200 transition-colors');
        setBtn.textContent = 'Set';
        setBtn.addEventListener('click', async () => {
            const icon = buildIconClass(input.value.trim(), selectedWeight);
            await POST('bookmarks.update', { id: bm.id, icon });
            bm.icon = icon;
            renderIconDisplay(btn, bm);
            closePopover();
        });

        const clearBtn = el('button', 'px-2 py-1 text-xs rounded border border-slate-700 hover:border-slate-500 text-slate-400 hover:text-white transition-colors');
        clearBtn.textContent = 'Clear';
        clearBtn.addEventListener('click', async () => {
            await POST('bookmarks.update', { id: bm.id, icon: '' });
            bm.icon = '';
            renderIconDisplay(btn, bm);
            closePopover();
        });
        actionRow.append(setBtn, clearBtn);

        const link = el('a', 'block text-xs text-slate-500 hover:text-slate-400 transition-colors text-center');
        link.href = 'https://phosphoricons.com';
        link.target = '_blank';
        link.textContent = 'Browse icons ↗';

        popover.append(previewRow, input, weightRow, actionRow, link);
        wrap.appendChild(popover);
        setTimeout(() => input.focus(), 10);

        const outsideClick = (e) => {
            if (!wrap.contains(e.target)) { closePopover(); document.removeEventListener('click', outsideClick); }
        };
        document.addEventListener('click', outsideClick);

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closePopover(); document.removeEventListener('click', outsideClick); }
            if (e.key === 'Enter') setBtn.click();
        });
    });

    wrap.appendChild(btn);
    return wrap;
}

function appendNewBookmarkRow(bmList, cat) {
    const row = el('div', 'link-row flex items-center gap-1.5 new-row');

    const fav = el('div', 'w-4 h-4 shrink-0'); // placeholder space

    const labelInput = el('input', 'w-32 shrink-0 bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded px-2 py-1 focus:outline-none focus:border-slate-400 focus:bg-slate-800');
    labelInput.placeholder = 'Label';

    const urlInput = el('input', 'flex-1 min-w-0 bg-slate-900 border border-slate-700 text-slate-500 text-xs rounded px-2 py-1 font-mono focus:outline-none focus:border-slate-400 focus:bg-slate-800');
    urlInput.placeholder = 'URL';

    let saving = false;
    async function trySave() {
        if (saving) return;
        // Wait a tick so the other input's blur fires first
        await new Promise(r => setTimeout(r, 80));
        const label = labelInput.value.trim();
        const url   = urlInput.value.trim();
        if (!label && !url) { row.remove(); return; }
        if (!label || !url) return; // wait for both
        saving = true;
        try {
            const bm = await POST('bookmarks.create', { category_id: cat.id, label, url });
            cat.bookmarks.push(bm);
            row.replaceWith(buildBookmarkRow(bm, cat));
        } catch (e) {
            saving = false;
            alert('Error: ' + e.message);
        }
    }
    labelInput.addEventListener('blur', trySave);
    urlInput.addEventListener('blur', trySave);
    urlInput.addEventListener('keydown', e => { if (e.key === 'Enter') urlInput.blur(); });

    row.append(fav, labelInput, urlInput);
    bmList.appendChild(row);
    labelInput.focus();
}

// ── SortableJS ────────────────────────────────────────────────────────────────
function initCategorySortable(container) {
    if (container._sortable) container._sortable.destroy();
    container._sortable = Sortable.create(container, {
        handle: '.drag-handle',
        animation: 150,
        filter: '.bookmark-list',
        onEnd: async () => {
            const ids = [...container.querySelectorAll(':scope > [data-id]')].map(el => parseInt(el.dataset.id));
            await POST('categories.reorder', { page_set_id: state.activeId, ids: JSON.stringify(ids) });
            ids.forEach((id, pos) => { const c = state.categories.find(c => c.id === id); if (c) c.position = pos; });
            state.categories.sort((a, b) => a.position - b.position);
        }
    });
}

function initBookmarkSortable(bmList) {
    if (bmList._sortable) bmList._sortable.destroy();
    bmList._sortable = Sortable.create(bmList, {
        group: 'bookmarks',
        handle: '.drag-handle',
        animation: 150,
        filter: '.new-row',
        onEnd: async (evt) => {
            const fromCatId = parseInt(evt.from.dataset.catId);
            const toCatId   = parseInt(evt.to.dataset.catId);
            const bmId      = parseInt(evt.item.dataset.id);

            if (fromCatId !== toCatId) {
                await POST('bookmarks.move', { id: bmId, target_category_id: toCatId });
                // Update local state
                const fromCat = state.categories.find(c => c.id === fromCatId);
                const toCat   = state.categories.find(c => c.id === toCatId);
                if (fromCat && toCat) {
                    const bm = fromCat.bookmarks.find(b => b.id === bmId);
                    if (bm) {
                        fromCat.bookmarks = fromCat.bookmarks.filter(b => b.id !== bmId);
                        toCat.bookmarks.push(bm);
                    }
                }
            }

            // Reorder within target category
            const ids = [...evt.to.querySelectorAll(':scope > [data-id]')].map(el => parseInt(el.dataset.id));
            await POST('bookmarks.reorder', { category_id: toCatId, ids: JSON.stringify(ids) });
            const cat = state.categories.find(c => c.id === toCatId);
            if (cat) ids.forEach((id, pos) => { const b = cat.bookmarks.find(b => b.id === id); if (b) b.position = pos; });
        }
    });
}

// ── Add category ──────────────────────────────────────────────────────────────
async function addCategory() {
    if (!state.activeId) return;
    const cat = await POST('categories.create', { page_set_id: state.activeId, name: 'New Category' });
    cat.bookmarks = [];
    state.categories.push(cat);
    const container = document.getElementById('category-list');
    const card = buildCategoryCard(cat);
    container.appendChild(card);
    initCategorySortable(container);
    // Focus the name for immediate editing
    card.querySelector('[contenteditable]').focus();
    const range = document.createRange();
    range.selectNodeContents(card.querySelector('[contenteditable]'));
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
}

// ── Utils ─────────────────────────────────────────────────────────────────────
function el(tag, cls) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    return e;
}

// ── Boot ──────────────────────────────────────────────────────────────────────
init().catch(e => console.error('Init failed:', e));
</script>
</body>
</html>
