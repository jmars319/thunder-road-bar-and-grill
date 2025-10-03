// Admin UI JS (extracted from admin/index.php)
/**
 * assets/js/admin.js
 * Client-side admin UI helpers used by the PHP admin pages. This
 * script runs inside a trusted admin session in the browser and
 * performs no critical security checks itself (CSRF and auth are
 * enforced server-side). Keep this file small and defensive:
 *
 * Responsibilities:
 *  - Render schema-driven form editors and the menu editor UI
 *  - Provide image picking, upload helpers, and trash/restore flows
 *  - Perform client-side validation to improve UX, but rely on
 *    server-side validation for authoritative checks.
 */

(function(){
  // Toast / modal helpers
  function showToast(msg, type='default', timeout=3500){
    const c = document.getElementById('toast-container');
    if (!c) return;
    const el = document.createElement('div'); el.className='toast '+(type==='success'? 'success': type==='error' ? 'error':''); el.textContent=msg;
    c.appendChild(el);
    setTimeout(()=>{ el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(()=>el.remove(),350) }, timeout);
  }
  function showConfirm(message){
    return new Promise((resolve)=>{
      const backdrop = document.getElementById('modal-backdrop');
      const body = document.getElementById('modal-body');
      const ok = document.getElementById('modal-ok');
      const cancel = document.getElementById('modal-cancel');
      const closeBtn = document.getElementById('modal-close');
      if (!backdrop || !body || !ok || !cancel) return resolve(false);
      // compose modal body
      body.innerHTML = '';
      const p = document.createElement('div'); p.textContent = message; body.appendChild(p);
      // remember previous focus so we can restore it
      const previouslyFocused = document.activeElement;
      backdrop.style.display = 'flex';

      // focus first focusable element in the modal
      const focusable = backdrop.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable && focusable.length) focusable[0].focus();

      function cleanup(){
        backdrop.style.display='none';
        ok.removeEventListener('click', onOk);
        cancel.removeEventListener('click', onCancel);
        if (closeBtn) closeBtn.removeEventListener('click', onClose);
        document.removeEventListener('keydown', onKey);
        // restore previous focus
        try { if (previouslyFocused && previouslyFocused.focus) previouslyFocused.focus(); } catch (e) {}
      }
      function onOk(){ cleanup(); resolve(true); }
      function onCancel(){ cleanup(); resolve(false); }
      function onClose(){ cleanup(); resolve(false); }

      function onKey(e){
        if (e.key === 'Escape') { onClose(); }
        // simple tab trap
        if (e.key === 'Tab') {
          const nodes = Array.prototype.slice.call(focusable || []);
          if (!nodes.length) return;
          const idx = nodes.indexOf(document.activeElement);
          if (e.shiftKey) {
            if (idx === 0) { nodes[nodes.length-1].focus(); e.preventDefault(); }
          } else {
            if (idx === nodes.length - 1) { nodes[0].focus(); e.preventDefault(); }
          }
        }
      }

      ok.addEventListener('click', onOk);
      cancel.addEventListener('click', onCancel);
      if (closeBtn) closeBtn.addEventListener('click', onClose);
      document.addEventListener('keydown', onKey);
    });
  }

  // expose a global helper so other inline/admin scripts can use the same modal
  try { window.showAdminConfirm = showConfirm; window.showAdminToast = showToast; } catch(e){}

  // Main
  const sectionSelect = document.getElementById('section-select');
  const schemaFields = document.getElementById('schema-fields');
  const saveForm = document.getElementById('schema-form');
  const uploadForm = document.getElementById('upload-form');
  const uploadResult = document.getElementById('upload-result');

  const siteContent = window.__siteContent || {};
  const schemaUrl = window.__schemaUrl || 'content-schemas.json';

  let schemas = {};

  function fetchSchemas(){
    return fetch(schemaUrl).then(r=>{ if (!r.ok) throw new Error('Failed to load schemas'); return r.json(); }).catch(()=>({}));
  }

  function makeOption(val, label){ const o = document.createElement('option'); o.value=val; o.textContent=label||val; return o; }

  function renderField(field, value){
    const wrap = document.createElement('div'); wrap.style.marginBottom='.6rem';
    const label = document.createElement('label'); label.style.display='block'; label.style.fontWeight='600'; label.textContent = field.label || field.key;
    wrap.appendChild(label);
    let input;
    if (field.type === 'textarea'){
      input = document.createElement('textarea'); input.style.width='100%'; input.style.minHeight='80px';
      input.name = field.key;
      input.value = Array.isArray(value) ? value.join('\n') : (value || '');
    } else if (field.type === 'image'){
      const row = document.createElement('div'); row.style.display='flex'; row.style.gap='0.5rem';
      input = document.createElement('input'); input.type='text'; input.name = field.key; input.style.flex='1'; input.value = value || '';
      input.setAttribute('data-field-type','image');
      const pick = document.createElement('button'); pick.type='button'; pick.textContent='Pick'; pick.addEventListener('click', ()=> openImagePicker(input));
      row.appendChild(input); row.appendChild(pick); wrap.appendChild(row);
      return wrap;
    } else {
      input = document.createElement('input'); input.type='text'; input.name = field.key; input.style.width='100%'; input.value = value || '';
    }
    wrap.appendChild(input);
    return wrap;
  }

  function openImagePicker(targetInput){
    const backdrop = document.getElementById('modal-backdrop');
    const body = document.getElementById('modal-body');
    if (!backdrop || !body) return;
    body.innerHTML = '<div style="max-height:320px;overflow:auto;display:flex;flex-wrap:wrap;gap:.5rem"></div>';
    const grid = body.firstChild;
  backdrop.style.display='flex';
  // NOTE: list-images.php returns a JSON list of filenames in
  // uploads/images. This UI trusts that the admin session is
  // authenticated; the server-side endpoint enforces auth and CSRF
  // where appropriate. The picker only displays filenames — the
  // chosen value is written into the text input for later saving.
  fetch('list-images.php').then(r=>r.json()).then(j=>{
      if (!j || !Array.isArray(j.files)) { grid.innerHTML = '<i>No images</i>'; return; }
        j.files.forEach(f=>{
        const thumb = document.createElement('div'); thumb.style.width='120px'; thumb.style.cursor='pointer'; thumb.style.textAlign='center';
        const img = document.createElement('img'); img.src = '../uploads/images/'+f; img.style.width='100%'; img.style.height='80px'; img.style.objectFit='cover';
        const lab = document.createElement('div'); lab.textContent = f; lab.style.fontSize='0.8rem'; lab.style.overflow='hidden'; lab.style.textOverflow='ellipsis'; lab.style.whiteSpace='nowrap';
        thumb.appendChild(img); thumb.appendChild(lab);
  thumb.addEventListener('click', ()=>{ targetInput.value = f; targetInput.dispatchEvent(new Event('input', { bubbles: true })); backdrop.style.display='none'; });
        grid.appendChild(thumb);
      });
    }).catch(()=>{ grid.innerHTML = '<i>Failed to load</i>'; });
    const ok = document.getElementById('modal-ok'); const cancel = document.getElementById('modal-cancel');
    function cleanup(){ backdrop.style.display='none'; ok.removeEventListener('click',onOk); cancel.removeEventListener('click',onCancel); }
    function onOk(){ cleanup(); }
    function onCancel(){ cleanup(); }
    ok.addEventListener('click', onOk); cancel.addEventListener('click', onCancel);
  }

  function renderSection(sec){
    if (!schemaFields) return;
    schemaFields.innerHTML = '';
    const schema = schemas[sec];
    const data = siteContent[sec] || {};
    if (!schema) {
      const ta = document.createElement('textarea'); ta.style.width='100%'; ta.style.height='200px'; ta.value = JSON.stringify(data, null, 2);
      const hint = document.createElement('div'); hint.textContent = 'No schema for this section — edit raw JSON below.';
      schemaFields.appendChild(hint); schemaFields.appendChild(ta);
      return;
    }
    schema.fields.forEach(f=>{
      const val = (data[f.key] !== undefined) ? data[f.key] : '';
      const fld = renderField(f, val);
      schemaFields.appendChild(fld);
    });
  }

  function populateSections(){
    if (!sectionSelect) return;
    sectionSelect.innerHTML = '';
  // Keep separate management areas out of the Site Content Editor dropdown.
  // Exclude keys that have dedicated admin sections such as 'images' and 'menu'.
  const allKeys = [...Object.keys(schemas), ...Object.keys(siteContent)];
  const filtered = allKeys.filter(k => k !== 'images' && k !== 'menu');
    const keys = new Set(filtered);
    keys.forEach(k=> sectionSelect.appendChild(makeOption(k, (schemas[k] && schemas[k].label) ? schemas[k].label + ' ('+k+')' : k)));
  }

  fetchSchemas().then(s=>{ schemas = s || {}; populateSections(); if (sectionSelect){ sectionSelect.addEventListener('change', ()=> renderSection(sectionSelect.value));
      if (sectionSelect.options.length) sectionSelect.selectedIndex = 0; renderSection(sectionSelect.value); }
  });

  if (saveForm) {
    saveForm.addEventListener('submit', function(e){
      e.preventDefault();
      const sec = sectionSelect.value;
      const inputs = schemaFields.querySelectorAll('[name]');
      const out = {};
      inputs.forEach(inp=>{
        const name = inp.name;
        const val = inp.value;
        if (inp.tagName.toLowerCase() === 'textarea' && (name === 'items' || name === 'hours')) {
          out[name] = val.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
        } else {
          out[name] = val;
        }
      });
      const csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || window.__csrfToken || '';
      const body = { section: sec, content: out, csrf_token: csrf };
      fetch('save-content.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(r=>r.json()).then(j=>{
          if (j && j.success) { showToast('Saved', 'success'); window.__siteContent[sec] = out; }
          else showToast('Save failed: '+(j.message||'unknown'),'error');
        }).catch(err=> showToast('Save error: '+err.message,'error'));
    });
  }

  if (uploadForm) {
    uploadForm.addEventListener('submit', function(e){
      e.preventDefault();
      const fd = new FormData(uploadForm);
      fetch('upload-image.php', { method: 'POST', body: fd }).then(r=>r.json()).then(j=>{
        if (j && j.success) {
          uploadResult.textContent = 'Uploaded: ' + j.filename;
          try {
            const sec = sectionSelect.value;
            const upType = fd.get('type');
            if (upType === sec) {
              const imgInput = schemaFields.querySelector('input[data-field-type="image"]');
              if (imgInput) { imgInput.value = j.filename; }
            }
            refreshImageList();
          } catch(e){}
        } else {
          uploadResult.textContent = 'Upload failed: ' + (j.message || 'unknown');
        }
      }).catch(err=>{ uploadResult.textContent = 'Upload error: '+err.message; });
    });
  }

  function refreshImageList() {
    const list = document.getElementById('image-list');
    if (!list) return;
    fetch('list-images.php').then(r=>r.json()).then(j=>{
      if (!j || !Array.isArray(j.files)) { list.innerHTML = '<i>No images</i>'; return; }
      list.innerHTML = '';
      j.files.forEach(f=>{
        const row = document.createElement('div');
        row.style.display='flex'; row.style.alignItems='center'; row.style.gap='1rem'; row.style.marginBottom='.5rem';
        const img = document.createElement('img'); img.src = '../uploads/images/'+f; img.style.height='48px'; img.style.objectFit='cover';
        const name = document.createElement('div'); name.textContent = f; name.style.flex='1';
  const del = document.createElement('button'); del.type='button'; del.textContent='Delete'; del.addEventListener('click', async ()=>{
          if (!await showConfirm('Delete '+f+'?')) return;
          const fd = new FormData(); fd.append('filename', f); fd.append('csrf_token', (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '');
          fetch('delete-image.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
            if (res && res.success) { refreshImageList(); showToast('Moved to trash','success'); }
            else showToast('Delete failed','error');
          });
        });
  const restoreBtn = document.createElement('button'); restoreBtn.type='button'; restoreBtn.textContent='Trash'; restoreBtn.disabled=true; restoreBtn.title='In images list';
        row.appendChild(img); row.appendChild(name); row.appendChild(del); row.appendChild(restoreBtn);
        list.appendChild(row);
      });
    }).catch(()=>{ list.innerHTML = '<i>Failed to list images</i>'; });
  }

  // Load trashed images into a separate view
  async function refreshTrashList() {
    const list = document.getElementById('image-list');
    if (!list) return;
    try {
      const r = await fetch('list-trash.php');
      const j = await r.json();
      if (!j || !Array.isArray(j.items)) { list.innerHTML = '<i>No trash</i>'; return; }
      list.innerHTML = '';
      j.items.forEach(it=>{
        const row = document.createElement('div');
        row.style.display='flex'; row.style.alignItems='center'; row.style.gap='1rem'; row.style.marginBottom='.5rem';
        const img = document.createElement('img'); img.src = '../uploads/trash/'+it.trash_name; img.style.height='48px'; img.style.objectFit='cover';
        const name = document.createElement('div'); name.textContent = it.meta && it.meta.original ? it.meta.original : it.trash_name; name.style.flex='1';
  const restore = document.createElement('button'); restore.type='button'; restore.textContent='Restore'; restore.addEventListener('click', async ()=>{
          if (!await showConfirm('Restore '+name.textContent+'?')) return;
          const fd = new FormData(); fd.append('trash_name', it.trash_name); fd.append('csrf_token', (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '');
          fetch('restore-image.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
            if (res && res.success) { showToast('Restored','success'); refreshTrashList(); refreshImageList(); }
            else showToast('Restore failed','error');
          });
        });
        row.appendChild(img); row.appendChild(name); row.appendChild(restore);
        list.appendChild(row);
      });
    } catch(e) { list.innerHTML = '<i>Failed to load trash</i>'; }
  }

  // add a simple toggle to switch between Images and Trash
  function ensureTrashToggle() {
    const container = document.getElementById('schema-form-wrap') || document.body;
    if (!container) return;
    if (document.getElementById('img-toggle')) return;
    const t = document.createElement('div'); t.id='img-toggle'; t.style.marginTop='.5rem'; t.style.display='flex'; t.style.gap='.5rem';
  const showImgs = document.createElement('button'); showImgs.type='button'; showImgs.className='btn btn-ghost'; showImgs.textContent='Images'; showImgs.addEventListener('click', ()=>{ refreshImageList(); });
  const showTrash = document.createElement('button'); showTrash.type='button'; showTrash.className='btn btn-ghost'; showTrash.textContent='Trash'; showTrash.addEventListener('click', ()=>{ refreshTrashList(); });
    t.appendChild(showImgs); t.appendChild(showTrash);
    container.parentNode.insertBefore(t, container.nextSibling);
  }

  // initial image list area
  const uploadFormElem = uploadForm;
  if (uploadFormElem && uploadFormElem.parentNode) {
    const imageArea = document.createElement('div'); imageArea.id='image-list'; imageArea.style.marginTop='.5rem';
    uploadFormElem.parentNode.insertBefore(imageArea, uploadFormElem.nextSibling);
    ensureTrashToggle();
    refreshImageList();
  }

  // expose a couple helpers for debugging
  // and implement Menu Management editor in the admin UI
  window.adminHelpers = { refreshImageList };

  // Menu admin: render editable sections with items, add/delete/reorder, and save whole array
  function initMenuAdmin(){
    const container = document.getElementById('menu-admin');
    if (!container) return;
    let listEl = document.getElementById('menu-list');
    const addBtn = document.getElementById('add-menu-item');
    if (!listEl) {
      listEl = document.createElement('div'); listEl.id = 'menu-list'; container.appendChild(listEl);
    }

    // support array-of-sections or legacy flat array
    let menuData = [];
    const rawMenu = window.__siteContent && window.__siteContent.menu;
    if (Array.isArray(rawMenu) && rawMenu.length && rawMenu[0] && rawMenu[0].items !== undefined) {
      menuData = JSON.parse(JSON.stringify(rawMenu));
    } else if (Array.isArray(rawMenu)) {
      menuData = [{ title: 'Menu', id: 'menu', items: JSON.parse(JSON.stringify(rawMenu)) }];
    } else {
      // default sections (so admin sees the sections immediately)
      menuData = [
        { title: "Burgers & Sandwiches", id: 'burgers-sandwiches', items: [] },
        { title: "Wings & Tenders", id: 'wings-tenders', items: [] },
        { title: "Salads", id: 'salads', items: [] },
        { title: "Flatbread Pizza", id: 'flatbread-pizza', items: [] },
        { title: "Sides", id: 'sides', items: [] },
        { title: "Additions & Dressings", id: 'additions-dressings', items: [] },
        { title: "Hershey's Ice Cream", id: 'hersheys-ice-cream', items: [] },
        { title: "Current Ice Cream Flavors", id: 'current-ice-cream-flavors', items: [] }
      ];
    }

    function makeInput(value, placeholder){ const i = document.createElement('input'); i.type='text'; i.value = value || ''; i.placeholder = placeholder || ''; i.style.width='100%'; return i; }
    function makeTextarea(value, placeholder){ const t = document.createElement('textarea'); t.value = value || ''; t.placeholder = placeholder || ''; t.style.width='100%'; t.style.minHeight='60px'; return t; }

    // persist expanded sections in localStorage. Default: none expanded (all collapsed).
    const STORAGE_KEY = 'admin.menu.expandedSections';
    let expandedSections = new Set();
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) expandedSections = new Set(JSON.parse(stored));
    } catch (e) { expandedSections = new Set(); }

    function render(){
      listEl.innerHTML = '';
      if (!menuData.length) {
        const hint = document.createElement('div'); hint.textContent = 'No sections yet. Click "Add Section" to create one.'; hint.style.color='#666'; listEl.appendChild(hint);
      }

      // render sections
      menuData.forEach((section, sidx) => {
        // ensure each section has a stable id to track collapsed state across renders
        if (!section.id) {
          section.id = 'section-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
        }
        const secWrap = document.createElement('div'); secWrap.style.border='1px solid #e8e8e8'; secWrap.style.padding='.6rem'; secWrap.style.marginBottom='.8rem';
  const header = document.createElement('div'); header.className = 'menu-section-header'; header.style.display='flex'; header.style.justifyContent='space-between'; header.style.alignItems='center';
  const left = document.createElement('div'); left.style.flex='1';
  const titleIn = makeInput(section.title||'', 'Section title'); titleIn.addEventListener('input', ()=> menuData[sidx].title = titleIn.value);
  left.appendChild(titleIn);
        // section-level details: support multiple detail lines (array)
        if (!Array.isArray(section.details) && section.details !== undefined && section.details !== null) {
          // normalize string -> array
          section.details = typeof section.details === 'string' ? [section.details] : [];
        }
        if (!Array.isArray(section.details)) section.details = [];

        const detailsContainer = document.createElement('div'); detailsContainer.className = 'section-details-admin'; detailsContainer.style.marginTop = '.4rem';

        function renderDetailsAdmin() {
          detailsContainer.innerHTML = '';
          section.details.forEach((d, di) => {
            const row = document.createElement('div'); row.style.display='flex'; row.style.gap='.4rem'; row.style.marginBottom='.3rem';
            const ta = document.createElement('textarea'); ta.style.flex='1'; ta.style.minHeight='48px'; ta.value = d || ''; ta.placeholder = 'Detail for section (shown in expanded view)';
            ta.addEventListener('input', ()=> { menuData[sidx].details[di] = ta.value; });
            const rem = document.createElement('button'); rem.type='button'; rem.className='btn btn-ghost'; rem.textContent='Remove'; rem.addEventListener('click', async ()=>{ if (!await showConfirm('Remove this section detail?')) return; menuData[sidx].details.splice(di,1); render(); });
            row.appendChild(ta); row.appendChild(rem);
            detailsContainer.appendChild(row);
          });
          const add = document.createElement('button'); add.type='button'; add.className='btn btn-ghost'; add.textContent='Add section detail'; add.addEventListener('click', ()=>{ menuData[sidx].details.push(''); render(); });
          detailsContainer.appendChild(add);
        }
        renderDetailsAdmin();
        left.appendChild(detailsContainer);
        header.appendChild(left);

        const hdrControls = document.createElement('div'); hdrControls.style.display='flex'; hdrControls.style.gap='.4rem';
        // collapse/expand toggle using persisted expandedSections (localStorage)
        const sectionId = section.id || ('section-' + sidx);
        const toggleBtn = document.createElement('button'); toggleBtn.type='button'; toggleBtn.className='menu-toggle'; toggleBtn.title = 'Collapse / expand';
        toggleBtn.setAttribute('aria-expanded','false');
        // use a chevron SVG that rotates when expanded
        toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>';
        toggleBtn.addEventListener('click', ()=>{
          try {
            if (expandedSections.has(sectionId)) {
              expandedSections.delete(sectionId);
              itemsWrap.classList.remove('expanded');
              toggleBtn.setAttribute('aria-expanded','false');
            } else {
              expandedSections.add(sectionId);
              itemsWrap.classList.add('expanded');
              toggleBtn.setAttribute('aria-expanded','true');
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(expandedSections)));
          } catch (e) { /* ignore storage errors */ }
        });

  const addItemBtn = document.createElement('button'); addItemBtn.type='button'; addItemBtn.textContent='Add Item'; addItemBtn.className='btn btn-ghost'; addItemBtn.addEventListener('click', ()=>{ menuData[sidx].items.push({ title:'', short:'', description:'', image:'', price: '', quantities: [] }); render(); });
        const upS = document.createElement('button'); upS.type='button'; upS.textContent='↑'; upS.title='Move section up'; upS.className='btn btn-ghost'; upS.addEventListener('click', ()=>{ if (sidx<=0) return; [menuData[sidx-1], menuData[sidx]] = [menuData[sidx], menuData[sidx-1]]; render(); });
        const downS = document.createElement('button'); downS.type='button'; downS.textContent='↓'; downS.title='Move section down'; downS.className='btn btn-ghost'; downS.addEventListener('click', ()=>{ if (sidx>=menuData.length-1) return; [menuData[sidx+1], menuData[sidx]] = [menuData[sidx], menuData[sidx+1]]; render(); });
        const delS = document.createElement('button'); delS.type='button'; delS.textContent='Delete'; delS.className='btn btn-danger'; delS.addEventListener('click', async ()=>{ if (!await showConfirm('Delete this section and its items?')) return; menuData.splice(sidx,1); render(); });
        hdrControls.appendChild(toggleBtn); hdrControls.appendChild(addItemBtn); hdrControls.appendChild(upS); hdrControls.appendChild(downS); hdrControls.appendChild(delS);
        header.appendChild(hdrControls);
        secWrap.appendChild(header);

        // items list
  const itemsWrap = document.createElement('div'); itemsWrap.className = 'menu-section-items'; itemsWrap.style.marginTop='.6rem';
        const items = Array.isArray(section.items) ? section.items : [];
        if (!items.length) {
          const hint = document.createElement('div'); hint.textContent = 'No items — use "Add Item"'; hint.className='small'; itemsWrap.appendChild(hint);
        }
        items.forEach((it, idx) => {
          const row = document.createElement('div'); row.style.display='grid'; row.style.gridTemplateColumns='1fr 200px'; row.style.gap='.5rem'; row.style.marginTop='.5rem'; row.style.borderTop='1px dashed #eee'; row.style.paddingTop='.5rem';
          const leftCol = document.createElement('div');
            const titleIn = makeInput(it.title||'', 'e.g. Classic Cheeseburger'); titleIn.title = 'Item title shown on the menu'; titleIn.addEventListener('input', ()=> menuData[sidx].items[idx].title = titleIn.value);
            const shortIn = makeInput(it.short||'', 'e.g. With lettuce, tomato & pickles'); shortIn.title = 'Short subtitle or note shown under the title'; shortIn.style.marginTop='.3rem'; shortIn.addEventListener('input', ()=> menuData[sidx].items[idx].short = shortIn.value);
            const descIn = makeTextarea(it.description||'', 'Detailed description, ingredients, or notes'); descIn.title = 'Long description shown when the item is expanded'; descIn.style.marginTop='.3rem'; descIn.addEventListener('input', ()=> menuData[sidx].items[idx].description = descIn.value);
            leftCol.appendChild(titleIn); leftCol.appendChild(shortIn); leftCol.appendChild(descIn);

            // price is optional for certain sections (e.g., Current Ice Cream Flavors)
            const allowPrice = !(section && section.id === 'current-ice-cream-flavors');
            let priceIn = null;
            if (allowPrice) {
              priceIn = makeInput(it.price||'', 'e.g. 9.99'); priceIn.title = 'Price in dollars (no $). Example: 9.99'; priceIn.style.marginTop='.3rem'; priceIn.addEventListener('input', ()=> menuData[sidx].items[idx].price = priceIn.value);
              // insert price after short
              leftCol.insertBefore(priceIn, descIn);
            }

            // quantity options: allow multiple quantity choices per item for specific sections
            const allowQuantity = section && (section.id === 'wings-tenders' || section.id === 'sides');
            let qtyContainer = null;
            if (allowQuantity) {
              // Ensure backward compatibility: convert old single `quantity` value into `quantities` array
              if (!Array.isArray(it.quantities) && it.quantity !== undefined) {
                // preserve item-level price as default for the legacy quantity
                it.quantities = [ { label: '', value: it.quantity, price: it.price !== undefined ? it.price : '' } ];
              }
              if (!Array.isArray(it.quantities)) it.quantities = [];

              qtyContainer = document.createElement('div'); qtyContainer.className = 'qty-options'; qtyContainer.style.marginTop = '.3rem';

              function createOptionRow(opt, optIndex) {
                const row = document.createElement('div'); row.style.display='flex'; row.style.gap='.4rem'; row.style.alignItems='center'; row.style.marginTop='.3rem';
                const labelInput = makeInput(opt.label || '', 'Label (e.g. 6 pc, 12 pc)'); labelInput.style.flex='1'; labelInput.addEventListener('input', ()=> { menuData[sidx].items[idx].quantities[optIndex].label = labelInput.value; });

                // value input: numeric stepper for wings, text for sides
                let valueInput;
                if (section.id === 'wings-tenders') {
                  const raw = document.createElement('input'); raw.type='number'; raw.min='1'; raw.step='1'; raw.value = opt.value || ''; raw.placeholder='e.g. 6'; raw.style.width='84px';
                  const wrapper = document.createElement('div'); wrapper.className='stepper'; wrapper.style.display='inline-flex'; wrapper.style.alignItems='center';
                  const down = document.createElement('button'); down.type='button'; down.className='stepper-btn'; down.dataset.step='down'; down.setAttribute('aria-label','Decrease'); down.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg>';
                  const up = document.createElement('button'); up.type='button'; up.className='stepper-btn'; up.dataset.step='up'; up.setAttribute('aria-label','Increase'); up.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H13v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>';
                  wrapper.appendChild(down); wrapper.appendChild(raw); wrapper.appendChild(up);
                  valueInput = wrapper;
                  valueInput._input = raw;
                  // wire buttons
                  down.addEventListener('click', ()=>{ const inner=valueInput._input; inner.step = inner.step || 1; inner.value = Math.max(parseInt(inner.min||1,10), (parseInt(inner.value||0,10) - parseInt(inner.step,10))); inner.dispatchEvent(new Event('input',{bubbles:true})); });
                  up.addEventListener('click', ()=>{ const inner=valueInput._input; inner.step = inner.step || 1; inner.value = Math.max(parseInt(inner.min||1,10), (parseInt(inner.value||0,10) + parseInt(inner.step,10))); inner.dispatchEvent(new Event('input',{bubbles:true})); });
                  valueInput._input.addEventListener('input', function(){ menuData[sidx].items[idx].quantities[optIndex].value = this.value; });
                } else {
                  valueInput = makeInput(opt.value||'', 'e.g. single, half'); valueInput.style.width='120px'; valueInput.addEventListener('input', ()=> { menuData[sidx].items[idx].quantities[optIndex].value = valueInput.value; });
                }

                // price input for this quantity option
                const priceInput = makeInput(opt.price || '', 'Price (e.g. 6.00)');
                priceInput.style.width = '100px';
                priceInput.title = 'Price specific to this quantity option';
                if (section.id === 'wings-tenders') { priceInput.type = 'number'; priceInput.step = '0.01'; priceInput.min = '0'; }
                priceInput.addEventListener('input', ()=>{ menuData[sidx].items[idx].quantities[optIndex].price = priceInput.value; });

                const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.className='btn btn-ghost'; removeBtn.textContent='Remove'; removeBtn.addEventListener('click', async ()=>{
                  if (!await showConfirm('Remove this quantity option?')) return;
                  menuData[sidx].items[idx].quantities.splice(optIndex,1);
                  render();
                });

                row.appendChild(labelInput);
                const valueWrap = document.createElement('div'); valueWrap.style.display='flex'; valueWrap.style.alignItems='center'; valueWrap.appendChild(valueInput);
                row.appendChild(valueWrap);
                // price next to the value
                const priceWrap = document.createElement('div'); priceWrap.style.display='flex'; priceWrap.style.alignItems='center'; priceWrap.appendChild(priceInput);
                row.appendChild(priceWrap);
                row.appendChild(removeBtn);
                return row;
              }

              function renderQtyOptions() {
                qtyContainer.innerHTML = '';
                const opts = menuData[sidx].items[idx].quantities || [];
                if (!opts.length) {
                  const hint = document.createElement('div'); hint.className='small'; hint.textContent = 'No quantity options — add one below.'; qtyContainer.appendChild(hint);
                }
                opts.forEach((o, oi) => {
                  qtyContainer.appendChild(createOptionRow(o, oi));
                });
                const addBtn = document.createElement('button'); addBtn.type='button'; addBtn.className='btn btn-ghost'; addBtn.textContent = 'Add quantity option'; addBtn.addEventListener('click', ()=>{
                  menuData[sidx].items[idx].quantities.push({ label: '', value: '', price: '' }); render();
                });
                qtyContainer.appendChild(addBtn);
              }

              // initial render for quantity options
              renderQtyOptions();

              // place qtyContainer next to price or before description
              if (priceIn) { leftCol.insertBefore(qtyContainer, descIn); }
              else { leftCol.insertBefore(qtyContainer, descIn); }
            }

          const rightCol = document.createElement('div'); rightCol.style.display='flex'; rightCol.style.flexDirection='column'; rightCol.style.gap='.4rem';
          const imgIn = makeInput(it.image||'', 'filename.jpg or https://...'); imgIn.title = 'Image filename within uploads/images or a full URL'; imgIn.setAttribute('data-field-type','image');
          const pick = document.createElement('button'); pick.type='button'; pick.textContent='Pick'; pick.className='btn btn-ghost'; pick.addEventListener('click', ()=> openImagePicker(imgIn));
          const imgRow = document.createElement('div'); imgRow.style.display='flex'; imgRow.style.gap='.4rem'; imgRow.appendChild(imgIn); imgRow.appendChild(pick);
          const preview = document.createElement('img'); preview.style.width='100%'; preview.style.height='80px'; preview.style.objectFit='cover'; preview.style.marginTop='.4rem'; if (imgIn.value) preview.src = '../uploads/images/' + imgIn.value;
          imgIn.addEventListener('input', ()=>{ menuData[sidx].items[idx].image = imgIn.value; if (imgIn.value) preview.src = '../uploads/images/' + imgIn.value; else preview.removeAttribute('src'); renderPreview(); });
          // Also update preview when textual fields change
          titleIn.addEventListener('input', renderPreview); shortIn.addEventListener('input', renderPreview); if (priceIn) priceIn.addEventListener('input', renderPreview); descIn.addEventListener('input', renderPreview);

          const itemControls = document.createElement('div'); itemControls.style.display='flex'; itemControls.style.gap='.4rem';
          const up = document.createElement('button'); up.type='button'; up.textContent='↑'; up.title='Move up'; up.className='btn btn-ghost'; up.addEventListener('click', ()=>{ if (idx<=0) return; [menuData[sidx].items[idx-1], menuData[sidx].items[idx]] = [menuData[sidx].items[idx], menuData[sidx].items[idx-1]]; render(); });
          const down = document.createElement('button'); down.type='button'; down.textContent='↓'; down.title='Move down'; down.className='btn btn-ghost'; down.addEventListener('click', ()=>{ if (idx>=menuData[sidx].items.length-1) return; [menuData[sidx].items[idx+1], menuData[sidx].items[idx]] = [menuData[sidx].items[idx], menuData[sidx].items[idx+1]]; render(); });
          const del = document.createElement('button'); del.type='button'; del.textContent='Delete'; del.className='btn btn-danger'; del.addEventListener('click', async ()=>{ if (!await showConfirm('Delete this item?')) return; menuData[sidx].items.splice(idx,1); render(); });
          itemControls.appendChild(up); itemControls.appendChild(down); itemControls.appendChild(del);
          // ensure preview updates after delete
          del.addEventListener('click', ()=> setTimeout(renderPreview, 100));

          rightCol.appendChild(imgRow); rightCol.appendChild(preview); rightCol.appendChild(itemControls);

          row.appendChild(leftCol); row.appendChild(rightCol);
          itemsWrap.appendChild(row);
        });

        // honor persisted expanded state: default collapsed (not expanded)
        const isExpanded = expandedSections.has(sectionId);
        if (!isExpanded) {
          itemsWrap.classList.remove('expanded');
          toggleBtn.setAttribute('aria-expanded','false');
        } else {
          itemsWrap.classList.add('expanded');
          toggleBtn.setAttribute('aria-expanded','true');
        }

        secWrap.appendChild(itemsWrap);
  const footer = document.createElement('div'); footer.style.display='flex'; footer.style.justifyContent='flex-end'; footer.style.marginTop='.6rem';
  const saveSec = document.createElement('button'); saveSec.type='button'; saveSec.textContent='Save Sections'; saveSec.className='btn btn-primary'; saveSec.addEventListener('click', async ()=>{ await saveMenu(); renderPreview(); });
        footer.appendChild(saveSec); secWrap.appendChild(footer);

        listEl.appendChild(secWrap);
      });
      // update preview after rendering admin list
      renderPreview();
    }

    function renderPreview(){
      const area = document.getElementById('preview-area');
      if (!area) return;
      area.innerHTML = '';
      menuData.forEach((section)=>{
        const sec = document.createElement('div'); sec.className='preview-section';
        const title = document.createElement('div'); title.className='preview-title'; title.textContent = section.title || 'Section';
        sec.appendChild(title);
        const itemsWrap = document.createElement('div');
        // section-level details shown once for the whole section
        if (Array.isArray(section.details) && section.details.length) {
          const secDetails = document.createElement('div'); secDetails.className = 'preview-section-details'; secDetails.style.marginBottom = '.5rem';
          section.details.forEach(d=>{ const p = document.createElement('div'); p.textContent = d; secDetails.appendChild(p); });
          sec.appendChild(secDetails);
        }
        (section.items || []).forEach(it=>{
          const pi = document.createElement('div'); pi.className='preview-item';
          if (it.image) { const im = document.createElement('img'); im.src = '../uploads/images/'+it.image; pi.appendChild(im); }
          const meta = document.createElement('div'); meta.className='preview-meta';
          const t = document.createElement('div'); t.textContent = it.title || ''; t.style.fontWeight='700';
          const s = document.createElement('div'); s.className='small'; s.textContent = it.short || '';
          const p = document.createElement('div'); p.className='preview-price'; p.textContent = it.price ? ('$'+it.price) : '';
          // show quantity(s) in preview for certain sections
          const q = document.createElement('div'); q.className = 'preview-qty';
          if (Array.isArray(it.quantities) && it.quantities.length) {
            // join labels/values/prices for preview
            const parts = it.quantities.map(function(o){
              if (!o) return '';
              const label = o.label ? o.label : '';
              const val = (o.value !== undefined && o.value !== '') ? (typeof o.value === 'number' ? String(o.value) : o.value) : '';
              const price = (o.price !== undefined && o.price !== '') ? ('$' + String(o.price)) : '';
              const seg = [label, val].filter(Boolean).join(': ');
              return seg + (price ? (' — ' + price) : '');
            }).filter(Boolean);
            q.textContent = parts.length ? ('Qty: ' + parts.join(' | ')) : '';
          } else {
            q.textContent = (it.quantity !== undefined && it.quantity !== '') ? ('Qty: ' + it.quantity) : '';
          }
          const d = document.createElement('div'); d.style.marginTop='.25rem'; d.innerHTML = it.description ? it.description.replace(/\n/g,'<br>') : '';
          meta.appendChild(t); if (s.textContent) meta.appendChild(s); if (p.textContent) meta.appendChild(p); if (q.textContent) meta.appendChild(q); meta.appendChild(d);
          pi.appendChild(meta); itemsWrap.appendChild(pi);
        });
        sec.appendChild(itemsWrap); area.appendChild(sec);
      });
    }

    async function saveMenu(){
      const csrf = (document.querySelector('input[name="csrf_token"]')||{}).value || window.__csrfToken || '';
      try {
        // Validate and normalize prices client-side before sending
        for (let s = 0; s < menuData.length; s++) {
          const sec = menuData[s];
          const allowPrice = !(sec && sec.id === 'current-ice-cream-flavors');
          if (!Array.isArray(sec.items)) continue;
            // Client-side validation: wings-tenders quantities must be integer >= 1.
            // Note: this improves UX but the server (`save-content.php`) performs
            // authoritative validation and normalization. Do not rely on client-side
            // checks for security.
          const isWings = sec && sec.id === 'wings-tenders';
          for (let i = 0; i < sec.items.length; i++) {
            const it = sec.items[i];
            if (isWings) {
              // support new `quantities` array; if legacy `quantity` exists, convert
              if (!Array.isArray(it.quantities) && it.quantity !== undefined) {
                it.quantities = [ { label: '', value: it.quantity } ];
                delete it.quantity;
              }
              if (!Array.isArray(it.quantities) || !it.quantities.length) {
                showToast('At least one quantity option is required for Wings & Tenders item: ' + (it.title || ''), 'error'); return;
              }
              for (let qi = 0; qi < it.quantities.length; qi++) {
                const qv = it.quantities[qi] && it.quantities[qi].value !== undefined ? String(it.quantities[qi].value).trim() : '';
                if (qv === '') { showToast('Quantity value required for option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                const qn = Number(qv);
                if (!Number.isInteger(qn) || qn < 1) { showToast('Invalid quantity for option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                it.quantities[qi].value = parseInt(qn, 10);
                // validate per-option price
                const qpriceRaw = it.quantities[qi] && it.quantities[qi].price !== undefined ? String(it.quantities[qi].price).trim() : '';
                if (qpriceRaw === '') { showToast('Price required for quantity option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                const qnum = Number(String(qpriceRaw).replace(/[^0-9\.\-]/g, ''));
                if (!isFinite(qnum) || qnum < 0) { showToast('Invalid price for quantity option #' + (qi+1) + ' in: ' + (it.title || ''), 'error'); return; }
                it.quantities[qi].price = qnum.toFixed(2);
              }
            }
            if (!allowPrice) {
              // ensure no price is sent for this section
              delete it.price;
              continue;
            }
            if (it.price === undefined || it.price === null || String(it.price).trim() === '') { delete it.price; continue; }
            // normalize: allow numbers like 9.99 or 9 -> '9.00'
            const num = Number(String(it.price).replace(/[^0-9\.\-]/g, ''));
            if (!isFinite(num)) { showToast('Invalid price: ' + (it.title || ''), 'error'); return; }
            // round to 2 decimals
            it.price = num.toFixed(2).replace(/\.00$/, '.00').replace(/^(-?)0\./, '$1.');
          }
        }
        const body = { section: 'menu', content: menuData, csrf_token: csrf };
        const res = await fetch('save-content.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const j = await res.json();
        if (j && j.success) { window.__siteContent = window.__siteContent || {}; window.__siteContent.menu = JSON.parse(JSON.stringify(menuData)); showToast('Menu saved', 'success'); }
        else showToast('Save failed: '+(j && j.message ? j.message : 'unknown'),'error');
      } catch(err){ showToast('Save error: '+err.message,'error'); }
    }

    if (addBtn) addBtn.addEventListener('click', ()=>{ menuData.push({ title:'New Section', id:'section-'+Date.now(), items:[] }); render(); });
    render();
  }

  // initialize menu admin if present
  initMenuAdmin();
  // profile menu toggle
  const profileBtn = document.getElementById('profile-btn');
  if (profileBtn) {
    // ARIA and keyboard support
    const menu = document.getElementById('profile-menu');
    profileBtn.setAttribute('aria-haspopup', 'true');
    profileBtn.setAttribute('aria-expanded', 'false');
    profileBtn.setAttribute('role', 'button');
    profileBtn.tabIndex = 0;
    if (menu) {
      menu.setAttribute('role', 'menu');
      menu.tabIndex = -1;
    }

    function openMenu(){ if (!menu) return; menu.style.display='block'; profileBtn.setAttribute('aria-expanded','true'); menu.querySelectorAll('button,a').forEach(el=>el.tabIndex=0); menu.focus(); }
    function closeMenu(){ if (!menu) return; menu.style.display='none'; profileBtn.setAttribute('aria-expanded','false'); profileBtn.focus(); }

    profileBtn.addEventListener('click', (e)=>{ e.stopPropagation(); if (!menu) return; (menu.style.display === 'block') ? closeMenu() : openMenu(); });
    profileBtn.addEventListener('keydown', (e)=>{ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); profileBtn.click(); } if (e.key === 'ArrowDown') { e.preventDefault(); openMenu(); }});

    // click outside to close (only when menu is open) to avoid stealing focus when it's closed
    document.addEventListener('click', (e)=>{
      const m = document.getElementById('profile-menu');
      if (!m) return;
      // only act when menu is visible/open
      if (m.style.display !== 'block') return;
      if (!profileBtn.contains(e.target) && !m.contains(e.target)) closeMenu();
    });
    // close on escape
    document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') { const m = document.getElementById('profile-menu'); if (m && m.style.display === 'block') closeMenu(); }});
  }
})();
