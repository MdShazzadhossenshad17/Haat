/**
 * HAAT! API Bridge v3
 * - Full Dynamic Synchronization between Frontend (app.js) ↔ PHP REST API ↔ MySQL
 * - Multi-Role Authentication (Seller, Customer, Rider, Admin, HATEX)
 * - Automatic Seller Store Creation & Resolution
 * - Automatic Rider Delivery Queue & Profile Resolution
 * - Seller Order Status Sync to MySQL (PUT /api/seller_orders.php)
 * - Customer Orders, Wishlist & Address Sync to MySQL
 * - Seller Product Creation Sync to MySQL (POST /api/products.php)
 * - Auto-sliding carousel for Featured Products & Popular Stores
 */

(function () {
  'use strict';

  const BASE = (function () {
    const p = window.location.pathname.replace(/\/index\.html$/i, '').replace(/\/+$/, '');
    return (p ? p : '') + '/api';
  })();

  /* ── HTTP helpers ────────────────────────────────────────────────────────── */
  async function api(ep, opts = {}) {
    try {
      const headers = { 'Content-Type': 'application/json' };
      const uid = localStorage.getItem('HAAT_API_USER_ID') || (window.state?.currentUser?.id ? String(window.state.currentUser.id) : null);
      if (uid) headers['X-User-Id'] = uid;
      const r = await fetch(BASE + ep, {
        headers: { ...headers, ...(opts.headers || {}) },
        credentials: 'same-origin',
        ...opts,
      });
      const d = await r.json().catch(() => ({}));
      return { ok: r.ok, status: r.status, data: d };
    } catch { return { ok: false, status: 0, data: {} }; }
  }
  const apiGet  = ep => api(ep, { method: 'GET' });
  const apiPost = (ep, b) => api(ep, { method: 'POST', body: JSON.stringify(b) });
  const apiPut  = (ep, b) => api(ep, { method: 'PUT',  body: JSON.stringify(b) });
  const apiDel  = ep => api(ep, { method: 'DELETE' });

  /* ── Wait for app.js to finish initialising ─────────────────────────────── */
  function onAppReady(cb) {
    if (window.state && window.render) { cb(); return; }
    const t = setInterval(() => { if (window.state && window.render) { clearInterval(t); cb(); } }, 80);
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 1 — SLIDER STYLES
     ═══════════════════════════════════════════════════════════════════════════ */
  function injectSliderCSS() {
    if (document.getElementById('haat-slider-css')) return;
    const s = document.createElement('style');
    s.id = 'haat-slider-css';
    s.textContent = `
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
      .haat-prod-slide {
        flex: 0 0 calc(100% / 4.5);
        min-width: calc(100% / 4.5);
        padding-right: 14px;
        box-sizing: border-box;
      }
      .haat-store-slide {
        flex: 0 0 calc(100% / 3.5);
        min-width: calc(100% / 3.5);
        padding-right: 16px;
        box-sizing: border-box;
      }
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
      .haat-slider-shell:hover .haat-sl-btn {
        opacity: 1;
        pointer-events: auto;
      }
      .haat-sl-btn:hover { background: #F85606; color: #fff; transform: translateY(-50%) scale(1.08); }
      .haat-sl-prev { left: -4px; }
      .haat-sl-next { right: -4px; }
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
     SECTION 2 — SLIDER BUILDER
     ═══════════════════════════════════════════════════════════════════════════ */
  function buildSlider(grid, slideClass, visibleCount, intervalMs) {
    if (!grid || grid.dataset.sliderDone === '1') return;
    const cards = [...grid.children];
    if (cards.length <= visibleCount) { grid.dataset.sliderDone = '1'; return; }

    const track = document.createElement('div');
    track.className = 'haat-slider-track';
    cards.forEach(card => {
      const slide = document.createElement('div');
      slide.className = slideClass;
      slide.appendChild(card);
      track.appendChild(slide);
    });

    const shell = document.createElement('div');
    shell.className = 'haat-slider-shell';
    shell.appendChild(track);

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
    window.addEventListener('resize', () => goTo(current));

    let timer = setInterval(() => goTo(current + 1 > maxIdx ? 0 : current + 1), intervalMs);
    function resetTimer() { clearInterval(timer); timer = setInterval(() => goTo(current + 1 > maxIdx ? 0 : current + 1), intervalMs); }

    shell.addEventListener('mouseenter', () => clearInterval(timer));
    shell.addEventListener('mouseleave', () => { timer = setInterval(() => goTo(current + 1 > maxIdx ? 0 : current + 1), intervalMs); });

    grid.parentNode.insertBefore(shell, grid);
    grid.parentNode.insertBefore(dotsWrap, grid);
    grid.remove();
    grid.dataset.sliderDone = '1';
  }

  function patchRender() {
    const _origRender = window.render;
    window.render = function () {
      _origRender.apply(this, arguments);
      requestAnimationFrame(postRender);
    };
  }

  function postRender() {
    const hash = location.hash || '#/';
    const isHome = hash === '#/' || hash === '' || hash === '#';
    if (!isHome) return;

    injectSliderCSS();

    const prodGrid = document.querySelector('.marketplace-product-grid');
    if (prodGrid) buildSlider(prodGrid, 'haat-prod-slide', 4.5, 3500);

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
     SECTION 3 — DYNAMIC DATA SYNC HELPERS (MySQL ↔ State)
     ═══════════════════════════════════════════════════════════════════════════ */
  async function syncCustomerOrders(userId) {
    const r = await apiGet('/orders.php');
    if (r.ok && Array.isArray(r.data.orders)) {
      if (r.data.orders.length === 0) {
        window.state.orders = (window.state.orders || []).filter(o => String(o.user_id) !== String(userId));
      } else {
        for (const summary of r.data.orders) {
          const detail = await apiGet(`/orders.php?id=${summary.id}`);
          if (detail.ok && detail.data.id) {
            const fullOrder = detail.data;
            const idx = (window.state.orders || []).findIndex(o => String(o.id) === String(fullOrder.id));
            if (idx >= 0) window.state.orders[idx] = { ...window.state.orders[idx], ...fullOrder };
            else window.state.orders.unshift(fullOrder);
          }
        }
      }
      if (window.persist) window.persist();
      if (typeof window.render === 'function') window.render();
    }
  }

  async function syncSellerOrders(storeId) {
    const r = await apiGet('/seller_orders.php');
    if (r.ok && Array.isArray(r.data.seller_orders) && r.data.seller_orders.length) {
      for (const so of r.data.seller_orders) {
        const detail = await apiGet(`/seller_orders.php?id=${so.id}`);
        if (detail.ok && detail.data.id) {
          const fullSo = detail.data;
          let parent = (window.state.orders || []).find(o => String(o.id) === String(fullSo.order_id));
          if (parent) {
            if (!Array.isArray(parent.seller_orders)) parent.seller_orders = [];
            const soIdx = parent.seller_orders.findIndex(s => String(s.id) === String(fullSo.id));
            if (soIdx >= 0) parent.seller_orders[soIdx] = fullSo;
            else parent.seller_orders.push(fullSo);
          } else {
            const newOrder = {
              id: fullSo.order_id,
              order_number: fullSo.order_number,
              user_id: fullSo.user_id || 1,
              shipping_name: fullSo.shipping_name,
              shipping_phone: fullSo.shipping_phone,
              shipping_address: fullSo.shipping_address,
              district: fullSo.district,
              division: fullSo.division,
              postal_code: fullSo.postal_code,
              order_status: fullSo.status,
              total_amount: fullSo.subtotal,
              shipping_cost: fullSo.shipping_cost || 60,
              grand_total: fullSo.seller_total,
              seller_orders: [fullSo],
              created_at: fullSo.created_at
            };
            window.state.orders.unshift(newOrder);
          }
        }
      }
      if (window.persist) window.persist();
    }
  }

  async function syncWishlist(userId) {
    const r = await apiGet('/wishlists.php');
    if (r.ok && Array.isArray(r.data)) {
      const pids = r.data.map(w => Number(w.product_id));
      if (!window.state.wishlistByUser) window.state.wishlistByUser = {};
      window.state.wishlistByUser[String(userId)] = pids;
      if (window.persist) window.persist();
    }
  }

  async function syncAddresses(userId) {
    const r = await apiGet('/addresses.php');
    if (r.ok && Array.isArray(r.data) && r.data.length) {
      const apiAddrs = r.data.map(a => ({
        id: a.id,
        user_id: userId,
        label: a.label || 'Home',
        name: a.name,
        phone: a.phone,
        address: a.address,
        district: a.district,
        division: a.division,
        postal_code: a.postal_code || '1205',
        latitude: parseFloat(a.latitude) || 23.75,
        longitude: parseFloat(a.longitude) || 90.38,
        is_default: parseInt(a.is_default) || 0
      }));
      window.state.addresses = [
        ...apiAddrs,
        ...(window.state.addresses || []).filter(a => String(a.user_id) !== String(userId))
      ];
      if (window.persist) window.persist();
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 4 — AUTH & MULTI-ROLE BRIDGE
     ═══════════════════════════════════════════════════════════════════════════ */
  function patchAuth() {
    /* ── Login ── */
    const _origLogin = window.handleAuthLogin;
    window.handleAuthLogin = async function (form) {
      const fd = new FormData(form);
      const email = (fd.get('email') || '').trim().toLowerCase();
      const password = (fd.get('password') || '').trim();

      if (!email || !password) {
        if (typeof window.showToast === 'function') window.showToast('Please enter your email and password.', 'warning');
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
        const u = r.data.user;
        const st = r.data.store;
        const rd = r.data.rider;

        localStorage.setItem('HAAT_API_USER_ID', String(u.id));
        localStorage.setItem('HAAT_API_ROLE', u.role);
        localStorage.setItem('HAAT_PW_' + email, password);

        // Upsert user in window.state.users
        const existingIdx = window.state.users.findIndex(x => String(x.id) === String(u.id) || x.email.toLowerCase() === email);
        const userObj = {
          id: u.id,
          name: u.name,
          email: u.email,
          phone: u.phone || (localUser?.phone || ''),
          role: u.role,
          password: password,
          status: 'active',
          store_id: st ? st.id : (u.store_id || null)
        };
        if (existingIdx >= 0) window.state.users[existingIdx] = userObj;
        else window.state.users.push(userObj);

        // Upsert store if seller
        if (st) {
          const storeIdx = window.state.stores.findIndex(s => String(s.id) === String(st.id) || String(s.user_id) === String(u.id));
          const storeObj = {
            id: st.id,
            user_id: u.id,
            store_name: st.store_name,
            store_slug: st.store_slug,
            description: st.description || `Official HAAT merchant store.`,
            logo_text: st.store_name.slice(0, 3).toUpperCase(),
            primary_color: '#F85606',
            banner_gradient: 'linear-gradient(135deg, #F85606 0%, #7C2D12 100%)',
            address: st.address || 'Dhaka',
            district: st.district || 'Dhaka',
            division: st.division || 'Dhaka',
            postal_code: '1205',
            latitude: parseFloat(st.latitude) || 23.75,
            longitude: parseFloat(st.longitude) || 90.39,
            delivery_charge: 60,
            free_delivery: 0,
            auto_greeting: `Assalamu Alaikum! Welcome to ${st.store_name}.`,
            status: 'active',
            is_published: 1,
            verification_status: 'verified'
          };
          if (storeIdx >= 0) window.state.stores[storeIdx] = { ...window.state.stores[storeIdx], ...storeObj };
          else window.state.stores.push(storeObj);
        }

        // Upsert rider if rider
        if (rd) {
          const riderIdx = window.state.riders.findIndex(r => String(r.id) === String(rd.id) || String(r.user_id) === String(u.id));
          const riderObj = {
            id: rd.id,
            user_id: u.id,
            name: u.name,
            phone: u.phone || (rd.phone || ''),
            vehicle_type: 'Motorcycle',
            hub: 'Dhaka Metro',
            status: 'active',
            current_lat: 23.7800,
            current_lon: 90.4100
          };
          if (riderIdx >= 0) window.state.riders[riderIdx] = { ...window.state.riders[riderIdx], ...riderObj };
          else window.state.riders.push(riderObj);
        }

        window.state.activeRole = u.role;
        window.state.currentUserId = u.id;
        window.state.currentUser = userObj;
        if (st) window.state.currentUser.name = st.store_name;
        window.state.lastAuthEmail = u.email;
        window.state.appliedCoupon = null;

        // Fetch dynamic user data from DB
        if (u.role === 'customer') {
          syncCustomerOrders(u.id);
          syncWishlist(u.id);
          syncAddresses(u.id);
        } else if (u.role === 'seller' && st) {
          syncSellerOrders(st.id);
        }

        if (window.persist) window.persist();

        if (typeof window.showToast === 'function') {
          window.showToast(`Welcome back, ${u.name}!`, 'success');
        }

        if (u.role === 'seller') location.hash = '#/dash/seller';
        else if (u.role === 'rider') location.hash = '#/dash/rider';
        else if (u.role === 'admin') location.hash = '#/dash/admin';
        else if (u.role === 'hatex' || u.role === 'logistics') location.hash = '#/dash/hatex';
        else location.hash = '#/account';

        if (typeof window.render === 'function') window.render();
        return;
      } else {
        if (r.status === 401 && r.data && r.data.error) {
          if (typeof window.showToast === 'function') {
            window.showToast(r.data.error, 'warning');
          }
          return;
        }
        _origLogin.call(this, form);
      }
    };

    /* ── Register ── */
    const _origReg = window.handleAuthRegister;
    window.handleAuthRegister = async function (form) {
      const fd = new FormData(form);
      const email = (fd.get('email') || '').trim().toLowerCase();
      const password = (fd.get('password') || '').trim();
      const cpassword = (fd.get('cpassword') || '').trim();
      const name = (fd.get('name') || '').trim();
      const phone = (fd.get('phone') || '').trim();

      if (!name || !email) {
        if (typeof window.showToast === 'function') window.showToast('Please provide your name and email address.', 'warning');
        return;
      }
      if (!password || password.length < 6) {
        if (typeof window.showToast === 'function') window.showToast('Password must be at least 6 characters long.', 'warning');
        return;
      }
      if (password !== cpassword) {
        if (typeof window.showToast === 'function') window.showToast('Passwords do not match. Please verify.', 'warning');
        return;
      }

      const regRole = window.state.registerRole || 'customer';
      const payload = {
        name,
        email,
        phone,
        password,
        role: regRole,
      };

      if (regRole === 'seller') {
        payload.store_name = (fd.get('store_name') || `${name}'s Store`).trim();
        payload.category = fd.get('category') || 'Fashion & Apparel';
        payload.address = (fd.get('address') || 'Shop 8, New Market').trim();
        payload.district = fd.get('district') || 'Dhaka';
        payload.division = fd.get('division') || fd.get('district') || 'Dhaka';
      } else if (regRole === 'customer') {
        payload.address = (fd.get('address') || 'House 14, Road 3, Dhanmondi').trim();
        payload.district = fd.get('district') || 'Dhaka';
        payload.division = fd.get('division') || 'Dhaka';
      } else if (regRole === 'rider') {
        payload.vehicle_type = fd.get('vehicle_type') || 'Motorcycle';
        payload.hub = fd.get('district') || 'Dhaka Metro';
      }

      const r = await apiPost('/auth.php?action=register', payload);
      if (r.ok && r.data.user) {
        const u = r.data.user;
        const st = r.data.store;
        const rd = r.data.rider;

        localStorage.setItem('HAAT_API_USER_ID', String(u.id));
        localStorage.setItem('HAAT_API_ROLE', u.role);
        localStorage.setItem('HAAT_PW_' + email, password);

        const existingIdx = window.state.users.findIndex(x => String(x.id) === String(u.id) || x.email.toLowerCase() === email);
        const newUserObj = {
          id: u.id,
          name: u.name,
          email: u.email,
          phone: u.phone || phone,
          role: u.role,
          password: password,
          status: 'active',
          store_id: st ? st.id : null
        };
        if (existingIdx >= 0) window.state.users[existingIdx] = newUserObj;
        else window.state.users.push(newUserObj);

        if (st) {
          const storeIdx = window.state.stores.findIndex(s => String(s.id) === String(st.id) || String(s.user_id) === String(u.id));
          const newStoreObj = {
            id: st.id,
            user_id: u.id,
            store_name: st.store_name,
            store_slug: st.store_slug,
            description: st.description || `Official HAAT merchant store.`,
            logo_text: st.store_name.slice(0, 3).toUpperCase(),
            primary_color: '#F85606',
            banner_gradient: 'linear-gradient(135deg, #F85606 0%, #7C2D12 100%)',
            address: st.address || payload.address,
            district: st.district || payload.district,
            division: st.division || payload.division,
            postal_code: '1205',
            latitude: 23.7500,
            longitude: 90.3900,
            delivery_charge: 60,
            free_delivery: 0,
            auto_greeting: `Assalamu Alaikum! Welcome to ${st.store_name}.`,
            status: 'active',
            is_published: 1,
            verification_status: 'verified'
          };
          if (storeIdx >= 0) window.state.stores[storeIdx] = newStoreObj;
          else window.state.stores.push(newStoreObj);
        }

        if (rd) {
          const riderIdx = window.state.riders.findIndex(r => String(r.id) === String(rd.id) || String(r.user_id) === String(u.id));
          const newRiderObj = {
            id: rd.id,
            user_id: u.id,
            name: u.name,
            phone: u.phone || phone,
            vehicle_type: payload.vehicle_type || 'Motorcycle',
            hub: payload.hub || 'Dhaka Metro',
            status: 'active',
            current_lat: 23.7800,
            current_lon: 90.4100
          };
          if (riderIdx >= 0) window.state.riders[riderIdx] = newRiderObj;
          else window.state.riders.push(newRiderObj);
        }

        window.state.activeRole = u.role;
        window.state.currentUserId = u.id;
        window.state.currentUser = newUserObj;
        if (st) {
          window.state.currentUser.name = st.store_name;
          window.state.currentUser.store_id = st.id;
        }

        if (!window.state.collectedCouponsByUser) window.state.collectedCouponsByUser = {};
        window.state.collectedCouponsByUser[String(u.id)] = [];
        if (!window.state.cartByUser) window.state.cartByUser = {};
        window.state.cartByUser[String(u.id)] = [];
        if (!window.state.wishlistByUser) window.state.wishlistByUser = {};
        window.state.wishlistByUser[String(u.id)] = [];
        window.state.activeChatChannel = null;

        if (u.role === 'customer') {
          syncCustomerOrders(u.id);
          syncWishlist(u.id);
          syncAddresses(u.id);
        } else if (u.role === 'seller' && st) {
          syncSellerOrders(st.id);
        }

        if (window.persist) window.persist();

        if (typeof window.showToast === 'function') {
          if (u.role === 'seller') window.showToast(`Store "${st ? st.store_name : u.name}" is now active and open!`, 'success');
          else window.showToast(`Welcome to HAAT, ${u.name}! Account registered.`, 'success');
        }

        if (u.role === 'seller') location.hash = '#/dash/seller';
        else if (u.role === 'rider') location.hash = '#/dash/rider';
        else location.hash = '#/account';

        if (typeof window.render === 'function') window.render();
        return;
      } else if (r.status === 409) {
        if (typeof window.showToast === 'function') {
          window.showToast('An account with this email address already exists. Please log in.', 'warning');
        }
        return;
      } else {
        if (r.data && r.data.error) {
          if (typeof window.showToast === 'function') {
            window.showToast(r.data.error, 'danger');
          }
          return;
        }
        _origReg.call(this, form);
      }
    };

    /* ── Logout ── */
    const _origOut = window.handleLogout;
    window.handleLogout = async function () {
      await apiPost('/auth.php?action=logout', {}).catch(() => {});
      localStorage.removeItem('HAAT_API_USER_ID');
      localStorage.removeItem('HAAT_API_ROLE');
      if (_origOut) _origOut.call(this);
    };
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 5 — CART, CHECKOUT, SELLER STATUS & WISHLIST BRIDGE
     ═══════════════════════════════════════════════════════════════════════════ */
  function patchCartOrder() {
    /* Add to cart */
    const _origCart = window.addToCartDirect;
    window.addToCartDirect = function (productId, qty, variantName, variantVal) {
      _origCart.call(this, productId, qty, variantName, variantVal);
      if (localStorage.getItem('HAAT_API_USER_ID')) {
        apiPost('/cart.php', {
          product_id: productId,
          quantity: qty || 1,
          variant_name: variantName || null,
          variant_value: variantVal || null
        }).catch(() => {});
      }
    };
    window.addToCart = window.addToCartDirect;

    /* Place order */
    const _origOrder = window.handlePlaceOrder;
    if (_origOrder) {
      window.handlePlaceOrder = async function (form) {
        const cartSnapshot = (window.state.cart || []).map(c => ({ ...c }));
        const fd = form ? new FormData(form) : null;
        const shippingName = fd ? (fd.get('shipping_name') || '').trim() : '';
        const shippingPhone = fd ? (fd.get('shipping_phone') || '').trim() : '';
        const shippingAddress = fd ? (fd.get('shipping_address') || '').trim() : '';
        const district = fd ? (fd.get('district') || 'Dhaka') : 'Dhaka';
        const division = fd ? (fd.get('division') || 'Dhaka') : 'Dhaka';
        const postalCode = fd ? (fd.get('postal_code') || '1205') : '1205';
        const paymentMethod = (fd && fd.get('payment_method')) || window.state.selectedPaymentMethod || 'cash_on_delivery';

        _origOrder.call(this, form);

        const apiUserId = localStorage.getItem('HAAT_API_USER_ID') || (window.state.currentUser ? String(window.state.currentUser.id) : null);
        if (!apiUserId || !cartSnapshot.length) return;

        const addr = (window.state.addresses || []).find(a => String(a.user_id) === String(apiUserId) && a.is_default) || {};
        const postData = {
          payment_method: paymentMethod,
          shipping_name: shippingName || addr.name || window.state.currentUser?.name || 'Customer',
          shipping_phone: shippingPhone || addr.phone || window.state.currentUser?.phone || '01700000000',
          shipping_address: shippingAddress || addr.address || 'Dhaka',
          district: district || addr.district || 'Dhaka',
          division: division || addr.division || 'Dhaka',
          postal_code: postalCode || addr.postal_code || '1205',
          items: cartSnapshot.map(c => ({
            product_id: c.product_id,
            quantity: c.quantity || 1,
            variant_name: c.variant_name || null,
            variant_value: c.variant_value || null,
          })),
        };
        if (window.state.appliedCoupon?.code) {
          postData.coupon_code = window.state.appliedCoupon.code;
        }

        const res = await apiPost('/orders.php', postData);
        if (res.ok && res.data.order_id) {
          const placed = window.state.orders[0];
          if (placed) {
            const oldNum = placed.order_number;
            placed.id = res.data.order_id;
            placed.order_number = res.data.order_number;
            if (Array.isArray(placed.seller_orders)) {
              placed.seller_orders.forEach(so => { so.order_id = res.data.order_id; });
            }
            if (location.hash && location.hash.includes(oldNum)) {
              location.hash = `#/order/${res.data.order_number}`;
            }
          }
          if (window.persist) window.persist();
        }
      };
    }

    /* Seller Order Status Updates → Sync to MySQL */
    const _origUpdateStatus = window.updateSellerOrderStatus;
    if (_origUpdateStatus) {
      window.updateSellerOrderStatus = function (sellerOrderId, newStatus) {
        const sId = Number(sellerOrderId);
        let statusVal = newStatus;
        if (!statusVal) {
          const selectEl = document.getElementById(`sellerStatusSelect_${sId}`);
          if (selectEl) statusVal = selectEl.value;
        }
        _origUpdateStatus.call(this, sellerOrderId, newStatus);
        if (statusVal && ['pending', 'order_accepted', 'processing', 'packaged'].includes(statusVal)) {
          apiPut(`/seller_orders.php?id=${sId}`, { status: statusVal }).catch(() => {});
        }
      };
    }

    /* Wishlist Toggle → Sync to MySQL */
    const _origWish = window.toggleWishlist;
    if (_origWish) {
      window.toggleWishlist = function (productId) {
        const pid = Number(productId);
        const wasInWish = (window.state.wishlist || []).includes(pid);
        _origWish.call(this, productId);
        if (window.state.activeRole === 'customer' && localStorage.getItem('HAAT_API_USER_ID')) {
          if (wasInWish) {
            apiDel(`/wishlists.php?product_id=${pid}`).catch(() => {});
          } else {
            apiPost('/wishlists.php', { product_id: pid }).catch(() => {});
          }
        }
      };
    }

    /* Seller Product Creation → Sync to MySQL */
    const _origAddProd = window.handleAddProduct;
    if (_origAddProd) {
      window.handleAddProduct = async function (form) {
        const fd = new FormData(form);
        const store = typeof window.getSellerOwnStore === 'function' ? window.getSellerOwnStore() : window.state.stores[0];
        _origAddProd.call(this, form);

        if (store && store.id) {
          const prodData = {
            store_id: store.id,
            name: fd.get('name'),
            price: parseFloat(fd.get('price')),
            sale_price: parseFloat(fd.get('sale_price')) || null,
            sku: fd.get('sku'),
            subcategory_id: Number(fd.get('subcategory_id') || 101),
            stock: parseInt(fd.get('stock') || 25),
            is_featured: 1,
            description: 'Verified merchant product in HAAT catalogue.'
          };
          const r = await apiPost('/products.php', prodData);
          if (r.ok && r.data.id) {
            const added = window.state.products.find(p => p.name === prodData.name) || window.state.products[0];
            if (added) {
              added.id = r.data.id;
              if (window.persist) window.persist();
            }
          }
        }
      };
    }

    /* Customer Review Submission — Sync to MySQL (POST /api/reviews.php) */
    const _origSubmitRev = window.handleSubmitReview;
    if (_origSubmitRev) {
      window.handleSubmitReview = async function (form, productId) {
        const fd = new FormData(form);
        const targetPid = Number(productId || fd.get('product_id'));
        const rating = parseInt(fd.get('rating') || 5);
        const comment = fd.get('comment') || '';
        _origSubmitRev.call(this, form, targetPid);

        try {
          await apiPost('/reviews.php', {
            product_id: targetPid,
            rating: rating,
            comment: comment
          });
        } catch (e) {
          console.warn('[HAAT Bridge] Review API sync error:', e);
        }
      };
    }

    /* Seller Store Profile Updates → Sync to MySQL */
    const _origSaveStore = window.handleSaveStore;
    if (_origSaveStore) {
      window.handleSaveStore = async function (form) {
        _origSaveStore.call(this, form);
        const fd = new FormData(form);
        const store = (typeof window.getSellerOwnStore === 'function' ? window.getSellerOwnStore() : null) || window.state.stores[0];
        if (store && store.id) {
          await apiPut('/stores.php', {
            store_name: fd.get('store_name'),
            description: fd.get('description'),
            address: fd.get('address'),
            district: fd.get('district'),
            division: fd.get('division'),
            delivery_charge: parseFloat(fd.get('delivery_charge')) || 0,
            free_delivery: fd.get('free_delivery') ? 1 : 0,
            auto_greeting: (fd.get('auto_greeting') || '').trim()
          }).catch(() => {});
        }
      };
    }

    /* User Profile Changes → Sync to MySQL */
    const _origUpdateProf = window.handleUpdateProfile;
    if (_origUpdateProf) {
      window.handleUpdateProfile = async function (form) {
        _origUpdateProf.call(this, form);
        const fd = new FormData(form);
        await apiPut('/users.php', {
          name: fd.get('name'),
          phone: fd.get('phone')
        }).catch(() => {});
      };
    }

    /* Customer Address Management → Sync to MySQL */
    const _origAddAddress = window.handleAddAddress;
    if (_origAddAddress) {
      window.handleAddAddress = async function (form) {
        const fd = new FormData(form);
        _origAddAddress.call(this, form);
        const r = await apiPost('/addresses.php', {
          label: fd.get('label') || 'Home',
          name: fd.get('name'),
          phone: fd.get('phone'),
          address: fd.get('address'),
          district: fd.get('district'),
          division: fd.get('division'),
          postal_code: fd.get('postal_code') || '1205',
          is_default: 0
        }).catch(() => {});
        if (r && r.ok && r.data.id) {
          const added = window.state.addresses[window.state.addresses.length - 1];
          if (added) added.id = r.data.id;
          if (window.persist) window.persist();
        }
      };
    }

    const _origDelAddress = window.deleteAddress;
    if (_origDelAddress) {
      window.deleteAddress = function (addressId) {
        _origDelAddress.call(this, addressId);
        apiDel(`/addresses.php?id=${addressId}`).catch(() => {});
      };
    }

    /* HATEX Rider Assignment & Status Transitions → Sync to MySQL */
    const _origAssignRider = window.assignRider;
    if (_origAssignRider) {
      window.assignRider = function (sellerOrderId, riderId) {
        _origAssignRider.call(this, sellerOrderId, riderId);
        apiPut(`/seller_orders.php?id=${sellerOrderId}`, {
          assigned_rider_id: riderId,
          status: 'shipped'
        }).catch(() => {});
        apiPost('/delivery.php', {
          seller_order_id: sellerOrderId,
          status: 'picked_up',
          location: 'HATEX Central Sorting Hub',
          note: 'Assigned delivery rider'
        }).catch(() => {});
      };
    }

    const _origAdvanceRider = window.advanceRiderStatus;
    if (_origAdvanceRider) {
      window.advanceRiderStatus = function (sellerOrderId, newStatus) {
        _origAdvanceRider.call(this, sellerOrderId, newStatus);
        const loc = newStatus === 'delivered' ? 'Customer Doorstep' : 'In Transit Waypoint';
        apiPut(`/seller_orders.php?id=${sellerOrderId}`, { status: newStatus }).catch(() => {});
        apiPost('/delivery.php', {
          seller_order_id: sellerOrderId,
          status: newStatus,
          location: loc,
          note: `Status updated to ${newStatus}`
        }).catch(() => {});
      };
    }
  }

  /* ═══════════════════════════════════════════════════════════════════════════
     SECTION 6 — LOAD REAL DATA FROM API → MERGE INTO state
     ═══════════════════════════════════════════════════════════════════════════ */
  async function loadApiData() {
    const ping = await apiGet('/auth.php?action=check');
    if (!ping.ok && ping.status === 0) {
      console.info('[HAAT Bridge] API offline — using localStorage only');
      return;
    }

    const st = window.state;
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

    /* Stores — include user_id */
    const stores = await apiGet('/stores.php');
    if (stores.ok && stores.data.stores?.length) {
      const fallbackColors = ['#1E4332','#0284C7','#15803D','#B45309','#F85606'];
      const dbStores = stores.data.stores.map(s => {
        const col = fallbackColors[s.id % fallbackColors.length] || '#1E4332';
        const localStore = (st.stores || []).find(x => x.id === s.id);
        const mappedStatus = s.status === 'approved' ? 'verified' : (s.status === 'rejected' ? 'rejected' : (s.status === 'pending' ? 'pending' : (localStore?.verification_status || 'verified')));
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
          status: s.status || 'approved',
          is_published: s.is_published ?? 1,
          verification_status: mappedStatus,
          verification_documents: localStore?.verification_documents || s.verification_documents || null,
          verified_at: s.status === 'approved' ? (localStore?.verified_at || s.created_at) : null,
          rejection_reason: localStore?.rejection_reason || null,
        };
      });

      // Merge DB stores while preserving any local store with user_id
      const dbStoreIds = new Set(dbStores.map(x => x.id));
      const localOnly = (st.stores || []).filter(x => !dbStoreIds.has(x.id));
      st.stores = [...dbStores, ...localOnly];
    }

    /* Products — KEEP original images if they are real URLs */
    const prods = await apiGet('/products.php?limit=60');
    if (prods.ok && prods.data.products?.length) {
      const dbIds = new Set(prods.data.products.map(p => p.id));
      const mappedDbProds = prods.data.products.map(p => {
        const orig = origByid[p.id];
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
          rating:       p.avg_rating != null ? parseFloat(p.avg_rating) : 0,
          reviews_count:p.review_count != null ? parseInt(p.review_count) : 0,
          delivery_charge: orig?.delivery_charge ?? 60,
          free_delivery:   orig?.free_delivery   ?? 0,
        };
      });

      // Preserve newly created products in local storage that have not yet synced or are awaiting API refresh
      const localOnly = (st.products || []).filter(p => !dbIds.has(p.id) && p.id > 6);
      st.products = [...localOnly, ...mappedDbProds];
    }

    /* Session check — if logged in on server, sync user and related data */
    if (ping.ok && ping.data.logged_in && ping.data.user) {
      const u = ping.data.user;
      const stRow = ping.data.store;
      const rdRow = ping.data.rider;

      localStorage.setItem('HAAT_API_USER_ID', String(u.id));
      localStorage.setItem('HAAT_API_ROLE', u.role);

      const existingIdx = st.users.findIndex(x => String(x.id) === String(u.id) || x.email.toLowerCase() === u.email.toLowerCase());
      const userObj = {
        id: u.id,
        name: u.name,
        email: u.email,
        phone: u.phone || '',
        role: u.role,
        status: 'active',
        store_id: stRow ? stRow.id : (u.store_id || null)
      };
      if (existingIdx >= 0) st.users[existingIdx] = { ...st.users[existingIdx], ...userObj };
      else st.users.push(userObj);

      if (stRow) {
        const sIdx = st.stores.findIndex(s => String(s.id) === String(stRow.id) || String(s.user_id) === String(u.id));
        const sObj = {
          id: stRow.id,
          user_id: u.id,
          store_name: stRow.store_name,
          store_slug: stRow.store_slug,
          description: stRow.description || 'Official HAAT merchant store.',
          logo_text: stRow.store_name.slice(0, 3).toUpperCase(),
          primary_color: '#F85606',
          banner_gradient: 'linear-gradient(135deg, #F85606 0%, #7C2D12 100%)',
          address: stRow.address || 'Dhaka',
          district: stRow.district || 'Dhaka',
          division: stRow.division || 'Dhaka',
          postal_code: '1205',
          latitude: parseFloat(stRow.latitude) || 23.75,
          longitude: parseFloat(stRow.longitude) || 90.39,
          delivery_charge: 60,
          free_delivery: 0,
          auto_greeting: `Assalamu Alaikum! Welcome to ${stRow.store_name}.`,
          status: 'active',
          is_published: 1,
          verification_status: 'verified'
        };
        if (sIdx >= 0) st.stores[sIdx] = { ...st.stores[sIdx], ...sObj };
        else st.stores.push(sObj);
      }

      if (rdRow) {
        const rIdx = st.riders.findIndex(r => String(r.id) === String(rdRow.id) || String(r.user_id) === String(u.id));
        const rObj = {
          id: rdRow.id,
          user_id: u.id,
          name: u.name,
          phone: u.phone || (rdRow.phone || ''),
          vehicle_type: 'Motorcycle',
          hub: 'Dhaka Metro',
          status: 'active',
          current_lat: 23.7800,
          current_lon: 90.4100
        };
        if (rIdx >= 0) st.riders[rIdx] = { ...st.riders[rIdx], ...rObj };
        else st.riders.push(rObj);
      }

      st.activeRole = u.role;
      st.currentUserId = u.id;
      st.currentUser = userObj;
      if (stRow) st.currentUser.name = stRow.store_name;

      if (u.role === 'customer') {
        syncCustomerOrders(u.id);
        syncWishlist(u.id);
        syncAddresses(u.id);
      } else if (u.role === 'seller' && stRow) {
        syncSellerOrders(stRow.id);
      } else if (u.role === 'admin') {
        const uRes = await apiGet('/users.php?limit=50');
        if (uRes.ok && Array.isArray(uRes.data?.users)) {
          const dbUsers = uRes.data.users.map(ux => ({
            id: ux.id,
            name: ux.name,
            email: ux.email,
            phone: ux.phone || '',
            role: ux.role,
            status: ux.status || 'active'
          }));
          const existingIds = new Set(dbUsers.map(x => x.id));
          st.users = [...dbUsers, ...(st.users || []).filter(x => !existingIds.has(x.id))];
        }
      }
    }

    /* Notifications */
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

    /* Product Reviews — Sync from MySQL */
    try {
      const revRes = await apiGet('/reviews.php?limit=100');
      if (revRes.ok && Array.isArray(revRes.data?.reviews) && revRes.data.reviews.length) {
        const apiRevs = revRes.data.reviews.map(r => ({
          id: r.id,
          product_id: r.product_id,
          user_id: r.user_id,
          user_name: r.user_name || r.reviewer_name || 'Verified Buyer',
          rating: parseInt(r.rating || 5),
          comment: r.comment || '',
          order_number: '',
          verified_purchase: 1,
          created_at: (r.created_at || '').slice(0, 10) || new Date().toISOString().slice(0, 10)
        }));
        const apiRevIds = new Set(apiRevs.map(x => x.id));
        st.reviews = [...apiRevs, ...(st.reviews || []).filter(x => !apiRevIds.has(x.id))];
      }
    } catch (_) {}

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

    loadApiData().catch(() => {
      requestAnimationFrame(postRender);
    });
  });

  window.haatApiSync = {
    saveProduct:   (p) => p.id && apiPut(`/products.php?id=${p.id}`, p).catch(() => {}),
    deleteProduct: (pid) => apiDel(`/products.php?id=${pid}`).catch(() => {}),
    saveOrder:     (o) => o.id && apiPut(`/orders.php?id=${o.id}`, { order_status: o.order_status }).catch(() => {}),
    saveInventory: (pid, qty) => apiPut(`/inventory.php?product_id=${pid}`, { quantity: qty }).catch(() => {}),
    approveStore:  (sid) => apiPut(`/stores.php?action=approve&id=${sid}`).catch(() => {}),
    rejectStore:   (sid) => apiPut(`/stores.php?action=reject&id=${sid}`).catch(() => {}),
    syncCustomerOrders,
    syncSellerOrders,
    syncWishlist,
    syncAddresses
  };

  console.info('[HAAT Bridge] v3 loaded — dynamic MySQL integration active');

})();
