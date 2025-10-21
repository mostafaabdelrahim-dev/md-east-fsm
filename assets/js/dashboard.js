(function(){
  function el(sel, ctx){ return (ctx||document).querySelector(sel); }
  function els(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }
  function fmtCurrency(amount){ try { return new Intl.NumberFormat(undefined,{style:'currency',currency: 'SAR'}).format(amount); } catch(e){ return amount; } }

  // Tabs
  els('.mdefsm-tabs .tab').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      els('.mdefsm-tabs .tab').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      const id = btn.dataset.tab;
      els('.mdefsm-tab').forEach(t=>t.classList.add('hidden'));
      el('#tab-'+id).classList.remove('hidden');
    })
  });

  // Fetch helpers
  async function apiGet(path){
    const r = await fetch(MDEFSM.rest + path, { headers: {'X-WP-Nonce': MDEFSM.nonce} });
    if(!r.ok) throw new Error('HTTP '+r.status);
    const ct = r.headers.get('content-type')||'';
    if(ct.includes('text/html')) return r.text();
    return r.json();
  }
  async function apiPost(path, body){
    const r = await fetch(MDEFSM.rest + path, {
      method: 'POST',
      headers: {'X-WP-Nonce': MDEFSM.nonce, 'Content-Type': 'application/json'},
      body: body ? JSON.stringify(body) : null
    });
    if(!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  }
  async function apiDelete(path){
    const r = await fetch(MDEFSM.rest + path, {
      method: 'DELETE',
      headers: {'X-WP-Nonce': MDEFSM.nonce}
    });
    if(!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
  }

  // Load orders
  async function loadOrders(){
    const rows = await apiGet('orders');
    const tbody = el('#ordersTable tbody');
    tbody.innerHTML='';
    let todaySum = 0, todayCount = 0, pending = 0;
    const todayStr = new Date().toISOString().slice(0,10);
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td><a href="#" data-oid="${r.id}" class="order-open">#${r.number}</a></td>
        <td>${r.customer||''}</td>
        <td>${r.status}</td>
        <td>${r.date}</td>
        <td>${r.total} ${r.currency}</td>
        <td class="action-row">
          <button class="btn small" data-act="send" data-id="${r.id}">ارسال فاتوره</button>
          <button class="btn small" data-act="del" data-id="${r.id}">مسح</button>
        </td>`;
      tbody.appendChild(tr);
      if((r.date||'').startsWith(todayStr)){ todaySum+=Number(r.total); todayCount++; }
      if(['pending','on-hold','processing'].includes(r.status)) pending++;
    });
    el('#todayRevenue').textContent = fmtCurrency(todaySum);
    el('#ordersToday').textContent = String(todayCount);
    el('#ordersPending').textContent = String(pending);
  }

  // Order modal and actions
  document.addEventListener('click', async (e)=>{
    const open = e.target.closest('.order-open');
    if(open){
      e.preventDefault();
      const id = open.dataset.oid;
      const data = await apiGet('order/'+id);
      el('#invNumber').textContent = '#'+data.number;
      el('#invDate').textContent = data.date;
      el('#invStatus').textContent = data.status;
      el('#invPayment').textContent = data.payment;
      el('#invTotal').textContent = data.total + ' ' + data.currency;
      const tb = el('#invItems tbody'); tb.innerHTML='';
      (data.items||[]).forEach(it=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${it.sku||''}</td><td>${it.name}</td><td>${it.qty}</td><td>${it.total}</td>`;
        tb.appendChild(tr);
      });
      el('#invBilling').innerHTML = `${data.billing.name}<br>${data.billing.email} — ${data.billing.phone}<br>${data.billing.address||''}`;
      el('#orderModal').classList.remove('hidden');
    }

    const actionBtn = e.target.closest('button[data-act]');
    if(actionBtn){
      const id = actionBtn.dataset.id;
      const act = actionBtn.dataset.act;
      if(act==='del'){
        if(!confirm('Delete order #'+id+' ?')) return;
        await apiPost('order/'+id+'/delete',{});
        await loadOrders();
      }
      if(act==='send'){
        actionBtn.disabled = true; actionBtn.textContent = 'جارٍ الإرسال...';
        try{
          await apiPost('order/'+id+'/send-invoice',{});
          actionBtn.textContent = 'تم الإرسال ✅';
        }catch(err){ actionBtn.textContent = 'فشل الإرسال'; }
        setTimeout(()=>{ actionBtn.disabled=false; actionBtn.textContent='ارسال فاتوره'; }, 2000);
      }
    }
  });

  el('#closeModal')?.addEventListener('click', ()=> el('#orderModal').classList.add('hidden') );
  el('#printInvoice')?.addEventListener('click', ()=>{
    const html = el('#orderModalContent').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`<html><head><title>Invoice</title>
      <style>body{font-family:Tahoma,Arial} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ccc;padding:6px} th{background:#f3f3f3} .summary{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}</style>
      </head><body>${html}</body></html>`);
    w.document.close(); w.focus(); w.print();
  });

  el('#mdefsm-refresh')?.addEventListener('click', ()=>{ loadOrders(); loadWholesale(); loadChart(); loadProducts(); });

  // Wholesale list
  async function loadWholesale(){
    const rows = await apiGet('wholesale');
    const tbody = el('#whTable tbody'); tbody.innerHTML='';
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.id}</td><td>${r.date}</td><td>${r.name}</td>
        <td>${r.phone||''}</td><td>${r.email||''}</td><td>${r.company||''}</td>
        <td>${r.product||''}</td><td>${r.qty||''}</td>
        <td><button class="btn small" data-delwh="${r.id}">مسح</button></td>`;
      tbody.appendChild(tr);
    });
  }
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-delwh]');
    if(btn){
      if(!confirm('Delete wholesale request?')) return;
      await apiPost('wholesale/'+btn.dataset.delwh+'/delete',{});
      await loadWholesale();
    }
  });

  // Chart
  let chart;
  async function loadChart(){
    const data = await apiGet('stats/last7');
    const ctx = el('#sales7days');
    if(chart) chart.destroy();
    chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.labels,
        datasets: [{ label: 'Sales (7 days)', data: data.values, tension: 0.3, borderWidth: 2, fill: false }]
      },
      options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
  }

  // === Products ===
  async function loadProducts(){
    const rows = await apiGet('products');
    const tbody = el('#prodTable tbody'); tbody.innerHTML='';
    rows.forEach(p=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${p.id}</td><td>${p.name}</td><td>${p.sku||''}</td><td>${p.price||''}</td><td>${p.stock??''}</td>
        <td><button class="btn small" data-editp="${p.id}">تعديل</button> <button class="btn small" data-delp="${p.id}">حذف</button></td>`;
      tbody.appendChild(tr);
    });
  }

  // FRONTEND IMAGES PANEL — list + delete
  async function loadImages(productId){
    const data = await apiGet(`products/${productId}/images`);
    renderImages(productId, data);
  }
  function renderImages(productId, data){
    const wrap = el('#mdefsm-images');
    if(!wrap) return;
    const out = [];

    out.push('<div style="margin-bottom:8px"><strong>الصورة الرئيسية</strong></div>');
    if (data.featured){
      out.push(`
        <div class="mdefsm-img">
          <img src="${data.featured.url}" style="max-width:100%;height:auto;display:block;margin-bottom:6px"/>
          <div>
            <button data-att="${data.featured.id}" data-p="${productId}" data-hard="0" class="button">إزالة من المنتج</button>
            <button data-att="${data.featured.id}" data-p="${productId}" data-hard="1" class="button button-link-delete">حذف نهائي</button>
          </div>
        </div>`);
    } else {
      out.push('<div style="color:#777;margin-bottom:10px">لا توجد صورة رئيسية</div>');
    }

    out.push('<div style="margin:12px 0 8px"><strong>معرض الصور</strong></div>');
    if (Array.isArray(data.gallery) && data.gallery.length){
      data.gallery.forEach(g=>{
        out.push(`
          <div class="mdefsm-img" style="margin-bottom:12px">
            <img src="${g.url}" style="max-width:100%;height:auto;display:block;margin-bottom:6px"/>
            <div>
              <button data-att="${g.id}" data-p="${productId}" data-hard="0" class="button">إزالة من المنتج</button>
              <button data-att="${g.id}" data-p="${productId}" data-hard="1" class="button button-link-delete">حذف نهائي</button>
            </div>
          </div>`);
      });
    } else {
      out.push('<div style="color:#777">لا توجد صور في المعرض</div>');
    }

    wrap.innerHTML = out.join('');
  }

  // Buttons inside images panel
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('button[data-att][data-p]');
    if(!btn) return;
    const att  = btn.getAttribute('data-att');
    const pid  = btn.getAttribute('data-p');
    const hard = btn.getAttribute('data-hard') === '1';
    if (hard && !confirm('هل أنت متأكد من الحذف النهائي للصورة؟')) return;

    btn.disabled = true;
    try{
      await apiDelete(`media/${att}?product=${encodeURIComponent(pid)}&hard=${hard?1:0}`);
      await loadImages(pid);
    }catch(err){
      alert('فشل الحذف، حاول مرة أخرى.');
    }finally{
      btn.disabled = false;
    }
  });

  // Edit / Delete product events
  document.addEventListener('click', async (e)=>{
    const ed = e.target.closest('button[data-editp]');
    const del = e.target.closest('button[data-delp]');
    if(ed){
      const p = await apiGet('product/'+ed.dataset.editp);
      el('#p_id').value = p.id;
      el('#p_name').value = p.name||'';
      el('#p_regular_price').value = p.regular_price||'';
      el('#p_sale_price').value = p.sale_price||'';
      el('#p_sku').value = p.sku||'';
      el('#p_stock').value = p.stock_quantity||'';
      el('#p_status').value = p.status||'publish';
      el('#p_short').value = p.short_description||'';
      el('#p_desc').value = p.description||'';

      // Clear upload previews (these are for new uploads)
      el('#p_featured').value = p.featured_image||'';
      el('#p_featured_preview').innerHTML = '';
      el('#p_gallery_preview').innerHTML = '';
      el('#p_gallery_preview').removeAttribute('data-ids');

      // Load existing images into the management panel
      if (el('#mdefsm-images')) {
        el('#mdefsm-images').setAttribute('data-current-product', String(p.id));
        await loadImages(p.id);
      }
    }
    if(del){
      if(!confirm('Delete product?')) return;
      await apiPost('product/'+del.dataset.delp+'/delete',{});
      await loadProducts();
    }
  });

  // Upload helper
  async function uploadFile(file){
    const fd = new FormData();
    fd.append('file', file);
    const r = await fetch(MDEFSM.rest + 'upload', { method: 'POST', headers: {'X-WP-Nonce': MDEFSM.nonce}, body: fd });
    if(!r.ok) throw new Error('upload failed');
    return r.json();
  }
  el('#p_featured_file')?.addEventListener('change', async (e)=>{
    const file = e.target.files[0];
    if(!file) return;
    const data = await uploadFile(file);
    el('#p_featured').value = data.id;
    el('#p_featured_preview').innerHTML = `<img src="${data.url}" />`;
  });
  el('#p_gallery_files')?.addEventListener('change', async (e)=>{
    const out = [];
    for(const file of e.target.files){
      const data = await uploadFile(file);
      out.push(data.id);
      const img = document.createElement('img');
      img.src = data.url; el('#p_gallery_preview').appendChild(img);
    }
    el('#p_gallery_preview').dataset.ids = JSON.stringify(out);
  });

  el('#productForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const gallery = JSON.parse(el('#p_gallery_preview').dataset.ids||'[]');
    const body = {
      id: el('#p_id').value||undefined,
      name: el('#p_name').value,
      regular_price: el('#p_regular_price').value,
      sale_price: el('#p_sale_price').value,
      sku: el('#p_sku').value,
      stock_quantity: el('#p_stock').value,
      status: el('#p_status').value,
      short_description: el('#p_short').value,
      description: el('#p_desc').value,
      featured_image: el('#p_featured').value||undefined,
      gallery: gallery
    };
    const res = await apiPost('product', body);
    alert('Saved product #'+res.id);
    el('#p_id').value = '';
    e.target.reset();
    el('#p_featured_preview').innerHTML='';
    el('#p_gallery_preview').innerHTML=''; el('#p_gallery_preview').removeAttribute('data-ids');

    // Refresh lists and clear images panel
    await loadProducts();
    if (el('#mdefsm-images')) { el('#mdefsm-images').innerHTML = ''; el('#mdefsm-images').setAttribute('data-current-product','0'); }
  });
  el('#resetProduct')?.addEventListener('click', ()=>{
    el('#p_id').value=''; el('#productForm').reset(); el('#p_featured_preview').innerHTML=''; el('#p_gallery_preview').innerHTML=''; el('#p_gallery_preview').removeAttribute('data-ids');
    if (el('#mdefsm-images')) { el('#mdefsm-images').innerHTML=''; el('#mdefsm-images').setAttribute('data-current-product','0'); }
  });
  el('#newProduct')?.addEventListener('click', ()=>{
    el('#resetProduct').click();
  });

  // Public hook if needed elsewhere
  window.MDEFSM_UI_setProductId = async function(productId){
    if (productId && el('#mdefsm-images')) {
      el('#mdefsm-images').setAttribute('data-current-product', String(productId));
      await loadImages(productId);
    }
  };

  // Init
  loadOrders(); loadWholesale(); loadChart(); loadProducts();
})();
