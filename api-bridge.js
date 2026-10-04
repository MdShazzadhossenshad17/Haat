/**
 * HAAT! API Bridge v2
 * - Syncs app.js ↔ PHP REST API ↔ MySQL
 * - Fixes product image display (keeps Unsplash URLs from mock data)
 * - Adds auto-sliding carousel to Featured Products & Popular Stores
 */

(function () {
  'use strict';

  const BASE = '/HAAT!/api';

  /* ── HTTP helpers ────────────────────────────────────────────────────────── */
  async function api(ep, opts = {}) {
    try {
      const r = await fetch(BASE + ep, {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        ...opts,
      });
      const d = await r.json().catch(() => ({}));
      return { ok: r.ok, status: r.status, data: d };
    } catch { return { ok: false, status: 0, data: {} }; }
  }
  const apiGet  = ep => api(ep, { method: 'GET' });
  const apiPost = (ep, b) => api(ep, { method: 'POST', body: JSON.stringify(b) });

  /* ── Wait for app.js to finish initialising ─────────────────────────────── */
  function onAppReady(cb) {
    if (window.state && window.render) { cb(); return; }
    const t = setInterval(() => { if (window.state && window.render) { clearInterval(t); cb(); } }, 80);
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 1 — SLIDER STYLES (inject once)
     ═══════════════════════════════════════════════════════════════════════════ */
  function injectSliderCSS() {
    if (document.getElementById('haat-slider-css')) return;
    const s = document.createElement('style');
    s.id = 'haat-slider-css';
    s.textContent = `
      /* ── shared slider shell ── */
      .haat-slider-shell {
        position: relative;
        overflow: hidden;
        width: 100%;
      }
      .haat-slider-track {
        display: flex;
        transition: transform .45s cubic-bezier(.4,0,.2,1);
        will-change: transform;
      }
      /* Product slider — 4.5 visible cards */
      .haat-prod-slide {
        flex: 0 0 calc(100% / 4.5);
        min-width: calc(100% / 4.5);
        padding-right: 14px;
        box-sizing: border-box;
      }
      /* Store slider — 3.5 visible cards */
      .haat-store-slide {
        flex: 0 0 calc(100% / 3.5);
        min-width: calc(100% / 3.5);
        padding-right: 16px;
        box-sizing: border-box;
      }
      /* ── arrow buttons — invisible by default, fade in on slider hover ── */
      .haat-sl-btn {
        position: absolute;
        top: 50%; transform: translateY(-50%);
        width: 38px; height: 38px;
        border-radius: 50%;
        border: none;
        background: rgba(255,255,255,.92);
        box-shadow: 0 2px 12px rgba(0,0,0,.18);
        cursor: pointer;
        display: grid; place-items: center;
        font-size: 18px;
        z-index: 10;
        color: #1E293B;
        opacity: 0;
        pointer-events: none;
        transition: opacity .25s ease, background .2s, transform .2s;
      }
      /* Reveal arrows only when hovering the whole slider */
      .haat-slider-shell:hover .haat-sl-btn {
        opacity: 1;
        pointer-events: auto;
      }
      .haat-sl-btn:hover { background: #F85606; color: #fff; transform: translateY(-50%) scale(1.08); }
      .haat-sl-prev { left: -4px; }
      .haat-sl-next { right: -4px; }
      /* ── dots ── */
      .haat-sl-dots {
        display: flex; justify-content: center; gap: 6px; margin-top: 14px;
      }
      .haat-sl-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #CBD5E1; border: none; cursor: pointer;
        transition: background .25s, transform .25s;
        padding: 0;
      }
      .haat-sl-dot.active { background: #F85606; transform: scale(1.3); }
      /* ── card in slider: fill their slot ── */
      .haat-prod-slide .haat-item-card,
      .haat-store-slide .haat-item-card {
        width: 100% !important;
        margin: 0 !important;
      }
      @media (max-width: 900px) {
        .haat-prod-slide  { flex: 0 0 calc(100% / 2.4); min-width: calc(100% / 2.4); }
        .haat-store-slide { flex: 0 0 calc(100% / 1.9); min-width: calc(100% / 1.9); }
      }
      @media (max-width: 540px) {
        .haat-prod-slide  { flex: 0 0 calc(100% / 1.25); min-width: calc(100% / 1.25); }
        .haat-store-slide { flex: 0 0 90%;                min-width: 90%; }
      }
    `;
    document.head.appendChild(s);
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 2 — BUILD A SLIDER FROM AN EXISTING GRID
     ═══════════════════════════════════════════════════════════════════════════
     grid       — the existing DOM node containing the cards
     slideClass — CSS class to give each slide wrapper
     visibleCount — how many cards are visible at once (for dot count)
     intervalMs — auto-advance milliseconds
  ═══════════════════════════════════════════════════════════════════════════ */
  function buildSlider(grid, slideClass, visibleCount, intervalMs) {
    if (!grid || grid.dataset.sliderDone === '1') return;
    const cards = [...grid.children];
    if (cards.length <= visibleCount) { grid.dataset.sliderDone = '1'; return; }

    /* Wrap each card in a slide div */
    const track = document.createElement('div');
    track.className = 'haat-slider-track';
    cards.forEach(card => {
      const slide = document.createElement('div');
      slide.className = slideClass;
      slide.appendChild(card);
      track.appendChild(slide);
    });

    /* Shell */
    const shell = document.createElement('div');
    shell.className = 'haat-slider-shell';
    shell.appendChild(track);

    /* Arrows */
    const prev = document.createElement('button');
    prev.className = 'haat-sl-btn haat-sl-prev';
    prev.innerHTML = '<i class="bi bi-chevron-left"></i>';
    prev.setAttribute('aria-label', 'Previous');

    const next = document.createElement('button');
    next.className = 'haat-sl-btn haat-sl-next';
    next.innerHTML = '<i class="bi bi-chevron-right"></i>';
    next.setAttribute('aria-label', 'Next');

    shell.appendChild(prev);
    shell.appendChild(next);

    /* Dots */
    const totalSlides = cards.length;
    const dotsWrap = document.createElement('div');
    dotsWrap.className = 'haat-sl-dots';
    const dots = [];
    for (let i = 0; i < totalSlides; i++) {
      const d = document.createElement('button');
      d.className = 'haat-sl-dot' + (i === 0 ? ' active' : '');
      d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
      d.addEventListener('click', () => goTo(i));
      dots.push(d);
      dotsWrap.appendChild(d);
    }

    /* State */
    let current = 0;
    const maxIdx = totalSlides - Math.floor(visibleCount);

    function goTo(idx) {
      current = Math.max(0, Math.min(idx, maxIdx));
      const slideW = track.children[0]?.getBoundingClientRect().width || (shell.offsetWidth / visibleCount);
      track.style.transform = `translateX(-${current * slideW}px)`;
      dots.forEach((d, i) => d.classList.toggle('active', i === current));
    }

    prev.addEventListener('click', () => { goTo(current - 1); resetTimer(); });
    next.addEventListener('click', () => { goTo(current + 1); resetTimer(); });

    /* Recalculate on resize */
    window.addEventListener('resize', () => goTo(current));

    /* Auto advance */
    let timer = setInterval(() => goTo(current + 1 > maxIdx ? 0 : current + 1), intervalMs);
    function resetTimer() { clearInterval(timer); timer = setInterval(() => goTo(current + 1 > maxIdx ? 0 : current + 1), intervalMs); }

    /* Pause on hover */
    shell.addEventListener('mouseenter', () => clearInterval(timer));
    shell.addEventListener('mouseleave', () => { timer = setInterval(() => goTo(current + 1 > maxIdx ? 0 : current + 1), intervalMs); });

    /* Swap grid with shell + dots */
    grid.parentNode.insertBefore(shell, grid);
    grid.parentNode.insertBefore(dotsWrap, grid);
    grid.remove();
    grid.dataset.sliderDone = '1';
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 3 — PATCH render() TO ALSO RUN POST-RENDER HOOK
     ═══════════════════════════════════════════════════════════════════════════ */
  function patchRender() {
    const _origRender = window.render;
    window.render = function () {
      _origRender.apply(this, arguments);
      requestAnimationFrame(postRender);
    };
  }

  function postRender() {
    /* Only on home page */
    const hash = location.hash || '#/';
    const isHome = hash === '#/' || hash === '' || hash === '#';
    if (!isHome) return;

    injectSliderCSS();

    /* ── Featured Products slider ───────────────────────────────────────── */
    const prodGrid = document.querySelector('.marketplace-product-grid');
    if (prodGrid) {
      buildSlider(prodGrid, 'haat-prod-slide', 4.5, 3500);
    }

    /* ── Popular Stores slider ──────────────────────────────────────────── */
    /* The stores grid uses an inline style, no class — find it via section heading */
    const sections = [...document.querySelectorAll('.section-block')];
    for (const sec of sections) {
      const h2 = sec.querySelector('h2');
      if (h2 && h2.textContent.includes('Popular Merchant')) {
        const storeGrid = sec.querySelector('[style*="grid-template-columns"]');
        if (storeGrid) buildSlider(storeGrid, 'haat-store-slide', 3.5, 4200);
        break;
      }
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 4 — AUTH BRIDGE
     ═══════════════════════════════════════════════════════════════════════════ */
  function patchAuth() {
    /* Login */
    const _origLogin = window.handleAuthLogin;
    window.handleAuthLogin = async function (form) {
      const fd = new FormData(form);
      const email = (fd.get('email') || '').trim().toLowerCase();
      const password = (fd.get('password') || '').trim();

      if (!email || !password) {
        _origLogin.call(this, form);
        return;
      }

      const localUser = window.state.users.find(x => x.email.toLowerCase() === email);
      const isDemo = localUser && ([1, 2, 3, 4, 5, 6, 7].includes(localUser.id) || ['customer@haat.com.bd', 'seller@abc-fashion.com', 'admin@haat.com.bd', 'logistics@hatex.com.bd', 'tareq@hatex.com.bd'].includes(email));
      let apiPw = password;
      if (isDemo && password === 'demo1234') {
        apiPw = 'haat2026';
      }

      const r = await apiPost('/auth.php?action=login', { email, password: apiPw });
      if (r.ok && r.data.user) {
        localStorage.setItem('HAAT_API_USER_ID', r.data.user.id);
        localStorage.setItem('HAAT_API_ROLE',    r.data.user.role);
        localStorage.setItem('HAAT_PW_' + email, password);
        const u = r.data.user;
        let existing = window.state.users.find(x => x.email.toLowerCase() === email);
        if (!existing) {
          existing = { id: u.id, name: u.name, email: u.email, role: u.role, phone: '', password: password, status: 'active' };
          window.state.users.push(existing);
        } else {
          existing.id = u.id;
          existing.password = password;
        }
        window.state.currentUserId = existing.id;
        window.state.currentUser = existing;
        window.state.activeRole = existing.role;
      }
      _origLogin.call(this, form);
    };

    /* Register */
    const _origReg = window.handleAuthRegister;
    window.handleAuthRegister = async function (form) {
      const fd = new FormData(form);
      const email = (fd.get('email') || '').trim().toLowerCase();
      const password = (fd.get('password') || '').trim();
      const cpassword = (fd.get('cpassword') || '').trim();
      const name = (fd.get('name') || '').trim();

      // Ensure given and confirm password match before calling API
      if (!name || !email || !password || password.length < 6 || password !== cpassword) {
        _origReg.call(this, form);
        return;
      }

      const regRole = window.state.registerRole || 'customer';
      const r = await apiPost('/auth.php?action=register', {
        name,
        email,
        phone: (fd.get('phone') || '').trim(),
        password,
        role: (regRole === 'seller') ? 'seller' : 'customer',
      });
      if (r.ok && r.data.user) {
        localStorage.setItem('HAAT_API_USER_ID', r.data.user.id);
        localStorage.setItem('HAAT_API_ROLE',    r.data.user.role);
        localStorage.setItem('HAAT_PW_' + email, password);
        if (regRole === 'seller') {
          const storeName = (fd.get('store_name') || name + "'s Store").trim();
          await apiPost('/stores.php', { store_name: storeName }).catch(() => {});
        }
        _origReg.call(this, form);
        if (window.state.currentUser) {
          window.state.currentUser.id = r.data.user.id;
          window.state.currentUserId = r.data.user.id;
          if (window.persist) window.persist();
        }
        return;
      } else if (r.status === 409) {
        if (typeof window.showToast === 'function') {
          window.showToast('An account with this email address already exists. Please log in.', 'warning');
        }
        return;
      }
      _origReg.call(this, form);
    };

    /* Logout */
    const _origOut = window.handleLogout;
    window.handleLogout = async function () {
      await apiPost('/auth.php?action=logout', {}).catch(() => {});
      localStorage.removeItem('HAAT_API_USER_ID');
      localStorage.removeItem('HAAT_API_ROLE');
      if (_origOut) _origOut.call(this);
    };
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 5 — CART & ORDER BRIDGE
     ═══════════════════════════════════════════════════════════════════════════ */
  function patchCartOrder() {
    /* Add to cart */
    const _origCart = window.addToCartDirect;
    window.addToCartDirect = function (productId, qty, variantName, variantVal) {
      _origCart.call(this, productId, qty, variantName, variantVal);
      if (localStorage.getItem('HAAT_API_USER_ID')) {
        apiPost('/cart.php', { product_id: productId, quantity: qty || 1,
          variant_name: variantName || null, variant_value: variantVal || null }).catch(() => {});
      }
    };
    window.addToCart = window.addToCartDirect;

    /* Place order */
    const _origOrder = window.handlePlaceOrder;
    if (_origOrder) {
      window.handlePlaceOrder = async function (form) {
        _origOrder.call(this, form);
        const apiUserId = localStorage.getItem('HAAT_API_USER_ID');
        if (!apiUserId) return;
        const cart = window.state.cart || [];
        if (!cart.length) return;
        const addr = (window.state.addresses || []).find(a => a.user_id === parseInt(apiUserId) && a.is_default) || {};
        await apiPost('/orders.php', {
          payment_method:   window.state.selectedPaymentMethod || 'cash_on_delivery',
          shipping_name:    addr.name     || window.state.currentUser?.name || '',
          shipping_phone:   addr.phone    || window.state.currentUser?.phone || '',
          shipping_address: addr.address  || '',
          district:         addr.district || 'Dhaka',
          division:         addr.division || 'Dhaka',
          postal_code:      addr.postal_code || '',
          items: cart.map(c => ({
            product_id: c.product_id, quantity: c.quantity || 1,
            variant_name: c.variant_name || null, variant_value: c.variant_value || null,
          })),
        }).catch(() => {});
      };
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 6 — LOAD REAL DATA FROM API → MERGE INTO state
     Preserves existing Unsplash images — only adds NEW products from DB
     ═══════════════════════════════════════════════════════════════════════════ */
  async function loadApiData() {
    const ping = await apiGet('/auth.php?action=check');
    if (!ping.ok && ping.status === 0) {
      console.info('[HAAT Bridge] API offline — using localStorage only');
      return;
    }

    const st = window.state;
    /* Save original products (with Unsplash images) keyed by id */
    const origByid = {};
    (st.products || []).forEach(p => { origByid[p.id] = p; });

    /* Categories */
    const cats = await apiGet('/categories.php');
    if (cats.ok && Array.isArray(cats.data) && cats.data.length) {
      const iconMap = { fashion:'bi-gem', electronics:'bi-phone', groceries:'bi-basket', crafts:'bi-palette' };
      st.categories = cats.data.map(c => ({
        ...c, icon: iconMap[c.slug] || 'bi-tag',
      }));
    }

    /* Brands */
    const brands = await apiGet('/brands.php');
    if (brands.ok && Array.isArray(brands.data) && brands.data.length) st.brands = brands.data;

    /* Stores */
    const stores = await apiGet('/stores.php');
    if (stores.ok && stores.data.stores?.length) {
      const fallbackColors = ['#1E4332','#0284C7','#15803D','#B45309','#F85606'];
      st.stores = stores.data.stores.map(s => {
        const col = fallbackColors[s.id % fallbackColors.length] || '#1E4332';
        return {
          id: s.id, user_id: s.user_id,
          store_name: s.store_name, store_slug: s.store_slug,
          description: s.description || '',
          logo_text: (s.store_name || 'S').substring(0, 3).toUpperCase(),
          primary_color: col,
          banner_gradient: `linear-gradient(135deg, ${col} 0%, #0a0a0a 100%)`,
          address: s.address || '', district: s.district || 'Dhaka',
          division: s.division || 'Dhaka', postal_code: s.postal_code || '',
          latitude: parseFloat(s.latitude) || 23.77,
          longitude: parseFloat(s.longitude) || 90.40,
          delivery_charge: s.delivery_charge ?? 60,
          free_delivery:   s.free_delivery   ?? 0,
          auto_greeting: s.auto_greeting || `Welcome to ${s.store_name}!`,
          status: 'active', is_published: 1,
          verification_status: s.verification_status || 'unverified',
        };
      });
    }

    /* Products — KEEP original images if they are real URLs */
    const prods = await apiGet('/products.php?limit=60');
    if (prods.ok && prods.data.products?.length) {
      st.products = prods.data.products.map(p => {
        const orig = origByid[p.id];
        /* Use Unsplash URLs from mock data if available; fall back to placeholder */
        const img1 = (orig?.image_1 && !orig.image_1.includes('images.php')) ? orig.image_1 : (p.image_1_url || '');
        const img2 = (orig?.image_2 && !orig.image_2.includes('images.php')) ? orig.image_2 : (p.image_2_url || '');
        const img3 = (orig?.image_3 && !orig.image_3.includes('images.php')) ? orig.image_3 : (p.image_3_url || '');
        return {
          id: p.id, store_id: p.store_id,
          subcategory_id: p.subcategory_id, brand_id: p.brand_id || null,
          collection_id:  p.collection_id  || null,
          name: p.name, slug: p.slug, sku: p.sku || '',
          description: p.description || '',
          price:     parseFloat(p.price),
          sale_price: p.sale_price ? parseFloat(p.sale_price) : null,
          image_1: img1, image_2: img2, image_3: img3,
          variant_1_name:  p.variant_1_name  || null,
          variant_1_value: p.variant_1_value || null,
          variant_2_name:  p.variant_2_name  || null,
          variant_2_value: p.variant_2_value || null,
          is_featured: parseInt(p.is_featured || orig?.is_featured || 0),
          is_flash_sale: parseInt(p.is_flash_sale || orig?.is_flash_sale || 0),
          rating:       parseFloat(p.avg_rating   || orig?.rating || 0),
          reviews_count:parseInt(p.review_count   || orig?.reviews_count || 0),
          delivery_charge: orig?.delivery_charge ?? 60,
          free_delivery:   orig?.free_delivery   ?? 0,
        };
      });
    }

    /* If logged in, load user-specific data */
    const apiUserId = localStorage.getItem('HAAT_API_USER_ID');
    if (apiUserId) {
      const notifs = await apiGet('/notifications.php?limit=20');
      if (notifs.ok && notifs.data.notifications?.length) {
        const uid = parseInt(apiUserId);
        const apiNotifs = notifs.data.notifications.map(n => ({
          id: n.id, user_id: uid, target_role: st.activeRole || 'customer',
          type: n.type, title: n.title, message: n.message,
          link: n.order_id ? `#/order/${n.order_id}` : '',
          is_read: parseInt(n.is_read), created_at: n.created_at, order_id: n.order_id,
        }));
        const existing = new Set(apiNotifs.map(x => x.id));
        st.notifications = [...apiNotifs, ...(st.notifications || []).filter(x => !existing.has(x.id))];
      }
    }

    /* Re-render with fresh API data */
    try { window.render(); } catch (_) {}
    try { window.updateGlobalHeader(); } catch (_) {}
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     INIT
     ═══════════════════════════════════════════════════════════════════════════ */
  onAppReady(function () {
    injectSliderCSS();
    patchAuth();
    patchCartOrder();
    patchRender();

    /* First-run: load API data, then sliders fire via the patched render() */
    loadApiData().catch(() => {
      /* API down — still run sliders on current state */
      requestAnimationFrame(postRender);
    });
  });

  window.haatApiSync = {
    saveProduct: (p) => p.id && api(`/products.php?id=${p.id}`, { method: 'PUT', body: JSON.stringify(p) }).catch(() => {}),
    saveOrder:   (o) => o.id && api(`/orders.php?id=${o.id}`,   { method: 'PUT', body: JSON.stringify({ order_status: o.order_status }) }).catch(() => {}),
    saveInventory: (pid, qty) => api(`/inventory.php?product_id=${pid}`, { method: 'PUT', body: JSON.stringify({ quantity: qty }) }).catch(() => {}),
  };

  console.info('[HAAT Bridge] v2 loaded — images fixed, sliders active, API connected');

})();
