/**
 * HAAT (হাট) — Global • Regional • Artisanal
 * Multi-Vendor Marketplace & HATEX Dedicated Logistics Platform
 * Architecture: 21 Tables Schema with Option 2 Internal Coordinate Distance Calculation
 */

(function () {
  'use strict';

  /* =========================================================================
     1. UTILITY FUNCTIONS & HAVERSINE DISTANCE FORMULA (Option 2)
     ========================================================================= */

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  window.$ = $;
  window.$$ = $$;

  const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    }[c]));

  const money = (n) => '৳' + Number(n?.finalPrice !== undefined ? n.finalPrice : (n || 0)).toLocaleString('en-BD');

  function getDisplayRoleName(role) {
    if (role === 'admin') return 'Admin';
    if (role === 'hatex') return 'HATEX';
    if (role === 'rider') {
      const r = (state.riders || []).find((x) => x.user_id === state.currentUser?.id || (state.currentUser && x.name === state.currentUser.name));
      return r?.name || state.currentUser?.name || (state.riders && state.riders[0]?.name) || 'Rider';
    }
    if (role === 'seller') {
      const st = typeof getSellerOwnStore === 'function' ? getSellerOwnStore() : null;
      return st?.store_name || state.currentUser?.name || 'Seller Store';
    }
    if (role === 'customer') return state.currentUser?.name || 'Customer';
    return role ? role.charAt(0).toUpperCase() + role.slice(1) : 'User';
  }
  window.getDisplayRoleName = getDisplayRoleName;

  const STORAGE_KEY = 'HAAT_DBMS_STATE_V2';

  function readStorage(key, fallback) {
    try {
      const data = localStorage.getItem(STORAGE_KEY + '_' + key);
      return data !== null ? JSON.parse(data) : fallback;
    } catch {
      return fallback;
    }
  }

  function writeStorage(key, value) {
    try {
      localStorage.setItem(STORAGE_KEY + '_' + key, JSON.stringify(value));
    } catch {}
  }

  /**
   * Option 2: Internal Haversine Distance Calculation Formula
   * Mirrors the MySQL Stored Function: haversine_distance(lat1, lon1, lat2, lon2)
   * Formula: d = 2 * R * asin(sqrt(sin²(Δlat/2) + cos(lat1)*cos(lat2)*sin²(Δlon/2)))
   * Earth Radius = 6,371 km
   */
  function haversineDistance(lat1, lon1, lat2, lon2) {
    if (lat1 == null || lon1 == null || lat2 == null || lon2 == null) return 0;
    const nLat1 = Number(lat1), nLon1 = Number(lon1), nLat2 = Number(lat2), nLon2 = Number(lon2);
    if (isNaN(nLat1) || isNaN(nLon1) || isNaN(nLat2) || isNaN(nLon2)) return 0;
    const R = 6371; // Earth's mean radius in km
    const dLat = ((nLat2 - nLat1) * Math.PI) / 180;
    const dLon = ((nLon2 - nLon1) * Math.PI) / 180;
    const rLat1 = (nLat1 * Math.PI) / 180;
    const rLat2 = (nLat2 * Math.PI) / 180;

    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(rLat1) * Math.cos(rLat2) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const d = R * c;
    return Math.round(d * 10) / 10; // 1 decimal place (e.g. 4.2 km)
  }
  window.haversineDistance = haversineDistance;

  /* =========================================================================
     2. MOCK DATABASE (21 Tables Structure Aligned with haat.sql)
     ========================================================================= */

  // 1. USERS (Clean role names: Admin, HATEX, Rider's name, Customer's name, Seller's store name)
  const defaultUsers = [
    { id: 1, name: 'Rahim Sakib', email: 'customer@haat.com.bd', role: 'customer', phone: '01711223344', status: 'active', password: 'haat2026' },
    { id: 2, name: 'ABC Fashion Store', email: 'seller@abc-fashion.com', role: 'seller', phone: '01811223344', status: 'active', password: 'haat2026' },
    { id: 3, name: 'XYZ Electronics & Gadgets', email: 'seller@xyz-electronics.com', role: 'seller', phone: '01911223344', status: 'active', password: 'haat2026' },
    { id: 4, name: 'Admin', email: 'admin@haat.com.bd', role: 'admin', phone: '01511223344', status: 'active', password: 'haat2026' },
    { id: 5, name: 'HATEX', email: 'logistics@hatex.com.bd', role: 'logistics', phone: '01611223344', status: 'active', password: 'haat2026' },
    { id: 6, name: 'Tareq Ahmed', email: 'tareq@hatex.com.bd', role: 'rider', phone: '01822334455', status: 'active', password: 'haat2026' },
    { id: 7, name: 'Sumon Mia', email: 'sumon@hatex.com.bd', role: 'rider', phone: '01933445566', status: 'active', password: 'haat2026' }
  ];

  // 2. STORES (Seller Mini-Stores with internal coordinates & seller-controlled delivery charge / free delivery / auto-greeting)
  const defaultStores = [
    {
      id: 1,
      user_id: 2,
      store_name: 'ABC Fashion Store',
      store_slug: 'abc-fashion',
      description: 'Handcrafted Heritage Dhakai Jamdani, Premium Combed Cotton Panjabis & Artisan Wear.',
      logo_text: 'ABC',
      primary_color: '#1E4332',
      banner_gradient: 'linear-gradient(135deg, #1E4332 0%, #0F231A 100%)',
      address: 'Shop 14, Noor Market, Farmgate',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1215',
      latitude: 23.757000,
      longitude: 90.389000,
      delivery_charge: 60,
      free_delivery: 0,
      auto_greeting: 'Assalamu Alaikum! Welcome to ABC Fashion Store. Thank you for reaching out to us! How can we help you today?',
      status: 'active',
      is_published: 1,
      verification_status: 'verified',
      verified_at: '2026-09-18 14:00',
      verification_documents: {
        trade_license_no: 'TRAD/DNCC/019284/2025',
        trade_license_doc: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80',
        nid_no: '19892691234567890',
        nid_doc_front: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
        nid_doc_back: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
        tin_no: '839201948201',
        tin_doc: 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80',
        bank_doc: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=800&q=80',
        notes: 'Handloom & Fashion heritage merchant trade verification.'
      }
    },
    {
      id: 2,
      user_id: 3,
      store_name: 'XYZ Electronics & Gadgets',
      store_slug: 'xyz-electronics',
      description: 'Official flagship tech store: AMOLED smartwatches, ANC wireless earbuds & accessories.',
      logo_text: 'XYZ',
      primary_color: '#0284C7',
      banner_gradient: 'linear-gradient(135deg, #0369A1 0%, #082F49 100%)',
      address: 'Plot 45, Wireless Gate, Mohakhali',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1212',
      latitude: 23.778000,
      longitude: 90.398000,
      delivery_charge: 70,
      free_delivery: 0,
      auto_greeting: 'Hello & Welcome to XYZ Electronics & Gadgets! All our gadgets include official warranty. How may we assist you?',
      status: 'active',
      is_published: 1,
      verification_status: 'verified',
      verified_at: '2026-09-18 16:40',
      verification_documents: {
        trade_license_no: 'TRAD/DSCC/058291/2025',
        trade_license_doc: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80',
        nid_no: '19912691234567891',
        nid_doc_front: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
        nid_doc_back: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
        tin_no: '918273645102',
        tin_doc: 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80',
        bank_doc: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=800&q=80',
        notes: 'Flagship consumer electronics importer & authorized warranty distributor.'
      }
    },
    {
      id: 3,
      user_id: 8,
      store_name: 'Fresh Harvest Haat',
      store_slug: 'fresh-harvest',
      description: 'Wild Sundarbans mangrove raw honey, Dinajpur aromatic Kalijira rice & organic cold-pressed oils.',
      logo_text: 'FHH',
      primary_color: '#15803D',
      banner_gradient: 'linear-gradient(135deg, #15803D 0%, #052E16 100%)',
      address: 'Bazar Road, Hemayetpur, Savar',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1340',
      latitude: 23.848000,
      longitude: 90.267000,
      delivery_charge: 50,
      free_delivery: 1,
      auto_greeting: 'Welcome to Fresh Harvest Haat! We deliver 100% pure organic village harvests. Let us know what you need!',
      status: 'active',
      is_published: 1,
      verification_status: 'unverified',
      verification_documents: null
    },
    {
      id: 4,
      user_id: 9,
      store_name: 'Dhamrai Bell Metal & Crafts',
      store_slug: 'dhamrai-crafts',
      description: 'Centuries-old lost-wax bell metal (Kasha) handicrafts, thali sets & brass decor.',
      logo_text: 'DMC',
      primary_color: '#B45309',
      banner_gradient: 'linear-gradient(135deg, #B45309 0%, #451A03 100%)',
      address: 'Rathkhola, Dhamrai Bazar',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1350',
      latitude: 23.918000,
      longitude: 90.210000,
      delivery_charge: 80,
      free_delivery: 0,
      auto_greeting: 'Welcome to Dhamrai Bell Metal & Crafts! Handcrafted heritage metalware from Dhamrai artisans.',
      status: 'active',
      is_published: 1,
      verification_status: 'pending',
      verification_submitted_at: '2026-09-29 11:30',
      verification_documents: {
        trade_license_no: 'TRAD/DHAM/004812/2026',
        trade_license_doc: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80',
        nid_no: '19842691234567892',
        nid_doc_front: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
        nid_doc_back: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
        tin_no: '748392018394',
        tin_doc: 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80',
        bank_doc: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=800&q=80',
        notes: 'Dhamrai bell metal artisan cottage industry registration documents submitted for official verification.'
      }
    }
  ];

  // 3. CUSTOMER ADDRESSES (Internal coordinates attached behind the scenes)
  const defaultAddresses = [
    {
      id: 1,
      user_id: 1,
      label: 'Home',
      name: 'Rahim Sakib',
      phone: '01711223344',
      address: 'House 12, Road 5, Mirpur 10',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1216',
      latitude: 23.806000,
      longitude: 90.368000,
      is_default: 1
    },
    {
      id: 2,
      user_id: 1,
      label: 'Office',
      name: 'Rahim Sakib',
      phone: '01711223344',
      address: 'Level 5, Concord Tower, Gulshan 1',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1212',
      latitude: 23.780000,
      longitude: 90.418000,
      is_default: 0
    }
  ];

  // 4. CATEGORIES & 5. SUBCATEGORIES (Controlled by Admin)
  const defaultCategories = [
    {
      id: 1,
      name: 'Fashion & Apparel',
      slug: 'fashion',
      icon: 'bi-gem',
      subcategories: [
        { id: 101, name: "Men's Shirts & Panjabis", slug: 'mens-shirts' },
        { id: 102, name: "Women's Sarees & Kurtis", slug: 'womens-sarees' },
        { id: 103, name: 'Footwear & Sandals', slug: 'footwear' }
      ]
    },
    {
      id: 2,
      name: 'Electronics & Gadgets',
      slug: 'electronics',
      icon: 'bi-phone',
      subcategories: [
        { id: 201, name: 'Audio & Wireless Earbuds', slug: 'audio' },
        { id: 202, name: 'Smartwatches & Wearables', slug: 'smartwatches' },
        { id: 203, name: 'Computer Accessories', slug: 'computer-accessories' }
      ]
    },
    {
      id: 3,
      name: 'Groceries & Organic Food',
      slug: 'groceries',
      icon: 'bi-basket',
      subcategories: [
        { id: 301, name: 'Pure Honey & Organic Ghee', slug: 'honey-ghee' },
        { id: 302, name: 'Aromatic Rice & Pulses', slug: 'rice-pulses' },
        { id: 303, name: 'Village Spices & Dry Harvest', slug: 'spices' }
      ]
    },
    {
      id: 4,
      name: 'Artisanal & Traditional Crafts',
      slug: 'crafts',
      icon: 'bi-palette',
      subcategories: [
        { id: 401, name: 'Bell Metal & Brass Decor', slug: 'bell-metal' },
        { id: 402, name: 'Clay Pottery & Terracotta', slug: 'clay-pottery' }
      ]
    }
  ];

  // 6. BRANDS
  const defaultBrands = [
    { id: 1, name: 'Aarong Handloom', slug: 'aarong' },
    { id: 2, name: 'Apex Footwear', slug: 'apex' },
    { id: 3, name: 'TechZone Audio', slug: 'techzone' },
    { id: 4, name: 'Sundarbans Natural', slug: 'sundarbans' },
    { id: 5, name: 'Dhamrai Metal Heritage', slug: 'dhamrai-metal' }
  ];

  // 7. COLLECTIONS (Store-specific)
  const defaultCollections = [
    { id: 1, store_id: 1, name: 'Men’s Festive Heritage 2026', slug: 'mens-festive' },
    { id: 2, store_id: 1, name: 'Dhakai Jamdani Elegance', slug: 'jamdani-elegance' },
    { id: 3, store_id: 2, name: 'Next-Gen Wireless Sound', slug: 'wireless-sound' },
    { id: 4, store_id: 3, name: 'Pure Forest Harvest', slug: 'forest-harvest' }
  ];

  // 8. PRODUCTS & 9. INVENTORY
  const defaultProducts = [
    {
      id: 1,
      store_id: 1,
      subcategory_id: 101,
      brand_id: 1,
      collection_id: 1,
      name: 'Premium Combed Cotton Semi-Fitting Panjabi',
      slug: 'premium-cotton-panjabi',
      sku: 'PAN-COT-01',
      description: '100% fine combed breathable cotton with subtle jacquard weave embroidery along the collar and placket. Perfect for Eid and celebrations.',
      price: 2600,
      sale_price: 2100,
      delivery_charge: 60,
      free_delivery: 0,
      is_flash_sale: 1,
      image_1: 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Color',
      variant_1_value: 'Ivory White, Pastel Green',
      variant_2_name: 'Size',
      variant_2_value: 'M (40), L (42), XL (44)',
      is_featured: 1,
      rating: 4.8,
      reviews_count: 38
    },
    {
      id: 2,
      store_id: 1,
      subcategory_id: 102,
      brand_id: 1,
      collection_id: 2,
      name: 'Handloom Dhakai Jamdani Saree (80 Count)',
      slug: 'handloom-dhakai-jamdani',
      sku: 'JAM-DHK-80',
      description: 'Authentic 80-count cotton Dhakai Jamdani saree handwoven on traditional pit looms in Sonargaon with antique silver floral motifs.',
      price: 15500,
      sale_price: 13200,
      delivery_charge: 60,
      free_delivery: 1,
      is_flash_sale: 1,
      image_1: 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Color',
      variant_1_value: 'Crimson Red & Antique Gold',
      variant_2_name: 'Length',
      variant_2_value: '12 Haat Standard',
      is_featured: 1,
      rating: 4.9,
      reviews_count: 52
    },
    {
      id: 3,
      store_id: 2,
      subcategory_id: 201,
      brand_id: 3,
      collection_id: 3,
      name: 'Wireless ANC Active Noise-Cancelling Earbuds Pro',
      slug: 'wireless-anc-earbuds-pro',
      sku: 'EAR-ANC-PRO',
      description: 'Up to 35dB active hybrid noise cancellation, dual-mic ENC crystal calling, IPX5 sweat resistance and 32-hour playback.',
      price: 3200,
      sale_price: 2450,
      delivery_charge: 70,
      free_delivery: 0,
      is_flash_sale: 1,
      image_1: 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1572536147248-ac59a8abfa4b?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Color',
      variant_1_value: 'Midnight Black, Alpine White',
      variant_2_name: 'Case',
      variant_2_value: 'Matte Charging Case',
      is_featured: 1,
      rating: 4.7,
      reviews_count: 84
    },
    {
      id: 4,
      store_id: 2,
      subcategory_id: 202,
      brand_id: 3,
      collection_id: 3,
      name: 'AMOLED Smart Fitness Watch with Bluetooth Calling',
      slug: 'amoled-smart-fitness-watch',
      sku: 'WTC-AML-FIT',
      description: '1.43-inch Always-on AMOLED display, 24/7 heart rate & SpO2 tracking, 100+ sports modes with metallic rotating crown.',
      price: 4500,
      sale_price: 3490,
      delivery_charge: 70,
      free_delivery: 0,
      is_flash_sale: 0,
      image_1: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Strap Color',
      variant_1_value: 'Obsidian Black, Steel Silver',
      variant_2_name: 'Size',
      variant_2_value: '46mm Dial',
      is_featured: 1,
      rating: 4.6,
      reviews_count: 67
    },
    {
      id: 5,
      store_id: 3,
      subcategory_id: 301,
      brand_id: 4,
      collection_id: 4,
      name: 'Pure Wild Sundarbans Mangrove Honey (500g Glass Jar)',
      slug: 'wild-sundarbans-honey-500g',
      sku: 'HNY-SUN-500',
      description: 'Raw, unpasteurized natural honey collected directly by Mowals from deep Sundarbans Khalsi & Goran blossoms. 100% lab certified.',
      price: 1100,
      sale_price: 850,
      delivery_charge: 50,
      free_delivery: 1,
      is_flash_sale: 1,
      image_1: 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1558642452-9d2a7deb7f62?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1471943311424-646960669fbc?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Packaging',
      variant_1_value: '500g Sealed Glass Jar',
      variant_2_name: 'Harvest',
      variant_2_value: 'Spring Blossom 2026',
      is_featured: 1,
      rating: 4.9,
      reviews_count: 95
    },
    {
      id: 6,
      store_id: 3,
      subcategory_id: 302,
      brand_id: 4,
      collection_id: 4,
      name: 'Aromatic Dinajpur Kalijira Polao Rice (5kg Cloth Bag)',
      slug: 'dinajpur-kalijira-rice-5kg',
      sku: 'RCE-KLJ-5KG',
      description: 'Premium small-grain aromatic Kalijira rice cultivated in Dinajpur riverbeds. Naturally aged for optimal fluffiness and fragrance.',
      price: 850,
      sale_price: 720,
      delivery_charge: 50,
      free_delivery: 1,
      is_flash_sale: 0,
      image_1: 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1536304929831-ee1ca9d44906?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1516684732162-798a0062be99?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Weight',
      variant_1_value: '5kg Cloth Bag, 10kg Family Sack',
      variant_2_name: 'Variety',
      variant_2_value: 'Premium Aged Kalijira',
      is_featured: 0,
      rating: 4.8,
      reviews_count: 44
    },
    {
      id: 7,
      store_id: 4,
      subcategory_id: 401,
      brand_id: 5,
      collection_id: null,
      name: 'Hand-hammered Dhamrai Kasha (Bell Metal) Thali Set',
      slug: 'dhamrai-bell-metal-thali',
      sku: 'KAS-DHM-05',
      description: 'Traditional 5-piece bell metal feast dining set including heavy thali, two bati bowls, glass, and chamoch crafted by Dhamrai metalsmiths.',
      price: 6800,
      sale_price: 5900,
      delivery_charge: 80,
      free_delivery: 0,
      is_flash_sale: 0,
      image_1: 'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1615529328331-f8917597711f?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Finish',
      variant_1_value: 'Golden Polish, Antique Patina',
      variant_2_name: 'Pieces',
      variant_2_value: '5-Piece Royal Set',
      is_featured: 0,
      rating: 4.9,
      reviews_count: 29
    },
    {
      id: 8,
      store_id: 1,
      subcategory_id: 101,
      brand_id: 1,
      collection_id: 1,
      name: 'Linen Casual Band-Collar Kurta Shirt',
      slug: 'linen-casual-kurta-shirt',
      sku: 'SHR-LIN-02',
      description: 'Breathable pure natural linen fabric with wooden button details. Pre-shrunk and enzyme-washed for ultimate everyday comfort.',
      price: 1850,
      sale_price: 1450,
      delivery_charge: 60,
      free_delivery: 0,
      is_flash_sale: 0,
      image_1: 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=700&q=80',
      image_2: 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=700&q=80',
      image_3: 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?auto=format&fit=crop&w=700&q=80',
      variant_1_name: 'Color',
      variant_1_value: 'Beige Sand, Charcoal Slate',
      variant_2_name: 'Size',
      variant_2_value: 'M, L, XL',
      is_featured: 0,
      rating: 4.5,
      reviews_count: 19
    }
  ];

  // 9. INVENTORY
  const defaultInventory = [
    { id: 1, product_id: 1, quantity: 28, reserved_quantity: 3 },
    { id: 2, product_id: 2, quantity: 8, reserved_quantity: 1 },
    { id: 3, product_id: 3, quantity: 45, reserved_quantity: 5 },
    { id: 4, product_id: 4, quantity: 20, reserved_quantity: 2 },
    { id: 5, product_id: 5, quantity: 50, reserved_quantity: 6 },
    { id: 6, product_id: 6, quantity: 60, reserved_quantity: 4 },
    { id: 7, product_id: 7, quantity: 0, reserved_quantity: 0 },
    { id: 8, product_id: 8, quantity: 15, reserved_quantity: 1 }
  ];

  // 12. COUPONS (Admin coupons target selected Category/Subcategory; Seller coupons target Store)
  const defaultCoupons = [
    {
      id: 1,
      store_id: null,
      category_id: 1, // Fashion & Apparel
      subcategory_id: null,
      code: 'HAAT100',
      discount_type: 'fixed',
      discount_value: 100,
      min_order: 1000,
      max_discount: 100,
      expiry_date: '2026-12-31',
      usage_limit: 1000,
      used_count: 142,
      is_active: 1
    },
    {
      id: 2,
      store_id: null,
      category_id: 2, // Electronics & Gadgets
      subcategory_id: 201, // Audio & Wireless Earbuds
      code: 'TECH10',
      discount_type: 'percent',
      discount_value: 10,
      min_order: 1500,
      max_discount: 500,
      expiry_date: '2026-12-31',
      usage_limit: 500,
      used_count: 88,
      is_active: 1
    },
    {
      id: 3,
      store_id: 1, // Store 1 specific coupon (ABC Fashion Store)
      category_id: null,
      subcategory_id: null,
      code: 'ABC20',
      discount_type: 'percent',
      discount_value: 20,
      min_order: 2000,
      max_discount: 800,
      expiry_date: '2026-12-31',
      usage_limit: 200,
      used_count: 31,
      is_active: 1
    },
    {
      id: 4,
      store_id: null,
      category_id: 3, // Groceries & Organic Food
      subcategory_id: null,
      code: 'VILLAGE15',
      discount_type: 'percent',
      discount_value: 15,
      min_order: 800,
      max_discount: 300,
      expiry_date: '2026-12-31',
      usage_limit: 300,
      used_count: 54,
      is_active: 1
    },
    {
      id: 5,
      store_id: null,
      category_id: 4, // Artisanal & Traditional Crafts
      subcategory_id: null,
      code: 'CRAFT50',
      discount_type: 'fixed',
      discount_value: 50,
      min_order: 500,
      max_discount: 50,
      expiry_date: '2026-12-31',
      usage_limit: 400,
      used_count: 19,
      is_active: 1
    }
  ];

  // 12B. ADMIN CAMPAIGNS (Free Delivery on Selected Category/Subcategory & Flash Sale / Discount Campaigns)
  const defaultCampaigns = [
    {
      id: 1,
      name: 'Groceries & Honey Free Delivery Campaign',
      campaign_type: 'free_delivery', // 'free_delivery' | 'flash_discount'
      category_id: 3, // Groceries & Organic Food
      subcategory_id: null, // All subcategories in Groceries
      discount_percent: 0,
      min_order: 0,
      badge_text: 'FREE DELIVERY CAMPAIGN',
      is_active: 1
    },
    {
      id: 2,
      name: 'Audio Earbuds Free Delivery Fest',
      campaign_type: 'free_delivery',
      category_id: 2,
      subcategory_id: 201, // Audio & Wireless Earbuds
      discount_percent: 0,
      min_order: 1000,
      badge_text: 'FREE DELIVERY ON AUDIO',
      is_active: 1
    },
    {
      id: 3,
      name: 'Mega Heritage Fashion Flash Sale',
      campaign_type: 'flash_discount',
      category_id: 1,
      subcategory_id: 101,
      discount_percent: 15,
      min_order: 0,
      badge_text: 'FLASH SALE 15% EXTRA',
      is_active: 1
    }
  ];

  // 12C. ADMIN CONTROLLABLE HERO BANNER SLIDES (Daraz-style Multi-Slide Carousel)
  const defaultBanners = [
    {
      id: 1,
      title: 'SHOP EVERYTHING IN ONE PLACE',
      subtitle: 'From authentic Dhakai Jamdani and village harvests to flagship electronics — delivered nationwide by HATEX Express.',
      badge: 'NATIONWIDE MULTI-VENDOR MARKETPLACE',
      cta_text: 'Explore Marketplace',
      target_link: '#/products',
      image_url: 'village_banner.webp',
      is_active: 1
    },
    {
      id: 2,
      title: 'MEGA FESTIVE FLASH SALE — UP TO 40% OFF',
      subtitle: 'Exclusive discounts on Heritage Fashion, Jamdani Sarees & Panjabis. Collect vouchers & save instantly at checkout!',
      badge: 'MEGA SALE CAMPAIGN',
      cta_text: 'Shop Fashion Deals',
      target_link: '#/category/fashion',
      image_url: 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1400&q=80',
      is_active: 1
    },
    {
      id: 3,
      title: 'FREE DELIVERY FESTIVAL ON GROCERIES & AUDIO',
      subtitle: 'Enjoy ৳0 HATEX Express shipping on selected categories & subcategories! Admin campaign live now.',
      badge: 'FREE DELIVERY CAMPAIGN',
      cta_text: 'Claim Free Delivery',
      target_link: '#/category/groceries',
      image_url: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1400&q=80',
      is_active: 1
    },
    {
      id: 4,
      title: 'NEXT-GEN GADGETS & SMART WEARABLES',
      subtitle: 'ANC Wireless Earbuds & AMOLED Smartwatches with official warranty and 10% TECH10 category voucher.',
      badge: 'TECH FEST 2026',
      cta_text: 'Browse Electronics',
      target_link: '#/category/electronics',
      image_url: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1400&q=80',
      is_active: 1
    }
  ];

  // 12D. READYMADE QUICK MESSAGES (For Customer & Seller Only)
  const defaultQuickMessages = {
    customer: [
      'Is this product currently in stock?',
      'When will my order be shipped?',
      'Can you please pack my order securely?',
      'Do you offer free delivery on this item?'
    ],
    seller: [
      'Yes, this item is in stock and ready to ship!',
      'Your order is being packed and will be handed to HATEX today.',
      'Thank you for shopping at our store! Let us know if you need help.',
      'All our products go through strict quality check before dispatch.'
    ]
  };

  // 12E. DYNAMIC REPORTS (Submitted by Customer, Seller, Rider, HATEX -> Viewed & Resolved by Admin)
  const defaultReports = [
    {
      id: 1,
      reporter_id: 1,
      reporter_name: 'Rahim Sakib',
      reporter_role: 'customer',
      topic: 'Delivery Delay Inquiry',
      target_type: 'Order #HAAT10234',
      order_id: 1,
      priority: 'Normal',
      description: 'Please confirm when the rider will pick up my packaged order from the hub.',
      status: 'Pending',
      admin_reply: '',
      created_at: '2026-09-30 10:20 AM'
    },
    {
      id: 2,
      reporter_id: 2,
      reporter_name: 'ABC Fashion Store',
      reporter_role: 'seller',
      topic: 'Hub Pickup Request',
      target_type: 'Order #SO-101-ABC',
      order_id: 1,
      priority: 'High',
      description: 'Package SO-101-ABC is packed and ready for urgent HATEX rider pickup.',
      status: 'In Review',
      admin_reply: 'Coordinating with HATEX dispatch team now.',
      created_at: '2026-09-30 11:05 AM'
    },
    {
      id: 3,
      reporter_id: 6,
      reporter_name: 'Tareq Ahmed',
      reporter_role: 'rider',
      topic: 'Route Traffic Update',
      target_type: 'Mirpur 10 Route',
      order_id: 1,
      priority: 'Normal',
      description: 'Heavy rain near Farmgate checkpoint, taking alternate road to Mirpur.',
      status: 'Resolved',
      admin_reply: 'Noted, drive safely. Customer ETA updated.',
      created_at: '2026-09-30 11:40 AM'
    }
  ];

  // 17. RIDERS (fleet managed by HATEX)
  const defaultRiders = [
    { id: 1, user_id: 6, name: 'Tareq Ahmed', phone: '01822334455', vehicle_type: 'Motorcycle', hub: 'Dhaka Metro', status: 'active', current_lat: 23.757000, current_lon: 90.389000 },
    { id: 2, user_id: 7, name: 'Sumon Mia', phone: '01933445566', vehicle_type: 'Motorcycle', hub: 'Dhaka Metro', status: 'active', current_lat: 23.778000, current_lon: 90.398000 }
  ];

  // 13. ORDERS, 14. SELLER ORDERS, 15. ORDER ITEMS, 16. PAYMENTS & 18. DELIVERY TRACKING
  const defaultOrders = [
    {
      id: 1,
      order_number: 'HAAT10234',
      user_id: 1,
      coupon_id: 1,
      total_amount: 4550.00,
      shipping_cost: 130.00,
      discount_amount: 100.00,
      grand_total: 4580.00,
      order_status: 'packaged',
      shipping_name: 'Rahim Sakib',
      shipping_phone: '01711223344',
      shipping_address: 'House 12, Road 5, Mirpur 10',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1216',
      customer_lat: 23.806000,
      customer_lon: 90.368000,
      notes: 'Please call before delivery.',
      created_at: '2026-09-29 10:15:00',
      seller_orders: [
        {
          id: 101,
          order_id: 1,
          store_id: 1, // ABC Fashion Store
          seller_order_number: 'SO-101-ABC',
          subtotal: 2100.00,
          shipping_cost: 60.00,
          discount_amount: 100.00,
          seller_total: 2060.00,
          status: 'packaged',
          assigned_rider_id: null,
          items: [
            {
              id: 1,
              seller_order_id: 101,
              product_id: 1,
              product_name: 'Premium Combed Cotton Semi-Fitting Panjabi',
              variant_name: 'Color: Ivory White, Size: L (42)',
              variant_value: 'Ivory / 42',
              unit_price: 2100.00,
              quantity: 1,
              subtotal: 2100.00
            }
          ],
          tracking: [
            {
              id: 1,
              status: 'pending',
              location: 'HAAT Online Order Hub',
              latitude: 23.806000,
              longitude: 90.368000,
              note: 'Customer order placed successfully.',
              updated_by: 'System',
              created_at: '10:15 AM'
            },
            {
              id: 2,
              status: 'order_accepted',
              location: 'ABC Fashion Store, Farmgate',
              latitude: 23.757000,
              longitude: 90.389000,
              note: 'Seller accepted order and verified stock.',
              updated_by: 'ABC Fashion Store',
              created_at: '10:25 AM'
            },
            {
              id: 3,
              status: 'packaged',
              location: 'ABC Fashion Store, Farmgate',
              latitude: 23.757000,
              longitude: 90.389000,
              note: 'Seller packed parcel for HATEX pickup.',
              updated_by: 'ABC Fashion Store',
              created_at: '10:45 AM'
            }
          ]
        },
        {
          id: 102,
          order_id: 1,
          store_id: 2, // XYZ Electronics
          seller_order_number: 'SO-102-XYZ',
          subtotal: 2450.00,
          shipping_cost: 70.00,
          discount_amount: 0.00,
          seller_total: 2520.00,
          status: 'packaged',
          assigned_rider_id: null,
          items: [
            {
              id: 2,
              seller_order_id: 102,
              product_id: 3,
              product_name: 'Wireless ANC Active Noise-Cancelling Earbuds Pro',
              variant_name: 'Midnight Black',
              variant_value: 'Black',
              unit_price: 2450.00,
              quantity: 1,
              subtotal: 2450.00
            }
          ],
          tracking: [
            {
              id: 5,
              status: 'pending',
              location: 'HAAT Digital Marketplace',
              latitude: 23.806000,
              longitude: 90.368000,
              note: 'Order confirmed and payment submitted.',
              updated_by: 'System',
              created_at: '10:15 AM'
            },
            {
              id: 6,
              status: 'packaged',
              location: 'XYZ Electronics Warehouse, Mohakhali',
              latitude: 23.778000,
              longitude: 90.398000,
              note: 'Packed and awaiting HATEX rider pickup.',
              updated_by: 'XYZ Electronics & Gadgets',
              created_at: '11:00 AM'
            }
          ]
        }
      ],
      payment: {
        method: 'bkash',
        transaction_reference: 'TRX987241852B',
        amount: 4580.00,
        status: 'verified',
        paid_at: '2026-09-29 10:16:00'
      }
    },
    {
      id: 2,
      order_number: 'HAAT10236',
      user_id: 1,
      coupon_id: null,
      total_amount: 7000.00,
      shipping_cost: 120.00,
      discount_amount: 0.00,
      grand_total: 7120.00,
      order_status: 'assigned_to_rider',
      shipping_name: 'Rahim Sakib',
      shipping_phone: '01711223344',
      shipping_address: 'House 12, Road 5, Mirpur 10',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1216',
      customer_lat: 23.806000,
      customer_lon: 90.368000,
      notes: 'Deliver afternoon.',
      created_at: '2026-09-30 11:30:00',
      seller_orders: [
        {
          id: 103,
          order_id: 2,
          store_id: 2,
          seller_order_number: 'SO-103-XYZ',
          subtotal: 7000.00,
          shipping_cost: 120.00,
          discount_amount: 0.00,
          seller_total: 7120.00,
          status: 'assigned_to_rider',
          assigned_rider_id: 6,
          items: [
            {
              id: 3,
              seller_order_id: 103,
              product_id: 4,
              product_name: 'AMOLED Smart Fitness Watch with Bluetooth Calling',
              variant_name: 'Silver Mesh',
              variant_value: 'Silver',
              unit_price: 3500.00,
              quantity: 2,
              subtotal: 7000.00
            }
          ]
        }
      ],
      payment: {
        method: 'cash_on_delivery',
        transaction_reference: 'COD-HAAT10236',
        amount: 7120.00,
        status: 'pending',
        paid_at: null
      }
    },
    {
      id: 3,
      order_number: 'HAAT10190',
      user_id: 1,
      coupon_id: null,
      total_amount: 2635.00,
      shipping_cost: 0.00,
      discount_amount: 0.00,
      grand_total: 2635.00,
      order_status: 'delivered',
      shipping_name: 'Rahim Sakib',
      shipping_phone: '01711223344',
      shipping_address: 'House 12, Road 5, Mirpur 10',
      district: 'Dhaka',
      division: 'Dhaka',
      postal_code: '1216',
      customer_lat: 23.806000,
      customer_lon: 90.368000,
      notes: 'Delivered to door.',
      created_at: '2026-09-22 14:00:00',
      seller_orders: [
        {
          id: 104,
          order_id: 3,
          store_id: 1,
          seller_order_number: 'SO-104-ABC',
          subtotal: 1785.00,
          shipping_cost: 0.00,
          discount_amount: 0.00,
          seller_total: 1785.00,
          status: 'delivered',
          assigned_rider_id: 6,
          items: [
            {
              id: 4,
              seller_order_id: 104,
              product_id: 1,
              product_name: 'Premium Combed Cotton Semi-Fitting Panjabi',
              variant_name: 'Ivory / 42',
              variant_value: 'Ivory / 42',
              unit_price: 1785.00,
              quantity: 1,
              subtotal: 1785.00
            }
          ]
        },
        {
          id: 105,
          order_id: 3,
          store_id: 3,
          seller_order_number: 'SO-105-FHH',
          subtotal: 850.00,
          shipping_cost: 0.00,
          discount_amount: 0.00,
          seller_total: 850.00,
          status: 'delivered',
          assigned_rider_id: 6,
          items: [
            {
              id: 5,
              seller_order_id: 105,
              product_id: 5,
              product_name: 'Pure Wild Sundarbans Mangrove Honey (500g Glass Jar)',
              variant_name: '500g Glass Jar',
              variant_value: '500g',
              unit_price: 850.00,
              quantity: 1,
              subtotal: 850.00
            }
          ]
        }
      ],
      payment: {
        method: 'bkash',
        transaction_reference: 'TRX918273645A',
        amount: 2635.00,
        status: 'verified',
        paid_at: '2026-09-22 14:05:00'
      }
    }
  ];

  // 19. PRODUCT REVIEWS
  const defaultReviews = [
    {
      id: 1,
      product_id: 1,
      user_id: 1,
      user_name: 'Rahim Sakib',
      rating: 5,
      comment: 'Superb fabric quality! The jacquard texture on the collar looks very sophisticated and pure cotton feels breathable.',
      created_at: '2026-09-20'
    },
    {
      id: 2,
      product_id: 3,
      user_id: 1,
      user_name: 'Tanzir Hasan',
      rating: 5,
      comment: 'Noise cancellation is surprisingly good for this price segment. Bass response is punchy.',
      created_at: '2026-09-24'
    }
  ];

  // 20. MESSAGES (Customer ↔ Seller / Rider, and Seller/Rider/HATEX ↔ Admin)
  const defaultMessages = [
    {
      id: 0,
      order_id: 1,
      channel_id: 'seller_1_order_1',
      sender_id: 2,
      sender_name: 'ABC Fashion Store',
      sender_role: 'seller',
      receiver_id: 1,
      receiver_name: 'Rahim Sakib',
      receiver_role: 'customer',
      message: 'Assalamu Alaikum! Welcome to ABC Fashion Store. Thank you for reaching out to us! How can we help you today?',
      created_at: '10:29 AM',
      is_read: 1,
      is_auto_greeting: 1
    },
    {
      id: 1,
      order_id: 1,
      channel_id: 'seller_1_order_1',
      sender_id: 1,
      sender_name: 'Rahim Sakib',
      sender_role: 'customer',
      receiver_id: 2,
      receiver_name: 'ABC Fashion Store',
      receiver_role: 'seller',
      message: 'Hello, when will my order SO-101-ABC ship?',
      created_at: '10:30 AM',
      is_read: 1
    },
    {
      id: 2,
      order_id: 1,
      channel_id: 'seller_1_order_1',
      sender_id: 2,
      sender_name: 'ABC Fashion Store',
      sender_role: 'seller',
      receiver_id: 1,
      receiver_name: 'Rahim Sakib',
      receiver_role: 'customer',
      message: 'Hello Rahim! Your Panjabi has been packaged and is ready for HATEX pickup. You can track it live in your dashboard.',
      created_at: '10:45 AM',
      is_read: 1
    },
    {
      id: 5,
      order_id: 1,
      channel_id: 'rider_1_order_1',
      sender_id: 6,
      sender_name: 'Tareq Ahmed',
      sender_role: 'rider',
      receiver_id: 1,
      receiver_name: 'Rahim Sakib',
      receiver_role: 'customer',
      message: 'Hello! I am Tareq Ahmed from HATEX. Once I pick up your parcel, I will head straight to Mirpur 10.',
      created_at: '12:00 PM',
      is_read: 1
    },
    {
      id: 6,
      order_id: 1,
      channel_id: 'seller_1_order_admin',
      sender_id: 4,
      sender_name: 'Admin',
      sender_role: 'admin',
      receiver_id: 2,
      receiver_name: 'ABC Fashion Store',
      receiver_role: 'seller',
      message: 'Hello ABC Fashion Store! Your store metrics look great this month. Please ensure all products have accurate stock counts.',
      created_at: '09:00 AM',
      is_read: 0
    }
  ];

  // 21. NOTIFICATIONS (Dynamic multi-role notifications)
  const defaultNotifications = [
    {
      id: 1,
      user_id: 1,
      target_role: 'customer',
      order_id: 1,
      title: 'Parcel Packaged (SO-101-ABC)',
      message: 'ABC Fashion Store has packed your order and is awaiting HATEX pickup.',
      type: 'order',
      created_at: '11:15 AM',
      is_read: 0
    },
    {
      id: 2,
      user_id: 1,
      target_role: 'customer',
      order_id: null,
      title: 'Category Vouchers Available',
      message: 'Collect HAAT100 or TECH10 vouchers on the homepage for instant category discounts at checkout!',
      type: 'promo',
      created_at: '09:00 AM',
      is_read: 1
    },
    {
      id: 3,
      user_id: 2,
      target_role: 'seller',
      order_id: 1,
      title: 'New Customer Order Received',
      message: 'Rahim Sakib placed order #SO-101-ABC for Traditional Panjabi.',
      type: 'order',
      created_at: '10:00 AM',
      is_read: 0
    },
    {
      id: 4,
      user_id: 6,
      target_role: 'rider',
      order_id: 1,
      title: 'Delivery Task Assigned',
      message: 'You have been assigned to deliver order #HAAT10234 to Dhanmondi.',
      type: 'rider',
      created_at: '10:30 AM',
      is_read: 0
    },
    {
      id: 5,
      user_id: 4,
      target_role: 'admin',
      order_id: 1,
      title: 'Dynamic Customer Report',
      message: 'Rahim Sakib logged a Normal priority report regarding order #HAAT10234.',
      type: 'report',
      created_at: '10:45 AM',
      is_read: 0
    },
    {
      id: 6,
      user_id: 5,
      target_role: 'hatex',
      order_id: 1,
      title: 'Hub Shipment Scheduled',
      message: 'Shipment #HAAT10234 is scheduled for dispatch from Dhaka Central Hub.',
      type: 'order',
      created_at: '11:00 AM',
      is_read: 0
    }
  ];

  /* =========================================================================
     3. APPLICATION STATE MANAGEMENT
     ========================================================================= */

  let state = {
    users: readStorage('users', defaultUsers),
    stores: readStorage('stores', defaultStores),
    addresses: readStorage('addresses', defaultAddresses),
    categories: readStorage('categories', defaultCategories),
    brands: readStorage('brands', defaultBrands),
    collections: readStorage('collections', defaultCollections),
    products: readStorage('products', defaultProducts),
    inventory: readStorage('inventory', defaultInventory),
    coupons: readStorage('coupons', defaultCoupons),
    campaigns: readStorage('campaigns', defaultCampaigns),
    banners: readStorage('banners', defaultBanners),
    quickMessages: readStorage('quickMessages', defaultQuickMessages),
    reports: readStorage('reports', defaultReports),
    riders: readStorage('riders', defaultRiders),
    orders: readStorage('orders', defaultOrders),
    reviews: readStorage('reviews', defaultReviews),
    messages: readStorage('messages', defaultMessages),
    notifications: readStorage('notifications', defaultNotifications),
    collectedCouponsByUser: readStorage('collectedCouponsByUser', {}),
    // Per-user isolated cart and wishlist (every account is individual)
    cartByUser: readStorage('cartByUser', {}),
    wishlistByUser: readStorage('wishlistByUser', {}),
    greetedChannels: readStorage('greetedChannels', ['seller_1_order_1']),

    // Active session (null = Guest / Logged out)
    activeRole: readStorage('activeRole', null), // null | 'customer' | 'seller' | 'admin' | 'hatex' | 'rider'
    currentUserId: readStorage('currentUserId', null),
    currentUser: null,
    lastAuthEmail: readStorage('lastAuthEmail', ''),
    // cart and wishlist are proxied below — do not add them here directly

    // View states
    appliedCoupon: readStorage('appliedCoupon', null),
    selectedPaymentMethod: 'cash_on_delivery',
    currentSlideIndex: 0,
    currentCouponSlideIndex: 0,
    autoSlideIntervalMs: 4000,
    searchQuery: '',
    selectedCategory: null,
    filterBrand: null,
    filterPriceMax: null,
    filterInStockOnly: false,
    authTab: 'login',
    registerRole: 'customer',
    appSettings: readStorage('appSettings', {
      siteTitle: 'HAAT — Global • Regional • Artisanal',
      commissionPct: 5,
      defaultDeliveryCharge: 60,
      autoSlideSeconds: 4,
      escrowEnabled: true
    })
  };
  window.state = state;

  // Normalize cached data from previous versions so names, stores, coupons, and campaigns are always consistent
  (function normalizeStateData() {
    state.users.forEach((u) => {
      if (u.role === 'admin') u.name = 'Admin';
      if (u.role === 'logistics' && (u.name.includes('Central') || u.name.includes('HATEX'))) u.name = 'HATEX';
      if (u.name.includes('(Rider')) u.name = u.name.replace(/\s*\(Rider.*?\)/gi, '').trim();
      if (!u.password) {
        const storedPw = typeof localStorage !== 'undefined' ? localStorage.getItem('HAAT_PW_' + (u.email || '').toLowerCase()) : null;
        u.password = storedPw || 'haat2026';
      }
    });
    if (state.activeRole && !state.currentUserId && state.users.length) {
      const match = state.users.find((u) => u.role === state.activeRole);
      if (match) state.currentUserId = match.id;
    }
    state.stores.forEach((s) => {
      if (s.id === 1) { s.delivery_charge = 60; s.free_delivery = 0; }
      else if (s.id === 2) { s.delivery_charge = 70; s.free_delivery = 0; }
      else if (s.id === 3) { s.delivery_charge = 50; s.free_delivery = 1; }
      else if (s.id === 4) { s.delivery_charge = 80; s.free_delivery = 0; }
      else {
        if (!s.delivery_charge || isNaN(s.delivery_charge) || s.delivery_charge <= 0) s.delivery_charge = 60;
        if (s.free_delivery === undefined) s.free_delivery = 0;
      }
      if (!s.auto_greeting) {
        s.auto_greeting = `Assalamu Alaikum! Welcome to ${s.store_name}. Thank you for contacting us! How can we help you today?`;
      }
      if (s.id === 3 && s.user_id === 2) s.user_id = 8;
      if (s.id === 4 && s.user_id === 2) s.user_id = 9;

      // Stores are automatically open & active without needing prior admin approval
      s.status = 'active';
      s.is_published = s.is_published !== undefined ? s.is_published : 1;

      // Verification / KYC status (requires admin document verification to approve)
      if (!s.verification_status) {
        if (s.id === 1 || s.id === 2) {
          s.verification_status = 'verified';
          s.verified_at = '2026-09-18 14:00';
          s.verification_documents = s.verification_documents || {
            trade_license_no: s.id === 1 ? 'TRAD/DNCC/019284/2025' : 'TRAD/DSCC/058291/2025',
            trade_license_doc: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80',
            nid_no: s.id === 1 ? '19892691234567890' : '19912691234567891',
            nid_doc_front: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
            nid_doc_back: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
            tin_no: s.id === 1 ? '839201948201' : '918273645102',
            tin_doc: 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80',
            bank_doc: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=800&q=80',
            notes: 'Official registered business entity with valid trade license.'
          };
        } else if (s.id === 4) {
          s.verification_status = 'pending';
          s.verification_submitted_at = '2026-09-29 11:30';
          s.verification_documents = s.verification_documents || {
            trade_license_no: 'TRAD/DHAM/004812/2026',
            trade_license_doc: 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80',
            nid_no: '19842691234567892',
            nid_doc_front: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
            nid_doc_back: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80',
            tin_no: '748392018394',
            tin_doc: 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80',
            bank_doc: 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=800&q=80',
            notes: 'Dhamrai bell metal artisan cottage industry registration documents submitted for official verification.'
          };
        } else {
          s.verification_status = 'unverified';
        }
      }
    });
    state.products.forEach((p) => {
      const st = state.stores.find((s) => s.id === p.store_id);
      if (p.delivery_charge === undefined || isNaN(p.delivery_charge)) p.delivery_charge = st ? st.delivery_charge : 60;
      if (p.free_delivery === undefined) p.free_delivery = st ? st.free_delivery : 0;
      if (p.id === 1) { p.free_delivery = 0; p.delivery_charge = 60; }
      if (p.id === 3) { p.delivery_charge = 70; }
    });
    state.coupons.forEach((c) => {
      if (!c.store_id && !c.category_id) {
        c.category_id = c.code === 'HAAT100' ? 1 : 2;
        if (c.code === 'EID2026') {
          c.code = 'TECH10';
          c.subcategory_id = 201;
          c.expiry_date = '2026-12-31';
        }
      }
    });
    // Remove any customer <-> admin chat messages from inbox (customers only report to admin)
    state.messages = state.messages.filter((m) => m.channel_id !== 'admin_support');

    // Repair/ensure valid total_amount, shipping_cost, and grand_total for all existing orders
    (state.orders || []).forEach((o) => {
      let itemsTotal = (o.seller_orders || []).reduce((sum, so) => sum + Number(so.subtotal || 0), 0);
      if (!itemsTotal && o.items) {
        itemsTotal = o.items.reduce((sum, it) => sum + Number(it.subtotal || (it.unit_price * it.quantity) || 0), 0);
      }
      if (!itemsTotal) itemsTotal = Number(o.total_amount || 0);

      let shipTotal = (o.seller_orders || []).reduce((sum, so) => sum + Number(so.shipping_cost || 0), 0);
      if (shipTotal === 0 && o.shipping_cost) shipTotal = Number(o.shipping_cost || 0);
      if (shipTotal === 0 && itemsTotal > 0 && o.order_status !== 'delivered') {
        shipTotal = 130; // Default fallback delivery charge (e.g. ৳60 + ৳70)
      }

      const discTotal = Number(o.discount_amount || 0);
      o.total_amount = itemsTotal || 2500;
      o.shipping_cost = shipTotal;
      o.grand_total = Math.max(0, o.total_amount + o.shipping_cost - discTotal);
      if (o.payment) {
        o.payment.amount = o.grand_total;
      }
      (o.seller_orders || []).forEach((so) => {
        if (!so.subtotal || isNaN(so.subtotal)) so.subtotal = (so.items || []).reduce((s, it) => s + Number(it.subtotal || 0), 0);
        if (so.shipping_cost === undefined || isNaN(so.shipping_cost) || (so.shipping_cost === 0 && o.order_status !== 'delivered' && so.store_id !== 3)) {
          so.shipping_cost = (so.store_id === 2 ? 70 : 60);
        }
        if (!so.seller_total || isNaN(so.seller_total) || so.seller_total === 0) {
          so.seller_total = Math.max(0, Number(so.subtotal) + Number(so.shipping_cost) - Number(so.discount_amount || 0));
        }
      });
    });
  })();

  function persist() {
    writeStorage('users', state.users);
    writeStorage('stores', state.stores);
    writeStorage('addresses', state.addresses);
    writeStorage('categories', state.categories);
    writeStorage('products', state.products);
    writeStorage('inventory', state.inventory);
    writeStorage('coupons', state.coupons);
    writeStorage('campaigns', state.campaigns);
    writeStorage('banners', state.banners);
    writeStorage('quickMessages', state.quickMessages);
    writeStorage('reports', state.reports);
    writeStorage('riders', state.riders);
    writeStorage('orders', state.orders);
    writeStorage('reviews', state.reviews);
    writeStorage('messages', state.messages);
    writeStorage('notifications', state.notifications);
    writeStorage('collectedCouponsByUser', state.collectedCouponsByUser);
    writeStorage('cartByUser', state.cartByUser);
    writeStorage('wishlistByUser', state.wishlistByUser);
    writeStorage('greetedChannels', state.greetedChannels);
    writeStorage('appliedCoupon', state.appliedCoupon);
    writeStorage('appSettings', state.appSettings);
    writeStorage('activeRole', state.activeRole);
    writeStorage('currentUserId', state.currentUserId);
    writeStorage('lastAuthEmail', state.lastAuthEmail);
    updateGlobalHeader();
  }

  function resolveCurrentUser() {
    if (!state.activeRole) {
      state.currentUser = null;
      state.currentUserId = null;
      return;
    }
    let user = null;
    if (state.currentUserId != null) {
      user = state.users.find((u) => String(u.id) === String(state.currentUserId));
    }
    if (user) {
      state.currentUser = user;
      if (user.role === 'seller') {
        const sellerStore = state.stores.find((s) => String(s.user_id) === String(user.id) || (user.store_id && String(s.id) === String(user.store_id)));
        if (sellerStore) state.currentUser.name = sellerStore.store_name;
      }
      return;
    }

    if (state.activeRole === 'customer') {
      const match = state.currentUserId ? state.users.find((u) => String(u.id) === String(state.currentUserId)) : null;
      state.currentUser = match || state.users.find((u) => u.role === 'customer') || state.users[0];
    } else if (state.activeRole === 'seller') {
      const match = state.currentUserId ? state.users.find((u) => String(u.id) === String(state.currentUserId)) : null;
      state.currentUser = match || state.users.find((u) => u.role === 'seller') || state.users[1];
      const sellerStore = state.stores.find((s) => String(s.user_id) === String(state.currentUser?.id) || (state.currentUser?.store_id && String(s.id) === String(state.currentUser?.store_id)));
      if (sellerStore) state.currentUser.name = sellerStore.store_name;
    } else if (state.activeRole === 'admin') {
      state.currentUser = state.users.find((u) => u.role === 'admin') || { id: 4, name: 'Admin', email: 'admin@haat.com.bd', role: 'admin', phone: '01511223344', password: 'haat2026' };
      state.currentUser.name = 'Admin';
    } else if (state.activeRole === 'hatex') {
      state.currentUser = state.users.find((u) => u.name === 'HATEX' || (u.role === 'logistics' && u.id === 5)) || { id: 5, name: 'HATEX', email: 'logistics@hatex.com.bd', role: 'logistics', phone: '01611223344', password: 'haat2026' };
      state.currentUser.name = 'HATEX';
    } else if (state.activeRole === 'rider') {
      const match = state.currentUserId ? state.users.find((u) => String(u.id) === String(state.currentUserId)) : null;
      const rUser = match || state.users.find((u) => u.role === 'rider' || u.id === 6) || state.users[5];
      if (rUser) rUser.name = rUser.name.replace(/\s*\(Rider.*?\)/gi, '').trim();
      state.currentUser = rUser;
    } else {
      state.currentUser = null;
    }
    if (state.currentUser) {
      state.currentUserId = state.currentUser.id;
    }
  }
  resolveCurrentUser();

  // ===== PER-USER DATA ISOLATION =====
  // Every account is individual — coupons, cart, wishlist are all isolated per user.

  // Normalize dicts
  if (!state.collectedCouponsByUser || typeof state.collectedCouponsByUser !== 'object' || Array.isArray(state.collectedCouponsByUser)) {
    state.collectedCouponsByUser = {};
  }
  if (!state.cartByUser || typeof state.cartByUser !== 'object' || Array.isArray(state.cartByUser)) {
    state.cartByUser = {};
  }
  if (!state.wishlistByUser || typeof state.wishlistByUser !== 'object' || Array.isArray(state.wishlistByUser)) {
    state.wishlistByUser = {};
  }

  // Migrate any legacy flat data into user-1 bucket for backwards-compat
  const legacyFlatCoupons = readStorage('collectedCoupons', null);
  if (Array.isArray(legacyFlatCoupons) && legacyFlatCoupons.length > 0 && Object.keys(state.collectedCouponsByUser).length === 0) {
    state.collectedCouponsByUser['1'] = legacyFlatCoupons;
  }
  const legacyCart = readStorage('cart', null);
  if (Array.isArray(legacyCart) && legacyCart.length > 0 && Object.keys(state.cartByUser).length === 0) {
    state.cartByUser['1'] = legacyCart;
  }
  const legacyWishlist = readStorage('wishlist', null);
  if (Array.isArray(legacyWishlist) && legacyWishlist.length > 0 && Object.keys(state.wishlistByUser).length === 0) {
    state.wishlistByUser['1'] = legacyWishlist;
  }

  // ---- COUPON PROXY ----
  function getCurrentUserCollectedCouponIds() {
    if (!state.currentUser || state.activeRole !== 'customer') return [];
    const uid = String(state.currentUser.id);
    if (!Array.isArray(state.collectedCouponsByUser[uid])) state.collectedCouponsByUser[uid] = [];
    return state.collectedCouponsByUser[uid];
  }
  window.getCurrentUserCollectedCouponIds = getCurrentUserCollectedCouponIds;

  function isCouponCollectedByCurrentUser(couponId) {
    if (!state.currentUser || state.activeRole !== 'customer') return false;
    return getCurrentUserCollectedCouponIds().includes(Number(couponId));
  }
  window.isCouponCollectedByCurrentUser = isCouponCollectedByCurrentUser;

  Object.defineProperty(state, 'collectedCoupons', {
    get() { return getCurrentUserCollectedCouponIds(); },
    set(val) {
      if (!state.currentUser || state.activeRole !== 'customer') return;
      const uid = String(state.currentUser.id);
      state.collectedCouponsByUser[uid] = Array.isArray(val) ? val : [];
    },
    configurable: true, enumerable: true
  });

  // ---- CART PROXY (per-user isolation) ----
  function getCurrentUserCartKey() {
    if (!state.currentUser) return 'guest';
    return String(state.currentUser.id);
  }

  function getCurrentUserCartArr() {
    const k = getCurrentUserCartKey();
    if (!Array.isArray(state.cartByUser[k])) state.cartByUser[k] = [];
    return state.cartByUser[k];
  }

  Object.defineProperty(state, 'cart', {
    get() { return getCurrentUserCartArr(); },
    set(val) {
      const k = getCurrentUserCartKey();
      state.cartByUser[k] = Array.isArray(val) ? val : [];
    },
    configurable: true, enumerable: true
  });

  // ---- WISHLIST PROXY (per-user isolation) ----
  function getCurrentUserWishlistArr() {
    const k = getCurrentUserCartKey(); // reuse same user-key helper
    if (!Array.isArray(state.wishlistByUser[k])) state.wishlistByUser[k] = [];
    return state.wishlistByUser[k];
  }

  Object.defineProperty(state, 'wishlist', {
    get() { return getCurrentUserWishlistArr(); },
    set(val) {
      const k = getCurrentUserCartKey();
      state.wishlistByUser[k] = Array.isArray(val) ? val : [];
    },
    configurable: true, enumerable: true
  });

  function showToast(message, type = 'info') {
    const t = $('#toast');
    if (!t) return;
    t.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill text-success' : 'info-circle-fill'}"></i> ${esc(message)}`;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3500);
  }
  window.showToast = showToast;

  function openModal(htmlContent) {
    const modal = $('#globalModal');
    const content = $('#modalContent');
    if (modal && content) {
      content.innerHTML = htmlContent;
      modal.classList.remove('hidden');
    }
  }

  function closeModal() {
    const modal = $('#globalModal');
    if (modal) modal.classList.add('hidden');
  }

  window.closeModal = closeModal;
  window.openModal = openModal;
  window.showToast = showToast;

  /* =========================================================================
     4. HEADER, NAVIGATION GUARDS & PERSONA SWITCHER
     ========================================================================= */

  function getSellerOwnStore() {
    if (state.activeRole !== 'seller') return null;
    let st = state.stores.find((s) => (state.currentUser && String(s.user_id) === String(state.currentUser.id)) || (state.currentUser?.store_id && String(s.id) === String(state.currentUser.store_id)));
    if (!st && state.currentUser) {
      st = state.stores.find((s) => s.store_name === state.currentUser.name);
    }
    if (!st) {
      if (state.currentUser?.id === 2 || state.currentUser?.email === 'seller@abc-fashion.com') st = state.stores[0];
      else if (state.currentUser?.id === 3 || state.currentUser?.email === 'seller@xyz-electronics.com') st = state.stores[1];
    }
    return st || null;
  }

  window.handleVisitStore = function (storeSlug) {
    location.hash = `#/store/${storeSlug}`;
  };

  window.updateGlobalHeader = function updateGlobalHeader() {
    resolveCurrentUser();
    const nameEl = $('#headerAccountName');
    const roleEl = $('#headerAccountRole');
    const avatarEl = $('#headerAvatar');
    const cartCountEl = $('#cartCount');
    const wishlistCountEl = $('#wishlistCount');
    const itemsContainer = $('#profileDropdownItems');

    const wishlistLink = $('#headerWishlistLink') || $('a[href="#/wishlist"]');
    const cartLink = $('#headerCartLink') || $('a[href="#/cart"]');
    const notifWrap = $('#notificationDropdownWrap');
    const searchContainer = $('#headerSearchContainer') || $('.search-container');
    const logoLink = $('#headerLogoLink') || $('.logo-link');
    const footerProps = $('#footerPropsBar');
    const footerCols = $('#footerMainColumns');
    const floatingReportBtn = $('#floatingReportBtn') || $('.floating-admin-chat-btn');

    const unreadMsgs = state.activeRole
      ? state.messages.filter((m) => m.receiver_role === state.activeRole && !m.is_read).length
      : 0;

    // 1. Visibility of Cart, Wishlist, Notifications, Search & Footer based on Role & Route
    const curHash = (typeof window !== 'undefined' && window.location?.hash ? window.location.hash : (typeof location !== 'undefined' ? location.hash : '#/')).toLowerCase();
    const cleanRoute = curHash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
    const isLandingPage = cleanRoute === '' || cleanRoute === '#' || curHash === '#/' || curHash === '' || curHash === '#';
    if (footerProps) {
      footerProps.style.setProperty('display', isLandingPage ? 'block' : 'none', 'important');
    }

    if (!state.activeRole || !state.currentUser) {
      // Guest: No Cart, No Notifications, No Wishlist, No Floating Report Button
      if (wishlistLink) wishlistLink.style.display = 'none';
      if (cartLink) cartLink.style.display = 'none';
      if (notifWrap) notifWrap.style.display = 'none';
      if (floatingReportBtn) floatingReportBtn.style.display = 'none';
      if (searchContainer) searchContainer.style.display = 'block';
      if (footerCols) footerCols.style.display = 'grid';
      if (logoLink) {
        logoLink.setAttribute('href', '#/');
        logoLink.setAttribute('title', 'HAAT Marketplace Home');
      }
    } else if (state.activeRole === 'admin') {
      // Admin: Refresh Dashboard on logo click
      if (wishlistLink) wishlistLink.style.display = 'none';
      if (cartLink) cartLink.style.display = 'none';
      if (notifWrap) notifWrap.style.display = 'inline-flex';
      if (floatingReportBtn) floatingReportBtn.style.display = 'none';
      if (searchContainer) searchContainer.style.display = 'block';
      if (footerCols) footerCols.style.display = 'grid';
      if (logoLink) {
        logoLink.setAttribute('href', '#/dash/admin');
        logoLink.setAttribute('title', 'Click to refresh Admin Dashboard');
      }
    } else if (state.activeRole === 'hatex') {
      // HATEX Logistics Hub: Refresh Dashboard on logo click
      if (wishlistLink) wishlistLink.style.display = 'none';
      if (cartLink) cartLink.style.display = 'none';
      if (notifWrap) notifWrap.style.display = 'inline-flex';
      if (floatingReportBtn) floatingReportBtn.style.display = 'grid';
      if (searchContainer) searchContainer.style.display = 'none';
      if (footerCols) footerCols.style.display = 'none';
      if (logoLink) {
        logoLink.setAttribute('href', '#/dash/hatex');
        logoLink.setAttribute('title', 'Click to refresh HATEX Logistics Hub');
      }
    } else if (state.activeRole === 'rider') {
      // Rider Delivery Queue: Refresh Dashboard on logo click
      if (wishlistLink) wishlistLink.style.display = 'none';
      if (cartLink) cartLink.style.display = 'none';
      if (notifWrap) notifWrap.style.display = 'inline-flex';
      if (floatingReportBtn) floatingReportBtn.style.display = 'grid';
      if (searchContainer) searchContainer.style.display = 'none';
      if (footerCols) footerCols.style.display = 'none';
      if (logoLink) {
        logoLink.setAttribute('href', '#/dash/rider');
        logoLink.setAttribute('title', 'Click to refresh Rider Delivery Queue');
      }
    } else if (state.activeRole === 'seller') {
      // Seller: Navigates to Landing Page on logo click
      if (wishlistLink) wishlistLink.style.display = 'none';
      if (cartLink) cartLink.style.display = 'none';
      if (notifWrap) notifWrap.style.display = 'inline-flex';
      if (floatingReportBtn) floatingReportBtn.style.display = 'grid';
      if (searchContainer) searchContainer.style.display = 'block';
      if (footerCols) footerCols.style.display = 'grid';
      if (logoLink) {
        logoLink.setAttribute('href', '#/');
        logoLink.setAttribute('title', 'HAAT Marketplace Home');
      }
    } else {
      // Customer: Full access to Cart, Wishlist, Notifications, Report
      if (wishlistLink) wishlistLink.style.display = 'inline-flex';
      if (cartLink) cartLink.style.display = 'inline-flex';
      if (notifWrap) notifWrap.style.display = 'inline-flex';
      if (floatingReportBtn) floatingReportBtn.style.display = 'grid';
      if (searchContainer) searchContainer.style.display = 'block';
      if (footerCols) footerCols.style.display = 'grid';
      if (logoLink) {
        logoLink.setAttribute('href', '#/');
        logoLink.setAttribute('title', 'HAAT Marketplace Home');
      }
    }

    if (cartCountEl) cartCountEl.textContent = state.cart.reduce((s, i) => s + i.quantity, 0);
    if (wishlistCountEl) wishlistCountEl.textContent = state.wishlist ? state.wishlist.length : 0;

    if (!state.activeRole || !state.currentUser) {
      if (nameEl) nameEl.textContent = 'Log In / Sign Up';
      if (roleEl) roleEl.innerHTML = '<span class="user-role-label">Welcome</span>';
      if (avatarEl) avatarEl.innerHTML = '<i class="bi bi-person"></i>';

      if (itemsContainer) {
        itemsContainer.innerHTML = `
          <a href="#/auth" class="profile-menu-item" onclick="window.toggleAccountMenu(false)">
            <div class="menu-item-icon-box bg-customer"><i class="bi bi-box-arrow-in-right"></i></div>
            <div class="menu-item-text">
              <strong>Log In / Sign Up</strong>
              <small>Access account or register role</small>
            </div>
          </a>
        `;
      }
    } else {
      const sellerStore = typeof getSellerOwnStore === 'function' ? getSellerOwnStore() : null;
      const cleanRiderName = (state.currentUser.name || 'Tareq Ahmed').replace(/\s*\(Rider.*?\)/gi, '').trim();

      const roleMap = {
        customer: {
          label: 'Customer',
          name: state.currentUser.name || 'Customer',
          avatar: (state.currentUser.name || 'C')[0].toUpperCase(),
          badgeClass: 'role-badge-customer',
          bgClass: 'bg-customer',
          dashUrl: '#/account',
          profileUrl: '#/account/profile',
          desc: 'Orders, Wishlist & Profile'
        },
        seller: {
          label: 'Seller',
          name: sellerStore?.store_name || state.currentUser?.name || 'Seller Store',
          avatar: (sellerStore?.store_name || state.currentUser?.name || 'S')[0].toUpperCase(),
          badgeClass: 'role-badge-seller',
          bgClass: 'bg-seller',
          dashUrl: '#/dash/seller',
          profileUrl: '#/dash/seller/store',
          desc: 'Store Studio & Orders'
        },
        rider: {
          label: 'Rider',
          name: cleanRiderName,
          avatar: (cleanRiderName || 'R')[0].toUpperCase(),
          badgeClass: 'role-badge-rider',
          bgClass: 'bg-rider',
          dashUrl: '#/dash/rider/dashboard',
          profileUrl: '#/dash/rider/settings',
          desc: 'Orders & Delivery Routes'
        },
        admin: {
          label: 'Admin',
          name: 'Admin',
          avatar: 'A',
          badgeClass: 'role-badge-admin',
          bgClass: 'bg-admin',
          dashUrl: '#/dash/admin/overview',
          profileUrl: '#/dash/admin/settings',
          desc: 'Platform Control Center'
        },
        hatex: {
          label: 'HATEX',
          name: 'HATEX',
          avatar: 'H',
          badgeClass: 'role-badge-hatex',
          bgClass: 'bg-hatex',
          dashUrl: '#/dash/hatex/dashboard',
          profileUrl: '#/dash/hatex/settings',
          desc: 'Logistics & Fleet Hub'
        }
      };

      const cur = roleMap[state.activeRole] || roleMap.customer;

      if (nameEl) nameEl.textContent = cur.name;
      if (roleEl) roleEl.innerHTML = `<span class="role-badge-tag ${cur.badgeClass}">${cur.label}</span>`;
      if (avatarEl) avatarEl.textContent = cur.avatar;

      if (itemsContainer) {
        itemsContainer.innerHTML = `
          <div class="profile-dropdown-header">
            <div class="dropdown-user-avatar" style="background:var(--haat-primary);">${cur.avatar}</div>
            <div class="dropdown-user-details">
              <strong>${esc(cur.name)}</strong>
              <span class="role-badge-tag ${cur.badgeClass}">${cur.label}</span>
            </div>
          </div>
          <div class="dropdown-menu-divider"></div>
          <a href="${cur.dashUrl}" class="profile-menu-item profile-dashboard-item" onclick="window.toggleAccountMenu(false)">
            <div class="menu-item-icon-box ${cur.bgClass}"><i class="bi bi-speedometer2"></i></div>
            <div class="menu-item-text">
              <strong>Dashboard</strong>
              <small>${cur.desc}</small>
            </div>
          </a>
          <a href="${cur.profileUrl}" class="profile-menu-item" onclick="window.toggleAccountMenu(false)">
            <div class="menu-item-icon-box"><i class="bi bi-person-gear"></i></div>
            <div class="menu-item-text">
              <strong>Settings</strong>
              <small>Manage account & preferences</small>
            </div>
          </a>
          <a href="#/messages" class="profile-menu-item" onclick="window.toggleAccountMenu(false)">
            <div class="menu-item-icon-box" style="background:#EFF6FF;color:#2563EB;position:relative;">
              <i class="bi bi-chat-dots-fill"></i>
              ${unreadMsgs > 0 ? `<span style="position:absolute;top:-4px;right:-4px;width:14px;height:14px;border-radius:50%;background:#DC2626;color:#fff;font-size:8px;font-weight:800;display:grid;place-items:center;">${unreadMsgs}</span>` : ''}
            </div>
            <div class="menu-item-text">
              <strong>Messages</strong>
              <small>${unreadMsgs > 0 ? `${unreadMsgs} unread message${unreadMsgs !== 1 ? 's' : ''}` : 'Inbox & communications'}</small>
            </div>
          </a>
          ${
            state.activeRole !== 'admin'
              ? `
            <div class="profile-menu-item" onclick="window.toggleAccountMenu(false); window.openAdminReportModal();">
              <div class="menu-item-icon-box" style="background:#FFF7ED;color:#EA580C;"><i class="bi bi-flag-fill"></i></div>
              <div class="menu-item-text">
                <strong>Report Issue</strong>
                <small>Submit dynamic report to Admin</small>
              </div>
            </div>
          `
              : ''
          }
          <div class="dropdown-menu-divider"></div>
          <div class="profile-menu-item profile-logout-item" onclick="window.toggleAccountMenu(false); window.handleLogout();">
            <div class="menu-item-icon-box icon-logout"><i class="bi bi-box-arrow-right"></i></div>
            <div class="menu-item-text">
              <strong style="color:#DC2626;">Log Out</strong>
              <small>Exit session</small>
            </div>
          </div>
        `;
      }
    }

    // Dynamic Notifications beside Profile icon
    const notifCountEl = $('#notificationCount');
    const unreadCountEl = $('#unreadNotifCount');
    const notifListEl = $('#notificationFlyoutList');

    const curRole = state.activeRole || 'customer';
    const roleNotifs = (state.notifications || []).filter((n) => {
      if (!n.target_role || n.target_role === 'all') return true;
      return n.target_role === curRole;
    });

    const unreadNotifs = roleNotifs.filter((n) => !n.is_read);
    if (notifCountEl) {
      notifCountEl.textContent = unreadNotifs.length;
      notifCountEl.style.display = unreadNotifs.length > 0 ? 'inline-block' : 'none';
    }
    if (unreadCountEl) {
      unreadCountEl.textContent = unreadNotifs.length;
    }

    if (notifListEl) {
      if (roleNotifs.length === 0) {
        notifListEl.innerHTML = `
          <div style="padding:28px 16px;text-align:center;color:var(--text-muted);font-size:12.5px;">
            <i class="bi bi-bell-slash" style="font-size:24px;display:block;margin-bottom:6px;opacity:0.5;"></i>
            No notifications for ${esc(getDisplayRoleName(curRole))} right now
          </div>
        `;
      } else {
        notifListEl.innerHTML = roleNotifs
          .map((n) => {
            const iconMap = {
              promo: 'bi-tag-fill',
              order: 'bi-box-seam',
              chat: 'bi-chat-dots-fill',
              report: 'bi-flag-fill',
              rider: 'bi-bicycle',
              system: 'bi-shield-check'
            };
            const iconClass = iconMap[n.type] || 'bi-box-seam';
            return `
              <div class="notification-item ${n.is_read ? '' : 'unread'}" onclick="window.handleNotificationClick(${n.id})">
                <div class="notif-icon-circle ${n.type || 'order'}">
                  <i class="bi ${iconClass}"></i>
                </div>
                <div class="notif-body">
                  <div class="notif-title">${esc(n.title)}</div>
                  <div class="notif-msg">${esc(n.message)}</div>
                  <div class="notif-time"><i class="bi bi-clock"></i> ${n.created_at || 'Recently'}</div>
                </div>
              </div>
            `;
          })
          .join('');
      }
    }
  }

  window.addNotification = function ({ title, message, type = 'order', target_role = 'customer', order_id = null, link = '' }) {
    state.notifications = state.notifications || [];
    const notif = {
      id: Date.now() + Math.floor(Math.random() * 1000),
      user_id: state.currentUser?.id || 1,
      target_role: target_role,
      order_id: order_id,
      link: link,
      title: title,
      message: message,
      type: type,
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      is_read: 0
    };
    state.notifications.unshift(notif);
    if (state.notifications.length > 50) state.notifications.pop();
    persist();
    updateGlobalHeader();
    return notif;
  };

  window.toggleNotificationMenu = function (force) {
    const flyout = $('#notificationFlyout');
    if (!flyout) return;
    if (force !== undefined) {
      flyout.classList.toggle('hidden', !force);
    } else {
      flyout.classList.toggle('hidden');
    }
    if (!flyout.classList.contains('hidden')) {
      window.toggleAccountMenu(false);
    }
  };

  window.markAllNotificationsRead = function () {
    const curRole = state.activeRole || 'customer';
    if (state.notifications) {
      state.notifications.forEach((n) => {
        if (!n.target_role || n.target_role === 'all' || n.target_role === curRole) {
          n.is_read = 1;
        }
      });
      persist();
      updateGlobalHeader();
      showToast('All notifications marked as read', 'success');
    }
  };

  window.handleNotificationClick = function (id) {
    const notif = (state.notifications || []).find((n) => n.id === id);
    if (notif) {
      notif.is_read = 1;
      persist();
      updateGlobalHeader();
      window.toggleNotificationMenu(false);
      if (notif.link) {
        location.hash = notif.link;
        return;
      }
      if (notif.type === 'chat') {
        location.hash = state.activeRole === 'customer' ? '#/account/messages' : '#/messages';
        return;
      }
      if (notif.order_id) {
        const order = state.orders.find((o) => o.id === notif.order_id);
        if (order) {
          if (state.activeRole === 'customer') {
            location.hash = `#/order/${order.order_number}`;
          } else if (state.activeRole === 'seller') {
            location.hash = `#/dash/seller/orders`;
          } else if (state.activeRole === 'rider') {
            location.hash = `#/dash/rider/orders`;
          } else {
            location.hash = `#/order/${order.order_number}`;
          }
          return;
        }
      }
      if (notif.type === 'report') {
        location.hash = state.activeRole === 'admin' ? '#/dash/admin/overview' : '#/account/reports';
        return;
      }
      render();
    }
  };

  window.handleLogoClick = function (e) {
    if (state.activeRole === 'admin') {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      if (location.hash === '#/dash/admin') {
        render();
      } else {
        location.hash = '#/dash/admin';
      }
      showToast('Admin Dashboard refreshed', 'info');
      return false;
    }
    if (state.activeRole === 'hatex') {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      if (location.hash === '#/dash/hatex') {
        render();
      } else {
        location.hash = '#/dash/hatex';
      }
      showToast('HATEX Logistics Hub refreshed', 'info');
      return false;
    }
    if (state.activeRole === 'rider') {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      if (location.hash === '#/dash/rider') {
        render();
      } else {
        location.hash = '#/dash/rider';
      }
      showToast('Rider Delivery Queue refreshed', 'info');
      return false;
    }
    if (state.activeRole === 'seller') {
      if (location.hash === '#/' || location.hash === '' || location.hash === '#') {
        if (e) {
          e.preventDefault();
          e.stopPropagation();
        }
        render();
        window.scrollTo(0, 0);
        return false;
      }
      location.hash = '#/';
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      return false;
    }
    return true;
  };

  /* =========================================================================
     DYNAMIC REPORT CENTER MODAL (Customer, Seller, Rider, HATEX -> Admin)
     ========================================================================= */

  window.openAdminReportModal = function () {
    if (!state.activeRole) {
      showToast('Please log in to submit a report.', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'admin') {
      location.hash = '#/dash/admin/reports';
      return;
    }

    const roleTopics = {
      customer: [
        'Order Delivery Delay',
        'Product Quality / Wrong Item Received',
        'Seller Store Dispute',
        'Rider Delivery Behavior',
        'Payment / Refund Issue',
        'Coupon / Campaign Issue'
      ],
      seller: [
        'HATEX Rider Pickup Delay',
        'Order Payment Settlement Issue',
        'Customer Return Dispute',
        'Store Catalog / Technical Issue'
      ],
      rider: [
        'Customer Unreachable at Destination',
        'Merchant Package Not Ready',
        'Route / Weather Obstruction',
        'Vehicle / Delivery Issue'
      ],
      hatex: [
        'Hub Consolidation Escalation',
        'Seller Packaging Compliance Issue',
        'Fleet Capacity Alert',
        'System Logistics Sync Issue'
      ]
    };

    const topics = roleTopics[state.activeRole] || roleTopics.customer;
    const myReports = (state.reports || []).filter((r) => r.reporter_role === state.activeRole);

    const ordersOptions = state.orders
      .map((o) => `<option value="Order #${esc(o.order_number)}">Order #${esc(o.order_number)} (${money(o.grand_total)})</option>`)
      .join('');
    const storesOptions = state.stores
      .map((s) => `<option value="Store: ${esc(s.store_name)}">Store: ${esc(s.store_name)}</option>`)
      .join('');
    const ridersOptions = state.riders
      .map((r) => `<option value="Rider: ${esc(r.name)}">Rider: ${esc(r.name)}</option>`)
      .join('');

    openModal(`
      <div style="padding:10px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;border-bottom:1px solid #E2E8F0;padding-bottom:12px;">
          <div style="width:44px;height:44px;border-radius:50%;background:#FFF7ED;color:#F85606;display:grid;place-items:center;font-size:20px;">
            <i class="bi bi-flag-fill"></i>
          </div>
          <div>
            <h3 style="font-size:17px;font-weight:800;color:#1E293B;margin:0;">Submit Dynamic Report to Admin</h3>
            <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Reporting as <strong>${esc(state.currentUser?.name || state.activeRole)}</strong> (${esc(state.activeRole.toUpperCase())})</p>
          </div>
        </div>

        <form onsubmit="event.preventDefault(); window.handleSendAdminReport(this);">
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
              <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Report Category / Reason</label>
              <select name="topic" required style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13px;background:#fff;">
                ${topics.map((t) => `<option value="${esc(t)}">${esc(t)}</option>`).join('')}
              </select>
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Priority Level</label>
              <select name="priority" style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13px;background:#fff;">
                <option value="Normal">Normal</option>
                <option value="High">High</option>
                <option value="Urgent">Urgent</option>
              </select>
            </div>
          </div>

          <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Related Order / Store / Rider</label>
            <select name="target_type" style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13px;background:#fff;">
              <option value="General Platform Report">General Platform Report</option>
              <optgroup label="Orders">${ordersOptions}</optgroup>
              <optgroup label="Stores">${storesOptions}</optgroup>
              <optgroup label="Riders">${ridersOptions}</optgroup>
            </select>
          </div>

          <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">Report Details</label>
            <textarea name="report_desc" rows="3" required placeholder="Describe the issue clearly so Admin can investigate and take action..." style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13px;resize:vertical;"></textarea>
          </div>

          <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:16px;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn-village-primary"><i class="bi bi-send-fill"></i> Submit Report</button>
          </div>
        </form>

        ${
          myReports.length
            ? `
          <div style="border-top:1px solid #E2E8F0;padding-top:14px;">
            <strong style="font-size:12.5px;color:#1E293B;display:block;margin-bottom:8px;">My Submitted Reports (${myReports.length})</strong>
            <div style="display:flex;flex-direction:column;gap:8px;max-height:180px;overflow-y:auto;">
              ${myReports
                .map(
                  (r) => `
                <div style="padding:10px 12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                    <strong>${esc(r.topic)} • <span style="color:#64748B;">${esc(r.target_type)}</span></strong>
                    <span class="status-badge ${r.status === 'Resolved' ? 'resolved' : r.status === 'In Review' ? 'in_review' : 'pending'}">${esc(r.status)}</span>
                  </div>
                  <div style="color:#475569;">${esc(r.description)}</div>
                  ${r.admin_reply ? `<div style="margin-top:5px;padding:5px 8px;background:#ECFDF5;border-left:3px solid #10B981;color:#065F46;font-size:11.5px;"><strong>Admin Resolution:</strong> ${esc(r.admin_reply)}</div>` : ''}
                </div>
              `
                )
                .join('')}
            </div>
          </div>
        `
            : ''
        }
      </div>
    `);
  };

  window.handleSendAdminReport = function (form) {
    const fd = new FormData(form);
    const topic = fd.get('topic') || 'General Report';
    const priority = fd.get('priority') || 'Normal';
    const targetType = fd.get('target_type') || 'General';
    const descText = (fd.get('report_desc') || '').trim();

    if (!descText) return;

    const reporterName =
      state.activeRole === 'seller'
        ? getSellerOwnStore()?.store_name || 'ABC Fashion Store'
        : state.currentUser?.name || state.activeRole;

    const newReport = {
      id: Date.now(),
      reporter_id: state.currentUser?.id || 1,
      reporter_name: reporterName,
      reporter_role: state.activeRole || 'customer',
      topic: topic,
      target_type: targetType,
      order_id: state.orders[0]?.id || 1,
      priority: priority,
      description: descText,
      status: 'Pending',
      admin_reply: '',
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };

    state.reports.unshift(newReport);

    window.addNotification({
      title: `Report Logged: ${topic}`,
      message: `Your report regarding "${targetType}" has been submitted to Admin (${priority} Priority).`,
      type: 'report',
      target_role: state.activeRole || 'customer',
      link: (state.activeRole || 'customer') === 'customer' ? '#/account/reports' : '#/messages'
    });

    window.addNotification({
      title: `New Incident Report: ${topic}`,
      message: `Submitted by ${reporterName} (${(state.activeRole || 'User').toUpperCase()}): ${descText.length > 60 ? descText.slice(0, 60) + '...' : descText}`,
      type: 'report',
      target_role: 'admin',
      link: '#/dash/admin/reports'
    });

    persist();
    updateGlobalHeader();
    closeModal();
    showToast('Your report has been submitted to Admin dynamically!', 'success');
    render();
  };

  window.setDefaultAddress = function (addressId) {
    state.addresses.forEach((a) => {
      a.is_default = a.id === Number(addressId) ? 1 : 0;
    });
    persist();
    showToast('Default delivery address updated successfully!', 'success');
    render();
  };

  window.deleteAddress = function (addressId) {
    const addr = state.addresses.find((a) => a.id === Number(addressId));
    if (addr?.is_default) {
      showToast('Cannot remove your default address. Please set another default first.', 'warning');
      return;
    }
    state.addresses = state.addresses.filter((a) => a.id !== Number(addressId));
    persist();
    showToast('Address removed.', 'info');
    render();
  };

  window.removeFromWishlist = function (productId) {
    state.wishlist = state.wishlist.filter((id) => id !== Number(productId));
    persist();
    updateGlobalHeader();
    showToast('Item removed from wishlist');
    render();
  };

  window.clearWishlist = function () {
    state.wishlist = [];
    persist();
    updateGlobalHeader();
    showToast('Wishlist cleared.', 'info');
    render();
  };

  window.moveWishlistToCart = function (productId) {
    if (!state.activeRole) {
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole !== 'customer') {
      showToast('Only Customer accounts can purchase items.', 'warning');
      return;
    }
    const prod = state.products.find((p) => p.id === Number(productId));
    if (!prod) return;
    window.addToCartDirect(prod.id, 1);
    state.wishlist = state.wishlist.filter((id) => id !== Number(productId));
    persist();
    updateGlobalHeader();
    showToast(`Moved "${prod.name || 'Product'}" to cart!`, 'success');
    render();
  };

  /* =========================================================================
     AUTO-GREETING & READYMADE QUICK MESSAGES (Customer & Seller Only)
     ========================================================================= */

  function ensureSellerAutoGreeting(arg1, arg2, arg3 = 1) {
    let channelId = '';
    let storeId = 1;
    let orderId = 1;

    if (typeof arg1 === 'string' && arg1.startsWith('seller_')) {
      channelId = arg1;
      storeId = Number(arg2) || 1;
      orderId = Number(arg3) || 1;
    } else if (typeof arg2 === 'string' && arg2.startsWith('seller_')) {
      storeId = Number(arg1) || 1;
      channelId = arg2;
      orderId = Number(arg3) || 1;
    } else {
      storeId = Number(arg1) || Number(arg2) || 1;
      orderId = Number(arg3) || 1;
      channelId = `seller_${storeId}_order_${orderId}`;
    }

    if (!channelId || typeof channelId !== 'string' || !channelId.startsWith('seller_')) return;
    const store = getStore(storeId) || state.stores[0];
    const hasExistingGreeting = state.messages.some(
      (m) => m.channel_id === channelId && m.sender_role === 'seller'
    );
    if (!hasExistingGreeting) {
      const greetingText =
        store.auto_greeting ||
        `Assalamu Alaikum! Welcome to ${store.store_name}. Thank you for reaching out to us! How can we help you today?`;
      state.messages.push({
        id: Date.now(),
        order_id: orderId || 1,
        channel_id: channelId,
        sender_id: store.user_id || 2,
        sender_name: store.store_name,
        sender_role: 'seller',
        receiver_id: state.currentUser?.id || 1,
        receiver_name: state.currentUser?.name || 'Customer',
        receiver_role: 'customer',
        message: greetingText,
        created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        is_read: 0,
        is_auto_greeting: 1
      });
      state.greetedChannels = state.greetedChannels || [];
      if (!state.greetedChannels.includes(channelId)) {
        state.greetedChannels.push(channelId);
      }
      persist();
    }
  }

  window.setActiveChatChannel = function (channelId) {
    state.activeChatChannel = channelId;
    if (channelId && channelId.startsWith('seller_')) {
      const parts = channelId.split('_');
      ensureSellerAutoGreeting(channelId, Number(parts[1]), Number(parts[3] || 1));
    }
    render();
    setTimeout(() => {
      const stream = document.getElementById('dashChatStream');
      if (stream) stream.scrollTop = stream.scrollHeight;
    }, 60);
  };

  window.contactSellerFromOrder = function (orderId, storeId) {
    if (!state.activeRole) {
      showToast('Please log in to message the seller.', 'info');
      location.hash = '#/auth';
      return;
    }
    const chId = `seller_${storeId}_order_${orderId}`;
    state.activeChatChannel = chId;
    ensureSellerAutoGreeting(chId, storeId, orderId);
    location.hash = '#/account/messages';
  };

  window.contactRiderFromOrder = function (orderId, riderId) {
    if (!state.activeRole) {
      location.hash = '#/auth';
      return;
    }
    state.activeChatChannel = `rider_${riderId || 1}_order_${orderId}`;
    location.hash = '#/account/messages';
  };

  window.sendQuickReadyMessage = function (role, textOrIdx, channelId, receiverRole, receiverName) {
    let msgText = textOrIdx;
    if (typeof textOrIdx === 'number') {
      msgText = state.quickMessages?.[role]?.[textOrIdx] || '';
    }
    if (!msgText) return;
    const fakeForm = { chat_text: { value: String(msgText) } };
    if (role === 'customer') {
      window.handleSendDashboardChat(fakeForm);
    } else {
      window.handleRoleChat(fakeForm, role, channelId, receiverRole, receiverName);
    }
  };

  window.openManageQuickMessagesModal = function (role) {
    const list = state.quickMessages[role] || [];
    const ownStore = role === 'seller' ? getSellerOwnStore() : null;

    openModal(`
      <div style="padding:10px;">
        <h3 style="font-size:17px;font-weight:800;color:#1E293B;margin-bottom:4px;">
          <i class="bi bi-lightning-charge-fill" style="color:var(--haat-orange)"></i> Customize Readymade Quick Messages
        </h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
          Edit your one-click instant messages for ${role === 'seller' ? esc(ownStore?.store_name || 'Seller Store') : 'Customer Chat'}.
        </p>

        ${
          role === 'seller' && ownStore
            ? `
          <div style="margin-bottom:14px;padding:12px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;">
            <label style="font-size:12px;font-weight:800;color:#166534;display:block;margin-bottom:4px;">
              <i class="bi bi-robot"></i> Automatic First-Message Greeting to Customers
            </label>
            <textarea id="sellerAutoGreetingInput" rows="2" style="width:100%;padding:8px;border:1px solid #86EFAC;border-radius:6px;font-size:12.5px;">${esc(ownStore.auto_greeting || '')}</textarea>
          </div>
        `
            : ''
        }

        <div id="quickMsgsEditorList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
          ${list
            .map(
              (msg, idx) => `
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="text" class="qm-edit-input" value="${esc(msg)}" style="flex:1;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12.5px;">
              <button type="button" class="btn-secondary" style="color:#DC2626;padding:6px 10px;" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
            </div>
          `
            )
            .join('')}
        </div>

        <button type="button" class="btn-village-outline" style="width:100%;margin-bottom:16px;padding:7px;font-size:12px;" onclick="window.addQuickMsgInputRow()">
          + Add Another Readymade Message
        </button>

        <div style="display:flex;justify-content:flex-end;gap:10px;">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
          <button type="button" class="btn-village-primary" onclick="window.saveQuickMessages('${role}')"><i class="bi bi-check2"></i> Save Quick Messages</button>
        </div>
      </div>
    `);
  };

  window.addQuickMsgInputRow = function () {
    const box = $('#quickMsgsEditorList');
    if (!box) return;
    const div = document.createElement('div');
    div.style.cssText = 'display:flex;gap:8px;align-items:center;';
    div.innerHTML = `
      <input type="text" class="qm-edit-input" placeholder="Enter readymade message..." style="flex:1;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12.5px;">
      <button type="button" class="btn-secondary" style="color:#DC2626;padding:6px 10px;" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>
    `;
    box.appendChild(div);
  };

  window.saveQuickMessages = function (role) {
    const inputs = $$('.qm-edit-input');
    const updated = inputs.map((i) => i.value.trim()).filter(Boolean);
    state.quickMessages[role] = updated;

    if (role === 'seller') {
      const greetInput = $('#sellerAutoGreetingInput');
      const ownStore = getSellerOwnStore();
      if (greetInput && ownStore) {
        ownStore.auto_greeting = greetInput.value.trim();
      }
    }

    persist();
    closeModal();
    showToast('Readymade messages & greeting saved!', 'success');
    render();
  };

  window.handleSendDashboardChat = function (form) {
    const input = form.chat_text;
    const txt = (input?.value || '').trim();
    if (!txt) return;

    const channelId = state.activeChatChannel || `seller_1_order_${state.orders[0]?.id || 1}`;
    let targetRole = 'seller';
    let targetName = 'ABC Fashion Store';
    let orderId = state.orders[0]?.id || 1;
    let storeId = 1;

    if (channelId.startsWith('rider_')) {
      targetRole = 'rider';
      const parts = channelId.split('_');
      const rId = Number(parts[1]) || 1;
      orderId = Number(parts[3]) || orderId;
      const rider = state.riders.find((r) => r.id === rId) || state.riders[0];
      targetName = rider?.name || 'Tareq Ahmed';
    } else if (channelId.startsWith('seller_')) {
      targetRole = 'seller';
      const parts = channelId.split('_');
      storeId = Number(parts[1]) || 1;
      orderId = Number(parts[3]) || orderId;
      const store = getStore(storeId);
      targetName = store?.store_name || 'ABC Fashion Store';
    }

    // Check if this is the very first message in this seller channel — if so, trigger automatic store greeting first!
    const existingInChannel = state.messages.filter((m) => m.channel_id === channelId);

    const newMsg = {
      id: Date.now(),
      order_id: orderId,
      sender_id: state.currentUser?.id || 1,
      sender_name: state.currentUser?.name || 'Customer',
      sender_role: 'customer',
      receiver_id: targetRole === 'rider' ? 6 : 2,
      receiver_name: targetName,
      receiver_role: targetRole,
      channel_id: channelId,
      message: txt,
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      is_read: 1
    };

    state.messages.push(newMsg);
    if (input && input.value !== undefined) input.value = '';

    window.addNotification({
      title: `New Message from ${state.currentUser?.name || 'Customer'}`,
      message: txt.length > 70 ? txt.slice(0, 70) + '...' : txt,
      type: 'chat',
      target_role: targetRole,
      order_id: orderId,
      link: targetRole === 'seller' ? '#/dash/seller/messages' : '#/dash/rider/messages'
    });

    // If seller channel had no seller greeting yet, send auto-greeting immediately
    if (targetRole === 'seller' && !existingInChannel.some((m) => m.sender_role === 'seller')) {
      const store = getStore(storeId) || state.stores[0];
      const greetingMsg = store.auto_greeting || `Assalamu Alaikum! Welcome to ${store.store_name}. Thank you for messaging us!`;
      state.messages.push({
        id: Date.now() + 1,
        order_id: orderId,
        channel_id: channelId,
        sender_id: store.user_id || 2,
        sender_name: store.store_name,
        sender_role: 'seller',
        receiver_id: state.currentUser?.id || 1,
        receiver_name: state.currentUser?.name || 'Customer',
        receiver_role: 'customer',
        message: greetingMsg,
        created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        is_read: 1,
        is_auto_greeting: 1
      });

      window.addNotification({
        title: `Message from ${store.store_name}`,
        message: greetingMsg.length > 70 ? greetingMsg.slice(0, 70) + '...' : greetingMsg,
        type: 'chat',
        target_role: 'customer',
        order_id: orderId,
        link: '#/account/messages'
      });
    }

    persist();
    render();

    setTimeout(() => {
      const stream = document.getElementById('dashChatStream');
      if (stream) stream.scrollTop = stream.scrollHeight;
    }, 60);

    // Simulated responsive feedback from merchant / rider after 1 second if conversation already established
    if (targetRole === 'rider') {
      setTimeout(() => {
        const autoReplies = [
          'Hello Rahim! I am on the route with HATEX logistics and will call you before arriving.',
          'Understood! I will ensure your order is delivered with care.',
          'Noted! Thank you for the update. Arriving soon.'
        ];
        const replyText = autoReplies[Math.floor(Math.random() * autoReplies.length)];
        state.messages.push({
          id: Date.now() + 2,
          order_id: orderId,
          channel_id: channelId,
          sender_id: 6,
          sender_name: targetName,
          sender_role: 'rider',
          receiver_id: state.currentUser?.id || 1,
          receiver_name: state.currentUser?.name || 'Customer',
          receiver_role: 'customer',
          message: replyText,
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          is_read: 1
        });

        window.addNotification({
          title: `Message from Rider ${targetName}`,
          message: replyText,
          type: 'chat',
          target_role: 'customer',
          order_id: orderId,
          link: '#/account/messages'
        });

        persist();
        render();
        setTimeout(() => {
          const stream = document.getElementById('dashChatStream');
          if (stream) stream.scrollTop = stream.scrollHeight;
        }, 60);
      }, 1200);
    } else if (targetRole === 'seller' && existingInChannel.length > 0) {
      setTimeout(() => {
        const sellerReplies = [
          'Thank you for contacting us! Our team is checking this and will assist you promptly.',
          'We have noted your inquiry and will update you shortly.',
          'Thanks for reaching out! Let us know if you have any further questions.'
        ];
        const replyText = sellerReplies[Math.floor(Math.random() * sellerReplies.length)];
        state.messages.push({
          id: Date.now() + 2,
          order_id: orderId,
          channel_id: channelId,
          sender_id: storeId,
          sender_name: targetName,
          sender_role: 'seller',
          receiver_id: state.currentUser?.id || 1,
          receiver_name: state.currentUser?.name || 'Customer',
          receiver_role: 'customer',
          message: replyText,
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          is_read: 1
        });

        window.addNotification({
          title: `Message from ${targetName}`,
          message: replyText,
          type: 'chat',
          target_role: 'customer',
          order_id: orderId,
          link: '#/account/messages'
        });

        persist();
        render();
        setTimeout(() => {
          const stream = document.getElementById('dashChatStream');
          if (stream) stream.scrollTop = stream.scrollHeight;
        }, 60);
      }, 1200);
    }
  };

  window.toggleAccountMenu = function (force) {
    const flyout = $('#accountMenuFlyout');
    if (!flyout) return;
    if (force !== undefined) {
      flyout.classList.toggle('hidden', !force);
    } else {
      flyout.classList.toggle('hidden');
    }
  };

  window.handleLogout = function () {
    state.activeRole = null;
    state.currentUser = null;
    state.currentUserId = null;
    state.appliedCoupon = null;
    writeStorage('currentUserId', null);
    try {
      localStorage.removeItem('HAAT_API_USER_ID');
      localStorage.removeItem('HAAT_API_ROLE');
    } catch {}
    persist();
    showToast('You have been logged out. Browsing as Guest.', 'info');
    location.hash = '#/';
    render();
  };

  window.instantDemoLogin = function (role) {
    state.activeRole = role;
    state.appliedCoupon = null;
    if (role === 'customer') {
      const u = state.users.find(x => x.id === 1 || x.email === 'customer@haat.com.bd') || state.users.find(x => x.role === 'customer');
      state.currentUserId = u ? u.id : 1;
    } else if (role === 'seller') {
      const u = state.users.find(x => x.id === 2 || x.email === 'seller@abc-fashion.com') || state.users.find(x => x.role === 'seller');
      state.currentUserId = u ? u.id : 2;
    } else if (role === 'rider') {
      const u = state.users.find(x => x.id === 6 || x.role === 'rider');
      state.currentUserId = u ? u.id : 6;
    } else if (role === 'admin') {
      const u = state.users.find(x => x.id === 4 || x.role === 'admin');
      state.currentUserId = u ? u.id : 4;
    } else if (role === 'hatex') {
      const u = state.users.find(x => x.id === 5 || x.name === 'HATEX');
      state.currentUserId = u ? u.id : 5;
    }
    resolveCurrentUser();
    persist();
    showToast(`Logged in as ${state.currentUser?.name || role.toUpperCase()}!`, 'success');

    if (role === 'seller') location.hash = '#/dash/seller';
    else if (role === 'admin') location.hash = '#/dash/admin';
    else if (role === 'hatex') location.hash = '#/dash/hatex';
    else if (role === 'rider') location.hash = '#/dash/rider';
    else location.hash = '#/account';
    render();
  };

  window.switchAuthTab = function (tab) {
    state.authTab = tab;
    render();
  };

  window.switchRegisterRole = function (role) {
    state.registerRole = role;
    render();
  };

  window.handleAuthLogin = function (form) {
    const fd = new FormData(form);
    const email = (fd.get('email') || '').trim().toLowerCase();
    const password = (fd.get('password') || '').trim();
    const role = fd.get('role');

    if (!email) {
      showToast('Please enter your email address.', 'warning');
      return;
    }
    if (!password) {
      showToast('Please enter your password.', 'warning');
      return;
    }

    let user = state.users.find((u) => u.email.toLowerCase() === email);
    if (!user) {
      showToast('No account found with this email address. Please register or check your email.', 'warning');
      return;
    }

    const savedPw = user.password || (typeof localStorage !== 'undefined' ? localStorage.getItem('HAAT_PW_' + email) : null);
    const isDemo = [1, 2, 3, 4, 5, 6, 7].includes(user.id) || ['customer@haat.com.bd', 'seller@abc-fashion.com', 'seller@xyz-electronics.com', 'admin@haat.com.bd', 'logistics@hatex.com.bd', 'tareq@hatex.com.bd', 'sumon@hatex.com.bd'].includes(user.email.toLowerCase());

    let match = false;
    if (savedPw && password === savedPw) {
      match = true;
    } else if (isDemo && (password === 'demo1234' || password === 'haat2026')) {
      match = true;
    }

    if (!match) {
      showToast('Incorrect password. Please verify your credentials and try again.', 'warning');
      return;
    }

    const effectiveRole = user.role || role || 'customer';
    state.activeRole = effectiveRole;
    state.currentUserId = user.id;
    state.currentUser = user;
    state.lastAuthEmail = user.email;
    state.appliedCoupon = null;
    persist();
    showToast(`Welcome back, ${user.name}!`, 'success');

    if (effectiveRole === 'seller') location.hash = '#/dash/seller';
    else if (effectiveRole === 'admin') location.hash = '#/dash/admin';
    else if (effectiveRole === 'hatex') location.hash = '#/dash/hatex';
    else if (effectiveRole === 'rider') location.hash = '#/dash/rider';
    else location.hash = '#/account';
    render();
  };

  window.handleAuthRegister = function (form) {
    const fd = new FormData(form);
    const regRole = state.registerRole || 'customer';

    if (regRole === 'admin' || regRole === 'hatex') {
      showToast('Admin & HATEX accounts are fixed corporate roles.', 'info');
      return;
    }

    const name = (fd.get('name') || '').trim();
    const email = (fd.get('email') || '').trim().toLowerCase();
    const phone = (fd.get('phone') || '').trim();
    const password = (fd.get('password') || '').trim();
    const cpassword = (fd.get('cpassword') || '').trim();

    if (!name || !email) {
      showToast('Please provide your name and email address.', 'warning');
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showToast('Please provide a valid email address.', 'warning');
      return;
    }

    if (!password) {
      showToast('Please enter a password.', 'warning');
      return;
    }
    if (password.length < 6) {
      showToast('Password must be at least 6 characters long.', 'warning');
      return;
    }
    if (password !== cpassword) {
      showToast('Passwords do not match. Please ensure Password and Confirm Password are identical.', 'warning');
      return;
    }

    if (state.users.some((u) => u.email.toLowerCase() === email)) {
      showToast('An account with this email address already exists. Please log in.', 'warning');
      return;
    }

    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('HAAT_PW_' + email, password);
    }
    state.lastAuthEmail = email;

    const nextId = Math.max(...state.users.map((u) => u.id || 0), 0) + 1;

    if (regRole === 'customer') {
      const address = (fd.get('address') || 'House 14, Road 3, Dhanmondi').trim();
      const district = fd.get('district') || 'Dhaka';
      const division = fd.get('division') || 'Dhaka';

      const newUser = {
        id: nextId,
        name: name,
        email: email,
        role: 'customer',
        phone: phone,
        password: password,
        status: 'active'
      };
      state.users.push(newUser);
      state.addresses.push({
        id: Math.max(...state.addresses.map((a) => a.id || 0), 0) + 1,
        user_id: newUser.id,
        label: 'Home',
        name: name,
        phone: phone,
        address: address,
        district: district,
        division: division,
        postal_code: '1205',
        latitude: 23.7465,
        longitude: 90.3760,
        is_default: 1
      });

      state.activeRole = 'customer';
      state.currentUserId = newUser.id;
      state.currentUser = newUser;
      state.appliedCoupon = null;
      if (!state.collectedCouponsByUser) state.collectedCouponsByUser = {};
      state.collectedCouponsByUser[String(newUser.id)] = [];
      persist();
      showToast(`Welcome to HAAT, ${name}! Your account has been registered.`, 'success');
      location.hash = '#/account';
      render();
    } else if (regRole === 'seller') {
      const storeName = (fd.get('store_name') || `${name}'s Store`).trim();
      const category = fd.get('category') || 'Fashion & Apparel';
      const address = (fd.get('address') || 'Shop 8, New Market').trim();
      const district = fd.get('district') || 'Dhaka';

      const newUser = {
        id: nextId,
        name: storeName,
        email: email,
        role: 'seller',
        phone: phone,
        password: password,
        status: 'active'
      };
      state.users.push(newUser);

      const slug = storeName.toLowerCase().replace(/[^a-z0-9]+/g, '-');
      const newStore = {
        id: Math.max(...state.stores.map((s) => s.id || 0), 0) + 1,
        user_id: newUser.id,
        store_name: storeName,
        store_slug: slug,
        description: `Official HAAT merchant store specializing in ${category}.`,
        logo_text: storeName.slice(0, 3).toUpperCase(),
        primary_color: '#F85606',
        banner_gradient: 'linear-gradient(135deg, #F85606 0%, #7C2D12 100%)',
        address: address,
        district: district,
        division: district,
        postal_code: '1205',
        latitude: 23.7500,
        longitude: 90.3900,
        delivery_charge: 60,
        free_delivery: 0,
        auto_greeting: `Assalamu Alaikum! Welcome to ${storeName}. Thank you for reaching out to us!`,
        status: 'active',
        is_published: 1,
        verification_status: 'unverified',
        verification_documents: null
      };
      state.stores.push(newStore);

      state.activeRole = 'seller';
      state.currentUserId = newUser.id;
      state.currentUser = newUser;
      persist();
      showToast(`Store "${storeName}" is now active and open!`, 'success');
      location.hash = '#/dash/seller';
      render();
    } else if (regRole === 'rider') {
      const vehicle = fd.get('vehicle_type') || 'Motorcycle';
      const cleanName = name.replace(/\s*\(Rider.*?\)/gi, '').trim();

      const newUser = {
        id: nextId,
        name: cleanName,
        email: email,
        role: 'rider',
        phone: phone,
        password: password,
        status: 'active'
      };
      state.users.push(newUser);

      state.riders.push({
        id: Math.max(...state.riders.map((r) => r.id || 0), 0) + 1,
        user_id: newUser.id,
        name: cleanName,
        phone: phone,
        vehicle_type: vehicle,
        hub: fd.get('district') || 'Dhaka Metro',
        status: 'active',
        current_lat: 23.7800,
        current_lon: 90.4100
      });

      state.activeRole = 'rider';
      state.currentUserId = newUser.id;
      state.currentUser = newUser;
      persist();
      showToast(`Rider ${cleanName} registered!`, 'success');
      location.hash = '#/dash/rider';
      render();
    }
  };

  window.handleUpdateProfile = function (form) {
    const fd = new FormData(form);
    const name = fd.get('name');
    const email = fd.get('email');
    const phone = fd.get('phone');
    if (state.currentUser) {
      state.currentUser.name = name;
      state.currentUser.email = email;
      state.currentUser.phone = phone;
      const u = state.users.find((x) => x.id === state.currentUser.id);
      if (u) {
        u.name = name;
        u.email = email;
        u.phone = phone;
      }
    }
    persist();
    showToast('Profile information saved!', 'success');
    render();
  };

  window.switchPersona = function (newRole) {
    state.activeRole = newRole;
    persist();
    showToast(`Switched active session to: ${newRole ? newRole.toUpperCase() : 'GUEST'}`, 'success');

    if (newRole === 'seller') location.hash = '#/dash/seller';
    else if (newRole === 'admin') location.hash = '#/dash/admin';
    else if (newRole === 'hatex') location.hash = '#/dash/hatex';
    else if (newRole === 'rider') location.hash = '#/dash/rider';
    else if (newRole === 'customer') location.hash = '#/account';
    else location.hash = '#/';
  };

  window.handleSearch = function (q) {
    if (state.activeRole === 'hatex' || state.activeRole === 'rider') return;
    state.searchQuery = q.trim();
    location.hash = '#/products';
  };

  /* =========================================================================
     5. HELPER DATA RETRIEVERS, DELIVERY CHARGE & COUPON/CAMPAIGN ENGINE
     ========================================================================= */

  function getProduct(id) {
    return state.products.find((p) => p.id === Number(id));
  }
  window.getProduct = getProduct;

  function getStore(id) {
    return state.stores.find((s) => s.id === Number(id) || s.store_slug === String(id));
  }
  window.getStore = getStore;

  function getInventory(productId) {
    const inv = state.inventory.find((i) => i.product_id === Number(productId));
    if (!inv) return { quantity: 0, reserved_quantity: 0, available: 0, location: 'Warehouse Shelf A-1' };
    return {
      quantity: inv.quantity,
      reserved_quantity: inv.reserved_quantity,
      available: Math.max(0, inv.quantity - inv.reserved_quantity),
      location: inv.location || 'Warehouse Shelf A-1'
    };
  }
  window.getInventory = getInventory;

  function getCategory(id) {
    return (state.categories || []).find((c) => c.id === Number(id) || c.slug === String(id));
  }

  function getCategoryBySub(subId) {
    for (const cat of (state.categories || [])) {
      const sub = (cat.subcategories || []).find((s) => Number(s.id) === Number(subId));
      if (sub) return { category: cat, subcategory: sub };
    }
    return {
      category: state.categories?.[0] || { id: 1, name: 'General', slug: 'general' },
      subcategory: state.categories?.[0]?.subcategories?.[0] || { id: 101, name: 'General', slug: 'general' }
    };
  }

  function getCategoryBySubId(subId) {
    for (const cat of (state.categories || [])) {
      const sub = (cat.subcategories || []).find((s) => Number(s.id) === Number(subId));
      if (sub) return cat;
    }
    return state.categories?.[0] || null;
  }

  window.getCategory = getCategory;
  window.getCategoryBySub = getCategoryBySub;
  window.getCategoryBySubId = getCategoryBySubId;

  // Computes effective unit price including any active Admin Flash Discount Campaign on Category/Subcategory
  function getEffectiveProductPrice(p) {
    if (!p) return { finalPrice: 0, originalPrice: 0, hasDiscount: false, badgeText: '', valueOf() { return 0; }, toString() { return '0'; } };
    const originalPrice = Number(p.price || 0);
    const baseSale = Number(p.sale_price || p.price || 0);
    const { category, subcategory } = getCategoryBySub(p.subcategory_id);
    const activeFlash = (state.campaigns || []).find(
      (c) =>
        c.is_active &&
        c.campaign_type === 'flash_discount' &&
        Number(c.category_id) === Number(category.id) &&
        (!c.subcategory_id || Number(c.subcategory_id) === Number(subcategory.id))
    );
    let finalPrice = baseSale;
    let badgeText = '';
    if (activeFlash && activeFlash.discount_percent > 0) {
      finalPrice = Math.round(baseSale * (1 - activeFlash.discount_percent / 100));
      badgeText = activeFlash.badge_text || `${activeFlash.discount_percent}% OFF FLASH SALE`;
    }
    const hasDiscount = finalPrice < originalPrice;
    return {
      finalPrice,
      originalPrice,
      hasDiscount,
      badgeText,
      valueOf() { return finalPrice; },
      toString() { return String(finalPrice); }
    };
  }

  // Determines whether a product has Free Delivery (from Seller OR from Admin Category/Subcategory Campaign) or a specific Delivery Charge
  function getProductDeliveryInfo(p) {
    if (!p) return { isFree: false, fee: 60, badge: 'Delivery: ৳60', label: 'Delivery: ৳60', reason: 'Standard HATEX Delivery', source: 'standard' };
    const store = getStore(p.store_id) || state.stores[0];
    const { category, subcategory } = getCategoryBySub(p.subcategory_id);

    // 1. Check Admin Category / Subcategory Free Delivery Campaign
    const adminFreeCamp = (state.campaigns || []).find(
      (c) =>
        c.is_active &&
        c.campaign_type === 'free_delivery' &&
        Number(c.category_id) === Number(category.id) &&
        (!c.subcategory_id || Number(c.subcategory_id) === Number(subcategory.id))
    );
    if (adminFreeCamp) {
      return {
        isFree: true,
        fee: 0,
        badge: 'FREE DELIVERY (Campaign)',
        label: 'FREE DELIVERY (Campaign)',
        reason: adminFreeCamp.name || 'Campaign Free Delivery',
        source: 'admin_campaign',
        campaignName: adminFreeCamp.name
      };
    }

    // 2. Check Seller Store or Seller Product Free Delivery option
    if (p.free_delivery || store?.free_delivery) {
      return {
        isFree: true,
        fee: 0,
        badge: 'FREE DELIVERY',
        label: 'FREE DELIVERY',
        reason: 'Free Delivery',
        source: 'seller_free'
      };
    }

    // 3. Seller-controlled Delivery Charge
    const fee = Number(p.delivery_charge ?? store?.delivery_charge ?? state.appSettings?.defaultDeliveryCharge ?? 60);
    if (fee <= 0) {
      return { isFree: true, fee: 0, badge: 'FREE DELIVERY', label: 'FREE DELIVERY', reason: 'Free Delivery', source: 'seller_free' };
    }
    return {
      isFree: false,
      fee: fee,
      badge: `Delivery: ${money(fee)}`,
      label: `Delivery: ${money(fee)}`,
      reason: 'Standard HATEX Delivery',
      source: 'seller_fee'
    };
  }

  // Computes store-level shipping cost for a group of items in Cart / Checkout
  function getStoreGroupShippingCost(storeOrId, items) {
    const store = typeof storeOrId === 'object' ? storeOrId : (getStore(storeOrId) || state.stores[0]);
    if (!items || !items.length) {
      return { cost: 0, isFree: true, reason: 'No items', valueOf() { return 0; } };
    }
    if (store?.free_delivery) {
      return { cost: 0, isFree: true, reason: 'Free Delivery Store', valueOf() { return 0; } };
    }
    // If every item in this store group qualifies for free delivery (via product free delivery or Admin campaign), shipping is 0!
    const allFree = items.every((it) => {
      const prod = it.product || getProduct(it.product_id);
      return prod ? getProductDeliveryInfo(prod).isFree : false;
    });
    if (allFree) {
      return { cost: 0, isFree: true, reason: 'Free Delivery Campaign', valueOf() { return 0; } };
    }
    const fees = items
      .map((it) => {
        const prod = it.product || getProduct(it.product_id);
        return prod ? getProductDeliveryInfo(prod) : null;
      })
      .filter((d) => d && !d.isFree)
      .map((d) => Number(d.fee || 0));
    let calculatedFee = fees.length ? Math.max(...fees) : Number(store?.delivery_charge ?? 60);
    if (isNaN(calculatedFee) || calculatedFee < 0) {
      calculatedFee = Number(store?.delivery_charge ?? 60) || 60;
    }
    return {
      cost: calculatedFee,
      isFree: calculatedFee === 0,
      reason: calculatedFee === 0 ? 'Free Delivery' : 'Standard Delivery',
      valueOf() { return calculatedFee; }
    };
  }

  // Returns human-readable scope label for any coupon (Store vs Admin Category/Subcategory)
  function getCouponScopeLabel(c) {
    if (c.store_id) {
      const st = getStore(c.store_id);
      return st ? `Store: ${st.store_name}` : 'Store Voucher';
    }
    if (c.category_id) {
      const cat = state.categories.find((x) => x.id === Number(c.category_id));
      if (cat && c.subcategory_id) {
        const sub = (cat.subcategories || []).find((s) => s.id === Number(c.subcategory_id));
        if (sub) return `Subcategory: ${sub.name}`;
      }
      if (cat) return `Category: ${cat.name}`;
    }
    return 'All Categories';
  }

  // Evaluates coupon eligibility and exact discount amount against current cart items
  function evaluateCartCoupon(coupon) {
    if (!coupon || !coupon.is_active) {
      return { valid: false, discount: 0, reason: 'Coupon is inactive or invalid.' };
    }
    let eligibleSubtotal = 0;
    state.cart.forEach((item) => {
      const p = getProduct(item.product_id);
      if (!p) return;
      const unitPrice = Number(getEffectiveProductPrice(p).finalPrice || p.price || 0);
      const { category, subcategory } = getCategoryBySub(p.subcategory_id);

      if (coupon.store_id) {
        if (Number(p.store_id) === Number(coupon.store_id)) {
          eligibleSubtotal += unitPrice * item.quantity;
        }
      } else if (coupon.category_id) {
        const catMatch = Number(category.id) === Number(coupon.category_id);
        const subMatch = !coupon.subcategory_id || Number(subcategory.id) === Number(coupon.subcategory_id);
        if (catMatch && subMatch) {
          eligibleSubtotal += unitPrice * item.quantity;
        }
      } else {
        eligibleSubtotal += unitPrice * item.quantity;
      }
    });

    if (eligibleSubtotal <= 0) {
      return {
        valid: false,
        discount: 0,
        reason: `No items in your cart belong to ${getCouponScopeLabel(coupon)}.`
      };
    }

    if (eligibleSubtotal < (coupon.min_order || 0)) {
      return {
        valid: false,
        discount: 0,
        reason: `Minimum spend of ${money(coupon.min_order)} required on ${getCouponScopeLabel(coupon)} (Current: ${money(eligibleSubtotal)}).`
      };
    }

    let discount = 0;
    if (coupon.discount_type === 'percent') {
      discount = Math.round((eligibleSubtotal * coupon.discount_value) / 100);
      if (coupon.max_discount) discount = Math.min(discount, coupon.max_discount);
    } else {
      discount = Number(coupon.discount_value || 0);
    }
    discount = Math.min(discount, eligibleSubtotal);

    return {
      valid: true,
      discount: discount,
      eligibleSubtotal: eligibleSubtotal,
      scopeLabel: getCouponScopeLabel(coupon)
    };
  }
  window.getEffectiveProductPrice = getEffectiveProductPrice;
  window.getProductDeliveryInfo = getProductDeliveryInfo;
  window.getStoreGroupShippingCost = getStoreGroupShippingCost;
  window.evaluateCartCoupon = evaluateCartCoupon;

  window.collectCoupon = function (couponId) {
    if (!state.activeRole) {
      showToast('Please log in to collect vouchers!', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole !== 'customer' || !state.currentUser) {
      showToast('Sellers and staff can view vouchers but cannot collect or occupy them.', 'warning');
      return;
    }
    const idNum = Number(couponId);
    const c = state.coupons.find((x) => x.id === idNum);
    if (!c) return;

    const userCoupons = getCurrentUserCollectedCouponIds();
    if (!userCoupons.includes(idNum)) {
      userCoupons.push(idNum);
      window.addNotification({
        title: `Voucher Collected (${c.code})`,
        message: `You collected ${c.discount_type === 'percentage' || c.discount_type === 'percent' ? c.discount_value + '% OFF' : money(c.discount_value) + ' OFF'}. Applied automatically at Cart & Checkout!`,
        type: 'promo',
        target_role: 'customer',
        link: '#/cart'
      });
      persist();
      showToast(`Voucher "${c.code}" collected for ${esc(state.currentUser.name)}! Ready in Cart & Checkout.`, 'success');
      render();
    } else {
      showToast(`Voucher "${c.code}" is already in your account wallet!`, 'info');
    }
  };

  let couponSliderTimer = null;
  function startCouponSliderInterval() {
    clearInterval(couponSliderTimer);
    const activeCoupons = (state.coupons || []).filter((c) => c.is_active);
    const slideCount = Math.ceil(activeCoupons.length / 2) || 1;
    if (slideCount <= 1) return;
    couponSliderTimer = setInterval(() => {
      const raw = location.hash.slice(1) || '/';
      if (raw === '/' || raw === '') {
        state.currentCouponSlideIndex = (state.currentCouponSlideIndex + 1) % slideCount;
        updateCouponSliderDOM();
      }
    }, 5000);
  }
  window.startCouponSliderInterval = startCouponSliderInterval;

  function updateCouponSliderDOM() {
    const activeCoupons = (state.coupons || []).filter((c) => c.is_active);
    const slideCount = Math.ceil(activeCoupons.length / 2) || 1;
    if (state.currentCouponSlideIndex >= slideCount) state.currentCouponSlideIndex = 0;
    const track = document.getElementById('couponBannerTrack');
    const counter = document.getElementById('couponSlideCounter');
    const dots = [...document.querySelectorAll('.coupon-slider-dot')];
    if (track) {
      track.style.transform = `translateX(-${state.currentCouponSlideIndex * 100}%)`;
    }
    if (counter) {
      counter.textContent = `${state.currentCouponSlideIndex + 1} / ${slideCount}`;
    }
    dots.forEach((d, idx) => {
      d.classList.toggle('active', idx === state.currentCouponSlideIndex);
    });
  }

  window.stepCouponBannerSlide = function (delta) {
    const activeCoupons = (state.coupons || []).filter((c) => c.is_active);
    const slideCount = Math.ceil(activeCoupons.length / 2) || 1;
    state.currentCouponSlideIndex = (state.currentCouponSlideIndex + delta + slideCount) % slideCount;
    updateCouponSliderDOM();
    startCouponSliderInterval();
  };

  window.goToCouponBannerSlide = function (idx) {
    state.currentCouponSlideIndex = Number(idx) || 0;
    updateCouponSliderDOM();
    startCouponSliderInterval();
  };

  function renderCouponBannerItem(c) {
    const isCollected = state.collectedCoupons.includes(Number(c.id));
    const scopeText = getCouponScopeLabel(c);
    const discountText = c.discount_type === 'percent' ? `${c.discount_value}% OFF` : `${money(c.discount_value)} OFF`;

    let actionBtn = '';
    if (!state.activeRole) {
      actionBtn = `<button type="button" class="coupon-banner-btn collect" onclick="window.collectCoupon(${c.id})"><i class="bi bi-ticket-perforated"></i> Collect</button>`;
    } else if (state.activeRole === 'customer') {
      actionBtn = isCollected
        ? `<span class="coupon-banner-btn collected"><i class="bi bi-check-circle-fill"></i> Collected</span>`
        : `<button type="button" class="coupon-banner-btn collect" onclick="window.collectCoupon(${c.id})"><i class="bi bi-ticket-perforated"></i> Collect</button>`;
    } else {
      actionBtn = `<span class="coupon-banner-btn readonly"><i class="bi bi-eye"></i> View Only</span>`;
    }

    return `
      <div class="coupon-banner-card ${isCollected && state.activeRole === 'customer' ? 'is-collected' : ''}">
        <div class="coupon-banner-cutout-left"></div>
        <div class="coupon-banner-cutout-right"></div>
        <div class="coupon-banner-body">
          <div class="coupon-banner-left">
            <span class="coupon-banner-scope-tag"><i class="bi bi-tag-fill"></i> ${esc(scopeText)}</span>
            <div class="coupon-banner-amount">${esc(discountText)}</div>
            <div class="coupon-banner-condition">Min Spend ${money(c.min_order)} • Valid till ${esc(c.expires_at ? c.expires_at.slice(0, 10) : '31 Dec 2026')}</div>
          </div>
          <div class="coupon-banner-divider"></div>
          <div class="coupon-banner-right">
            <div class="coupon-banner-code-box">${esc(c.code)}</div>
            ${actionBtn}
          </div>
        </div>
      </div>
    `;
  }

  function renderDarazVoucherCard(c) {
    const isCollected = state.collectedCoupons.includes(Number(c.id));
    const scopeText = getCouponScopeLabel(c);

    let actionBtnHtml = '';
    if (!state.activeRole) {
      actionBtnHtml = `
        <button type="button" class="voucher-btn-collect" onclick="window.collectCoupon(${c.id})">
          Collect
        </button>
      `;
    } else if (state.activeRole === 'customer') {
      actionBtnHtml = isCollected
        ? `<span class="voucher-btn-collected"><i class="bi bi-check-circle-fill"></i> Collected</span>`
        : `<button type="button" class="voucher-btn-collect" onclick="window.collectCoupon(${c.id})">Collect</button>`;
    } else {
      // Seller, Admin, etc. only view coupons — cannot collect or occupy them
      actionBtnHtml = `<span class="voucher-btn-readonly"><i class="bi bi-eye"></i> View Only</span>`;
    }

    return `
      <div class="daraz-voucher-card ${isCollected && state.activeRole === 'customer' ? 'collected' : ''}">
        <div>
          <div style="display:flex;align-items:center;gap:8px;">
            <strong style="color:var(--haat-primary);font-size:15px;letter-spacing:0.8px;font-family:var(--font-mono);">${esc(c.code)}</strong>
            <span style="background:#FFF7ED;color:#EA580C;font-size:11px;font-weight:800;padding:2px 7px;border-radius:4px;">
              ${c.discount_type === 'percent' ? `${c.discount_value}% OFF` : `${money(c.discount_value)} OFF`}
            </span>
          </div>
          <div style="font-size:11.5px;color:var(--text-muted);margin-top:3px;">
            Min Spend ${money(c.min_order)} ${c.max_discount ? `• Cap ${money(c.max_discount)}` : ''}
          </div>
          <small style="color:${c.store_id ? '#C2410C' : '#2563EB'};font-weight:700;font-size:10.5px;display:block;margin-top:2px;">
            <i class="bi bi-${c.store_id ? 'shop' : 'tags-fill'}"></i> ${esc(scopeText)}
          </small>
        </div>
        <div>
          ${actionBtnHtml}
        </div>
      </div>
    `;
  }

  /* =========================================================================
     6. REUSABLE CARD RENDERERS
     ========================================================================= */

  function renderProductCard(p) {
    const inv = getInventory(p.id);
    const store = getStore(p.store_id) || state.stores[0];
    const effectivePrice = getEffectiveProductPrice(p);
    const discountPct = effectivePrice < p.price ? Math.round(((p.price - effectivePrice) / p.price) * 100) : 0;
    const isOut = inv.available <= 0;
    const inWish = state.wishlist.includes(p.id);
    const deliv = getProductDeliveryInfo(p);

    return `
      <div class="haat-item-card" onclick="location.hash='#/product/${p.id}'">
        <div class="item-img-wrap">
          <img src="${esc(p.image_1)}" alt="${esc(p.name)}" loading="lazy">
          <span class="hatex-delivery-badge"><i class="bi bi-truck"></i> HATEX Express</span>
          ${discountPct > 0 ? `<span class="discount-tag">-${discountPct}%</span>` : ''}
          ${isOut ? `<span class="discount-tag" style="background:#DC2626;left:8px;right:auto;top:32px;">OUT OF STOCK</span>` : ''}
        </div>
        <div class="item-card-body">
          <h3 class="item-card-title">${esc(p.name)}</h3>
          <div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;">
            <span class="item-card-price">${money(effectivePrice)}</span>
            ${effectivePrice < p.price ? `<span class="item-old-price">${money(p.price)}</span>` : ''}
          </div>
          <div style="margin:5px 0;">
            ${
              deliv.isFree
                ? `<span class="delivery-badge-free"><i class="bi bi-truck"></i> ${esc(deliv.badge)}</span>`
                : `<span class="delivery-badge-fee"><i class="bi bi-truck"></i> ${esc(deliv.badge)}</span>`
            }
          </div>
          <div class="item-rating-row">
            <span class="star-amber">★</span>
            <strong>${p.rating || '4.8'}</strong>
            <span>(${p.reviews_count || 12})</span>
            <span style="margin-left:auto;color:${isOut ? '#DC2626' : '#16A34A'};font-weight:700;">
              ${isOut ? 'Stock Out' : `${inv.available} In Stock`}
            </span>
          </div>
          <div class="item-seller-line">
            <span><i class="bi bi-shop"></i> ${esc(store.store_name)}</span>
            ${
              state.activeRole === 'customer'
                ? `
              <button type="button" class="btn-wishlist-quick" onclick="event.stopPropagation(); window.toggleWishlist(${p.id});" style="background:none;border:none;cursor:pointer;color:${inWish ? '#F85606' : '#94A3B8'};font-size:16px;">
                <i class="bi bi-heart${inWish ? '-fill' : ''}"></i>
              </button>
            `
                : ''
            }
          </div>
        </div>
      </div>
    `;
  }

  window.toggleWishlist = function (productId) {
    if (!state.activeRole) {
      showToast('Please log in to use Wishlist!', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole !== 'customer') {
      showToast('Only Customer accounts have a shopping wishlist.', 'warning');
      return;
    }
    const idx = state.wishlist.indexOf(Number(productId));
    if (idx >= 0) {
      state.wishlist.splice(idx, 1);
      showToast('Removed from Wishlist');
    } else {
      state.wishlist.push(Number(productId));
      showToast('Added to Wishlist!', 'success');
    }
    persist();
    render();
  };

  window.addToCartDirect = function (productId, qty = 1, variantName = null, variantVal = null) {
    if (!state.activeRole) {
      showToast('Please log in to shop and add items to cart!', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole !== 'customer') {
      showToast('Purchasing is only enabled for Customer accounts.', 'warning');
      return;
    }
    const p = getProduct(productId);
    if (!p) return;
    const inv = getInventory(productId);
    if (inv.available <= 0) {
      showToast('Sorry, this product is currently OUT OF STOCK.', 'error');
      return;
    }

    const existing = state.cart.find((c) => c.product_id === p.id);
    const currentQtyInCart = existing ? existing.quantity : 0;
    if (currentQtyInCart + qty > inv.available) {
      showToast(`Cannot add ${qty} units. Only ${inv.available - currentQtyInCart} more available in stock.`, 'warning');
      return;
    }

    if (existing) {
      existing.quantity += qty;
    } else {
      state.cart.push({
        product_id: p.id,
        store_id: p.store_id,
        quantity: qty,
        variant_name: variantName || p.variant_1_name || 'Standard',
        variant_value: variantVal || (p.variant_1_value ? p.variant_1_value.split(',')[0].trim() : 'Default')
      });
    }

    persist();
    showToast(`Added "${p.name.slice(0, 25)}..." to your cart!`, 'success');
  };
  window.addToCart = window.addToCartDirect;

  /* =========================================================================
     7. PAGE VIEW: 1. HOMEPAGE WITH DARAZ-STYLE MULTI-SLIDE BANNER CAROUSEL
     ========================================================================= */

  let heroSliderTimer = null;

  function startHeroSliderInterval() {
    clearInterval(heroSliderTimer);
    if (state.appSettings && state.appSettings.banner_auto_slide === false) return;
    const activeBanners = (state.banners || []).filter((b) => b.is_active);
    if (activeBanners.length <= 1) return;
    const intervalMs = Number(state.appSettings?.banner_interval_ms) || (Number(state.appSettings?.autoSlideSeconds || 4) * 1000);
    heroSliderTimer = setInterval(() => {
      const raw = location.hash.slice(1) || '/';
      if (raw === '/' || raw === '') {
        state.currentSlideIndex = (state.currentSlideIndex + 1) % activeBanners.length;
        updateHeroSliderDOM();
      }
    }, intervalMs);
  }

  function startBannerSliderTimer() {
    startHeroSliderInterval();
  }
  window.startBannerSliderTimer = startBannerSliderTimer;
  window.startHeroSliderInterval = startHeroSliderInterval;

  function updateHeroSliderDOM() {
    const activeBanners = (state.banners || []).filter((b) => b.is_active);
    if (!activeBanners.length) return;
    if (state.currentSlideIndex >= activeBanners.length) state.currentSlideIndex = 0;

    const track = $('#heroSlidesTrack');
    const counter = $('#heroSlideCounter');
    const dots = $$('.hero-slider-dot');

    if (track) {
      track.style.transform = `translateX(-${state.currentSlideIndex * 100}%)`;
    }
    if (counter) {
      counter.textContent = `${state.currentSlideIndex + 1} / ${activeBanners.length}`;
    }
    dots.forEach((d, idx) => {
      d.classList.toggle('active', idx === state.currentSlideIndex);
    });
  }

  window.stepHeroSlide = function (delta) {
    const activeBanners = (state.banners || []).filter((b) => b.is_active);
    if (!activeBanners.length) return;
    state.currentSlideIndex = (state.currentSlideIndex + delta + activeBanners.length) % activeBanners.length;
    updateHeroSliderDOM();
    startHeroSliderInterval();
  };

  window.goToHeroSlide = function (idx) {
    state.currentSlideIndex = Number(idx) || 0;
    updateHeroSliderDOM();
    startHeroSliderInterval();
  };

  function renderHomeView() {
    const featured = state.products.filter((p) => p.is_featured);
    const activeCoupons = state.coupons.filter((c) => c.is_active);
    const couponSlides = [];
    for (let i = 0; i < activeCoupons.length; i += 2) {
      couponSlides.push(activeCoupons.slice(i, i + 2));
    }
    const couponSlideCount = couponSlides.length || 1;
    if (state.currentCouponSlideIndex >= couponSlideCount) {
      state.currentCouponSlideIndex = 0;
    }

    const activeBanners = (state.banners || []).filter((b) => b.is_active);
    const activeCampaigns = (state.campaigns || []).filter((c) => c.is_active);
    const ownSellerStore = getSellerOwnStore();

    if (state.currentSlideIndex >= activeBanners.length) {
      state.currentSlideIndex = 0;
    }

    setTimeout(() => startHeroSliderInterval(), 60);
    setTimeout(() => startCouponSliderInterval(), 100);

    return `
      <!-- Village & Digital Haat Hero Section with Daraz-Style Multi-Slide Banner -->
      <section class="village-hero-section">
        <div class="container hero-layout-grid">
          <!-- Categories Sidebar with Hover Popup Subcategories -->
          <div class="haat-category-sidebar">
            <div style="padding:10px 16px;font-weight:800;font-size:12px;color:var(--haat-primary);border-bottom:1px solid #F1F5F9;text-transform:uppercase;letter-spacing:0.5px;">
              <i class="bi bi-grid-fill"></i> Marketplace Departments
            </div>
            ${state.categories
              .map(
                (c) => `
              <div class="cat-sidebar-item" onclick="location.hash='#/category/${c.slug}'">
                <span><i class="bi ${c.icon}" style="margin-right:8px;color:var(--haat-primary)"></i> ${esc(c.name)}</span>
                <i class="bi bi-chevron-right"></i>

                <!-- Hover Flyout Subcategories Popup -->
                <div class="cat-flyout-popup" onclick="event.stopPropagation()">
                  <div class="cat-flyout-head">
                    <strong><i class="bi ${c.icon}"></i> ${esc(c.name)}</strong>
                    <a href="#/category/${c.slug}">View All &rarr;</a>
                  </div>
                  <div class="cat-flyout-list">
                    ${(c.subcategories || [])
                      .map(
                        (sub) => `
                      <a href="#/category/${sub.slug}" class="cat-flyout-link">
                        <span>${esc(sub.name)}</span>
                        <i class="bi bi-arrow-right-short"></i>
                      </a>
                    `
                      )
                      .join('')}
                  </div>
                </div>
              </div>
            `
              )
              .join('')}
          </div>

          <!-- Daraz-Style Multi-Slide Hero Banner Carousel (Controlled by Admin) -->
          <div class="hero-slider-wrapper">
            <div class="hero-slide-counter" id="heroSlideCounter">${activeBanners.length ? `${state.currentSlideIndex + 1} / ${activeBanners.length}` : '0 / 0'}</div>
            <div class="hero-slides-track" id="heroSlidesTrack" style="transform: translateX(-${state.currentSlideIndex * 100}%);">
              ${activeBanners
                .map(
                  (b) => `
                <div class="hero-slide" style="background-image: url('${esc(b.image_url)}');" onclick="location.hash='${esc(b.target_link || '#/products')}'">
                  <div class="hero-slide-content">
                    <span class="hero-slide-tag"><i class="bi bi-stars"></i> ${esc(b.badge || 'MEGA CAMPAIGN')}</span>
                    <h1>${esc(b.title)}</h1>
                    <p>${esc(b.subtitle || '')}</p>
                    <button type="button" class="hero-slide-cta">
                      ${esc(b.cta_text || 'Shop Now')} <i class="bi bi-arrow-right"></i>
                    </button>
                  </div>
                </div>
              `
                )
                .join('')}
            </div>

            ${
              activeBanners.length > 1
                ? `
              <button type="button" class="hero-slider-arrow prev" onclick="event.stopPropagation(); window.stepHeroSlide(-1)" aria-label="Previous Slide">
                <i class="bi bi-chevron-left"></i>
              </button>
              <button type="button" class="hero-slider-arrow next" onclick="event.stopPropagation(); window.stepHeroSlide(1)" aria-label="Next Slide">
                <i class="bi bi-chevron-right"></i>
              </button>
              <div class="hero-slider-dots">
                ${activeBanners
                  .map(
                    (_, idx) => `
                  <button type="button" class="hero-slider-dot ${idx === state.currentSlideIndex ? 'active' : ''}" onclick="event.stopPropagation(); window.goToHeroSlide(${idx})" aria-label="Slide ${idx + 1}"></button>
                `
                  )
                  .join('')}
              </div>
            `
                : ''
            }
          </div>
        </div>
      </section>

      <!-- Collectible Coupons Banner Slider Section (Banner Slide Type) -->
      <section class="container section-block">
        <div class="coupon-banner-section-shell">
          <div class="coupon-banner-header">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <span class="coord-title"><i class="bi bi-ticket-perforated-fill" style="color:var(--haat-orange)"></i> COLLECTIBLE VOUCHERS & CATEGORY CAMPAIGNS</span>
              <span class="haversine-pill">${state.activeRole === 'customer' ? `${state.collectedCoupons.length} Collected` : state.activeRole === 'seller' ? 'VIEW ONLY FOR SELLERS' : 'LOGIN TO COLLECT'}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
              <span id="couponSlideCounter" style="font-size:12px;font-weight:700;color:var(--text-muted);margin-right:4px;">${state.currentCouponSlideIndex + 1} / ${couponSlideCount}</span>
              <button type="button" class="coupon-slide-nav-btn" onclick="window.stepCouponBannerSlide(-1)" title="Previous Voucher Slide" aria-label="Previous Voucher Banner Slide">
                <i class="bi bi-chevron-left"></i>
              </button>
              <button type="button" class="coupon-slide-nav-btn" onclick="window.stepCouponBannerSlide(1)" title="Next Voucher Slide" aria-label="Next Voucher Banner Slide">
                <i class="bi bi-chevron-right"></i>
              </button>
            </div>
          </div>

          <div class="coupon-banner-slider-wrapper">
            <div class="coupon-banner-track" id="couponBannerTrack" style="transform: translateX(-${state.currentCouponSlideIndex * 100}%);">
              ${couponSlides
                .map(
                  (slideList) => `
                <div class="coupon-banner-slide">
                  <div class="coupon-banner-grid">
                    ${slideList.map(renderCouponBannerItem).join('')}
                  </div>
                </div>
              `
                )
                .join('')}
            </div>

            ${
              couponSlideCount > 1
                ? `
              <div class="coupon-slider-dots">
                ${couponSlides
                  .map(
                    (_, idx) => `
                  <button type="button" class="coupon-slider-dot ${idx === state.currentCouponSlideIndex ? 'active' : ''}" onclick="window.goToCouponBannerSlide(${idx})" aria-label="Voucher Slide ${idx + 1}"></button>
                `
                  )
                  .join('')}
              </div>
            `
                : ''
            }
          </div>
        </div>
      </section>

      <!-- Featured Products Section -->
      <section class="container section-block">
        <div class="section-head-bar">
          <h2><i class="bi bi-stars" style="color:var(--haat-orange)"></i> Featured Products in HAAT</h2>
          <a href="#/products" class="btn-see-all-flash">View All Products &rarr;</a>
        </div>
        <div class="marketplace-product-grid">
          ${featured.map(renderProductCard).join('')}
        </div>
      </section>

      <!-- Popular Merchant Stores (No Latitude/Longitude Shown; Sellers Cannot Visit Own Store) -->
      <section class="container section-block">
        <div class="section-head-bar">
          <h2><i class="bi bi-shop-window" style="color:var(--haat-primary)"></i> Popular Merchant Mini-Stores</h2>
          <a href="#/stores" class="btn-see-all-flash">Browse All Stores &rarr;</a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
          ${state.stores
            .filter((s) => s.is_published && (!ownSellerStore || s.id !== ownSellerStore.id))
            .map(
              (s) => `
            <div class="haat-item-card" onclick="window.handleVisitStore('${esc(s.store_slug)}')" style="padding:16px;">
              <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
                <div style="width:48px;height:48px;border-radius:10px;background:${s.primary_color};color:#fff;display:grid;place-items:center;font-weight:800;font-size:18px;">
                  ${esc(s.logo_text)}
                </div>
                <div>
                  <h4 style="font-size:14.5px;font-weight:700;color:#1E293B;">${esc(s.store_name)}</h4>
                  <small style="color:var(--text-muted);"><i class="bi bi-geo-alt"></i> ${esc(s.district)}, ${esc(s.division)}</small>
                </div>
              </div>
              <p style="font-size:12px;color:#475569;margin-bottom:12px;line-height:1.4;">${esc(s.description)}</p>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:11.5px;border-top:1px solid #F1F5F9;padding-top:10px;">
                ${
                  s.free_delivery
                    ? `<span class="delivery-badge-free"><i class="bi bi-truck"></i> Free Delivery Store</span>`
                    : `<span class="delivery-badge-fee"><i class="bi bi-truck"></i> Delivery: ${money(s.delivery_charge ?? 60)}</span>`
                }
                <span style="color:var(--haat-orange);font-weight:700;">Visit Store &rarr;</span>
              </div>
            </div>
          `
            )
            .join('')}
        </div>
      </section>
    `;
  }

  /* =========================================================================
     8. PAGE VIEW: 2. PRODUCT LISTING WITH ADVANCED FILTERS
     ========================================================================= */

  function renderProductsView() {
    let filtered = [...state.products];

    // Filter by search query
    if (state.searchQuery) {
      const q = state.searchQuery.toLowerCase();
      filtered = filtered.filter(
        (p) =>
          p.name.toLowerCase().includes(q) ||
          p.description.toLowerCase().includes(q) ||
          p.sku.toLowerCase().includes(q)
      );
    }

    // Filter by category
    if (state.selectedCategory) {
      const cat = (state.categories || []).find((c) =>
        c.slug === state.selectedCategory ||
        String(c.id) === String(state.selectedCategory) ||
        (c.subcategories || []).some((s) => s.slug === state.selectedCategory || String(s.id) === String(state.selectedCategory))
      );
      if (cat) {
        const sub = (cat.subcategories || []).find((s) => s.slug === state.selectedCategory || String(s.id) === String(state.selectedCategory));
        if (sub) {
          filtered = filtered.filter((p) => String(p.subcategory_id) === String(sub.id) || String(p.category_id) === String(cat.id));
        } else {
          const subIds = cat.subcategories.map((s) => String(s.id));
          filtered = filtered.filter((p) => subIds.includes(String(p.subcategory_id)) || String(p.category_id) === String(cat.id));
        }
      } else {
        filtered = [];
      }
    }

    // Filter by brand
    if (state.filterBrand) {
      filtered = filtered.filter((p) => p.brand_id === Number(state.filterBrand));
    }

    // Filter by in-stock only
    if (state.filterInStockOnly) {
      filtered = filtered.filter((p) => getInventory(p.id).available > 0);
    }

    return `
      <div class="container">
        <!-- Breadcrumb / Header -->
        <div class="listing-header-bar" style="margin-top:20px;">
          <div class="listing-title-wrap">
            <h2>Marketplace Catalog</h2>
            <small>Showing ${filtered.length} products ${state.searchQuery ? `matching "${esc(state.searchQuery)}"` : ''}</small>
          </div>
          <div style="display:flex;align-items:center;gap:10px;">
            <label style="font-size:12px;font-weight:700;color:var(--text-muted);">SORT BY:</label>
            <select style="padding:6px 12px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;" onchange="window.sortProducts(this.value)">
              <option value="featured">Featured First</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
              <option value="rating">Top Rated</option>
            </select>
          </div>
        </div>

        <div class="listing-layout-grid">
          <!-- Sidebar Filters -->
          <aside class="filter-sidebar-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #E2E8F0;">
              <strong style="font-size:14px;color:#1E293B;"><i class="bi bi-funnel"></i> FILTERS</strong>
              <button type="button" onclick="window.resetFilters()" style="background:none;border:none;color:var(--haat-orange);font-size:11.5px;font-weight:700;cursor:pointer;">Reset</button>
            </div>

            <!-- Categories Tree -->
            <div class="filter-group">
              <span class="filter-title">Categories</span>
              <div class="filter-list">
                <label class="filter-item-label">
                  <span>
                    <input type="radio" name="catFilter" value="" ${!state.selectedCategory ? 'checked' : ''} onchange="window.filterCat('')">
                    All Categories
                  </span>
                  <span class="filter-badge-count">${state.products.length}</span>
                </label>
                ${state.categories
                  .map((c) => {
                    const count = state.products.filter((p) => {
                      const subs = c.subcategories.map((s) => s.id);
                      return subs.includes(p.subcategory_id);
                    }).length;
                    return `
                    <label class="filter-item-label">
                      <span>
                        <input type="radio" name="catFilter" value="${c.slug}" ${state.selectedCategory === c.slug ? 'checked' : ''} onchange="window.filterCat('${c.slug}')">
                        ${esc(c.name)}
                      </span>
                      <span class="filter-badge-count">${count}</span>
                    </label>
                  `;
                  })
                  .join('')}
              </div>
            </div>

            <!-- Brand Filter -->
            <div class="filter-group">
              <span class="filter-title">Brands</span>
              <div class="filter-list">
                ${state.brands
                  .map(
                    (b) => `
                  <label class="filter-item-label">
                    <span>
                      <input type="radio" name="brandFilter" value="${b.id}" ${state.filterBrand === b.id ? 'checked' : ''} onchange="window.filterBrand(${b.id})">
                      ${esc(b.name)}
                    </span>
                  </label>
                `
                  )
                  .join('')}
              </div>
            </div>

            <!-- Availability -->
            <div class="filter-group">
              <span class="filter-title">Availability</span>
              <label class="filter-item-label">
                <span>
                  <input type="checkbox" ${state.filterInStockOnly ? 'checked' : ''} onchange="window.toggleInStock(this.checked)">
                  In Stock Only
                </span>
              </label>
            </div>
          </aside>

          <!-- Products Grid -->
          <main>
            ${
              filtered.length
                ? `<div class="marketplace-product-grid">${filtered.map(renderProductCard).join('')}</div>`
                : `<div class="page-card" style="text-align:center;padding:60px 20px;background:#fff;border-radius:8px;">
                    <i class="bi bi-search" style="font-size:40px;color:#94A3B8;"></i>
                    <h3 style="margin:12px 0;font-size:18px;">No matching products found</h3>
                    <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">Try clearing filters or search terms.</p>
                    <button class="btn-village-primary" onclick="window.resetFilters()">Clear Filters</button>
                  </div>`
            }
          </main>
        </div>
      </div>
    `;
  }

  window.filterCat = function (slug) {
    state.selectedCategory = slug || null;
    if (slug) {
      location.hash = `#/category/${slug}`;
    } else {
      location.hash = '#/products';
    }
  };
  window.filterBrand = function (brandId) {
    state.filterBrand = state.filterBrand === brandId ? null : brandId;
    render();
  };
  window.toggleInStock = function (checked) {
    state.filterInStockOnly = checked;
    render();
  };
  window.resetFilters = function () {
    state.selectedCategory = null;
    state.filterBrand = null;
    state.filterInStockOnly = false;
    state.searchQuery = '';
    render();
  };
  window.sortProducts = function (mode) {
    if (mode === 'price_low') state.products.sort((a, b) => (a.sale_price || a.price) - (b.sale_price || b.price));
    else if (mode === 'price_high') state.products.sort((a, b) => (b.sale_price || b.price) - (a.sale_price || a.price));
    else if (mode === 'rating') state.products.sort((a, b) => (b.rating || 0) - (a.rating || 0));
    else state.products.sort((a, b) => (b.is_featured ? 1 : 0) - (a.is_featured ? 1 : 0));
    render();
  };

  function canCustomerReviewProduct(productId) {
    if (!state.activeRole || state.activeRole !== 'customer' || !state.currentUser) {
      return { eligible: false, reason: 'Please log in to your customer account to submit a review.' };
    }
    const currentUserId = state.currentUser.id;
    const userOrders = (state.orders || []).filter(
      (o) => o.user_id === currentUserId || o.shipping_phone === state.currentUser.phone
    );

    const ordersWithProduct = userOrders.filter((o) => {
      const inSellerOrders = (o.seller_orders || []).some((so) =>
        (so.items || []).some((it) => Number(it.product_id) === Number(productId))
      );
      const inDirectItems = (o.items || []).some((it) => Number(it.product_id) === Number(productId));
      return inSellerOrders || inDirectItems;
    });

    if (!ordersWithProduct.length) {
      return {
        eligible: false,
        reason: 'Only customers who have ordered and received this product can write a review.'
      };
    }

    const deliveredOrder = ordersWithProduct.find(
      (o) =>
        o.order_status === 'delivered' ||
        (o.seller_orders || []).some(
          (so) =>
            so.status === 'delivered' &&
            (so.items || []).some((it) => Number(it.product_id) === Number(productId))
        )
    );

    if (!deliveredOrder) {
      return {
        eligible: false,
        reason: 'You can write a review once your order has been successfully delivered by HATEX.'
      };
    }

    return { eligible: true, order: deliveredOrder };
  }
  window.canCustomerReviewProduct = canCustomerReviewProduct;

  /* =========================================================================
     9. PAGE VIEW: 3. PRODUCT DETAILS PAGE
     ========================================================================= */

  function renderProductDetailView(productId) {
    const p = getProduct(productId);
    if (!p) {
      return `
        <div class="container" style="padding:60px 0;text-align:center;">
          <h2>Product Not Found</h2>
          <a href="#/products" class="btn-village-primary" style="margin-top:16px;display:inline-block;">Return to Catalog</a>
        </div>
      `;
    }

    const store = getStore(p.store_id) || state.stores[0];
    const inv = getInventory(p.id);
    const { category, subcategory } = getCategoryBySub(p.subcategory_id);
    const reviews = state.reviews.filter((r) => r.product_id === p.id);
    const isOut = inv.available <= 0;
    const priceInfo = getEffectiveProductPrice(p);
    const delivInfo = getProductDeliveryInfo(p);
    const ownSellerStore = getSellerOwnStore();
    const isOwnStoreProduct = ownSellerStore && ownSellerStore.id === store.id;

    return `
      <div class="container">
        <!-- Breadcrumb -->
        <div style="margin:16px 0;font-size:12.5px;color:var(--text-muted);display:flex;gap:6px;align-items:center;">
          <a href="#/" style="color:#64748B;">Home</a> /
          <a href="#/category/${category.slug}" style="color:#64748B;">${esc(category.name)}</a> /
          <a href="#/products" style="color:#64748B;">${esc(subcategory.name)}</a> /
          <span style="color:#1E293B;font-weight:700;">${esc(p.name)}</span>
        </div>

        <div class="detail-box-layout">
          <!-- Col 1: Gallery with Thumbnails -->
          <div class="detail-gallery-col">
            <div class="gallery-large-view">
              <img id="mainGalleryImg" src="${esc(p.image_1)}" alt="${esc(p.name)}">
            </div>
            <div class="gallery-thumbs-row">
              <div class="thumb-square-btn active" onclick="window.switchDetailImg('${esc(p.image_1)}', this)">
                <img src="${esc(p.image_1)}" alt="Thumb 1">
              </div>
              ${
                p.image_2
                  ? `
                <div class="thumb-square-btn" onclick="window.switchDetailImg('${esc(p.image_2)}', this)">
                  <img src="${esc(p.image_2)}" alt="Thumb 2">
                </div>
              `
                  : ''
              }
              ${
                p.image_3
                  ? `
                <div class="thumb-square-btn" onclick="window.switchDetailImg('${esc(p.image_3)}', this)">
                  <img src="${esc(p.image_3)}" alt="Thumb 3">
                </div>
              `
                  : ''
              }
            </div>
          </div>

          <!-- Col 2: Info & Variants Selection -->
          <div class="detail-middle-col">
            <h1>${esc(p.name)}</h1>
            <div class="ratings-meta-strip">
              <span class="star-amber">★★★★★</span>
              <strong>${p.rating || 4.8}</strong>
              <span>(${reviews.length || 24} customer reviews)</span>
              <span>•</span>
              <span style="font-family:var(--font-mono);font-size:11px;">SKU: ${esc(p.sku)}</span>
            </div>

            <!-- Price & Delivery Panel -->
            <div class="price-display-panel">
              <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">
                <span class="price-main-figure">${money(priceInfo.finalPrice)}</span>
                ${priceInfo.hasDiscount ? `<span class="price-old-figure">${money(priceInfo.originalPrice)}</span>` : ''}
                ${priceInfo.badgeText ? `<span class="status-badge verified" style="background:#FFF7ED;color:#EA580C;border:1px solid #FDBA74;">${esc(priceInfo.badgeText)}</span>` : ''}
              </div>
              <div style="margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                ${
                  delivInfo.isFree
                    ? `<span class="delivery-badge-free"><i class="bi bi-truck"></i> ${esc(delivInfo.reason)}</span>`
                    : `<span class="delivery-badge-fee"><i class="bi bi-truck"></i> Delivery Charge: ${money(delivInfo.fee)}${delivInfo.reason ? ` (${esc(delivInfo.reason)})` : ''}</span>`
                }
                <span style="font-size:12px;color:${isOut ? '#DC2626' : '#16A34A'};font-weight:700;">
                  <i class="bi bi-${isOut ? 'x-circle-fill' : 'check-circle-fill'}"></i>
                  ${isOut ? 'OUT OF STOCK' : `IN STOCK: ${inv.available} units available`}
                </span>
              </div>
            </div>

            <!-- Variants -->
            ${
              p.variant_1_name && p.variant_1_value
                ? `
              <div class="variant-selection-group">
                <div class="variant-heading-label">${esc(p.variant_1_name)}:</div>
                <div class="variant-pills-wrap">
                  ${p.variant_1_value
                    .split(',')
                    .map(
                      (v, i) => `
                    <button type="button" class="variant-selector-pill ${i === 0 ? 'active' : ''}" onclick="window.selectVariant(this)">
                      ${esc(v.trim())}
                    </button>
                  `
                    )
                    .join('')}
                </div>
              </div>
            `
                : ''
            }

            ${
              p.variant_2_name && p.variant_2_value
                ? `
              <div class="variant-selection-group">
                <div class="variant-heading-label">${esc(p.variant_2_name)}:</div>
                <div class="variant-pills-wrap">
                  ${p.variant_2_value
                    .split(',')
                    .map(
                      (v, i) => `
                    <button type="button" class="variant-selector-pill ${i === 0 ? 'active' : ''}" onclick="window.selectVariant(this)">
                      ${esc(v.trim())}
                    </button>
                  `
                    )
                    .join('')}
                </div>
              </div>
            `
                : ''
            }

            <!-- Purchasing Actions or Seller Catalog View Only Mode -->
            ${
              state.activeRole === 'seller'
                ? `
              <div style="background:#F8FAFC;border:1.5px dashed #CBD5E1;border-radius:8px;padding:16px;margin:18px 0;">
                <div style="display:flex;align-items:center;gap:8px;color:#334155;font-weight:700;font-size:13.5px;">
                  <i class="bi bi-eye-fill" style="color:var(--haat-primary);font-size:16px;"></i> Seller Catalog View (View Only)
                </div>
                <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;line-height:1.5;">
                  Sellers can view product details and visit other stores in read-only mode, but cannot purchase, collect coupons, or submit reviews.
                </p>
              </div>
            `
                : `
              <!-- Quantity Stepper -->
              <div style="margin-bottom:20px;">
                <div class="variant-heading-label">Quantity:</div>
                <div class="qty-stepper-box">
                  <button type="button" onclick="window.stepDetailQty(-1)">-</button>
                  <input type="number" id="detailQtyInput" value="1" min="1" max="${inv.available || 1}" readonly>
                  <button type="button" onclick="window.stepDetailQty(1)">+</button>
                </div>
              </div>

              <!-- Action Buttons (Redirects Guest to Login) -->
              <div class="detail-action-buttons">
                <button type="button" class="btn-add-cart-instant" ${isOut ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''} onclick="window.handleDetailAddToCart(${p.id})">
                  <i class="bi bi-cart-plus"></i> ${!state.activeRole ? 'Login to Add to Cart' : 'Add to Cart'}
                </button>
                <button type="button" class="btn-buy-instant" ${isOut ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''} onclick="window.handleDetailBuyNow(${p.id})">
                  <i class="bi bi-lightning-charge-fill"></i> ${!state.activeRole ? 'Login to Buy Now' : 'Buy Now'}
                </button>
              </div>
            `
            }

            <!-- Description -->
            <div style="margin-top:24px;border-top:1px solid #E2E8F0;padding-top:16px;">
              <h4 style="font-size:14px;color:#1E293B;margin-bottom:8px;">Product Overview</h4>
              <p style="font-size:13px;color:#475569;line-height:1.6;">${esc(p.description)}</p>
            </div>
          </div>

          <!-- Col 3: Delivery Specs & Merchant Store Card -->
          <div class="delivery-seller-sidebar">
            <div class="merchant-profile-card">
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                <div style="width:44px;height:44px;border-radius:8px;background:${store.primary_color};color:#fff;display:grid;place-items:center;font-weight:800;font-size:16px;">
                  ${esc(store.logo_text)}
                </div>
                <div>
                  <h4 style="font-size:14px;font-weight:700;">${esc(store.store_name)}</h4>
                  <small style="color:var(--text-muted);"><i class="bi bi-geo-alt"></i> ${esc(store.district)}, ${esc(store.division)}</small>
                </div>
              </div>
              <div class="merchant-metrics-strip">
                <div>
                  <div class="merchant-metric-num">98.4%</div>
                  <div class="merchant-metric-lbl">Positive Feedback</div>
                </div>
                <div>
                  <div class="merchant-metric-num">${store.free_delivery ? 'FREE' : money(store.delivery_charge ?? 60)}</div>
                  <div class="merchant-metric-lbl">Store Delivery</div>
                </div>
              </div>
              ${
                isOwnStoreProduct
                  ? `
                <a href="#/dash/seller" class="btn-village-primary" style="display:block;text-align:center;width:100%;font-size:12.5px;padding:8px 0;margin-top:8px;">
                  <i class="bi bi-speedometer2"></i> Manage Your Store in Dashboard
                </a>
              `
                  : state.activeRole === 'seller'
                  ? `
                <button type="button" class="btn-village-primary" style="display:block;text-align:center;width:100%;font-size:12.5px;padding:8px 0;margin-top:8px;" onclick="window.handleVisitStore('${esc(store.store_slug)}')">
                  <i class="bi bi-shop"></i> Visit Merchant Store (View Only)
                </button>
              `
                  : `
                <button type="button" class="btn-village-primary" style="display:block;text-align:center;width:100%;font-size:12.5px;padding:8px 0;margin-top:8px;" onclick="window.handleVisitStore('${esc(store.store_slug)}')">
                  <i class="bi bi-shop"></i> Visit Merchant Store
                </button>
                <button type="button" class="btn-village-outline" style="display:block;width:100%;font-size:12.5px;padding:8px 0;margin-top:6px;color:#334155;border-color:#CBD5E1;" onclick="window.openMessageModal('seller', ${store.id})">
                  <i class="bi bi-chat-dots"></i> ${!state.activeRole ? 'Login to Message Seller' : 'Message Seller'}
                </button>
              `
              }
            </div>

            <!-- HATEX Logistics Delivery Card -->
            <div class="service-spec-card">
              <h4>HATEX Delivery & Shipping</h4>
              <div class="spec-line">
                <i class="bi bi-truck text-success"></i>
                <div>
                  <strong>${delivInfo.isFree ? 'Free Doorstep Delivery' : `Delivery Charge: ${money(delivInfo.fee)}`}</strong>
                  <div style="font-size:11.5px;color:var(--text-muted);">${esc(delivInfo.reason)} • 24–48 Hours across Bangladesh</div>
                </div>
              </div>
              <div class="spec-line">
                <i class="bi bi-shield-check text-success"></i>
                <div>
                  <strong>7 Days Easy Return</strong>
                  <div style="font-size:11.5px;color:var(--text-muted);">Hassle-free replacement for defective items</div>
                </div>
              </div>
              <div class="spec-line">
                <i class="bi bi-cash-coin text-success"></i>
                <div>
                  <strong>Cash on Delivery (COD) Available</strong>
                  <div style="font-size:11.5px;color:var(--text-muted);">Also supports bKash & Nagad with full delivery breakdown</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer Reviews Section -->
        <div class="page-card" style="background:#fff;border-radius:8px;padding:24px;border:1px solid #E2E8F0;margin-bottom:40px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;border-bottom:1px solid #F1F5F9;padding-bottom:12px;">
            <h3 style="font-size:18px;font-weight:700;"><i class="bi bi-chat-left-text-fill" style="color:var(--haat-orange)"></i> Customer Reviews (${reviews.length})</h3>
            ${(() => {
              if (state.activeRole === 'seller') {
                return '<span style="font-size:12px;color:var(--text-muted);display:inline-flex;align-items:center;gap:5px;"><i class="bi bi-shield-lock"></i> Sellers cannot submit reviews</span>';
              }
              if (!state.activeRole) {
                return `<button type="button" class="btn-village-primary" onclick="window.openReviewModal(${p.id})"><i class="bi bi-box-arrow-in-right"></i> Login to Write a Review</button>`;
              }
              const eligibility = canCustomerReviewProduct(p.id);
              if (eligibility.eligible) {
                return `<button type="button" class="btn-village-primary" onclick="window.openReviewModal(${p.id})"><i class="bi bi-star-fill text-warning"></i> + Write a Review</button>`;
              }
              return `<span style="font-size:12px;color:#64748B;background:#F8FAFC;border:1px solid #E2E8F0;padding:6px 12px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;"><i class="bi bi-shield-lock text-warning"></i> ${esc(eligibility.reason)}</span>`;
            })()}
          </div>
          ${
            reviews.length
              ? reviews
                  .map(
                    (r) => `
                <div style="padding:14px 0;border-bottom:1px solid #F1F5F9;">
                  <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                    <span class="star-amber">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</span>
                    <strong style="font-size:13px;">${esc(r.user_name)}</strong>
                    <span class="status-badge verified" style="font-size:10px;"><i class="bi bi-patch-check-fill"></i> Verified Purchase</span>
                    <small style="color:var(--text-muted);margin-left:auto;">${r.created_at}</small>
                  </div>
                  <p style="font-size:13px;color:#334155;">${esc(r.comment)}</p>
                </div>
              `
                  )
                  .join('')
              : '<p style="color:var(--text-muted);font-size:13px;">No reviews yet. Be the first to share your experience!</p>'
          }
        </div>
      </div>
    `;
  }

  window.switchDetailImg = function (src, el) {
    const main = $('#mainGalleryImg');
    if (main) main.src = src;
    $$('.thumb-square-btn').forEach((t) => t.classList.remove('active'));
    el.classList.add('active');
  };

  window.selectVariant = function (btn) {
    const parent = btn.closest('.variant-pills-wrap');
    if (parent) {
      parent.querySelectorAll('.variant-selector-pill').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
    }
  };

  window.stepDetailQty = function (delta) {
    const input = $('#detailQtyInput');
    if (!input) return;
    let v = parseInt(input.value) + delta;
    const max = parseInt(input.max) || 99;
    if (v < 1) v = 1;
    if (v > max) v = max;
    input.value = v;
  };

  window.handleDetailAddToCart = function (productId) {
    if (!state.activeRole) {
      showToast('Please log in to shop and add items to cart!', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'seller') {
      showToast('Sellers are not permitted to make purchases or add items to cart.', 'warning');
      return;
    }
    const qty = parseInt($('#detailQtyInput')?.value || 1);
    addToCartDirect(productId, qty);
  };

  window.handleDetailBuyNow = function (productId) {
    if (!state.activeRole) {
      showToast('Please log in to purchase items!', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'seller') {
      showToast('Sellers are not permitted to make purchases.', 'warning');
      return;
    }
    window.handleDetailAddToCart(productId);
    location.hash = '#/cart';
  };

  window.handleVisitStore = function (slug) {
    location.hash = `#/store/${slug}`;
  };

  /* =========================================================================
     10. PAGE VIEW: 4. SELLER MINI-STORE (`#/store/:slug`)
     ========================================================================= */

  function renderStoreView(slug) {
    const store = getStore(slug);
    if (!store) {
      return `
        <div class="container" style="padding:60px 16px;text-align:center;">
          <div class="page-card" style="max-width:480px;margin:0 auto;background:#fff;padding:40px;border-radius:12px;border:1px solid #E2E8F0;">
            <i class="bi bi-shop-window" style="font-size:44px;color:#94A3B8;margin-bottom:12px;display:block;"></i>
            <h2 style="font-size:20px;font-weight:800;color:#1E293B;margin-bottom:8px;">Store Not Found</h2>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">The store you are looking for does not exist or may have been temporarily deactivated.</p>
            <a href="#/stores" class="btn-village-primary"><i class="bi bi-arrow-left"></i> Browse All Stores</a>
          </div>
        </div>
      `;
    }
    const ownStore = getSellerOwnStore();
    const isOwnStore = state.activeRole === 'seller' && ownStore && ownStore.id === store.id;

    const storeProducts = state.products.filter((p) => p.store_id === store.id);
    const storeCollections = state.collections.filter((c) => c.store_id === store.id);
    const storeCoupons = state.coupons.filter((c) => c.store_id === store.id && c.is_active);

    return `
      <div class="container">
        ${
          isOwnStore
            ? `
          <div style="background:#EFF6FF;border:1.5px solid #93C5FD;border-radius:10px;padding:12px 18px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;color:#1E40AF;font-size:13px;font-weight:600;">
              <i class="bi bi-eye-fill" style="font-size:18px;color:#2563EB;"></i>
              <span><strong>Your Storefront Live View:</strong> You are viewing your merchant storefront as customers see it. Purchasing, reviews, and messaging are disabled for seller accounts.</span>
            </div>
            <a href="#/dash/seller/store" class="btn-village-primary" style="padding:6px 14px;font-size:12px;border-radius:6px;text-decoration:none;"><i class="bi bi-gear-fill"></i> Store Settings</a>
          </div>
        `
            : state.activeRole === 'seller'
            ? `
          <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:10px;padding:10px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;color:#475569;font-size:12.5px;">
            <i class="bi bi-info-circle-fill" style="color:var(--haat-primary);"></i>
            <span><strong>Merchant View Mode:</strong> Sellers can view other storefront catalogs in read-only mode (purchases and messaging disabled).</span>
          </div>
        `
            : ''
        }
        <!-- Store Banner Header (No Lat/Lon Displayed) -->
        <div class="storefront-hero-banner" style="background:${store.banner_gradient};">
          <div class="store-hero-left">
            <div class="store-big-avatar" style="color:${store.primary_color};">
              ${esc(store.logo_text)}
            </div>
            <div class="store-hero-meta">
              <h1>${esc(store.store_name)} <i class="bi bi-patch-check-fill" style="color:var(--haat-gold);font-size:18px;"></i></h1>
              <p>${esc(store.description)}</p>
              <div style="display:flex;gap:12px;align-items:center;margin-top:8px;font-size:12px;flex-wrap:wrap;">
                <span><i class="bi bi-geo-alt-fill"></i> ${esc(store.address)}, ${esc(store.district)}</span>
                <span class="haversine-pill" style="background:rgba(255,255,255,0.2);">
                  <i class="bi bi-truck"></i> ${store.free_delivery ? 'Free Delivery Store' : `Delivery Charge: ${money(store.delivery_charge ?? 60)}`}
                </span>
              </div>
            </div>
          </div>
          <div>
            ${
              isOwnStore
                ? `<span class="haversine-pill" style="background:rgba(255,255,255,0.25);color:#fff;font-weight:700;padding:8px 14px;display:inline-block;"><i class="bi bi-patch-check-fill"></i> Your Storefront</span>`
                : state.activeRole === 'seller'
                ? `<span class="voucher-btn-readonly" style="background:rgba(255,255,255,0.2);color:#fff;border-color:rgba(255,255,255,0.35);padding:8px 14px;display:inline-block;"><i class="bi bi-eye"></i> Seller View Only</span>`
                : `<button type="button" class="btn-village-primary" onclick="window.openMessageModal('seller', ${store.id})">
                    <i class="bi bi-chat-dots-fill"></i> ${!state.activeRole ? 'Login to Contact Store' : 'Contact Store'}
                  </button>`
            }
          </div>
        </div>

        <!-- Store Navigation Tabs -->
        <div class="store-tabs-nav">
          <a class="store-tab-link active" onclick="window.switchStoreTab('products', this)">Store Catalog (${storeProducts.length})</a>
          <a class="store-tab-link" onclick="window.switchStoreTab('collections', this)">Store Collections (${storeCollections.length})</a>
          <a class="store-tab-link" onclick="window.switchStoreTab('about', this)">About Merchant</a>
        </div>

        <!-- Tab 1: Products -->
        <div id="storeTabProducts">
          ${
            storeCoupons.length
              ? `
            <div class="coord-distance-card" style="margin-top:0;margin-bottom:20px;">
              <div class="coord-distance-head">
                <span class="coord-title"><i class="bi bi-gift-fill" style="color:var(--haat-orange)"></i> STORE-EXCLUSIVE VOUCHERS</span>
                <span class="haversine-pill">${state.activeRole === 'seller' ? 'VIEW ONLY FOR SELLERS' : 'COLLECT & SAVE'}</span>
              </div>
              <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:12px;">
                ${storeCoupons.map(renderDarazVoucherCard).join('')}
              </div>
            </div>
          `
              : ''
          }
          <div class="marketplace-product-grid" style="margin-bottom:40px;">
            ${storeProducts.map(renderProductCard).join('')}
          </div>
        </div>

        <!-- Tab 2: Collections -->
        <div id="storeTabCollections" style="display:none;margin-bottom:40px;">
          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
            ${storeCollections
              .map(
                (c) => `
              <div class="page-card" style="background:#fff;padding:20px;border-radius:8px;border:1px solid #E2E8F0;">
                <h3 style="color:var(--haat-primary);font-size:16px;">${esc(c.name)}</h3>
                <p style="font-size:12.5px;color:var(--text-muted);margin:8px 0 14px;">Store-specific collection crafted for seasonal and festive shopping.</p>
                <span class="haversine-pill">Verified Merchant Collection</span>
              </div>
            `
              )
              .join('')}
          </div>
        </div>

        <!-- Tab 3: About -->
        <div id="storeTabAbout" style="display:none;margin-bottom:40px;">
          <div class="page-card" style="background:#fff;padding:24px;border-radius:8px;border:1px solid #E2E8F0;">
            <h3 style="font-size:16px;color:#1E293B;margin-bottom:8px;">About ${esc(store.store_name)}</h3>
            <p style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:14px;">${esc(store.description)}</p>
            <div style="font-size:12.5px;color:#64748B;">
              <div><strong>Registered Address:</strong> ${esc(store.address)}, ${esc(store.district)}, ${esc(store.division)} - ${esc(store.postal_code)}</div>
              <div style="margin-top:4px;"><strong>Store Delivery Policy:</strong> ${store.free_delivery ? 'Free Delivery on All Products' : `${money(store.delivery_charge ?? 60)} standard delivery charge`}</div>
              <div style="margin-top:4px;"><strong>Logistics Verification:</strong> Approved HATEX Dispatch Point</div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  window.switchStoreTab = function (tabName, linkEl) {
    $$('.store-tab-link').forEach((l) => l.classList.remove('active'));
    linkEl.classList.add('active');
    $('#storeTabProducts').style.display = tabName === 'products' ? 'block' : 'none';
    $('#storeTabCollections').style.display = tabName === 'collections' ? 'block' : 'none';
    $('#storeTabAbout').style.display = tabName === 'about' ? 'block' : 'none';
  };

  /* =========================================================================
     11. PAGE VIEW: 5. CART (Multi-Vendor Split Order Architecture)
     ========================================================================= */

  function renderCartView() {
    if (!state.activeRole) {
      showToast('Please log in to view your cart.', 'info');
      location.hash = '#/auth';
      return '';
    }
    if (state.activeRole === 'seller') {
      return `
        <div class="container" style="padding:60px 16px;text-align:center;">
          <div class="page-card" style="max-width:540px;margin:0 auto;background:#fff;padding:40px;border-radius:12px;border:1px solid #E2E8F0;box-shadow:var(--shadow-sm);">
            <div style="width:64px;height:64px;border-radius:50%;background:#FFF7ED;color:var(--haat-orange);display:grid;place-items:center;font-size:32px;margin:0 auto 16px;">
              <i class="bi bi-shield-slash"></i>
            </div>
            <h2 style="margin:0 0 10px;font-size:20px;font-weight:800;color:#1E293B;">Purchasing Disabled for Seller Accounts</h2>
            <p style="color:var(--text-muted);font-size:13.5px;line-height:1.6;margin-bottom:24px;">
              Under HAAT multi-vendor marketplace governance, registered seller accounts cannot make purchases, maintain customer carts, or checkout. Sellers have catalog view permissions only.
            </p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
              <a href="#/dash/seller" class="btn-village-primary"><i class="bi bi-speedometer2"></i> Seller Dashboard</a>
              <a href="#/products" class="btn-village-outline"><i class="bi bi-shop"></i> Browse Marketplace</a>
            </div>
          </div>
        </div>
      `;
    }
    if (!state.cart.length) {
      return `
        <div class="container" style="padding:60px 16px;text-align:center;">
          <div class="page-card" style="max-width:500px;margin:0 auto;background:#fff;padding:40px;border-radius:12px;border:1px solid #E2E8F0;">
            <i class="bi bi-cart-x" style="font-size:48px;color:#94A3B8;"></i>
            <h2 style="margin:14px 0 8px;">Your Shopping Cart is Empty</h2>
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">Explore Bangladesh's multi-vendor digital market to discover items.</p>
            <a href="#/products" class="btn-village-primary"><i class="bi bi-bag"></i> Start Shopping</a>
          </div>
        </div>
      `;
    }

    // Group cart items by seller/store
    const groupedByStore = {};
    state.cart.forEach((item, index) => {
      const p = getProduct(item.product_id);
      if (!p) return;
      const sId = p.store_id;
      if (!groupedByStore[sId]) {
        groupedByStore[sId] = {
          store: getStore(sId) || state.stores[0],
          items: []
        };
      }
      groupedByStore[sId].items.push({ ...item, product: p, cartIndex: index });
    });

    let rawSubtotal = 0;
    state.cart.forEach((i) => {
      const p = getProduct(i.product_id);
      if (p) rawSubtotal += Number(getEffectiveProductPrice(p).finalPrice || p.price || 0) * Number(i.quantity || 1);
    });

    // Dynamic shipping cost per store (controlled by Seller Store, Product Free Delivery, or Admin Category Campaign)
    const storeCount = Object.keys(groupedByStore).length;
    let shippingTotal = 0;
    Object.entries(groupedByStore).forEach(([sId, g]) => {
      const shipInfo = getStoreGroupShippingCost(g.store || Number(sId), g.items);
      g.shippingInfo = shipInfo;
      shippingTotal += Number(shipInfo?.cost || 0);
    });
    if (isNaN(shippingTotal)) shippingTotal = 0;

    // Coupon discount calculation
    let discount = 0;
    if (state.appliedCoupon) {
      const evalRes = evaluateCartCoupon(state.appliedCoupon);
      if (evalRes && evalRes.valid) {
        discount = Number(evalRes.discount || 0);
      } else {
        state.appliedCoupon = null;
      }
    }
    const safeSubtotal = Number(rawSubtotal) || 0;
    const safeShipping = Number(shippingTotal) || 0;
    const safeDiscount = Math.min(Number(discount) || 0, safeSubtotal);
    let grandTotal = Math.max(0, safeSubtotal + safeShipping - safeDiscount);
    if (grandTotal === 0 && safeSubtotal > 0 && safeDiscount < safeSubtotal) {
      grandTotal = safeSubtotal + safeShipping;
    }

    // Collected coupons list for quick 1-click apply
    const myCollectedCoupons = (state.collectedCoupons || [])
      .map((id) => state.coupons.find((c) => c.id === id && c.is_active))
      .filter(Boolean);

    return `
      <div class="container">
        <div style="margin:20px 0 12px;display:flex;align-items:center;justify-content:space-between;">
          <h2 style="font-size:22px;font-weight:800;color:#1E293B;">Shopping Cart (${state.cart.length} items from ${storeCount} stores)</h2>
          <button type="button" onclick="window.clearCart()" style="background:none;border:none;color:#DC2626;font-size:12.5px;font-weight:700;cursor:pointer;">
            <i class="bi bi-trash"></i> Empty Cart
          </button>
        </div>

        <div class="cart-split-layout">
          <!-- Multi-Store Order Separation -->
          <div class="cart-vendor-groups">
            ${Object.values(groupedByStore)
              .map((group) => {
                const storeSubtotal = group.items.reduce((s, it) => s + getEffectiveProductPrice(it.product).finalPrice * it.quantity, 0);
                return `
                <div class="seller-cart-container">
                  <div class="seller-cart-head">
                    <div style="display:flex;align-items:center;gap:10px;">
                      <i class="bi bi-shop" style="color:var(--haat-primary);font-size:18px;"></i>
                      <strong style="font-size:15px;color:#1E293B;">${esc(group.store.store_name)}</strong>
                      <span style="font-size:11px;color:var(--text-muted);">(${esc(group.store.district)})</span>
                    </div>
                    <span style="font-size:12.5px;font-weight:700;color:var(--haat-primary);">Store Subtotal: ${money(storeSubtotal)}</span>
                  </div>

                  ${group.items
                    .map((it) => {
                      const unitPrice = getEffectiveProductPrice(it.product).finalPrice;
                      const itemDeliv = getProductDeliveryInfo(it.product);
                      return `
                    <div class="cart-item-line">
                      <img src="${esc(it.product.image_1)}" alt="${esc(it.product.name)}" style="width:64px;height:64px;object-fit:cover;border-radius:6px;">
                      <div>
                        <h4 style="font-size:13.5px;font-weight:700;color:#1E293B;margin-bottom:4px;">${esc(it.product.name)}</h4>
                        <div style="font-size:11.5px;color:var(--text-muted);">${esc(it.variant_name)}: ${esc(it.variant_value)}</div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                          <span style="font-size:13px;font-weight:800;color:var(--haat-orange);">${money(unitPrice)}</span>
                          <span class="${itemDeliv.isFree ? 'delivery-badge-free' : 'delivery-badge-fee'}"><i class="bi bi-truck"></i> ${itemDeliv.isFree ? 'Free Delivery' : `Delivery: ${money(itemDeliv.fee)}`}</span>
                        </div>
                      </div>
                      <div class="qty-stepper-box" style="margin-bottom:0;">
                        <button type="button" onclick="window.updateCartQty(${it.cartIndex}, -1)">-</button>
                        <input type="text" value="${it.quantity}" readonly>
                        <button type="button" onclick="window.updateCartQty(${it.cartIndex}, 1)">+</button>
                      </div>
                      <button type="button" onclick="window.removeCartItem(${it.cartIndex})" style="background:none;border:none;color:#94A3B8;cursor:pointer;font-size:18px;">
                        <i class="bi bi-x-circle"></i>
                      </button>
                    </div>
                  `;
                    })
                    .join('')}

                  <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;margin-top:10px;border-top:1px dashed #E2E8F0;font-size:12px;color:var(--text-muted);">
                    <span><i class="bi bi-truck"></i> Store Delivery Charge: <strong style="color:${group.shippingInfo.isFree ? '#15803D' : '#1E293B'};">${group.shippingInfo.isFree ? 'FREE (৳0)' : money(group.shippingInfo.cost)}</strong></span>
                    <span class="${group.shippingInfo.isFree ? 'delivery-badge-free' : 'delivery-badge-fee'}">${esc(group.shippingInfo.reason)}</span>
                  </div>
                </div>
              `;
              })
              .join('')}
          </div>

          <!-- Summary & Collectible Coupon Selector -->
          <div>
            <div class="cart-summary-panel">
              <h3 style="font-size:16px;font-weight:800;margin-bottom:14px;color:#1E293B;">Order Summary</h3>
              <div class="summary-cost-line">
                <span>Items Subtotal</span>
                <span>${money(rawSubtotal)}</span>
              </div>
              <div class="summary-cost-line">
                <span>Delivery Charge (${storeCount} Store${storeCount > 1 ? 's' : ''})</span>
                <span style="font-weight:700;color:${shippingTotal === 0 ? '#16A34A' : '#1E293B'};">${shippingTotal === 0 ? 'FREE (৳0)' : money(shippingTotal)}</span>
              </div>
              ${
                discount > 0 && state.appliedCoupon
                  ? `
                <div class="summary-cost-line" style="color:#16A34A;font-weight:700;">
                  <span>Coupon Discount (${esc(state.appliedCoupon.code)})</span>
                  <span>-${money(discount)}</span>
                </div>
              `
                  : ''
              }
              <div class="summary-cost-line total">
                <span>Total Order Amount</span>
                <span>${money(grandTotal)}</span>
              </div>

              <!-- Simplified Clean Coupon / Promo Input -->
              <div style="margin-top:16px;padding-top:14px;border-top:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                  <span style="font-size:12.5px;font-weight:700;color:#334155;"><i class="bi bi-ticket-perforated"></i> Voucher / Promo</span>
                  ${
                    myCollectedCoupons.length
                      ? `
                    <select onchange="if(this.value) window.applyCouponCode(this.value)" style="font-size:11.5px;padding:3px 8px;border:1px solid #CBD5E1;border-radius:4px;background:#fff;color:#475569;max-width:160px;cursor:pointer;">
                      <option value="">Apply collected...</option>
                      ${myCollectedCoupons.map((c) => `<option value="${esc(c.code)}" ${state.appliedCoupon?.code === c.code ? 'selected' : ''}>${esc(c.code)} (${c.discount_type === 'percent' ? c.discount_value + '%' : money(c.discount_value)})</option>`).join('')}
                    </select>
                  `
                      : ''
                  }
                </div>
                <div style="display:flex;gap:6px;">
                  <input type="text" id="couponCodeInput" value="${esc(state.appliedCoupon?.code || '')}" placeholder="Enter voucher code" style="flex:1;padding:8px 10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:12.5px;text-transform:uppercase;">
                  <button type="button" class="btn-village-primary" style="padding:8px 14px;font-size:12px;font-weight:700;" onclick="window.applyCouponCode($('#couponCodeInput').value)">Apply</button>
                </div>
                ${
                  state.appliedCoupon
                    ? `
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding:6px 10px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;font-size:12px;color:#15803D;">
                    <span><i class="bi bi-check-circle-fill"></i> <strong>${esc(state.appliedCoupon.code)}</strong> applied (-${money(discount)})</span>
                    <button type="button" onclick="window.removeCoupon()" style="background:none;border:none;color:#DC2626;font-size:11.5px;font-weight:700;cursor:pointer;">Remove</button>
                  </div>
                `
                    : ''
                }
              </div>

              <button type="button" class="btn-checkout-primary" onclick="location.hash='#/checkout'">
                Proceed to Checkout (${money(grandTotal)}) <i class="bi bi-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  window.updateCartQty = function (cartIndex, delta) {
    const it = state.cart[cartIndex];
    if (!it) return;
    const inv = getInventory(it.product_id);
    const newQty = it.quantity + delta;
    if (newQty <= 0) {
      window.removeCartItem(cartIndex);
      return;
    }
    if (newQty > inv.available) {
      showToast(`Only ${inv.available} units in stock.`, 'warning');
      return;
    }
    it.quantity = newQty;
    persist();
    render();
  };

  window.removeCartItem = function (cartIndex) {
    state.cart.splice(cartIndex, 1);
    persist();
    render();
    showToast('Item removed from cart.');
  };

  window.clearCart = function () {
    state.cart = [];
    state.appliedCoupon = null;
    persist();
    render();
  };

  window.applyCouponCode = function (code) {
    if (!code) {
      showToast('Please enter or select a coupon code.', 'warning');
      return;
    }
    const clean = code.trim().toUpperCase();
    const c = state.coupons.find((x) => x.code.toUpperCase() === clean && x.is_active);
    if (!c) {
      showToast('Invalid or expired coupon code.', 'error');
      return;
    }
    const evalRes = evaluateCartCoupon(c, state.cart);
    if (!evalRes.valid) {
      showToast(evalRes.reason, 'warning');
      return;
    }
    if (!state.collectedCoupons.includes(c.id)) {
      state.collectedCoupons.push(c.id);
    }
    state.appliedCoupon = c;
    persist();
    showToast(`${evalRes.reason} (-${money(evalRes.discount)})`, 'success');
    render();
  };

  window.removeCoupon = function () {
    state.appliedCoupon = null;
    persist();
    render();
  };

  /* =========================================================================
     12. PAGE VIEW: 6. CHECKOUT & ORDER CONFIRMATION (COD + Delivery Charge Breakdown)
     ========================================================================= */

  function renderCheckoutView() {
    if (!state.activeRole) {
      showToast('Please log in to complete checkout.', 'info');
      location.hash = '#/auth';
      return '';
    }
    if (state.activeRole === 'seller') {
      location.hash = '#/cart';
      return '';
    }
    if (!state.cart.length) {
      location.hash = '#/cart';
      return '';
    }

    const userAddrs = state.currentUser ? state.addresses.filter(a => a.user_id === state.currentUser.id) : [];
    const defaultAddr = userAddrs.find((a) => a.is_default) || userAddrs[0] || (state.currentUser ? { name: state.currentUser.name, phone: state.currentUser.phone, address: '', district: 'Dhaka', division: 'Dhaka' } : null);
    const selectedMethod = state.selectedPaymentMethod || 'cash_on_delivery';

    // Compute totals with dynamic Seller / Product / Admin Campaign delivery charges
    const grouped = {};
    state.cart.forEach((item) => {
      const p = getProduct(item.product_id);
      if (!p) return;
      if (!grouped[p.store_id]) grouped[p.store_id] = { store: getStore(p.store_id) || state.stores[0], items: [] };
      grouped[p.store_id].items.push({ ...item, product: p });
    });

    let rawSubtotal = 0;
    state.cart.forEach((i) => {
      const p = getProduct(i.product_id);
      if (p) rawSubtotal += Number(getEffectiveProductPrice(p).finalPrice || p.price || 0) * Number(i.quantity || 1);
    });

    let shippingTotal = 0;
    Object.entries(grouped).forEach(([sId, g]) => {
      const shipInfo = getStoreGroupShippingCost(g.store || Number(sId), g.items);
      g.shippingInfo = shipInfo;
      shippingTotal += Number(shipInfo?.cost || 0);
    });
    if (isNaN(shippingTotal)) shippingTotal = 0;

    let discount = 0;
    if (state.appliedCoupon) {
      const evalRes = evaluateCartCoupon(state.appliedCoupon);
      if (evalRes && evalRes.valid) discount = Number(evalRes.discount || 0);
    }
    const safeSubtotal = Number(rawSubtotal) || 0;
    const safeShipping = Number(shippingTotal) || 0;
    const safeDiscount = Math.min(Number(discount) || 0, safeSubtotal);
    let grandTotal = Math.max(0, safeSubtotal + safeShipping - safeDiscount);
    if (grandTotal === 0 && safeSubtotal > 0 && safeDiscount < safeSubtotal) {
      grandTotal = safeSubtotal + safeShipping;
    }

    const myCollectedCoupons = (state.collectedCoupons || [])
      .map((id) => state.coupons.find((c) => c.id === id && c.is_active))
      .filter(Boolean);

    const methodLabels = {
      cash_on_delivery: 'Cash on Delivery (COD)',
      bkash: 'bKash Digital Payment',
      nagad: 'Nagad Digital Payment'
    };

    return `
      <div class="container" style="max-width:920px;margin-top:24px;margin-bottom:60px;">
        <!-- Checkout Stepper Progress -->
        <div class="checkout-stepper-bar">
          <div class="checkout-step-node done">
            <span class="step-num-circle"><i class="bi bi-check"></i></span>
            <span>1. Cart</span>
          </div>
          <div class="checkout-step-node active">
            <span class="step-num-circle">2</span>
            <span>2. Delivery Address</span>
          </div>
          <div class="checkout-step-node active">
            <span class="step-num-circle">3</span>
            <span>3. Payment & Order Confirmation</span>
          </div>
        </div>

        <form onsubmit="event.preventDefault(); window.handlePlaceOrder(this);">
          <!-- Step 1: Delivery Address -->
          <div class="page-card" style="background:#fff;padding:22px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
              <h3 style="font-size:16px;font-weight:800;color:#1E293B;"><i class="bi bi-geo-alt-fill" style="color:var(--haat-orange)"></i> Step 1: Delivery Address</h3>
              <span class="haversine-pill" style="font-size:10px;"><i class="bi bi-truck"></i> Doorstep Delivery</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
              <div class="form-group-auth">
                <label>Recipient Name</label>
                <input type="text" name="shipping_name" value="${esc(defaultAddr?.name || state.currentUser?.name || '')}" required placeholder="Full Name" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
              </div>
              <div class="form-group-auth">
                <label>Contact Phone</label>
                <input type="text" name="shipping_phone" value="${esc(defaultAddr?.phone || state.currentUser?.phone || '')}" required placeholder="017xxxxxxxx" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
              </div>
            </div>

            <div class="form-group-auth" style="margin-bottom:12px;">
              <label>Street Address (House, Road, Area)</label>
              <input type="text" name="shipping_address" value="${esc(defaultAddr?.address || '')}" required placeholder="House, Road, Area" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
              <div class="form-group-auth">
                <label>District</label>
                <select name="district" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
                  <option value="Dhaka" selected>Dhaka</option>
                  <option value="Gazipur">Gazipur</option>
                  <option value="Narayanganj">Narayanganj</option>
                  <option value="Chittagong">Chittagong</option>
                  <option value="Sylhet">Sylhet</option>
                </select>
              </div>
              <div class="form-group-auth">
                <label>Division</label>
                <select name="division" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
                  <option value="Dhaka" selected>Dhaka</option>
                  <option value="Chittagong">Chittagong</option>
                  <option value="Rajshahi">Rajshahi</option>
                  <option value="Khulna">Khulna</option>
                  <option value="Sylhet">Sylhet</option>
                </select>
              </div>
              <div class="form-group-auth">
                <label>Postal Code</label>
                <input type="text" name="postal_code" value="${esc(defaultAddr?.postal_code || '1216')}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
              </div>
            </div>
          </div>

          <!-- Step 2: Payment Method (COD & Digital Methods with Live Total Order + Delivery Charge Summary) -->
          <div class="page-card" style="background:#fff;padding:22px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:20px;">
            <h3 style="font-size:16px;font-weight:800;color:#1E293B;margin-bottom:14px;"><i class="bi bi-credit-card-2-front-fill" style="color:var(--haat-primary)"></i> Step 2: Select Payment Method</h3>

            <div class="payment-method-tile ${selectedMethod === 'cash_on_delivery' ? 'selected' : ''}" onclick="window.selectPaymentMethod('cash_on_delivery', this, ${rawSubtotal}, ${shippingTotal}, ${discount}, ${grandTotal})">
              <div style="display:flex;align-items:center;gap:12px;">
                <input type="radio" name="payment_method" value="cash_on_delivery" ${selectedMethod === 'cash_on_delivery' ? 'checked' : ''}>
                <div>
                  <strong>Cash on Delivery (COD)</strong>
                  <div style="font-size:11.5px;color:var(--text-muted);">Pay in cash when the HATEX delivery rider hands over your parcel at your doorstep</div>
                </div>
              </div>
              <span class="pay-badge cod">COD • ${money(grandTotal)}</span>
            </div>

            <div class="payment-method-tile ${selectedMethod === 'bkash' ? 'selected' : ''}" onclick="window.selectPaymentMethod('bkash', this, ${rawSubtotal}, ${shippingTotal}, ${discount}, ${grandTotal})">
              <div style="display:flex;align-items:center;gap:12px;">
                <input type="radio" name="payment_method" value="bkash" ${selectedMethod === 'bkash' ? 'checked' : ''}>
                <div>
                  <strong>bKash Digital Payment (Manual TrxID)</strong>
                  <div style="font-size:11.5px;color:var(--text-muted);">Send Money / Merchant Payment to <code>01811223344</code></div>
                </div>
              </div>
              <span class="pay-badge bkash">bKash • ${money(grandTotal)}</span>
            </div>

            <div class="payment-method-tile ${selectedMethod === 'nagad' ? 'selected' : ''}" onclick="window.selectPaymentMethod('nagad', this, ${rawSubtotal}, ${shippingTotal}, ${discount}, ${grandTotal})">
              <div style="display:flex;align-items:center;gap:12px;">
                <input type="radio" name="payment_method" value="nagad" ${selectedMethod === 'nagad' ? 'checked' : ''}>
                <div>
                  <strong>Nagad Digital Payment</strong>
                  <div style="font-size:11.5px;color:var(--text-muted);">Send to Nagad Merchant <code>01911223344</code></div>
                </div>
              </div>
              <span class="pay-badge nagad">Nagad • ${money(grandTotal)}</span>
            </div>

            <!-- Live Payment Method Total Order + Delivery Charge Confirmation Banner -->
            <div id="checkoutPaymentTotalBanner" style="margin-top:14px;background:linear-gradient(135deg, #ECFDF5 0%, #F0FDF4 100%);border:1.5px solid #86EFAC;border-radius:10px;padding:16px;">
              <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                  <span style="font-size:11px;font-weight:800;text-transform:uppercase;color:#15803D;display:block;">
                    <i class="bi bi-check-circle-fill"></i> SELECTED PAYMENT SUMMARY (<span id="selectedPayMethodTitle">${esc(methodLabels[selectedMethod])}</span>)
                  </span>
                  <div style="font-size:13px;color:#1E293B;margin-top:4px;">
                    Items Subtotal: <strong>${money(rawSubtotal)}</strong>
                    + Delivery Charge: <strong>${shippingTotal === 0 ? 'FREE (৳0)' : money(shippingTotal)}</strong>
                    ${discount > 0 ? `- Coupon Discount: <strong style="color:#16A34A;">${money(discount)}</strong>` : ''}
                  </div>
                </div>
                <div style="text-align:right;">
                  <div style="font-size:11px;color:#475569;font-weight:700;">Total Amount with Delivery Charge</div>
                  <div style="font-size:22px;font-weight:800;color:#EA580C;" id="selectedPayGrandTotal">${money(grandTotal)}</div>
                </div>
              </div>
            </div>

            <!-- Manual Transaction Reference Input Field (Only required for bKash/Nagad) -->
            <div id="trxIdContainer" style="margin-top:14px;background:#FAF8F5;padding:14px;border-radius:8px;border:1px solid #EBE4D8;display:${selectedMethod === 'cash_on_delivery' ? 'none' : 'block'};">
              <label style="display:block;font-size:12.5px;font-weight:700;color:#1E293B;margin-bottom:6px;">
                Transaction Reference / TrxID <span style="color:#DC2626;">*</span>
              </label>
              <input type="text" name="transaction_reference" value="TRX-${Date.now().toString().slice(-6)}" placeholder="e.g. 9J48A92BLP" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-family:var(--font-mono);font-size:13.5px;">
              <small style="color:var(--text-muted);font-size:11px;display:block;margin-top:4px;">Admin verifies this transaction reference for digital payments.</small>
            </div>
          </div>

          <!-- Step 3: Order Confirmation & Full Store + Delivery Charge Breakdown -->
          <div class="page-card" style="background:#fff;padding:22px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:24px;">
            <h3 style="font-size:16px;font-weight:800;color:#1E293B;margin-bottom:14px;"><i class="bi bi-receipt" style="color:var(--haat-orange)"></i> Step 3: Order Confirmation (${Object.keys(grouped).length} Seller Store${Object.keys(grouped).length > 1 ? 's' : ''})</h3>

            ${Object.values(grouped)
              .map((g) => {
                const storeSub = g.items.reduce((s, it) => s + getEffectiveProductPrice(it.product).finalPrice * it.quantity, 0);
                return `
              <div style="padding:12px;border:1px solid #E2E8F0;border-radius:8px;margin-bottom:12px;background:#F8FAFC;">
                <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:13.5px;margin-bottom:6px;">
                  <span><i class="bi bi-shop" style="color:var(--haat-primary);"></i> ${esc(g.store.store_name)}</span>
                  <span>Items: ${money(storeSub)}</span>
                </div>
                ${g.items
                  .map((it) => {
                    const uPrice = getEffectiveProductPrice(it.product).finalPrice;
                    return `<div style="font-size:12px;color:#475569;margin-left:14px;display:flex;justify-content:space-between;">
                      <span>• ${esc(it.product.name)} × ${it.quantity} (${esc(it.variant_value)})</span>
                      <span>${money(uPrice * it.quantity)}</span>
                    </div>`;
                  })
                  .join('')}
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px dashed #CBD5E1;font-size:12px;">
                  <span><i class="bi bi-truck"></i> Store Delivery Charge (${esc(g.shippingInfo.reason)})</span>
                  <strong style="color:${g.shippingInfo.isFree ? '#15803D' : '#1E293B'};">${g.shippingInfo.isFree ? 'FREE (৳0)' : money(g.shippingInfo.cost)}</strong>
                </div>
              </div>
            `;
              })
              .join('')}

            <!-- Simple Coupon Selector in Checkout -->
            <div style="margin:14px 0;padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-size:12.5px;font-weight:700;color:#334155;"><i class="bi bi-ticket-perforated"></i> Voucher / Promo Code</span>
                ${
                  myCollectedCoupons.length
                    ? `
                  <select onchange="if(this.value) window.applyCouponCode(this.value)" style="font-size:11.5px;padding:3px 8px;border:1px solid #CBD5E1;border-radius:4px;background:#fff;color:#475569;max-width:160px;cursor:pointer;">
                    <option value="">Apply collected...</option>
                    ${myCollectedCoupons.map((c) => `<option value="${esc(c.code)}" ${state.appliedCoupon?.code === c.code ? 'selected' : ''}>${esc(c.code)} (${c.discount_type === 'percent' ? c.discount_value + '%' : money(c.discount_value)})</option>`).join('')}
                  </select>
                `
                    : ''
                }
              </div>
              <div style="display:flex;gap:6px;">
                <input type="text" id="checkoutCouponInput" value="${esc(state.appliedCoupon?.code || '')}" placeholder="Enter voucher code" style="flex:1;padding:8px 10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:12px;text-transform:uppercase;">
                <button type="button" class="btn-village-primary" style="padding:8px 14px;font-size:12px;font-weight:700;" onclick="window.applyCouponCode($('#checkoutCouponInput').value)">Apply</button>
              </div>
              ${
                state.appliedCoupon
                  ? `
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding:6px 10px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;font-size:12px;color:#15803D;">
                  <span><i class="bi bi-check-circle-fill"></i> <strong>${esc(state.appliedCoupon.code)}</strong> applied (-${money(discount)})</span>
                  <button type="button" onclick="window.removeCoupon()" style="background:none;border:none;color:#DC2626;font-size:11.5px;font-weight:700;cursor:pointer;">Remove</button>
                </div>
              `
                  : ''
              }
            </div>

            <!-- Final Totals Table -->
            <div style="margin-top:14px;padding-top:12px;border-top:1px solid #E2E8F0;font-size:13.5px;">
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;color:#475569;">
                <span>Order Items Subtotal:</span>
                <strong>${money(rawSubtotal)}</strong>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;color:#475569;">
                <span>Total Delivery Charge:</span>
                <strong style="color:${shippingTotal === 0 ? '#16A34A' : '#1E293B'};">${shippingTotal === 0 ? 'FREE (৳0)' : money(shippingTotal)}</strong>
              </div>
              ${
                discount > 0
                  ? `
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;color:#16A34A;font-weight:700;">
                  <span>Coupon Discount (${esc(state.appliedCoupon?.code || '')}):</span>
                  <strong>-${money(discount)}</strong>
                </div>
              `
                  : ''
              }
              <div style="display:flex;justify-content:space-between;padding-top:10px;border-top:2px solid #E2E8F0;font-size:18px;font-weight:800;color:var(--haat-orange);">
                <span>Total Order Amount (with Delivery Charge):</span>
                <span id="checkoutFinalTotalText">${money(grandTotal)}</span>
              </div>
            </div>

            <button type="submit" id="confirmOrderSubmitBtn" class="btn-checkout-primary" style="margin-top:20px;padding:14px;font-size:16px;">
              <i class="bi bi-bag-check-fill"></i> Confirm Order (${esc(methodLabels[selectedMethod])} — ${money(grandTotal)})
            </button>
          </div>
        </form>
      </div>
    `;
  }

  window.selectPaymentMethod = function (method, tileEl, subtotal, shipping, discount, grandTotal) {
    state.selectedPaymentMethod = method;
    $$('.payment-method-tile').forEach((t) => t.classList.remove('selected'));
    if (tileEl) {
      tileEl.classList.add('selected');
      const radio = tileEl.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
    }
    const trxBox = $('#trxIdContainer');
    if (trxBox) {
      trxBox.style.display = method === 'cash_on_delivery' ? 'none' : 'block';
    }
    const methodLabels = {
      cash_on_delivery: 'Cash on Delivery (COD)',
      bkash: 'bKash Digital Payment',
      nagad: 'Nagad Digital Payment'
    };
    const titleEl = $('#selectedPayMethodTitle');
    if (titleEl) titleEl.textContent = methodLabels[method] || method;
    const btnEl = $('#confirmOrderSubmitBtn');
    if (btnEl && grandTotal !== undefined) {
      btnEl.innerHTML = `<i class="bi bi-bag-check-fill"></i> Confirm Order (${esc(methodLabels[method] || method)} — ${money(grandTotal)})`;
    }
    const payTotalEl = $('#selectedPayGrandTotal');
    if (payTotalEl && grandTotal !== undefined) {
      payTotalEl.textContent = money(grandTotal);
    }
  };

  window.handlePlaceOrder = function (form) {
    if (!state.activeRole) {
      showToast('Please log in to place an order.', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'seller') {
      showToast('Sellers are not permitted to place orders.', 'error');
      return;
    }
    const fd = new FormData(form);
    const shippingName = fd.get('shipping_name');
    const shippingPhone = fd.get('shipping_phone');
    const shippingAddress = fd.get('shipping_address');
    const district = fd.get('district');
    const division = fd.get('division');
    const postalCode = fd.get('postal_code');
    const paymentMethod = fd.get('payment_method') || state.selectedPaymentMethod || 'cash_on_delivery';
    const trxRef = paymentMethod === 'cash_on_delivery' ? 'COD-PAY-ON-DELIVERY' : (fd.get('transaction_reference') || 'TRX-MANUAL');

    // Group items by seller
    const grouped = {};
    let subtotal = 0;
    state.cart.forEach((item) => {
      const p = getProduct(item.product_id);
      if (!p) return;
      const uPrice = getEffectiveProductPrice(p).finalPrice;
      subtotal += uPrice * item.quantity;
      if (!grouped[p.store_id]) grouped[p.store_id] = { store: getStore(p.store_id) || state.stores[0], items: [] };
      grouped[p.store_id].items.push({ ...item, product: p, unitPrice: uPrice });
    });

    const storeIds = Object.keys(grouped);
    let shippingCost = 0;
    storeIds.forEach((sId) => {
      const shipInfo = getStoreGroupShippingCost(grouped[sId].store || Number(sId), grouped[sId].items);
      const sc = Number(shipInfo?.cost || 0);
      grouped[sId].shippingCost = sc;
      shippingCost += sc;
    });
    if (isNaN(shippingCost)) shippingCost = 0;

    let discount = 0;
    if (state.appliedCoupon) {
      const evalRes = evaluateCartCoupon(state.appliedCoupon);
      if (evalRes && evalRes.valid) discount = Number(evalRes.discount || 0);
    }
    const safeSub = Number(subtotal) || 0;
    const safeShip = Number(shippingCost) || 0;
    const safeDisc = Math.min(Number(discount) || 0, safeSub);
    let grandTotal = Math.max(0, safeSub + safeShip - safeDisc);
    if (grandTotal === 0 && safeSub > 0 && safeDisc < safeSub) {
      grandTotal = safeSub + safeShip;
    }

    const customerLat = 23.806000;
    const customerLon = 90.368000;

    const orderId = state.orders.reduce((max, o) => Math.max(max, o.id), 0) + 1;
    const orderNumber = 'HAAT' + (10234 + orderId);

    // Build seller orders
    const sellerOrders = storeIds.map((sId, idx) => {
      const g = grouped[sId];
      const sSub = g.items.reduce((s, it) => s + it.unitPrice * it.quantity, 0);
      const sShip = g.shippingCost || 0;
      const sDisc = Math.round(discount / storeIds.length);
      const sOrderNum = `SO-${orderId}0${idx + 1}-${g.store.store_slug.toUpperCase().slice(0, 3)}`;

      // Deduct inventory
      g.items.forEach((it) => {
        const inv = state.inventory.find((x) => x.product_id === it.product.id);
        if (inv) {
          inv.reserved_quantity = Math.min(inv.quantity, inv.reserved_quantity + it.quantity);
        }
      });

      return {
        id: orderId * 100 + idx + 1,
        order_id: orderId,
        store_id: Number(sId),
        seller_order_number: sOrderNum,
        subtotal: sSub,
        shipping_cost: sShip,
        discount_amount: sDisc,
        seller_total: Math.max(0, sSub + sShip - sDisc),
        status: 'pending',
        assigned_rider_id: null,
        items: g.items.map((it, iIdx) => ({
          id: orderId * 1000 + iIdx + 1,
          seller_order_id: orderId * 100 + idx + 1,
          product_id: it.product.id,
          product_name: it.product.name,
          variant_name: it.variant_name,
          variant_value: it.variant_value,
          unit_price: it.unitPrice,
          quantity: it.quantity,
          subtotal: it.unitPrice * it.quantity
        })),
        tracking: [
          {
            id: Date.now() + idx,
            status: 'order_placed',
            location: 'HAAT Central Marketplace',
            latitude: customerLat,
            longitude: customerLon,
            note: `Order placed via ${paymentMethod === 'cash_on_delivery' ? 'Cash on Delivery (COD)' : paymentMethod.toUpperCase()}. Total with delivery: ${money(grandTotal)}`,
            updated_by: 'Customer',
            created_at: 'Just now'
          },
          {
            id: Date.now() + idx + 1,
            status: 'pending',
            location: `${g.store.store_name}, ${g.store.district}`,
            latitude: g.store.latitude,
            longitude: g.store.longitude,
            note: 'Order received by seller store (Pending Seller Acceptance).',
            updated_by: 'System',
            created_at: 'Just now'
          }
        ]
      };
    });

    const newOrder = {
      id: orderId,
      order_number: orderNumber,
      user_id: state.currentUser?.id || state.currentUserId || 1,
      coupon_id: state.appliedCoupon?.id || null,
      total_amount: subtotal,
      shipping_cost: shippingCost,
      discount_amount: discount,
      grand_total: grandTotal,
      order_status: 'order_placed',
      shipping_name: shippingName,
      shipping_phone: shippingPhone,
      shipping_address: shippingAddress,
      district: district,
      division: division,
      postal_code: postalCode,
      customer_lat: customerLat,
      customer_lon: customerLon,
      notes: '',
      created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
      seller_orders: sellerOrders,
      payment: {
        method: paymentMethod,
        transaction_reference: trxRef,
        amount: grandTotal,
        status: paymentMethod === 'cash_on_delivery' ? 'cod_confirmed' : 'submitted',
        paid_at: new Date().toISOString()
      }
    };

    state.orders.unshift(newOrder);
    state.cart = [];
    state.appliedCoupon = null;

    // Dispatch dynamic multi-role notifications
    window.addNotification({
      title: `Order Placed (#${newOrder.order_number})`,
      message: `Your order for ${money(grandTotal)} was placed successfully! You can track live delivery anytime.`,
      type: 'order',
      target_role: 'customer',
      order_id: newOrder.id,
      link: `#/order/${newOrder.order_number}`
    });

    sellerOrders.forEach((so) => {
      window.addNotification({
        title: `New Order Received (#${so.seller_order_number})`,
        message: `Order received for ${money(so.seller_total)} (${so.items.length} item${so.items.length > 1 ? 's' : ''}). Please pack and prepare for HATEX pickup.`,
        type: 'order',
        target_role: 'seller',
        order_id: newOrder.id,
        link: '#/dash/seller/orders'
      });
    });

    window.addNotification({
      title: `Shipment Pending Pickup (#${newOrder.order_number})`,
      message: `Destination: ${district}. Registered for HATEX courier dispatch.`,
      type: 'order',
      target_role: 'hatex',
      order_id: newOrder.id,
      link: '#/dash/hatex'
    });

    persist();
    updateGlobalHeader();

    showToast(`Order #${orderNumber} confirmed! Total with delivery: ${money(grandTotal)}`, 'success');
    location.hash = `#/order/${newOrder.order_number}`;
  };

  /* =========================================================================
     13. PAGE VIEW: 7. ORDER TRACKING WITH DARAZ LIFECYCLE & RIDER CHAT
     ========================================================================= */

  function renderTrackingView(orderQuery) {
    if (!state.activeRole) {
      showToast('Please log in to track orders.', 'info');
      location.hash = '#/auth';
      return '';
    }
    let order = null;
    if (orderQuery) {
      order = state.orders.find((o) => o.order_number === orderQuery || String(o.id) === String(orderQuery));
      if (!order) {
        return `
          <div class="container" style="padding:60px 16px;text-align:center;">
            <div class="page-card" style="max-width:500px;margin:0 auto;background:#fff;padding:40px;border-radius:12px;border:1px solid #E2E8F0;">
              <i class="bi bi-search" style="font-size:44px;color:#94A3B8;margin-bottom:12px;display:block;"></i>
              <h2 style="font-size:20px;font-weight:800;color:#1E293B;margin-bottom:8px;">Order Not Found</h2>
              <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">We could not locate an order matching "${esc(orderQuery)}". Please verify your order number.</p>
              <a href="#/account/orders" class="btn-village-primary"><i class="bi bi-box-seam"></i> View My Orders</a>
            </div>
          </div>
        `;
      }
    } else {
      order = state.orders[0];
    }

    if (!order) {
      return `
        <div class="container" style="padding:60px 16px;text-align:center;">
          <h2>No Orders Found</h2>
          <a href="#/products" class="btn-village-primary" style="margin-top:14px;display:inline-block;">Shop Now</a>
        </div>
      `;
    }

    // Unified 6-stage lifecycle supporting Daraz seller statuses
    const statusHierarchy = {
      'pending': 0,
      'order_placed': 0,
      'order_accepted': 0,
      'processing': 0,
      'packaged': 1,
      'ready_to_ship': 1,
      'reached_hub': 2,
      'at_hub': 2,
      'assigned_to_rider': 3,
      'assigned': 3,
      'out_for_delivery': 4,
      'in_transit': 4,
      'delivered': 5
    };

    let currentStepIdx = statusHierarchy[order.order_status] ?? 0;

    const totalSellers = (order.seller_orders || []).length;
    const allPackaged = totalSellers > 0 && order.seller_orders.every((so) =>
      ['packaged', 'ready_to_ship', 'reached_hub', 'at_hub', 'assigned_to_rider', 'in_transit', 'out_for_delivery', 'delivered'].includes(so.status)
    );
    const anyOutForDelivery = (order.seller_orders || []).some((so) =>
      ['out_for_delivery', 'in_transit'].includes(so.status)
    );
    const anyAssignedRider = (order.seller_orders || []).some((so) =>
      so.assigned_rider_id != null || ['assigned_to_rider', 'out_for_delivery', 'in_transit', 'delivered'].includes(so.status)
    );

    if (allPackaged && currentStepIdx < 1) currentStepIdx = 1;
    if (anyAssignedRider && currentStepIdx < 3) currentStepIdx = 3;
    if (anyOutForDelivery && currentStepIdx < 4) currentStepIdx = 4;

    // Determine assigned rider if any
    const assignedRiderId = order.seller_orders?.find((so) => so.assigned_rider_id)?.assigned_rider_id || (currentStepIdx >= 3 ? 1 : null);
    const assignedRider = assignedRiderId ? (state.riders.find((r) => r.id === Number(assignedRiderId)) || state.riders[0]) : null;

    const progressWidth = currentStepIdx === 5 ? 100
      : currentStepIdx === 4 ? 82
      : currentStepIdx === 3 ? 62
      : currentStepIdx === 2 ? 42
      : currentStepIdx === 1 ? 22
      : 5;

    let minRemainingDist = 999;
    order.seller_orders.forEach((so) => {
      const store = getStore(so.store_id) || state.stores[0];
      const latestTrack = so.tracking[so.tracking.length - 1];
      const curLat = latestTrack?.latitude || store.latitude;
      const curLon = latestTrack?.longitude || store.longitude;
      const rem = haversineDistance(curLat, curLon, order.customer_lat, order.customer_lon);
      if (rem < minRemainingDist) minRemainingDist = rem;
    });
    if (minRemainingDist === 999) minRemainingDist = 4.4;
    const estMinutes = Math.max(12, Math.round((minRemainingDist / 25) * 60));

    const stepConfig = [
      { key: 'order_placed', label: 'Order Placed / Accepted', badgeText: order.order_status === 'order_accepted' ? 'ORDER ACCEPTED' : order.order_status === 'processing' ? 'PROCESSING' : 'ORDER PLACED', badgeClass: order.order_status || 'order_placed', icon: 'bi-check-lg' },
      { key: 'packaged', label: 'Packaged Done', badgeText: 'PACKAGED DONE', badgeClass: 'packaged', icon: 'bi-box-seam-fill' },
      { key: 'reached_hub', label: 'Reached to Hub', badgeText: 'REACHED TO HUB', badgeClass: 'reached_hub', icon: 'bi-building-check' },
      { key: 'assigned_to_rider', label: 'Assigned to Rider', badgeText: 'ASSIGNED TO RIDER', badgeClass: 'assigned_to_rider', icon: 'bi-person-badge-fill' },
      { key: 'out_for_delivery', label: 'Out for Delivery', badgeText: 'OUT FOR DELIVERY', badgeClass: 'in_transit', icon: 'bi-bicycle' },
      { key: 'delivered', label: 'Delivered', badgeText: 'DELIVERED', badgeClass: 'delivered', icon: 'bi-check-circle-fill' }
    ];
    const currentStepInfo = stepConfig[currentStepIdx] || stepConfig[0];

    const darazSteps = [
      {
        num: 1,
        label: 'Order Placed',
        icon: 'bi-check-lg',
        contentHtml: `
          <span class="daraz-step-label">Order Placed</span>
          <span class="daraz-step-time">${order.created_at || '10:15 AM'}</span>
          <span class="daraz-node-badge verified"><i class="bi bi-shield-check"></i> Confirmed</span>
        `
      },
      {
        num: 2,
        label: 'Packaged Done',
        icon: 'bi-box-seam',
        contentHtml: `
          <span class="daraz-step-label">Packaged Done</span>
          <span class="daraz-step-time">${currentStepIdx >= 1 ? 'All sellers packaged' : 'Seller Processing'}</span>
          <span class="daraz-node-badge ${currentStepIdx >= 1 ? 'verified' : ''}">
            ${currentStepIdx >= 1 ? '<i class="bi bi-check2"></i> Packaged Done' : 'Merchant Packing'}
          </span>
          <span class="daraz-node-subinfo">Controlled by Sellers</span>
        `
      },
      {
        num: 3,
        label: 'Reached to Hub',
        icon: 'bi-building-check',
        contentHtml: `
          <span class="daraz-step-label">Reached to Hub</span>
          <span class="daraz-step-time">${currentStepIdx >= 2 ? 'HATEX Sorting Hub' : 'Pending collection'}</span>
          <span class="daraz-node-badge ${currentStepIdx >= 2 ? 'hub' : ''}">
            ${currentStepIdx >= 2 ? '<i class="bi bi-boxes"></i> Consolidated' : 'Hub Transfer'}
          </span>
          <span class="daraz-node-subinfo">HATEX Central Hub</span>
        `
      },
      {
        num: 4,
        label: 'Assigned to Rider',
        icon: 'bi-person-badge',
        contentHtml: (currentStepIdx >= 3 && assignedRider) ? `
          <span class="daraz-step-label" style="color:#7C3AED;">Assigned to Rider</span>
          <div class="daraz-node-rider-box">
            <span class="daraz-node-rider-name">${esc(assignedRider.name)}</span>
            <a href="tel:${esc(assignedRider.phone)}" class="daraz-node-rider-phone" title="Call Rider"><i class="bi bi-telephone-fill"></i> ${esc(assignedRider.phone)}</a>
            <button type="button" class="daraz-node-chat-btn" onclick="window.contactRiderFromOrder(${order.id}, ${assignedRider.id})">
              <i class="bi bi-chat-dots-fill"></i> Chat with Rider
            </button>
          </div>
          <span class="daraz-node-subinfo">Assigned Rider</span>
        ` : `
          <span class="daraz-step-label">Assigned to Rider</span>
          <span class="daraz-step-time">Awaiting assignment</span>
          <span class="daraz-node-badge">Dispatch Queue</span>
          <span class="daraz-node-subinfo">HATEX / Rider Pool</span>
        `
      },
      {
        num: 5,
        label: 'Out for Delivery',
        icon: 'bi-bicycle',
        contentHtml: `
          <span class="daraz-step-label">Out for Delivery</span>
          <span class="daraz-step-time">${currentStepIdx >= 4 ? `~${minRemainingDist} km away` : 'Next stop'}</span>
          <span class="daraz-node-badge ${currentStepIdx >= 4 ? 'transit' : ''}">
            ${currentStepIdx >= 4 ? '<i class="bi bi-bicycle"></i> En Route (~' + estMinutes + 'm)' : 'Transit Route'}
          </span>
          <span class="daraz-node-subinfo">Last-Mile Delivery</span>
        `
      },
      {
        num: 6,
        label: 'Delivered',
        icon: 'bi-house-door',
        contentHtml: `
          <span class="daraz-step-label">Delivered</span>
          <span class="daraz-step-time">${currentStepIdx >= 5 ? 'Received' : esc(order.district || 'Dhaka')}</span>
          <span class="daraz-node-badge ${currentStepIdx >= 5 ? 'verified' : ''}">
            ${currentStepIdx >= 5 ? '<i class="bi bi-check2-all"></i> Delivered' : 'Destination'}
          </span>
          <span class="daraz-node-subinfo">Customer Doorstep</span>
        `
      }
    ];

    const firstStoreId = order.seller_orders[0]?.store_id || 1;

    return `
      <div class="container" style="margin-top:24px;margin-bottom:60px;">
        <!-- Clean Daraz-Style Stepper Card -->
        <div class="daraz-tracker-card">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding-bottom:16px;border-bottom:1px solid #F1F5F9;">
            <div>
              <h1 style="font-size:20px;font-weight:800;color:#1E293B;margin:0 0 4px 0;">Order #${esc(order.order_number)}</h1>
              <div style="font-size:12.5px;color:#64748B;">
                Placed on ${order.created_at} • Payment: <strong>${esc((order.payment?.method || 'COD').toUpperCase())}</strong> • Total with Delivery: <strong style="color:var(--haat-orange);">${money(order.grand_total)}</strong>
              </div>
            </div>
            <div>
              <span class="status-badge ${currentStepInfo.badgeClass}"><i class="bi ${currentStepInfo.icon}"></i> ${currentStepInfo.badgeText}</span>
            </div>
          </div>

          <!-- Daraz Visual Stepper -->
          <div class="daraz-stepper-wrap">
            <div class="daraz-progress-track">
              <div class="daraz-progress-fill" style="width: ${progressWidth}%;"></div>
            </div>
            <div class="daraz-steps-row">
              ${darazSteps
                .map((st, i) => {
                  let cls = '';
                  if (i < currentStepIdx) cls = 'completed';
                  else if (i === currentStepIdx) cls = 'active';
                  return `
                  <div class="daraz-step-node ${cls}">
                    <div class="daraz-step-circle">
                      ${i < currentStepIdx ? '<i class="bi bi-check-lg"></i>' : `<i class="bi ${st.icon}"></i>`}
                    </div>
                    ${st.contentHtml}
                  </div>
                `;
                })
                .join('')}
            </div>
          </div>

          <!-- Daraz Tracking Actions & Rider / Seller Communication Bar -->
          <div class="daraz-tracker-actions-bar">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              ${
                state.activeRole === 'customer'
                  ? `
                <button type="button" class="btn-village-primary" style="padding:9px 18px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:8px;" onclick="window.contactSellerFromOrder(${order.id}, ${firstStoreId})">
                  <i class="bi bi-chat-left-text-fill"></i> Message Product Seller
                </button>
              `
                  : ''
              }
              ${
                currentStepIdx >= 3 && assignedRider
                  ? `
                <button type="button" class="btn-village-outline" style="padding:9px 18px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:8px;background:#EFF6FF;border-color:#3B82F6;color:#1D4ED8;" onclick="window.contactRiderFromOrder(${order.id}, ${assignedRider.id})">
                  <i class="bi bi-chat-dots-fill"></i> Chat with Rider (${esc(assignedRider.name)})
                </button>
                <a href="tel:${esc(assignedRider.phone)}" class="btn-secondary" style="font-size:12.5px;padding:9px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                  <i class="bi bi-telephone-outbound"></i> Call Rider (${esc(assignedRider.phone)})
                </a>
              `
                  : `<span style="font-size:12px;color:#64748B;background:#F8FAFC;padding:8px 12px;border-radius:6px;border:1px dashed #CBD5E1;"><i class="bi bi-bicycle"></i> Rider chat unlocks automatically once a delivery rider is assigned</span>`
              }
              ${
                currentStepIdx === 5 && state.activeRole === 'customer'
                  ? `
                <button type="button" class="btn-village-primary" style="padding:9px 20px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;" onclick="window.openReviewModal ? window.openReviewModal() : showToast('Thank you for rating!', 'success')">
                  <i class="bi bi-star-fill"></i> Rate & Review Products
                </button>
              `
                  : ''
              }
              <button type="button" class="btn-secondary" style="font-size:12.5px;padding:9px 14px;display:inline-flex;align-items:center;gap:6px;" onclick="window.openAdminReportModal(${order.id})">
                <i class="bi bi-flag-fill" style="color:#DC2626;"></i> Report Issue on Order
              </button>
            </div>
          </div>
        </div>

        <!-- Per-Seller Package & Status Breakdown + Ordered Products Summary -->
        <div class="daraz-order-summary-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #F1F5F9;">
            <h2 style="font-size:15px;font-weight:800;color:#1E293B;margin:0;">Seller Packages & Delivery Breakdown (${(order.seller_orders || []).length} Store Package${(order.seller_orders || []).length > 1 ? 's' : ''})</h2>
            <span style="font-size:12px;color:var(--text-muted);">Destination: ${esc(order.shipping_address)}, ${esc(order.district)}</span>
          </div>

          <div style="display:flex;flex-direction:column;gap:14px;">
            ${(order.seller_orders || [])
              .map((so) => {
                const store = getStore(so.store_id) || state.stores[0];
                const rider = so.assigned_rider_id ? state.riders.find((r) => r.id === Number(so.assigned_rider_id)) : null;
                return `
                  <div style="border:1px solid #E2E8F0;border-radius:8px;padding:14px;background:#F8FAFC;">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;padding-bottom:8px;border-bottom:1px solid #E2E8F0;margin-bottom:10px;">
                      <div>
                        <strong style="font-size:14px;color:#1E293B;"><i class="bi bi-shop" style="color:var(--haat-primary);"></i> ${esc(store.store_name)}</strong>
                        <span style="font-size:11.5px;color:var(--text-muted);margin-left:8px;">Sub-Order: <code>${esc(so.seller_order_number)}</code></span>
                      </div>
                      <div style="display:flex;align-items:center;gap:8px;">
                        ${rider ? `<span class="haversine-pill"><i class="bi bi-bicycle"></i> Rider: ${esc(rider.name)}</span>` : ''}
                        <span class="status-badge ${esc(so.status)}">${esc(so.status.replace(/_/g, ' ').toUpperCase())}</span>
                      </div>
                    </div>
                    ${(so.items || [])
                      .map(
                        (it) => `
                      <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:13px;">
                        <span>• <strong>${esc(it.product_name)}</strong> (${esc(it.variant_name)}: ${esc(it.variant_value)}) × ${it.quantity}</span>
                        <strong>${money(it.subtotal)}</strong>
                      </div>
                    `
                      )
                      .join('')}
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px dashed #CBD5E1;font-size:12px;color:#475569;">
                      <span>Delivery Charge: <strong>${so.shipping_cost === 0 ? 'FREE (৳0)' : money(so.shipping_cost)}</strong></span>
                      <span>Package Total: <strong style="color:var(--haat-primary);">${money(so.seller_total)}</strong></span>
                    </div>
                  </div>
                `;
              })
              .join('')}
          </div>

          <div style="margin-top:16px;padding-top:12px;border-top:2px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;font-size:14px;">
            <span style="color:#475569;">Items: <strong>${money(order.total_amount)}</strong> + Delivery Charge: <strong>${order.shipping_cost === 0 ? 'FREE (৳0)' : money(order.shipping_cost)}</strong> ${order.discount_amount > 0 ? `- Discount: <strong style="color:#16A34A;">${money(order.discount_amount)}</strong>` : ''}</span>
            <strong style="font-size:18px;color:var(--haat-orange);">Grand Total: ${money(order.grand_total)}</strong>
          </div>
        </div>
      </div>
    `;
  }

  /* =========================================================================
     13B. WISHLIST TABLE VIEW (INSIDE DASHBOARD OR STANDALONE)
     ========================================================================= */

  function renderWishlistTableView(isInsideDashboard = false) {
    if (!state.activeRole) {
      location.hash = '#/auth';
      return '';
    }
    const wishProds = state.products.filter((p) => state.wishlist.includes(p.id));

    let contentHtml = '';

    if (!wishProds.length) {
      contentHtml = `
        <div style="padding:48px 24px;text-align:center;background:#fff;border-radius:10px;border:1px solid #E2E8F0;">
          <div style="width:60px;height:60px;border-radius:50%;background:#FFF7ED;color:var(--haat-orange);display:grid;place-items:center;font-size:28px;margin:0 auto 14px;">
            <i class="bi bi-heart"></i>
          </div>
          <h3 style="font-size:18px;font-weight:800;color:#1E293B;margin-bottom:6px;">Your Wishlist is Empty</h3>
          <p style="font-size:13px;color:var(--text-muted);max-width:380px;margin:0 auto 18px;">
            Browse our village marketplace, discover artisanal products and save items you want to buy later.
          </p>
          <a href="#/products" class="btn-village-primary" style="display:inline-block;padding:9px 20px;"><i class="bi bi-shop"></i> Explore Products</a>
        </div>
      `;
    } else {
      contentHtml = `
        <div style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #F1F5F9;">
            <div>
              <h2 style="font-size:18px;font-weight:800;color:#1E293B;margin:0;">My Wishlist (${wishProds.length} Items)</h2>
              <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Manage your saved products or move them to your cart.</p>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
              <button type="button" class="btn-secondary" style="font-size:12px;padding:6px 14px;color:#DC2626;border-color:#FECACA;" onclick="window.clearWishlist()">
                <i class="bi bi-trash3"></i> Clear Wishlist
              </button>
              <a href="#/products" class="btn-village-outline" style="font-size:12px;padding:6px 14px;">
                <i class="bi bi-plus-circle"></i> Add More Items
              </a>
            </div>
          </div>

          <div class="wishlist-table-container">
            <table class="wishlist-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Unit Price</th>
                  <th>Delivery</th>
                  <th>Stock Status</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                ${wishProds
                  .map((p) => {
                    const store = getStore(p.store_id);
                    const thumb = p.image_1 || 'panjabi.jpg';
                    const inv = getInventory(p.id);
                    const inStock = inv.available > 0;
                    const priceInfo = getEffectiveProductPrice(p);
                    const delivInfo = getProductDeliveryInfo(p);
                    return `
                    <tr>
                      <td>
                        <div class="wishlist-item-flex">
                          <img src="${esc(thumb)}" alt="${esc(p.name)}" class="wishlist-thumb" onerror="this.src='panjabi.jpg'">
                          <div>
                            <a href="#/product/${p.id}" style="font-weight:700;color:#1E293B;font-size:13.5px;display:block;text-decoration:none;" class="hover-underline">
                              ${esc(p.name)}
                            </a>
                            <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">
                              Store: <strong style="color:#475569;">${esc(store?.store_name || 'Merchant')}</strong>
                            </div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <strong style="color:var(--haat-primary);font-size:14.5px;">${money(priceInfo.finalPrice)}</strong>
                      </td>
                      <td>
                        <span class="${delivInfo.isFree ? 'delivery-badge-free' : 'delivery-badge-fee'}">${delivInfo.isFree ? 'Free Delivery' : money(delivInfo.fee)}</span>
                      </td>
                      <td>
                        <span class="status-badge ${inStock ? 'verified' : 'pending'}" style="font-size:11px;">
                          ${inStock ? `<i class="bi bi-check-circle"></i> In Stock (${inv.available})` : 'Out of Stock'}
                        </span>
                      </td>
                      <td style="text-align:right;">
                        <div style="display:inline-flex;gap:8px;align-items:center;">
                          <button type="button" class="btn-village-primary" style="padding:6px 12px;font-size:12px;" onclick="window.moveWishlistToCart(${p.id})">
                            <i class="bi bi-cart-plus"></i> Move to Cart
                          </button>
                          <button type="button" class="btn-secondary" style="padding:6px 10px;font-size:12px;color:#DC2626;border-color:#FCA5A5;" onclick="window.removeFromWishlist(${p.id})" title="Remove from wishlist">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  `;
                  })
                  .join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    }

    if (isInsideDashboard) return contentHtml;
    return `<div class="container" style="margin-top:24px;margin-bottom:60px;">${contentHtml}</div>`;
  }

  /* =========================================================================
     13C. CUSTOMER MESSAGES VIEW (NO ADMIN CHAT — ONLY SELLERS & ASSIGNED RIDERS + QUICK REPLIES)
     ========================================================================= */

  function renderDashboardMessagesView() {
    const customerOrders = (state.orders || []).filter(o => String(o.user_id) === String(state.currentUser?.id));
    const channels = [];
    const seenIds = new Set();

    customerOrders.forEach((order) => {
      (order.seller_orders || []).forEach((so) => {
        const store = getStore(so.store_id) || state.stores[0];
        const sellerChanId = `seller_${so.store_id}_order_${order.id}`;
        if (!seenIds.has(sellerChanId)) {
          seenIds.add(sellerChanId);
          channels.push({
            id: sellerChanId,
            type: 'seller',
            storeId: so.store_id,
            orderId: order.id,
            orderNumber: order.order_number,
            title: store.store_name,
            roleLabel: 'Seller Store',
            badgeClass: 'role-badge-seller',
            status: so.status,
            avatar: store.logo_text?.[0] || 'S',
            subtext: `Order #${order.order_number} (${so.seller_order_number})`,
            isRider: false
          });
        }

        // Channel for Delivery Rider: available as soon as rider is assigned or out for delivery!
        const isRiderAssigned =
          so.assigned_rider_id != null ||
          ['assigned_to_rider', 'out_for_delivery', 'in_transit', 'delivered'].includes(so.status) ||
          ['assigned_to_rider', 'out_for_delivery', 'in_transit', 'delivered'].includes(order.order_status);

        if (isRiderAssigned) {
          const rider = state.riders.find((r) => r.id === Number(so.assigned_rider_id || 1)) || state.riders[0];
          const riderChanId = `rider_${rider.id}_order_${order.id}`;
          if (!seenIds.has(riderChanId)) {
            seenIds.add(riderChanId);
            channels.push({
              id: riderChanId,
              type: 'rider',
              riderId: rider.id,
              orderId: order.id,
              orderNumber: order.order_number,
              title: rider.name,
              roleLabel: 'Delivery Rider',
              badgeClass: 'role-badge-rider',
              status: so.status === 'out_for_delivery' ? 'Out for Delivery' : 'Assigned to Order',
              avatar: 'R',
              subtext: `Order #${order.order_number} • ${rider.phone}`,
              isRider: true
            });
          }
        }
      });
    });

    // Also include any direct seller inquiries started from a store page or active selection
    state.stores.forEach((store) => {
      const directId = `seller_${store.id}_order_1`;
      const hasDirectMsg = state.messages.some((m) => m.channel_id === directId);
      if ((hasDirectMsg || state.activeChatChannel === directId) && !seenIds.has(directId)) {
        seenIds.add(directId);
        channels.push({
          id: directId,
          type: 'seller',
          storeId: store.id,
          orderId: 1,
          orderNumber: 'STORE-INQUIRY',
          title: store.store_name,
          roleLabel: 'Seller Store',
          badgeClass: 'role-badge-seller',
          status: 'Active',
          avatar: store.logo_text?.[0] || 'S',
          subtext: `Direct Store Chat • ${store.district || 'Verified Merchant'}`,
          isRider: false
        });
      }
    });

    // If state.activeChatChannel is set (e.g. from Product page or Order tracking) and not in channels, construct it
    if (state.activeChatChannel && !seenIds.has(state.activeChatChannel)) {
      seenIds.add(state.activeChatChannel);
      if (state.activeChatChannel.startsWith('seller_')) {
        const parts = state.activeChatChannel.split('_');
        const sId = Number(parts[1]) || 1;
        const oId = Number(parts[3]) || 1;
        const store = getStore(sId) || state.stores[0];
        channels.unshift({
          id: state.activeChatChannel,
          type: 'seller',
          storeId: store.id,
          orderId: oId,
          orderNumber: oId === 1 ? 'STORE-INQUIRY' : `ORDER-${oId}`,
          title: store.store_name,
          roleLabel: 'Seller Store',
          badgeClass: 'role-badge-seller',
          status: 'Active',
          avatar: store.logo_text?.[0] || 'S',
          subtext: `Direct Store Chat • ${store.district || 'Verified Merchant'}`,
          isRider: false
        });
      } else if (state.activeChatChannel.startsWith('rider_')) {
        const parts = state.activeChatChannel.split('_');
        const rId = Number(parts[1]) || 1;
        const oId = Number(parts[3]) || 1;
        const rider = state.riders.find((r) => r.id === rId) || state.riders[0];
        channels.unshift({
          id: state.activeChatChannel,
          type: 'rider',
          riderId: rider.id,
          orderId: oId,
          orderNumber: `ORDER-${oId}`,
          title: rider.name,
          roleLabel: 'Delivery Rider',
          badgeClass: 'role-badge-rider',
          status: 'Assigned to Order',
          avatar: 'R',
          subtext: `HATEX Delivery • ${rider.phone}`,
          isRider: true
        });
      }
    }

    // NOTE: Admin chat channel is intentionally excluded from Customer Messages per requirement #2
    // Customers only submit dynamic Reports to Admin!

    if (!channels.length) {
      return `
        <div style="background:#fff;padding:40px 20px;text-align:center;border-radius:10px;border:1px solid #E2E8F0;">
          <i class="bi bi-chat-left-dots" style="font-size:36px;color:#94A3B8;display:block;margin-bottom:12px;"></i>
          <h3 style="font-size:17px;font-weight:800;color:#1E293B;">No Active Conversations Yet</h3>
          <p style="font-size:13px;color:var(--text-muted);max-width:440px;margin:6px auto 16px;">
            You can message any Seller Store regarding products or orders, and chat with your Delivery Rider once assigned. Need to contact Admin? Use the dynamic Report system below.
          </p>
          <div style="display:flex;gap:10px;justify-content:center;">
            <a href="#/products" class="btn-village-primary" style="display:inline-block;padding:8px 18px;">Browse Products</a>
            <button type="button" class="btn-village-outline" onclick="window.openStartNewChatModal()"><i class="bi bi-chat-plus"></i> Message a Store</button>
            <button type="button" class="btn-village-outline" onclick="window.openAdminReportModal()"><i class="bi bi-flag-fill"></i> Submit Report to Admin</button>
          </div>
        </div>
      `;
    }

    const activeChannelId = state.activeChatChannel && channels.some((c) => c.id === state.activeChatChannel)
      ? state.activeChatChannel
      : channels[0].id;
    state.activeChatChannel = activeChannelId;

    const activeChannel = channels.find((c) => c.id === activeChannelId) || channels[0];

    // If active channel is a seller store and has no messages yet, trigger automatic seller greeting!
    if (activeChannel.type === 'seller') {
      ensureSellerAutoGreeting(activeChannel.id, activeChannel.storeId, activeChannel.orderId);
    }

    const channelMessages = state.messages.filter((m) => {
      if (m.channel_id === activeChannel.id) return true;
      if (activeChannel.type === 'seller' && m.order_id === activeChannel.orderId && m.sender_name === activeChannel.title) return true;
      if (activeChannel.type === 'rider' && m.order_id === activeChannel.orderId && (m.receiver_role === 'rider' || m.sender_role === 'rider')) return true;
      return false;
    });

    const customerQuickList = state.quickMessages?.customer || [];

    return `
      <div style="background:#fff;border-radius:10px;border:1px solid #E2E8F0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <div>
            <h2 style="font-size:18px;font-weight:800;color:#1E293B;margin:0;">Seller & Assigned Rider Messages</h2>
            <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Chat directly with Seller Stores and assigned Delivery Riders. (To contact Admin, submit a Report)</p>
          </div>
          <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" class="btn-village-primary" style="font-size:11.5px;padding:6px 12px;" onclick="window.openStartNewChatModal()">
              <i class="bi bi-chat-plus-fill"></i> + Message Any Store
            </button>
            <button type="button" class="btn-village-outline" style="font-size:11.5px;padding:6px 12px;color:#DC2626;border-color:#FCA5A5;" onclick="window.openAdminReportModal()">
              <i class="bi bi-flag-fill"></i> Dynamic Report to Admin
            </button>
          </div>
        </div>

        <div class="dash-messages-split">
          <!-- Left: Contextual Conversations List -->
          <div class="dash-convos-list">
            <div style="padding:10px 14px;background:#FAF8F5;border-bottom:1px solid #EBE4D8;font-size:11.5px;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:flex;justify-content:space-between;align-items:center;">
              <span>Active Chats (${channels.length})</span>
              <button type="button" class="btn-village-outline" style="font-size:10px;padding:2px 7px;font-weight:700;border-radius:4px;" onclick="window.openStartNewChatModal()" title="Start conversation with any merchant">
                + New
              </button>
            </div>
            ${channels
              .map(
                (ch) => `
              <div class="convo-item-card ${ch.id === activeChannelId ? 'active' : ''}" onclick="window.setActiveChatChannel('${ch.id}')">
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="user-avatar" style="width:34px;height:34px;font-size:13px;flex-shrink:0;background:${ch.type === 'rider' ? '#2563EB' : '#F85606'};color:#fff;">
                    ${esc(ch.avatar)}
                  </div>
                  <div style="min-width:0;flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                      <strong style="font-size:13px;color:#1E293B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;">${esc(ch.title)}</strong>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                      ${esc(ch.subtext)}
                    </div>
                    <span class="role-badge-tag ${ch.badgeClass}" style="font-size:9.5px;padding:1px 5px;margin-top:4px;display:inline-block;">
                      ${esc(ch.roleLabel)}
                    </span>
                  </div>
                </div>
              </div>
            `
              )
              .join('')}
          </div>

          <!-- Right: Interactive Chat Panel -->
          <div class="dash-chat-main">
            <div class="dash-chat-head">
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="user-avatar" style="width:36px;height:36px;font-size:14px;background:${activeChannel.type === 'rider' ? '#2563EB' : '#F85606'};color:#fff;">
                  ${esc(activeChannel.avatar)}
                </div>
                <div>
                  <strong style="font-size:14px;color:#1E293B;display:block;">${esc(activeChannel.title)}</strong>
                  <span style="font-size:11.5px;color:var(--text-muted);">
                    <span class="role-badge-tag ${activeChannel.badgeClass}" style="font-size:10px;padding:1px 5px;">${esc(activeChannel.roleLabel)}</span>
                    • ${esc(activeChannel.subtext)}
                  </span>
                </div>
              </div>
              <span class="status-badge ${activeChannel.type === 'rider' ? 'in_transit' : 'verified'}" style="font-size:11px;">
                ${activeChannel.type === 'rider' ? '<i class="bi bi-bicycle"></i> Assigned Rider' : '<i class="bi bi-shop"></i> Auto-Greeting Active'}
              </span>
            </div>

            <!-- Messages Stream -->
            <div class="dash-chat-stream" id="dashChatStream">
              ${
                channelMessages.length
                  ? channelMessages
                      .map((m) => {
                        const isOut = m.sender_role === 'customer';
                        return `
                    <div class="chat-bubble-row ${isOut ? 'outgoing' : 'incoming'}">
                      <div class="chat-bubble-pill">
                        ${m.is_auto_greeting ? `<span style="font-size:9.5px;font-weight:800;text-transform:uppercase;color:#EA580C;display:block;margin-bottom:2px;"><i class="bi bi-robot"></i> Automatic Store Greeting</span>` : ''}
                        ${esc(m.message)}
                      </div>
                      <span class="chat-bubble-meta">${esc(m.sender_name)} • ${m.created_at || 'Recently'}</span>
                    </div>
                  `;
                      })
                      .join('')
                  : `
                <div style="text-align:center;padding:30px 10px;color:var(--text-muted);font-size:12.5px;">
                  <i class="bi bi-chat-quote" style="font-size:24px;display:block;margin-bottom:6px;opacity:0.6;"></i>
                  Start a conversation with ${esc(activeChannel.title)} regarding your order.
                </div>
              `
              }
            </div>

            <!-- Readymade Quick Messages Bar for Customer (Click to Send + Editable) -->
            <div class="quick-messages-strip">
              <span style="font-size:10.5px;font-weight:800;color:#64748B;text-transform:uppercase;"><i class="bi bi-lightning-fill" style="color:var(--haat-orange);"></i> Quick Send:</span>
              ${customerQuickList
                .map(
                  (qMsg, qIdx) => `
                <button type="button" class="quick-msg-chip" onclick="window.sendCustomerQuickMsg(${qIdx})">
                  ${esc(qMsg)}
                </button>
              `
                )
                .join('')}
              <button type="button" class="quick-msg-manage-btn" onclick="window.openManageQuickMessagesModal('customer')">
                <i class="bi bi-pencil-square"></i> Edit Quick Messages
              </button>
            </div>

            <!-- Chat Compose Bar -->
            <form class="dash-chat-input-bar" onsubmit="event.preventDefault(); window.handleSendDashboardChat(this);">
              <input type="text" name="chat_text" placeholder="Type your message to ${esc(activeChannel.title)}..." class="msg-input-field" required autocomplete="off">
              <button type="submit" class="msg-send-btn" title="Send Message">
                <i class="bi bi-send-fill"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    `;
  }

  window.sendCustomerQuickMsg = function (idx) {
    const msg = state.quickMessages?.customer?.[idx];
    if (!msg) return;
    const fakeForm = { chat_text: { value: msg } };
    window.handleSendDashboardChat(fakeForm);
  };

  /* =========================================================================
     14. PAGE VIEW: 8. CUSTOMER ACCOUNT HUB (`#/account`)
     ========================================================================= */

  function renderAccountView(subTab = 'profile') {
    if (!state.activeRole) {
      location.hash = '#/auth';
      return '';
    }

    const user = state.currentUser || (state.currentUserId != null ? state.users.find((u) => String(u.id) === String(state.currentUserId)) : null) || state.users[0];
    const myOrders = (state.orders || []).filter((o) => String(o.user_id) === String(user.id));
    const myAddresses = (state.addresses || []).filter((a) => String(a.user_id) === String(user.id));
    const myReports = (state.reports || []).filter((r) => r.reporter_role === 'customer' && (String(r.user_id) === String(user.id) || r.reporter_name === user.name));
    const myCollectedCoupons = (state.collectedCoupons || []).map((id) => state.coupons.find((c) => c.id === id && c.is_active)).filter(Boolean);

    return `
      <div class="container">
        <div class="dash-shell">
          <!-- Sidebar Navigation -->
          <aside class="dash-nav-card">
            <div class="dash-nav-header">
              <strong style="font-size:16px;color:#1E293B;display:block;">${esc(user.name)}</strong>
              <small style="color:#64748B;">${esc(user.email)}</small>
            </div>
            <a href="#/account/profile" class="dash-nav-item ${subTab === 'profile' ? 'active' : ''}">
              <i class="bi bi-person"></i> My Profile
            </a>
            <a href="#/account/orders" class="dash-nav-item ${subTab === 'orders' ? 'active' : ''}">
              <i class="bi bi-box-seam"></i> My Orders (${myOrders.length})
            </a>
            <a href="#/account/coupons" class="dash-nav-item ${subTab === 'coupons' ? 'active' : ''}">
              <i class="bi bi-ticket-perforated"></i> My Vouchers (${myCollectedCoupons.length})
            </a>
            <a href="#/account/addresses" class="dash-nav-item ${subTab === 'addresses' ? 'active' : ''}">
              <i class="bi bi-geo-alt"></i> Saved Addresses (${myAddresses.length})
            </a>
            <a href="#/account/wishlist" class="dash-nav-item ${subTab === 'wishlist' ? 'active' : ''}">
              <i class="bi bi-heart"></i> Wishlist (${state.wishlist.length})
            </a>
            <a href="#/account/messages" class="dash-nav-item ${subTab === 'messages' ? 'active' : ''}">
              <i class="bi bi-chat-dots"></i> Messages (Seller & Rider)
            </a>
            <a href="#/account/reports" class="dash-nav-item ${subTab === 'reports' ? 'active' : ''}">
              <i class="bi bi-flag"></i> My Reports to Admin (${myReports.length})
            </a>
          </aside>

          <!-- Main Content Area -->
          <main>
            ${
              subTab === 'orders'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:16px;">My Orders History</h2>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Payment</th>
                        <th>Delivery Charge</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${
                        myOrders.length
                          ? myOrders
                              .map((o) => {
                                const orderItemsTotal = o.total_amount || (o.seller_orders || []).reduce((s, so) => s + (so.subtotal || 0), 0) || 0;
                                const orderShipping = o.shipping_cost !== undefined ? o.shipping_cost : (o.seller_orders || []).reduce((s, so) => s + (so.shipping_cost || 0), 0) || 0;
                                const orderGrandTotal = (o.grand_total && o.grand_total > 0) ? o.grand_total : Math.max(0, orderItemsTotal + orderShipping - (o.discount_amount || 0));
                                return `
                              <tr>
                                <td><strong>${esc(o.order_number)}</strong></td>
                                <td>${o.created_at.slice(0, 10)}</td>
                                <td><span style="font-size:11.5px;font-weight:700;text-transform:uppercase;">${esc(o.payment?.method || 'COD')}</span></td>
                                <td>${orderShipping === 0 ? '<span class="delivery-badge-free">FREE</span>' : money(orderShipping)}</td>
                                <td><strong style="color:var(--haat-orange);">${money(orderGrandTotal)}</strong></td>
                                <td><span class="status-badge ${o.order_status}">${esc(o.order_status.replace(/_/g, ' '))}</span></td>
                                <td style="white-space:nowrap;display:flex;gap:6px;align-items:center;">
                                  <button type="button" class="btn-village-primary" onclick="window.viewCustomerOrderDetails('${esc(o.order_number)}')" style="padding:5px 12px;font-size:11.5px;font-weight:700;">
                                    <i class="bi bi-file-earmark-text"></i> Order Details
                                  </button>
                                  <a href="#/order/${esc(o.order_number)}" class="btn-secondary" style="padding:5px 10px;font-size:11.5px;font-weight:700;border-radius:4px;text-decoration:none;">
                                    <i class="bi bi-truck"></i> Track / Chat
                                  </a>
                                </td>
                              </tr>
                            `;
                              })
                              .join('')
                          : `<tr><td colspan="7" style="text-align:center;padding:36px;color:var(--text-muted);"><i class="bi bi-box-seam" style="font-size:28px;display:block;margin-bottom:8px;color:#94A3B8;"></i>No orders placed yet. <a href="#/products" style="color:var(--haat-orange);font-weight:700;">Explore Products &rarr;</a></td></tr>`
                      }
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'addresses'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;">My Delivery Addresses</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Select your default delivery address or add new locations.</p>
                  </div>
                  <button type="button" class="btn-village-primary" onclick="window.openAddAddressModal()">+ Add New Address</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
                  ${
                    myAddresses.length
                      ? myAddresses
                          .map(
                            (a) => `
                        <div style="border:1.5px solid ${a.is_default ? 'var(--haat-orange)' : '#E2E8F0'};border-radius:8px;padding:16px;position:relative;background:#fff;">
                          ${
                            a.is_default
                              ? '<span class="status-badge verified" style="position:absolute;top:12px;right:12px;"><i class="bi bi-check-circle-fill"></i> DEFAULT</span>'
                              : `<button type="button" class="btn-secondary" style="position:absolute;top:12px;right:12px;padding:3px 9px;font-size:11px;border-radius:4px;" onclick="window.setDefaultAddress(${a.id})"><i class="bi bi-check2"></i> Set Default</button>`
                          }
                          <span class="role-badge-tag role-badge-customer" style="margin-bottom:6px;">${esc(a.label)}</span>
                          <strong style="display:block;font-size:14px;color:#1E293B;">${esc(a.name)}</strong>
                          <div style="font-size:12.5px;color:#475569;margin:4px 0;">${esc(a.address)}</div>
                          <div style="font-size:12px;color:var(--text-muted);">${esc(a.district)}, ${esc(a.division)} - ${esc(a.postal_code)}</div>
                          <div style="font-size:12px;color:#1E293B;margin-top:4px;">Phone: ${esc(a.phone)}</div>
                          ${
                            !a.is_default
                              ? `<div style="margin-top:10px;padding-top:8px;border-top:1px dashed #E2E8F0;text-align:right;">
                                  <button type="button" style="background:none;border:none;color:#EF4444;font-size:11.5px;cursor:pointer;" onclick="window.deleteAddress(${a.id})"><i class="bi bi-trash"></i> Remove</button>
                                </div>`
                              : ''
                          }
                        </div>
                      `
                          )
                          .join('')
                      : `<div style="grid-column: 1 / -1;text-align:center;padding:36px;color:var(--text-muted);border:1.5px dashed #CBD5E1;border-radius:8px;"><i class="bi bi-geo-alt" style="font-size:28px;display:block;margin-bottom:8px;color:#94A3B8;"></i>No delivery addresses saved yet. Click "+ Add New Address" above to save a delivery address.</div>`
                  }
                </div>
              </div>
            `
                : subTab === 'wishlist'
                ? renderWishlistTableView(true)
                : subTab === 'messages'
                ? `
              <div class="page-card" style="background:#fff;padding:20px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="margin-bottom:14px;">
                  <h2 style="font-size:18px;font-weight:800;margin:0;"><i class="bi bi-chat-dots-fill" style="color:var(--haat-primary);"></i> Messages & Communications</h2>
                  <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Chat directly with store sellers and HATEX delivery riders for your orders.</p>
                </div>
                ${renderDashboardMessagesView()}
              </div>
            `
                : subTab === 'reports'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0;">My Dynamic Reports to Admin (${myReports.length})</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Submit dynamic reports regarding orders, sellers, delivery riders, or payments directly to Admin.</p>
                  </div>
                  <button type="button" class="btn-village-primary" onclick="window.openAdminReportModal()">
                    <i class="bi bi-flag-fill"></i> + Submit New Report
                  </button>
                </div>
                ${
                  myReports.length
                    ? `
                  <div class="table-wrap">
                    <table class="data-table">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Category & Target</th>
                          <th>Order</th>
                          <th>Priority</th>
                          <th>Details & Admin Resolution</th>
                          <th>Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${myReports
                          .map(
                            (r) => `
                          <tr>
                            <td><code>#${r.id}</code></td>
                            <td>
                              <strong>${esc(r.category)}</strong>
                              <div style="font-size:11px;color:var(--text-muted);">Target: ${esc(r.target_entity)}</div>
                            </td>
                            <td><code>${esc(r.order_number || 'N/A')}</code></td>
                            <td><span class="status-badge ${r.priority === 'High' || r.priority === 'Urgent' ? 'cancelled' : 'packaged'}">${esc(r.priority)}</span></td>
                            <td>
                              <div style="font-size:12.5px;color:#1E293B;"><strong>${esc(r.subject)}</strong>: ${esc(r.details)}</div>
                              ${r.admin_reply ? `<div style="margin-top:4px;font-size:11.5px;color:#15803D;background:#F0FDF4;padding:4px 8px;border-radius:4px;"><strong>Admin Reply:</strong> ${esc(r.admin_reply)}</div>` : ''}
                            </td>
                            <td><span class="status-badge ${r.status === 'resolved' ? 'delivered' : 'pending'}">${esc(r.status.toUpperCase())}</span></td>
                          </tr>
                        `
                          )
                          .join('')}
                      </tbody>
                    </table>
                  </div>
                `
                    : `<p style="color:var(--text-muted);font-size:13px;">You haven't submitted any reports yet.</p>`
                }
              </div>
            `
                : subTab === 'coupons'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0;"><i class="bi bi-ticket-perforated-fill" style="color:var(--haat-orange)"></i> My Collected Vouchers & Wallet (${myCollectedCoupons.length})</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Vouchers collected in this individual account (${esc(user.name)}). Applied automatically at Cart & Checkout.</p>
                  </div>
                  <a href="#/deals" class="btn-village-outline" style="font-size:12px;padding:6px 14px;">
                    <i class="bi bi-gift"></i> Browse Deals & Vouchers &rarr;
                  </a>
                </div>
                ${
                  myCollectedCoupons.length
                    ? `
                  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
                    ${myCollectedCoupons
                      .map((c) => {
                        const scopeText = getCouponScopeLabel(c);
                        const discountText = c.discount_type === 'percent' ? `${c.discount_value}% OFF` : `${money(c.discount_value)} OFF`;
                        return `
                        <div style="border:1.5px solid #E2E8F0;border-radius:10px;padding:16px;background:#F8FAFC;position:relative;">
                          <span class="status-badge active" style="position:absolute;top:12px;right:12px;font-size:10.5px;"><i class="bi bi-check-circle-fill"></i> In Wallet</span>
                          <span class="role-badge-tag role-badge-customer" style="margin-bottom:6px;">${esc(scopeText)}</span>
                          <strong style="display:block;font-size:18px;color:var(--haat-orange);margin:4px 0;">${esc(discountText)}</strong>
                          <div style="font-family:var(--font-mono);font-size:13px;font-weight:800;color:var(--haat-primary);background:#fff;border:1px dashed #CBD5E1;padding:4px 8px;border-radius:4px;display:inline-block;margin:4px 0;">${esc(c.code)}</div>
                          <div style="font-size:11.5px;color:var(--text-muted);margin-top:6px;">Min Spend: ${money(c.min_order)} ${c.max_discount ? `• Max Cap: ${money(c.max_discount)}` : ''}</div>
                          <div style="margin-top:12px;display:flex;gap:8px;">
                            <a href="#/cart" class="btn-village-primary" style="font-size:11.5px;padding:5px 12px;text-decoration:none;"><i class="bi bi-bag-check"></i> Use in Cart</a>
                            <a href="#/products" class="btn-secondary" style="font-size:11.5px;padding:5px 10px;text-decoration:none;">Shop Now</a>
                          </div>
                        </div>
                      `;
                      })
                      .join('')}
                  </div>
                `
                    : `
                  <div style="text-align:center;padding:40px 20px;border:1.5px dashed #CBD5E1;border-radius:8px;">
                    <i class="bi bi-ticket-perforated" style="font-size:36px;color:#94A3B8;display:block;margin-bottom:10px;"></i>
                    <h3 style="font-size:15px;font-weight:700;color:#1E293B;margin-bottom:4px;">No Vouchers in this Account Wallet</h3>
                    <p style="font-size:12.5px;color:var(--text-muted);max-width:440px;margin:0 auto 16px;">Vouchers in HAAT are strictly personal to each account. Browse the Deals & Campaign center to collect exclusive discounts for ${esc(user.name)}.</p>
                    <a href="#/deals" class="btn-village-primary" style="font-size:12px;padding:8px 18px;text-decoration:none;"><i class="bi bi-gift"></i> Collect Available Vouchers</a>
                  </div>
                `
                }
              </div>
            `
                : `
              <!-- Default: Profile & Settings -->
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0;">Settings & Change Profile</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Update your personal details, email address and contact number.</p>
                  </div>
                  <span class="role-badge-tag role-badge-customer" style="font-size:11px;">${esc(user.name)}</span>
                </div>

                <form onsubmit="event.preventDefault(); window.handleUpdateProfile(this);" style="max-width:640px;">
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                      <label style="font-size:12px;color:var(--text-muted);font-weight:700;display:block;margin-bottom:4px;">Full Name</label>
                      <input type="text" name="name" value="${esc(user.name)}" required style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13.5px;">
                    </div>
                    <div>
                      <label style="font-size:12px;color:var(--text-muted);font-weight:700;display:block;margin-bottom:4px;">Account Email</label>
                      <input type="email" name="email" value="${esc(user.email)}" required style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13.5px;">
                    </div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                    <div>
                      <label style="font-size:12px;color:var(--text-muted);font-weight:700;display:block;margin-bottom:4px;">Phone Number</label>
                      <input type="text" name="phone" value="${esc(user.phone)}" required style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13.5px;">
                    </div>
                    <div>
                      <label style="font-size:12px;color:var(--text-muted);font-weight:700;display:block;margin-bottom:4px;">Account Role</label>
                      <input type="text" value="Customer" readonly style="width:100%;padding:9px;border:1.5px solid #E2E8F0;border-radius:6px;background:#F8FAFC;color:#64748B;font-size:13.5px;">
                    </div>
                  </div>
                  <button type="submit" class="btn-village-primary"><i class="bi bi-save"></i> Save Profile Changes</button>
                </form>
              </div>
            `
            }
          </main>
        </div>
      </div>
    `;
  }

  window.viewCustomerOrderDetails = function (orderNumber) {
    const o = state.orders.find((ord) => ord.order_number === orderNumber);
    if (!o) {
      showToast('Order not found.', 'error');
      return;
    }
    const allItems = [];
    (o.seller_orders || []).forEach((so) => {
      const store = getStore(so.store_id) || { store_name: 'HAAT Seller Store' };
      (so.items || []).forEach((it) => {
        allItems.push({ ...it, store_name: store.store_name, store_id: so.store_id, so_status: so.status });
      });
    });
    if (!allItems.length && o.items) {
      allItems.push(...o.items);
    }

    openModal(`
      <div style="padding:4px 0;">
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #F1F5F9;padding-bottom:12px;margin-bottom:16px;">
          <div>
            <h3 style="font-size:18px;font-weight:800;color:#1E293B;margin:0;">Order #${esc(o.order_number)} Details</h3>
            <small style="color:var(--text-muted);">Placed on: ${o.created_at}</small>
          </div>
          <span class="status-badge ${o.order_status}" style="font-size:12px;padding:4px 10px;">${esc(o.order_status.replace(/_/g, ' ').toUpperCase())}</span>
        </div>

        <!-- Customer & Delivery Destination Card -->
        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <span style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Recipient & Contact</span>
            <strong style="font-size:13.5px;color:#1E293B;">${esc(o.shipping_name)}</strong>
            <div style="font-size:12px;color:#475569;">${esc(o.shipping_phone)}</div>
          </div>
          <div>
            <span style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Delivery Destination</span>
            <div style="font-size:12.5px;color:#1E293B;">${esc(o.shipping_address)}, ${esc(o.district)}</div>
            <small style="color:var(--text-muted);">${esc(o.notes || 'HATEX Express Home Delivery')}</small>
          </div>
        </div>

        <!-- Ordered Items Table -->
        <div style="margin-bottom:16px;">
          <h4 style="font-size:14px;font-weight:800;color:#1E293B;margin-bottom:8px;">Purchased Items (${allItems.length})</h4>
          <div class="table-wrap">
            <table class="data-table" style="font-size:12.5px;">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Store</th>
                  <th>Quantity</th>
                  <th>Unit Price</th>
                  <th>Total</th>
                  ${o.order_status === 'delivered' ? '<th>Review</th>' : ''}
                </tr>
              </thead>
              <tbody>
                ${allItems
                  .map((it) => {
                    const prod = getProduct(it.product_id);
                    const img = prod?.image_1 || 'village_banner.webp';
                    return `
                    <tr>
                      <td style="display:flex;align-items:center;gap:10px;">
                        <img src="${esc(img)}" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:6px;border:1px solid #E2E8F0;">
                        <div>
                          <strong style="display:block;font-size:12.5px;">${esc(it.product_name)}</strong>
                          ${it.variant_name ? `<small style="color:var(--text-muted);">${esc(it.variant_name)}</small>` : ''}
                        </div>
                      </td>
                      <td><span style="font-size:11.5px;font-weight:600;color:var(--haat-primary);">${esc(it.store_name || 'Verified Merchant')}</span></td>
                      <td><strong>${it.quantity}</strong></td>
                      <td>${money(it.unit_price)}</td>
                      <td><strong style="color:var(--haat-orange);">${money(it.subtotal || it.unit_price * it.quantity)}</strong></td>
                      ${
                        o.order_status === 'delivered'
                          ? `<td><button type="button" class="btn-village-primary" style="padding:4px 8px;font-size:11px;" onclick="closeModal(); window.openReviewModal(${it.product_id})"><i class="bi bi-star"></i> Review</button></td>`
                          : ''
                      }
                    </tr>
                  `;
                  })
                  .join('')}
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pricing Summary -->
        ${(() => {
          const detailSubtotal = o.total_amount || (o.seller_orders || []).reduce((s, so) => s + (so.subtotal || 0), 0) || allItems.reduce((s, it) => s + (it.subtotal || it.unit_price * it.quantity || 0), 0);
          const detailShipping = o.shipping_cost !== undefined ? o.shipping_cost : (o.seller_orders || []).reduce((s, so) => s + (so.shipping_cost || 0), 0) || 0;
          const detailGrand = (o.grand_total && o.grand_total > 0) ? o.grand_total : Math.max(0, detailSubtotal + detailShipping - (o.discount_amount || 0));
          return `
          <div style="background:#FAF8F5;border:1px solid #EBE4D8;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12.5px;">
              <span style="color:#64748B;">Items Subtotal:</span>
              <strong>${money(detailSubtotal)}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12.5px;">
              <span style="color:#64748B;">HATEX Delivery Fee:</span>
              <span>${detailShipping === 0 ? '<span class="delivery-badge-free">FREE</span>' : money(detailShipping)}</span>
            </div>
            ${
              o.discount_amount > 0
                ? `
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12.5px;color:#15803D;">
                <span>Voucher Discount:</span>
                <strong>-${money(o.discount_amount)}</strong>
              </div>
            `
                : ''
            }
            <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid #EBE4D8;font-size:14px;color:var(--haat-primary);">
              <strong>Grand Total:</strong>
              <strong style="color:var(--haat-orange);font-size:16px;">${money(detailGrand)}</strong>
            </div>
          </div>
          `;
        })()}

        <!-- Actions -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <div style="font-size:12px;color:var(--text-muted);">
            Payment: <strong style="text-transform:uppercase;color:#1E293B;">${esc(o.payment?.method || 'Cash on Delivery')}</strong>
            ${o.payment?.transaction_reference ? ` • Ref: <code>${esc(o.payment.transaction_reference)}</code>` : ''}
          </div>
          <div style="display:flex;gap:8px;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Close</button>
            <a href="#/order/${esc(o.order_number)}" class="btn-village-primary" onclick="closeModal()">
              <i class="bi bi-truck"></i> Live Event Milestone Tracking
            </a>
          </div>
        </div>
      </div>
    `);
  };

  window.openAddAddressModal = function () {
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:14px;"><i class="bi bi-geo-alt"></i> Add New Delivery Address</h3>
      <form onsubmit="event.preventDefault(); window.handleAddAddress(this);">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;">Address Label</label>
            <input type="text" name="label" placeholder="e.g. Home, Office" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;">Recipient Name</label>
            <input type="text" name="name" value="${esc(state.currentUser?.name || '')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;">Phone Number</label>
          <input type="text" name="phone" value="${esc(state.currentUser?.phone || '')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;">Street Address (House, Road, Block)</label>
          <input type="text" name="address" placeholder="e.g. House 14, Road 3, Sector 7" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">
          <div>
            <label style="font-size:12px;font-weight:700;">District</label>
            <input type="text" name="district" value="Dhaka" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;">Division</label>
            <input type="text" name="division" value="Dhaka" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;">Postal Code</label>
            <input type="text" name="postal_code" value="1230" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Save Address</button>
      </form>
    `);
  };

  window.handleAddAddress = function (form) {
    const fd = new FormData(form);
    const newAddr = {
      id: Math.max(...state.addresses.map((a) => a.id || 0), 0) + 1,
      user_id: state.currentUser?.id || state.currentUserId || 1,
      label: fd.get('label') || 'Other',
      name: fd.get('name'),
      phone: fd.get('phone'),
      address: fd.get('address'),
      district: fd.get('district'),
      division: fd.get('division'),
      postal_code: fd.get('postal_code'),
      latitude: 23.790000 + Math.random() * 0.05,
      longitude: 90.380000 + Math.random() * 0.05,
      is_default: 0
    };
    state.addresses.push(newAddr);
    persist();
    closeModal();
    showToast('New delivery address saved!', 'success');
    render();
  };

  /* =========================================================================
     15. MESSAGING ROUTER & UNIVERSAL ROLE MESSAGING PANEL
     ========================================================================= */

  function renderMessagesView() {
    if (!state.activeRole) {
      showToast('Please log in to view messages.', 'info');
      location.hash = '#/auth';
      return '';
    }
    if (state.activeRole === 'customer') {
      return `<div class="container" style="margin-top:24px;margin-bottom:60px;">${renderDashboardMessagesView()}</div>`;
    }
    return `<div class="container" style="margin-top:24px;margin-bottom:60px;">${renderRoleMessagingPanel(state.activeRole)}</div>`;
  }

  window.openMessageModal = function (targetRole, targetId) {
    if (!state.activeRole) {
      showToast('Please log in to message the store!', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'seller') {
      const ownStore = getSellerOwnStore();
      if (ownStore && Number(targetId) === ownStore.id) {
        showToast('You cannot message your own store.', 'warning');
        return;
      }
      showToast('Sellers can only browse other stores in View-Only mode.', 'warning');
      return;
    }
    if (targetRole === 'seller' && state.activeRole === 'customer') {
      const store = getStore(targetId) || state.stores[0];
      const orderWithStore = state.orders.find((o) => (o.seller_orders || []).some((so) => so.store_id === store.id));
      const orderId = orderWithStore ? orderWithStore.id : 1;
      const chanId = `seller_${store.id}_order_${orderId}`;
      ensureSellerAutoGreeting(chanId, store.id, orderId);
      state.activeChatChannel = chanId;
      persist();
      if (location.hash === '#/account/messages') {
        render();
      } else {
        location.hash = '#/account/messages';
      }
      return;
    }
    location.hash = '#/messages';
  };

  window.openStartNewChatModal = function () {
    if (!state.activeRole) {
      showToast('Please log in to start a chat.', 'info');
      location.hash = '#/auth';
      return;
    }
    openModal(`
      <div style="padding:10px;">
        <h3 style="font-size:17px;font-weight:800;color:#1E293B;margin-bottom:4px;">
          <i class="bi bi-chat-plus-fill" style="color:var(--haat-orange)"></i> Start Chat with Merchant
        </h3>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
          Select any verified merchant store below to begin direct messaging about items, stock availability, or offers:
        </p>
        <div style="display:flex;flex-direction:column;gap:10px;max-height:360px;overflow-y:auto;margin-bottom:16px;">
          ${state.stores.map((st) => `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border:1px solid #E2E8F0;border-radius:8px;background:#FAFAFA;">
              <div style="display:flex;align-items:center;gap:12px;">
                <div class="user-avatar" style="width:38px;height:38px;font-size:14px;background:var(--haat-orange);color:#fff;">
                  ${esc(st.logo_text?.[0] || 'S')}
                </div>
                <div>
                  <strong style="font-size:13.5px;color:#1E293B;display:block;">${esc(st.store_name)}</strong>
                  <span style="font-size:11.5px;color:var(--text-muted);">${esc(st.district || 'Dhaka')} • ${st.rating || '4.9'} ★ • Verified Merchant</span>
                </div>
              </div>
              <button type="button" class="btn-village-primary" style="font-size:12px;padding:6px 14px;" onclick="closeModal(); window.openMessageModal('seller', ${st.id});">
                <i class="bi bi-chat-dots-fill"></i> Chat
              </button>
            </div>
          `).join('')}
        </div>
        <div style="text-align:right;">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        </div>
      </div>
    `);
  };

  function renderRoleMessagingPanel(viewerRole) {
    // Filter out customer->admin direct chat if any old message existed
    const roleMessages = state.messages.filter((m) =>
      (m.sender_role === viewerRole || m.receiver_role === viewerRole) &&
      !(viewerRole === 'customer' && (m.sender_role === 'admin' || m.receiver_role === 'admin'))
    );

    const channelMap = {};
    roleMessages.forEach((m) => {
      const cId = m.channel_id || `${m.sender_role}_${m.receiver_role}_order_${m.order_id}`;
      if (!channelMap[cId]) {
        const otherRole = m.sender_role === viewerRole ? m.receiver_role : m.sender_role;
        let otherName = m.sender_role === viewerRole ? m.receiver_name : m.sender_name;
        if (otherRole === 'admin') otherName = 'Admin';
        if (otherRole === 'hatex') otherName = 'HATEX';
        const roleColors = { customer: '#F85606', seller: '#1E4332', admin: '#2563EB', rider: '#059669', hatex: '#7C3AED' };
        const roleIcons = { customer: 'C', seller: 'S', admin: 'A', rider: 'R', hatex: 'H' };
        channelMap[cId] = {
          id: cId,
          otherRole,
          otherName,
          color: roleColors[otherRole] || '#64748B',
          avatar: roleIcons[otherRole] || '?',
          messages: [],
          unreadCount: 0
        };
      }
      channelMap[cId].messages.push(m);
      if (!m.is_read && m.receiver_role === viewerRole) channelMap[cId].unreadCount++;
    });

    // Ensure default channels exist for Seller, Rider, HATEX, Admin
    if (viewerRole === 'seller') {
      const store = typeof getSellerOwnStore === 'function' ? getSellerOwnStore() : null;
      if (store) {
        if (!channelMap[`seller_${store.id}_order_1`]) {
          channelMap[`seller_${store.id}_order_1`] = {
            id: `seller_${store.id}_order_1`,
            otherRole: 'customer',
            otherName: 'Customer Inquiries',
            color: '#F85606',
            avatar: 'C',
            messages: [],
            unreadCount: 0
          };
        }
        if (!channelMap[`seller_${store.id}_order_admin`]) {
          channelMap[`seller_${store.id}_order_admin`] = {
            id: `seller_${store.id}_order_admin`,
            otherRole: 'admin',
            otherName: 'Admin',
            color: '#2563EB',
            avatar: 'A',
            messages: [],
            unreadCount: 0
          };
        }
        if (!channelMap[`hatex_seller_${store.id}_order_1`]) {
          channelMap[`hatex_seller_${store.id}_order_1`] = {
            id: `hatex_seller_${store.id}_order_1`,
            otherRole: 'hatex',
            otherName: 'HATEX',
            color: '#7C3AED',
            avatar: 'H',
            messages: [],
            unreadCount: 0
          };
        }
      }
    } else if (viewerRole === 'rider') {
      const curRiderUser = state.currentUser;
      const rider = (state.riders || []).find((r) => String(r.user_id) === String(curRiderUser?.id) || (curRiderUser && r.name === curRiderUser.name)) || state.riders[0];
      const rId = rider?.id || 1;
      if (!channelMap[`rider_${rId}_order_1`]) {
        channelMap[`rider_${rId}_order_1`] = {
          id: `rider_${rId}_order_1`,
          otherRole: 'customer',
          otherName: 'Delivery Customer',
          color: '#F85606',
          avatar: 'C',
          messages: [],
          unreadCount: 0
        };
      }
      if (!channelMap[`hatex_rider_${rId}_order_1`]) {
        channelMap[`hatex_rider_${rId}_order_1`] = {
          id: `hatex_rider_${rId}_order_1`,
          otherRole: 'hatex',
          otherName: 'HATEX Logistics',
          color: '#7C3AED',
          avatar: 'H',
          messages: [],
          unreadCount: 0
        };
      }
    } else if (viewerRole === 'hatex') {
      if (!channelMap['hatex_rider_1_order_1']) {
        channelMap['hatex_rider_1_order_1'] = {
          id: 'hatex_rider_1_order_1',
          otherRole: 'rider',
          otherName: 'Tareq Ahmed',
          color: '#059669',
          avatar: 'R',
          messages: [],
          unreadCount: 0
        };
      }
      if (!channelMap['hatex_seller_1_order_1']) {
        channelMap['hatex_seller_1_order_1'] = {
          id: 'hatex_seller_1_order_1',
          otherRole: 'seller',
          otherName: 'ABC Fashion Store',
          color: '#1E4332',
          avatar: 'S',
          messages: [],
          unreadCount: 0
        };
      }
    } else if (viewerRole === 'admin') {
      // Connect Admin to all registered Seller stores
      (state.stores || []).forEach((st) => {
        const cId = `admin_store_${st.id}`;
        if (!channelMap[cId]) {
          channelMap[cId] = {
            id: cId,
            otherRole: 'seller',
            otherName: st.store_name,
            color: '#1E4332',
            avatar: 'S',
            messages: [],
            unreadCount: 0
          };
        }
      });
      // Connect Admin to HATEX Logistics
      if (!channelMap['admin_hatex_channel']) {
        channelMap['admin_hatex_channel'] = {
          id: 'admin_hatex_channel',
          otherRole: 'hatex',
          otherName: 'HATEX',
          color: '#7C3AED',
          avatar: 'H',
          messages: [],
          unreadCount: 0
        };
      }
      // Connect Admin to all Delivery Riders
      (state.riders || []).forEach((r) => {
        const cId = `admin_rider_${r.id}`;
        if (!channelMap[cId]) {
          channelMap[cId] = {
            id: cId,
            otherRole: 'rider',
            otherName: r.name,
            color: '#059669',
            avatar: 'R',
            messages: [],
            unreadCount: 0
          };
        }
      });
    }

    const channels = Object.values(channelMap);
    if (!channels.length) {
      channels.push({
        id: 'admin_hatex_channel',
        otherRole: 'hatex',
        otherName: 'HATEX',
        color: '#7C3AED',
        avatar: 'H',
        messages: [],
        unreadCount: 0
      });
    }
    const activeChannelId = state.activeChatChannel && channelMap[state.activeChatChannel]
      ? state.activeChatChannel
      : channels[0].id;
    state.activeChatChannel = activeChannelId;
    const activeCh = channelMap[activeChannelId] || channels[0];

    activeCh.messages.forEach((m) => {
      if (m.receiver_role === viewerRole) m.is_read = 1;
    });

    const totalUnread = channels.reduce((s, c) => s + c.unreadCount, 0);
    const showQuickMessages = viewerRole === 'seller';
    const sellerQuickList = state.quickMessages?.seller || [];

    return `
      <div style="background:#fff;border-radius:10px;border:1px solid #E2E8F0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #F1F5F9;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <div>
            <h2 style="font-size:17px;font-weight:800;color:#1E293B;margin:0;">
              <i class="bi bi-chat-quote-fill" style="color:var(--haat-orange);margin-right:6px;"></i>
              Messages & Communications (${esc(getDisplayRoleName(viewerRole))})
            </h2>
            <p style="font-size:11.5px;color:var(--text-muted);margin-top:3px;">
              ${totalUnread > 0 ? `<span style="color:#DC2626;font-weight:700;">${totalUnread} unread</span> • ` : ''}
              ${channels.length} active conversation${channels.length !== 1 ? 's' : ''}
            </p>
          </div>
          ${
            viewerRole !== 'admin'
              ? `<button type="button" class="btn-village-outline" style="font-size:11.5px;padding:5px 12px;color:#DC2626;border-color:#FCA5A5;" onclick="window.openAdminReportModal()">
                  <i class="bi bi-flag-fill"></i> Report to Admin
                </button>`
              : ''
          }
        </div>

        <div class="dash-messages-split">
          <!-- Left: Channel list -->
          <div class="dash-convos-list">
            <div style="padding:9px 14px;background:#FAF8F5;border-bottom:1px solid #EBE4D8;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">
              Conversations (${channels.length})
            </div>
            ${channels
              .map(
                (ch) => `
              <div class="convo-item-card ${ch.id === activeChannelId ? 'active' : ''}" onclick="window.setRoleActiveChatChannel('${ch.id}', '${viewerRole}')">
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="position:relative;flex-shrink:0;">
                    <div class="user-avatar" style="width:36px;height:36px;font-size:14px;background:${ch.color};color:#fff;">
                      ${esc(ch.avatar)}
                    </div>
                    ${ch.unreadCount > 0 ? `<span style="position:absolute;top:-3px;right:-3px;width:16px;height:16px;border-radius:50%;background:#DC2626;color:#fff;font-size:9px;font-weight:700;display:grid;place-items:center;">${ch.unreadCount}</span>` : ''}
                  </div>
                  <div style="min-width:0;flex:1;">
                    <strong style="font-size:12.5px;color:#1E293B;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(ch.otherName)}</strong>
                    <small style="color:var(--text-muted);font-size:10.5px;text-transform:capitalize;">${esc(ch.otherRole)}</small>
                    ${ch.messages.length ? `<div style="font-size:10.5px;color:#64748B;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">${esc(ch.messages[ch.messages.length - 1].message.slice(0, 38))}...</div>` : ''}
                  </div>
                </div>
              </div>
            `
              )
              .join('')}
          </div>

          <!-- Right: Chat window -->
          <div class="dash-chat-main">
            <div class="dash-chat-head">
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="user-avatar" style="width:36px;height:36px;font-size:14px;background:${activeCh.color};color:#fff;">
                  ${esc(activeCh.avatar)}
                </div>
                <div>
                  <strong style="font-size:14px;color:#1E293B;display:block;">${esc(activeCh.otherName)}</strong>
                  <span style="font-size:11px;color:#10B981;"><i class="bi bi-circle-fill" style="font-size:7px;"></i> Connected</span>
                </div>
              </div>
              <span style="font-size:10.5px;color:var(--text-muted);text-transform:capitalize;background:#F1F5F9;padding:3px 8px;border-radius:12px;">${esc(activeCh.otherRole)}</span>
            </div>

            <div class="dash-chat-stream" id="roleChatStream">
              ${
                activeCh.messages.length
                  ? activeCh.messages
                      .map((m) => {
                        const isOut = m.sender_role === viewerRole;
                        return `
                        <div class="chat-bubble-row ${isOut ? 'outgoing' : 'incoming'}">
                          <div class="chat-bubble-pill">
                            ${m.is_auto_greeting ? `<span style="font-size:9.5px;font-weight:800;text-transform:uppercase;color:#EA580C;display:block;margin-bottom:2px;"><i class="bi bi-robot"></i> Auto-Greeting Sent</span>` : ''}
                            ${esc(m.message)}
                          </div>
                          <span class="chat-bubble-meta">${esc(m.sender_name)} • ${m.created_at || 'Recently'}</span>
                        </div>
                      `;
                      })
                      .join('')
                  : `<div style="text-align:center;padding:30px;color:var(--text-muted);font-size:12.5px;">
                      <i class="bi bi-chat-quote" style="font-size:24px;display:block;margin-bottom:6px;opacity:0.6;"></i>
                      Start a conversation with ${esc(activeCh.otherName)}.
                    </div>`
              }
            </div>

            ${
              showQuickMessages
                ? `
              <!-- Readymade Quick Messages Strip (Only for Seller & Customer — Not shown for HATEX, Rider, or Admin) -->
              <div class="quick-messages-strip">
                <span style="font-size:10.5px;font-weight:800;color:#64748B;text-transform:uppercase;"><i class="bi bi-lightning-fill" style="color:var(--haat-orange);"></i> Quick Reply:</span>
                ${sellerQuickList
                  .map(
                    (qMsg, qIdx) => `
                  <button type="button" class="quick-msg-chip" onclick="window.sendQuickReadyMessage('seller', ${qIdx}, '${activeChannelId}', '${esc(activeCh.otherRole)}', '${esc(activeCh.otherName)}')">
                    ${esc(qMsg)}
                  </button>
                `
                  )
                  .join('')}
                <button type="button" class="quick-msg-manage-btn" onclick="window.openManageQuickMessagesModal('seller')">
                  <i class="bi bi-pencil-square"></i> Edit Quick Replies
                </button>
              </div>
            `
                : ''
            }

            <form class="dash-chat-input-bar" onsubmit="event.preventDefault(); window.handleRoleChat(this, '${viewerRole}', '${activeChannelId}', '${esc(activeCh.otherRole)}', '${esc(activeCh.otherName)}');">
              <input type="text" name="chat_text" placeholder="Message ${esc(activeCh.otherName)}..." class="msg-input-field" required autocomplete="off">
              <button type="submit" class="msg-send-btn" title="Send"><i class="bi bi-send-fill"></i></button>
            </form>
          </div>
        </div>
      </div>
    `;
  }

  window.setRoleActiveChatChannel = function (channelId) {
    state.activeChatChannel = channelId;
    persist();
    render();
    setTimeout(() => {
      const box = $('#roleChatStream');
      if (box) box.scrollTop = box.scrollHeight;
    }, 80);
  };

  window.handleRoleChat = function (form, senderRole, channelId, receiverRole, receiverName) {
    const input = form.chat_text;
    const txt = (input.value || '').trim();
    if (!txt) return;

    const senderDisplayName = getDisplayRoleName(senderRole);
    const newMsg = {
      id: state.messages.length + 1,
      order_id: state.orders[0]?.id || 1,
      channel_id: channelId,
      sender_id: state.currentUser?.id || 99,
      sender_name: senderDisplayName,
      sender_role: senderRole,
      receiver_id: 99,
      receiver_name: receiverName,
      receiver_role: receiverRole,
      message: txt,
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      is_read: 0
    };

    state.messages.push(newMsg);
    input.value = '';

    window.addNotification({
      title: `New Message from ${senderDisplayName}`,
      message: txt.length > 70 ? txt.slice(0, 70) + '...' : txt,
      type: 'chat',
      target_role: receiverRole,
      order_id: state.orders[0]?.id || 1,
      link: receiverRole === 'customer' ? '#/account/messages' : `#/dash/${receiverRole}/messages`
    });

    persist();
    updateGlobalHeader();
    render();

    setTimeout(() => {
      const box = $('#roleChatStream');
      if (box) box.scrollTop = box.scrollHeight;
    }, 80);
  };



  /* =========================================================================
     16. SELLER DASHBOARD (`#/dash/seller`)
     ========================================================================= */

  function renderSellerDashView(subTab = 'overview') {
    const store = getSellerOwnStore() || state.stores[0];
    const storeOrders = state.orders.flatMap((o) =>
      (o.seller_orders || []).filter((so) => so.store_id === store.id).map((so) => ({ ...so, parentOrder: o }))
    );
    const storeProducts = state.products.filter((p) => p.store_id === store.id);
    const storeCoupons = state.coupons.filter((c) => c.store_id === store.id);
    const totalSales = storeOrders.reduce((s, so) => s + so.seller_total, 0);
    const sellerUnreadMsgs = state.messages.filter((m) => m.receiver_role === 'seller' && !m.is_read).length;

    return `
      <div class="container">
        <div class="dash-shell">
          <!-- Sidebar (Shows Store Name only per requirement #11, no link to visit own store per requirement #7) -->
          <aside class="dash-nav-card">
            <div class="dash-nav-header">
              <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;">SELLER STORE ACCOUNT</div>
              <strong style="font-size:15px;color:#1E293B;display:block;">${esc(store.store_name)}</strong>
              <small style="color:#10B981;"><i class="bi bi-check-circle"></i> ${store.free_delivery ? 'Free Delivery Active' : `Delivery: ${money(store.delivery_charge ?? 60)}`}</small>
            </div>
            <a class="dash-nav-item ${subTab === 'overview' ? 'active' : ''}" onclick="location.hash='#/dash/seller/overview'">
              <i class="bi bi-speedometer2"></i> Dashboard Overview
            </a>
            <a class="dash-nav-item ${subTab === 'orders' ? 'active' : ''}" onclick="location.hash='#/dash/seller/orders'">
              <i class="bi bi-receipt"></i> Orders & Status Edit (${storeOrders.length})
            </a>
            <a class="dash-nav-item ${subTab === 'tracking' ? 'active' : ''}" onclick="location.hash='#/dash/seller/tracking'">
              <i class="bi bi-truck"></i> Product & Order Tracking
            </a>
            <a class="dash-nav-item ${subTab === 'products' ? 'active' : ''}" onclick="location.hash='#/dash/seller/products'">
              <i class="bi bi-box-seam"></i> Products & Delivery (${storeProducts.length})
            </a>
            <a class="dash-nav-item ${subTab === 'inventory' ? 'active' : ''}" onclick="location.hash='#/dash/seller/inventory'">
              <i class="bi bi-stack"></i> Inventory Management
            </a>
            <a class="dash-nav-item ${subTab === 'coupons' ? 'active' : ''}" onclick="location.hash='#/dash/seller/coupons'">
              <i class="bi bi-ticket-perforated"></i> Store Coupons (${storeCoupons.length})
            </a>
            <a class="dash-nav-item ${subTab === 'store' ? 'active' : ''}" onclick="location.hash='#/dash/seller/store'">
              <i class="bi bi-shop"></i> Store, Delivery & Greeting
            </a>
            <a class="dash-nav-item ${subTab === 'messages' ? 'active' : ''}" onclick="location.hash='#/dash/seller/messages'" style="display:flex;align-items:center;justify-content:space-between;">
              <span><i class="bi bi-chat-dots"></i> Messages & Quick Replies</span>
              ${sellerUnreadMsgs > 0 ? `<span style="background:#DC2626;color:#fff;font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:10px;">${sellerUnreadMsgs}</span>` : ''}
            </a>
            <a class="dash-nav-item" onclick="location.hash='#/stores'">
              <i class="bi bi-globe"></i> Visit Other Stores (View Only) &rarr;
            </a>
          </aside>

          <!-- Main Dashboard -->
          <main>
            ${
              subTab === 'store'
                ? `
              <!-- Store Verification & Business KYC Status Card -->
              <div class="page-card" style="background:#fff;padding:22px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                  <div>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <h3 style="font-size:17px;font-weight:800;color:#1E293B;margin:0;">Store Verification & Business KYC</h3>
                      ${
                        store.verification_status === 'verified'
                          ? `<span style="background:#ECFDF5;color:#059669;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;"><i class="bi bi-patch-check-fill"></i> Verified Store</span>`
                          : store.verification_status === 'pending'
                          ? `<span style="background:#FEF3C7;color:#B45309;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;"><i class="bi bi-hourglass-split"></i> Verification Pending Admin Review</span>`
                          : store.verification_status === 'rejected'
                          ? `<span style="background:#FEF2F2;color:#DC2626;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;"><i class="bi bi-x-circle-fill"></i> Documents Rejected</span>`
                          : `<span style="background:#F1F5F9;color:#64748B;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;"><i class="bi bi-shield"></i> Unverified Store</span>`
                      }
                    </div>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                      ${
                        store.verification_status === 'verified'
                          ? `Your store is officially verified! The Verified Merchant trust badge is active on your storefront and products.`
                          : store.verification_status === 'pending'
                          ? `Your business documents are submitted and currently undergoing review by HAAT Admin. Your store remains active and open to customers.`
                          : store.verification_status === 'rejected'
                          ? `Admin review indicated issues with your uploaded credentials. Please review the note below and submit updated documents.`
                          : `Your store is open and active right now! Submit your Trade License, NID, TIN, and Bank details to earn the official Verified Merchant badge.`
                      }
                    </p>
                  </div>
                  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <a href="#/store/${esc(store.store_slug)}" class="btn-village-secondary" style="font-size:12px;padding:7px 14px;text-decoration:none;"><i class="bi bi-eye"></i> View Live Storefront</a>
                    <button type="button" class="btn-village-primary" style="font-size:12px;padding:7px 14px;" onclick="window.openSellerVerificationModal()">
                      <i class="bi bi-file-earmark-check"></i> ${store.verification_status === 'verified' ? 'View / Update Credentials' : store.verification_status === 'pending' ? 'Update Submitted Documents' : 'Submit Verification Documents'}
                    </button>
                  </div>
                </div>

                ${
                  store.rejection_reason && store.verification_status === 'rejected'
                    ? `
                  <div style="margin-top:12px;padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;font-size:12px;color:#991B1B;">
                    <strong>Admin Rejection Note:</strong> ${esc(store.rejection_reason)}
                  </div>
                `
                    : ''
                }

                <!-- Verification Document Indicators -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid #F1F5F9;">
                  <div style="background:#F8FAFC;padding:10px;border-radius:6px;border:1px solid #E2E8F0;font-size:11.5px;">
                    <div style="font-weight:700;color:#1E293B;"><i class="bi bi-file-earmark-text"></i> Trade License</div>
                    <div style="color:var(--text-muted);margin-top:2px;">${store.verification_documents?.trade_license_no ? `<span style="color:#059669;font-weight:700;"><i class="bi bi-check2"></i> ${esc(store.verification_documents.trade_license_no)}</span>` : '<span style="color:#94A3B8;">Not uploaded</span>'}</div>
                  </div>
                  <div style="background:#F8FAFC;padding:10px;border-radius:6px;border:1px solid #E2E8F0;font-size:11.5px;">
                    <div style="font-weight:700;color:#1E293B;"><i class="bi bi-person-vcard"></i> National ID (NID)</div>
                    <div style="color:var(--text-muted);margin-top:2px;">${store.verification_documents?.nid_no ? `<span style="color:#059669;font-weight:700;"><i class="bi bi-check2"></i> ${esc(store.verification_documents.nid_no)}</span>` : '<span style="color:#94A3B8;">Not uploaded</span>'}</div>
                  </div>
                  <div style="background:#F8FAFC;padding:10px;border-radius:6px;border:1px solid #E2E8F0;font-size:11.5px;">
                    <div style="font-weight:700;color:#1E293B;"><i class="bi bi-receipt"></i> TIN Certificate</div>
                    <div style="color:var(--text-muted);margin-top:2px;">${store.verification_documents?.tin_no ? `<span style="color:#059669;font-weight:700;"><i class="bi bi-check2"></i> ${esc(store.verification_documents.tin_no)}</span>` : '<span style="color:#94A3B8;">Not uploaded</span>'}</div>
                  </div>
                  <div style="background:#F8FAFC;padding:10px;border-radius:6px;border:1px solid #E2E8F0;font-size:11.5px;">
                    <div style="font-weight:700;color:#1E293B;"><i class="bi bi-bank"></i> Bank Cheque / Proof</div>
                    <div style="color:var(--text-muted);margin-top:2px;">${store.verification_documents?.bank_doc ? `<span style="color:#059669;font-weight:700;"><i class="bi bi-check2"></i> Document Attached</span>` : '<span style="color:#94A3B8;">Not uploaded</span>'}</div>
                  </div>
                </div>
              </div>

              <!-- Store Profile, Delivery Charge / Free Delivery & Auto-Greeting Settings -->
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:4px;">Store Settings, Delivery Charge & Automatic Greeting</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">Configure your store details, default delivery charge, free delivery option, and automatic customer greeting message.</p>

                <form onsubmit="event.preventDefault(); window.handleSaveStore(this);">
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:12px;">
                    <div>
                      <label style="font-size:12px;font-weight:700;">Store Name</label>
                      <input type="text" name="store_name" value="${esc(store.store_name)}" required style="width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div>
                      <label style="font-size:12px;font-weight:700;">Store Slug</label>
                      <input type="text" name="store_slug" value="${esc(store.store_slug)}" required style="width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                  </div>

                  <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:700;">Store Description</label>
                    <textarea name="description" rows="2" style="width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:6px;">${esc(store.description)}</textarea>
                  </div>

                  <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                      <label style="font-size:12px;font-weight:700;">Pickup Address</label>
                      <input type="text" name="address" value="${esc(store.address)}" required style="width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div>
                      <label style="font-size:12px;font-weight:700;">District</label>
                      <input type="text" name="district" value="${esc(store.district)}" required style="width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div>
                      <label style="font-size:12px;font-weight:700;">Division</label>
                      <input type="text" name="division" value="${esc(store.division)}" required style="width:100%;padding:10px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                  </div>

                  <!-- Delivery Charge & Store Free Delivery Control -->
                  <div style="background:#ECFDF5;border:1.5px solid #86EFAC;border-radius:10px;padding:16px;margin-bottom:16px;">
                    <h4 style="font-size:14px;font-weight:800;color:#15803D;margin:0 0 8px 0;"><i class="bi bi-truck"></i> Store Delivery Charge & Free Delivery Control</h4>
                    <p style="font-size:12px;color:#334155;margin-bottom:12px;">Set your store's standard delivery charge, or enable Free Delivery across all products in your store. This updates product cards, cart, and order confirmation immediately.</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:center;">
                      <div>
                        <label style="font-size:12px;font-weight:700;color:#1E293B;display:block;margin-bottom:4px;">Standard Store Delivery Charge (৳)</label>
                        <input type="number" min="0" name="delivery_charge" value="${store.delivery_charge ?? 60}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-weight:700;">
                      </div>
                      <div style="padding-top:18px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;font-weight:800;color:#15803D;cursor:pointer;">
                          <input type="checkbox" name="free_delivery" ${store.free_delivery ? 'checked' : ''} style="width:18px;height:18px;">
                          Enable Store-Wide FREE Delivery (৳0)
                        </label>
                      </div>
                    </div>
                  </div>

                  <!-- Automatic Customer Greeting System -->
                  <div style="background:#FFF7ED;border:1.5px solid #FDBA74;border-radius:10px;padding:16px;margin-bottom:18px;">
                    <h4 style="font-size:14px;font-weight:800;color:#C2410C;margin:0 0 6px 0;"><i class="bi bi-robot"></i> Automatic First-Message Greeting to Customers</h4>
                    <p style="font-size:12px;color:#475569;margin-bottom:10px;">When any customer first opens a chat with your store, this greeting message is sent automatically.</p>
                    <textarea name="auto_greeting" rows="2" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13px;">${esc(store.auto_greeting || `Assalamu Alaikum! Welcome to ${store.store_name}. How can we help you today?`)}</textarea>
                  </div>

                  <button type="submit" class="btn-village-primary"><i class="bi bi-save"></i> Save Store, Delivery & Greeting Settings</button>
                </form>
              </div>
            `
                : subTab === 'products'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0;">My Store Products & Delivery Settings (${storeProducts.length})</h2>
                    <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Control pricing, per-product delivery charge, and free delivery options.</p>
                  </div>
                  <button type="button" class="btn-village-primary" onclick="window.openAddProductModal()">+ Add New Product</button>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Image</th>
                        <th>Name & SKU</th>
                        <th>Price</th>
                        <th>Delivery Charge / Option</th>
                        <th>Available Stock</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${storeProducts
                        .map((p) => {
                          const inv = getInventory(p.id);
                          const delivInfo = getProductDeliveryInfo(p);
                          return `
                          <tr>
                            <td><img src="${esc(p.image_1)}" style="width:42px;height:42px;object-fit:cover;border-radius:6px;"></td>
                            <td>
                              <strong>${esc(p.name)}</strong>
                              <div style="font-size:11px;color:var(--text-muted);"><code>${esc(p.sku)}</code></div>
                            </td>
                            <td><strong>${money(p.sale_price || p.price)}</strong></td>
                            <td>
                              <span class="${delivInfo.isFree ? 'delivery-badge-free' : 'delivery-badge-fee'}">
                                <i class="bi bi-truck"></i> ${delivInfo.isFree ? `FREE (${esc(delivInfo.reason)})` : `${money(delivInfo.fee)} (${esc(delivInfo.reason)})`}
                              </span>
                            </td>
                            <td>
                              <span style="font-weight:700;color:${inv.available <= 0 ? '#DC2626' : '#16A34A'}">
                                ${inv.available} units
                              </span>
                            </td>
                            <td>
                              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button type="button" class="btn-village-primary" style="padding:4px 9px;font-size:11px;" onclick="window.openEditProductDeliveryModal(${p.id})">
                                  <i class="bi bi-pencil-square"></i> Edit Product & Delivery
                                </button>
                                <button type="button" class="btn-village-outline" style="padding:4px 9px;font-size:11px;" onclick="window.openEditInventoryModal(${p.id})">
                                  Stock
                                </button>
                              </div>
                            </td>
                          </tr>
                        `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'inventory'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0 0 4px 0;">Inventory Stock Controller</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Manage physical stock, reserve levels, and warehouse shelf locations.</p>
                  </div>
                  <button type="button" class="btn-village-primary" style="display:inline-flex;align-items:center;gap:6px;" onclick="window.openAddProductModal()">
                    <i class="bi bi-plus-circle"></i> + Add New Product SKU
                  </button>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Product & SKU</th>
                        <th>Location</th>
                        <th>Total Stock</th>
                        <th>Reserved</th>
                        <th>Available Stock</th>
                        <th>Quick Adjust</th>
                        <th>Edit Inventory</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${storeProducts
                        .map((p) => {
                          const inv = getInventory(p.id);
                          const isLow = inv.available > 0 && inv.available <= 5;
                          const isOut = inv.available <= 0;
                          return `
                          <tr>
                            <td>
                              <div style="display:flex;align-items:center;gap:10px;">
                                <img src="${esc(p.image_1)}" style="width:38px;height:38px;object-fit:cover;border-radius:4px;border:1px solid #E2E8F0;">
                                <div>
                                  <strong style="font-size:13px;color:#1E293B;display:block;">${esc(p.name)}</strong>
                                  <div style="font-size:11px;color:var(--text-muted);"><code>${esc(p.sku)}</code></div>
                                </div>
                              </div>
                            </td>
                            <td><span style="font-size:11.5px;color:#475569;"><i class="bi bi-geo-alt"></i> ${esc(inv.location || 'Shelf A-1')}</span></td>
                            <td><strong style="font-size:13px;color:#1E293B;">${inv.quantity}</strong></td>
                            <td><span style="font-size:12.5px;color:var(--text-muted);">${inv.reserved_quantity}</span></td>
                            <td>
                              <span class="status-badge ${isOut ? 'cancelled' : isLow ? 'packaged' : 'delivered'}" style="font-size:11px;">
                                ${inv.available} units ${isOut ? '(Out of Stock)' : isLow ? '(Low Stock)' : '(In Stock)'}
                              </span>
                            </td>
                            <td>
                              <div style="display:flex;gap:4px;">
                                <button type="button" class="btn-village-outline" style="padding:2px 6px;font-size:11px;" onclick="window.adjustStock(${p.id}, -5)">-5</button>
                                <button type="button" class="btn-village-outline" style="padding:2px 6px;font-size:11px;" onclick="window.adjustStock(${p.id}, -1)">-1</button>
                                <button type="button" class="btn-village-outline" style="padding:2px 6px;font-size:11px;" onclick="window.adjustStock(${p.id}, 1)">+1</button>
                                <button type="button" class="btn-village-outline" style="padding:2px 6px;font-size:11px;" onclick="window.adjustStock(${p.id}, 5)">+5</button>
                              </div>
                            </td>
                            <td>
                              <button type="button" class="btn-village-primary" style="padding:4px 10px;font-size:11.5px;" onclick="window.openEditInventoryModal(${p.id})">
                                <i class="bi bi-pencil-square"></i> Edit Stock
                              </button>
                            </td>
                          </tr>
                        `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'coupons'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0 0 4px 0;">Store Coupons & Vouchers (${storeCoupons.length})</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin:0;">Create store-specific collectible vouchers for ${esc(store.store_name)}.</p>
                  </div>
                  <button type="button" class="btn-village-primary" onclick="window.openAddCouponModal()">
                    <i class="bi bi-plus-circle"></i> + Add Store Coupon
                  </button>
                </div>

                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Coupon Code</th>
                        <th>Discount Value</th>
                        <th>Min Order</th>
                        <th>Max Cap</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${storeCoupons
                        .map(
                          (c) => `
                        <tr>
                          <td><strong style="font-family:var(--font-mono);color:var(--haat-primary);">${esc(c.code)}</strong></td>
                          <td><strong style="color:var(--haat-orange);">${c.discount_type === 'percent' ? `${c.discount_value}% OFF` : money(c.discount_value) + ' OFF'}</strong></td>
                          <td>${money(c.min_order)}</td>
                          <td>${c.max_discount ? money(c.max_discount) : 'No Cap'}</td>
                          <td>${c.expiry_date || 'No Expiry'}</td>
                          <td>
                            <button type="button" class="status-badge ${c.is_active ? 'active' : 'cancelled'}" style="cursor:pointer;border:none;" onclick="window.toggleCouponStatus(${c.id})">
                              ${c.is_active ? 'Active' : 'Inactive'}
                            </button>
                          </td>
                          <td>
                            <div style="display:flex;gap:6px;">
                              <button type="button" class="btn-village-outline" style="padding:3px 8px;font-size:11px;" onclick="window.openEditCouponModal(${c.id})">Edit</button>
                              <button type="button" class="btn-secondary" style="padding:3px 8px;font-size:11px;color:#DC2626;" onclick="window.deleteCoupon(${c.id})"><i class="bi bi-trash"></i></button>
                            </div>
                          </td>
                        </tr>
                      `
                        )
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'orders'
                ? `
              <!-- Seller Orders & Fulfillment Pipeline -->
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0;">Seller Orders & Fulfillment Pipeline (${storeOrders.length})</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Merchant fulfillment workflow: <strong>Pending &rarr; Order Accepted &rarr; Processing &rarr; Packaged</strong>. Once an order is marked <strong>Packaged</strong>, it is handed over to <strong>HATEX Logistics</strong> for dispatch and doorstep delivery.</p>
                  </div>
                  <a href="#/dash/seller/tracking" class="btn-village-outline" style="font-size:12px;padding:6px 14px;">
                    <i class="bi bi-truck"></i> Open Seller Product Tracking &rarr;
                  </a>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Seller Order #</th>
                        <th>Customer & Address</th>
                        <th>Items & Delivery</th>
                        <th>Total</th>
                        <th>Current Status</th>
                        <th>Merchant Fulfillment Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${storeOrders
                        .map((so) => {
                          const isHandedToHatex = ['packaged', 'reached_hub', 'assigned_to_rider', 'in_transit', 'out_for_delivery', 'delivered'].includes(so.status);
                          const isDelivered = so.status === 'delivered';
                          const isCancelled = so.status === 'cancelled';

                          let actionHtml = '';
                          if (isDelivered) {
                            actionHtml = `
                              <span class="status-badge delivered" style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11.5px;font-weight:700;">
                                <i class="bi bi-check-circle-fill"></i> Delivered
                              </span>
                            `;
                          } else if (isCancelled) {
                            actionHtml = `
                              <span class="status-badge cancelled" style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11.5px;font-weight:700;">
                                <i class="bi bi-x-circle-fill"></i> Cancelled
                              </span>
                            `;
                          } else if (isHandedToHatex) {
                            actionHtml = `
                              <div style="display:flex;flex-direction:column;gap:3px;">
                                <span class="status-badge" style="background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                                  <i class="bi bi-box-seam-fill"></i> Packaged &bull; In HATEX Care
                                </span>
                                <a href="#/dash/seller/tracking" style="font-size:11px;color:var(--haat-primary);font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:3px;">
                                  <i class="bi bi-truck"></i> Track with HATEX &rarr;
                                </a>
                              </div>
                            `;
                          } else {
                            const merchantStatuses = [
                              { val: 'pending', label: 'Pending' },
                              { val: 'order_accepted', label: 'Order Accepted' },
                              { val: 'processing', label: 'Processing' },
                              { val: 'packaged', label: 'Packaged (Ready for HATEX)' }
                            ];
                            actionHtml = `
                              <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <select id="sellerStatusSelect_${so.id}" style="padding:6px 10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:12px;font-weight:700;background:#fff;">
                                  ${merchantStatuses
                                    .map(
                                      (st) => `
                                    <option value="${st.val}" ${so.status === st.val ? 'selected' : ''}>${st.label}</option>
                                  `
                                    )
                                    .join('')}
                                </select>
                                <button type="button" class="btn-village-primary" style="padding:6px 12px;font-size:11.5px;" onclick="window.updateSellerOrderStatus(${so.id})">
                                  <i class="bi bi-check2-circle"></i> Update
                                </button>
                              </div>
                            `;
                          }

                          return `
                        <tr>
                          <td>
                            <strong>${esc(so.seller_order_number)}</strong>
                            <div style="font-size:11px;color:var(--text-muted);">Main: #${esc(so.parentOrder.order_number)}</div>
                          </td>
                          <td>
                            <strong>${esc(so.parentOrder.shipping_name)}</strong>
                            <div style="font-size:11px;color:var(--text-muted);">${esc(so.parentOrder.shipping_address)}, ${esc(so.parentOrder.district)}</div>
                          </td>
                          <td>
                            ${(so.items || []).map((i) => `<div style="font-size:12px;">• ${esc(i.product_name)} × ${i.quantity}</div>`).join('')}
                            <div style="font-size:11px;color:#15803D;margin-top:2px;">Delivery: ${so.shipping_cost === 0 ? 'FREE' : money(so.shipping_cost)}</div>
                          </td>
                          <td><strong style="color:var(--haat-orange);">${money(so.seller_total)}</strong></td>
                          <td><span class="status-badge ${esc(so.status)}">${esc(so.status.replace(/_/g, ' ').toUpperCase())}</span></td>
                          <td>${actionHtml}</td>
                        </tr>
                      `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'tracking'
                ? `
              <!-- Seller Product & Order Tracking System -->
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin:0;"><i class="bi bi-truck" style="color:var(--haat-orange);"></i> Seller Product & Order Tracking System</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">Real-time tracking of all products dispatched from ${esc(store.store_name)}, including HATEX hub status, assigned rider, and customer delivery checkpoints.</p>
                  </div>
                </div>

                ${storeOrders
                  .map((so) => {
                    const rider = so.assigned_rider_id ? state.riders.find((r) => r.id === Number(so.assigned_rider_id)) : null;
                    const latestTrack = (so.tracking || [])[so.tracking.length - 1];
                    const curLat = latestTrack?.latitude || store.latitude;
                    const curLon = latestTrack?.longitude || store.longitude;
                    const dist = haversineDistance(curLat, curLon, so.parentOrder.customer_lat, so.parentOrder.customer_lon);
                    const stepOrder = ['pending', 'order_accepted', 'processing', 'packaged', 'reached_hub', 'assigned_to_rider', 'out_for_delivery', 'delivered'];
                    const curIdx = Math.max(0, stepOrder.indexOf(so.status));

                    return `
                    <div style="border:1.5px solid #E2E8F0;border-radius:10px;padding:18px;margin-bottom:18px;background:#F8FAFC;">
                      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding-bottom:12px;border-bottom:1px solid #E2E8F0;margin-bottom:14px;">
                        <div>
                          <span class="role-badge-tag role-badge-seller">Sub-Order #${esc(so.seller_order_number)}</span>
                          <strong style="font-size:15px;color:#1E293B;margin-left:8px;">Parent Order #${esc(so.parentOrder.order_number)}</strong>
                          <div style="font-size:12px;color:#475569;margin-top:4px;">
                            Customer: <strong>${esc(so.parentOrder.shipping_name)}</strong> (${esc(so.parentOrder.shipping_phone)}) • Destination: ${esc(so.parentOrder.shipping_address)}, ${esc(so.parentOrder.district)}
                          </div>
                        </div>
                        <div style="text-align:right;">
                          <span class="status-badge ${esc(so.status)}">${esc(so.status.replace(/_/g, ' ').toUpperCase())}</span>
                          <div style="font-size:11.5px;color:var(--haat-primary);font-weight:700;margin-top:4px;">Remaining Distance: ${dist} km</div>
                        </div>
                      </div>

                      <!-- Visual Pipeline -->
                      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(110px, 1fr));gap:8px;margin-bottom:14px;">
                        ${[
                          { k: 'pending', l: '1. Pending' },
                          { k: 'order_accepted', l: '2. Accepted' },
                          { k: 'processing', l: '3. Processing' },
                          { k: 'packaged', l: '4. Packaged' },
                          { k: 'assigned_to_rider', l: '5. Rider Assigned' },
                          { k: 'out_for_delivery', l: '6. Out for Delivery' },
                          { k: 'delivered', l: '7. Delivered' }
                        ]
                          .map((st) => {
                            const stIdx = stepOrder.indexOf(st.k);
                            const done = curIdx >= stIdx;
                            return `
                            <div style="padding:8px;border-radius:6px;text-align:center;font-size:11px;font-weight:700;background:${done ? '#DCFCE7' : '#fff'};color:${done ? '#15803D' : '#64748B'};border:1px solid ${done ? '#86EFAC' : '#E2E8F0'};">
                              ${done ? '<i class="bi bi-check-circle-fill"></i> ' : ''}${st.l}
                            </div>
                          `;
                          })
                          .join('')}
                      </div>

                      <!-- Products & Assigned Rider Info -->
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#fff;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                        <div>
                          <strong style="font-size:12px;color:#1E293B;display:block;margin-bottom:6px;">Products in Package:</strong>
                          ${(so.items || []).map((it) => `<div style="font-size:12px;color:#475569;">• ${esc(it.product_name)} × ${it.quantity} (${money(it.subtotal)})</div>`).join('')}
                        </div>
                        <div>
                          <strong style="font-size:12px;color:#1E293B;display:block;margin-bottom:6px;">Logistics & Rider Assignment:</strong>
                          ${
                            rider
                              ? `<div style="font-size:12px;color:#15803D;font-weight:700;"><i class="bi bi-bicycle"></i> Assigned Rider: ${esc(rider.name)} (${esc(rider.phone)})</div>`
                              : `<div style="font-size:12px;color:#64748B;">Awaiting HATEX Rider Assignment</div>`
                          }
                          <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">Latest Checkpoint: ${esc(latestTrack?.location || store.address)} — "${esc(latestTrack?.note || 'In preparation')}"</div>
                        </div>
                      </div>
                    </div>
                  `;
                  })
                  .join('')}
              </div>
            `
                : subTab === 'messages'
                ? renderRoleMessagingPanel('seller')
                : `
              <!-- Overview -->
              <div class="kpi-row">
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${money(totalSales)}</h3>
                    <p>Total Store Revenue</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-cash-stack"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${storeOrders.length}</h3>
                    <p>Store Orders</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-box-seam"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${storeProducts.length}</h3>
                    <p>Products Listed</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#FFF7ED;color:#EA580C;"><i class="bi bi-tags"></i></div>
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                  <h3 style="font-size:16px;font-weight:800;margin:0;">Recent Orders for ${esc(store.store_name)}</h3>
                  <a href="#/dash/seller/orders" class="btn-village-outline" style="font-size:12px;padding:5px 12px;">Edit Order Statuses &rarr;</a>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Delivery Charge</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${storeOrders
                        .slice(0, 5)
                        .map(
                          (so) => `
                        <tr>
                          <td><strong>${esc(so.seller_order_number)}</strong></td>
                          <td>${esc(so.parentOrder.shipping_name)}</td>
                          <td>${so.shipping_cost === 0 ? '<span class="delivery-badge-free">FREE</span>' : money(so.shipping_cost)}</td>
                          <td><strong>${money(so.seller_total)}</strong></td>
                          <td><span class="status-badge ${esc(so.status)}">${esc(so.status.replace(/_/g, ' '))}</span></td>
                          <td><a href="#/dash/seller/tracking" class="btn-village-outline" style="padding:3px 8px;font-size:11px;">Track Product</a></td>
                        </tr>
                      `
                        )
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
            }
          </main>
        </div>
      </div>
    `;
  }

  window.handleSaveStore = function (form) {
    const fd = new FormData(form);
    const store = getSellerOwnStore() || state.stores[0];
    store.store_name = fd.get('store_name');
    store.store_slug = fd.get('store_slug');
    store.description = fd.get('description');
    store.address = fd.get('address');
    store.district = fd.get('district');
    store.division = fd.get('division');
    store.delivery_charge = Math.max(0, parseFloat(fd.get('delivery_charge')) || 0);
    store.free_delivery = fd.get('free_delivery') ? 1 : 0;
    store.auto_greeting = (fd.get('auto_greeting') || '').trim();
    persist();
    updateGlobalHeader();
    showToast('Store profile, delivery charge, free delivery & automatic greeting saved!', 'success');
    render();
  };

  window.openSellerVerificationModal = function () {
    const store = getSellerOwnStore() || state.stores[0];
    const docs = store.verification_documents || {};

    openModal(`
      <div style="padding:6px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
          <div class="user-avatar" style="width:40px;height:40px;background:${store.primary_color || 'var(--haat-primary)'};color:#fff;font-size:14px;font-weight:800;">
            ${esc(store.logo_text || store.store_name.slice(0, 3).toUpperCase())}
          </div>
          <div>
            <h3 style="font-size:18px;font-weight:800;margin:0;color:#1E293B;">Store Verification & Business KYC Submission</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:2px 0 0 0;">Upload your official business documents for HAAT Admin approval to earn the Verified Merchant badge.</p>
          </div>
        </div>

        <form onsubmit="event.preventDefault(); window.handleSellerSubmitVerification(this);">
          <!-- Trade License -->
          <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:800;color:#1E293B;margin-bottom:8px;">
              <i class="bi bi-file-earmark-text-fill" style="color:var(--haat-orange);"></i> 1. Trade License (City Corporation / Pourashava / Union Parishad)
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Trade License Number</label>
                <input type="text" name="trade_license_no" value="${esc(docs.trade_license_no || '')}" placeholder="e.g. TRAD/DNCC/028491/2025" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              </div>
              <div>
                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Trade License Document URL / Image</label>
                <input type="text" name="trade_license_doc" value="${esc(docs.trade_license_doc || 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?auto=format&fit=crop&w=800&q=80')}" placeholder="Image URL / PDF Link" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              </div>
            </div>
          </div>

          <!-- National ID (NID) -->
          <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:800;color:#1E293B;margin-bottom:8px;">
              <i class="bi bi-person-vcard-fill" style="color:#2563EB;"></i> 2. Proprietor / Owner National ID (NID / Smart Card)
            </div>
            <div style="margin-bottom:8px;">
              <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">NID Number (10 or 17 Digits)</label>
              <input type="text" name="nid_no" value="${esc(docs.nid_no || '')}" placeholder="e.g. 19892691234567890" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">NID Front Side Document URL</label>
                <input type="text" name="nid_doc_front" value="${esc(docs.nid_doc_front || 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80')}" placeholder="Front image URL" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              </div>
              <div>
                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">NID Back Side Document URL</label>
                <input type="text" name="nid_doc_back" value="${esc(docs.nid_doc_back || 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=800&q=80')}" placeholder="Back image URL" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              </div>
            </div>
          </div>

          <!-- Tax Identification Number (TIN) & Bank Cheque -->
          <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <div style="font-size:13px;font-weight:800;color:#1E293B;margin-bottom:8px;">
                  <i class="bi bi-receipt-cutoff" style="color:#059669;"></i> 3. e-TIN Certificate
                </div>
                <div style="margin-bottom:6px;">
                  <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">12-Digit e-TIN Number</label>
                  <input type="text" name="tin_no" value="${esc(docs.tin_no || '')}" placeholder="e.g. 839201948201" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                </div>
                <div>
                  <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">TIN Document / Certificate URL</label>
                  <input type="text" name="tin_doc" value="${esc(docs.tin_doc || 'https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=800&q=80')}" placeholder="Certificate image URL" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                </div>
              </div>

              <div>
                <div style="font-size:13px;font-weight:800;color:#1E293B;margin-bottom:8px;">
                  <i class="bi bi-bank" style="color:#7C3AED;"></i> 4. Bank Account / Cheque Leaf
                </div>
                <div>
                  <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Bank Cheque Leaf / Statement URL</label>
                  <input type="text" name="bank_doc" value="${esc(docs.bank_doc || 'https://images.unsplash.com/photo-1559526324-4b87b5e36e44?auto=format&fit=crop&w=800&q=80')}" placeholder="Cheque image URL" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                </div>
                <small style="color:var(--text-muted);font-size:10.5px;display:block;margin-top:6px;">Required for payout remittance & settlement verification.</small>
              </div>
            </div>
          </div>

          <!-- Notes / Remarks -->
          <div style="margin-bottom:16px;">
            <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Business Description / Notes for Admin</label>
            <textarea name="notes" rows="2" placeholder="e.g. Registered artisanal handloom weaver cooperative registered in Narayanganj." style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">${esc(docs.notes || '')}</textarea>
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn-village-primary"><i class="bi bi-send-check"></i> Submit Credentials for Admin Approval</button>
          </div>
        </form>
      </div>
    `);
  };

  window.handleSellerSubmitVerification = function (form) {
    const fd = new FormData(form);
    const store = getSellerOwnStore() || state.stores[0];

    store.verification_documents = {
      trade_license_no: (fd.get('trade_license_no') || '').trim(),
      trade_license_doc: (fd.get('trade_license_doc') || '').trim(),
      nid_no: (fd.get('nid_no') || '').trim(),
      nid_doc_front: (fd.get('nid_doc_front') || '').trim(),
      nid_doc_back: (fd.get('nid_doc_back') || '').trim(),
      tin_no: (fd.get('tin_no') || '').trim(),
      tin_doc: (fd.get('tin_doc') || '').trim(),
      bank_doc: (fd.get('bank_doc') || '').trim(),
      notes: (fd.get('notes') || '').trim()
    };

    store.verification_status = 'pending';
    store.verification_submitted_at = new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    delete store.rejection_reason;

    // Send admin notification message
    state.messages.push({
      id: state.messages.length + 1,
      order_id: 1,
      channel_id: `admin_store_${store.id}`,
      sender_id: store.user_id,
      sender_name: store.store_name,
      sender_role: 'seller',
      receiver_id: 4,
      receiver_name: 'Admin',
      receiver_role: 'admin',
      message: `📄 Store Verification Request: "${store.store_name}" has submitted business documents (Trade License: ${store.verification_documents.trade_license_no || 'Uploaded'}, NID, TIN, Bank). Please review and approve under Sellers & Store KYC.`,
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      is_read: 0
    });

    persist();
    closeModal();
    showToast('Verification documents submitted! HAAT Admin will review your credentials for approval.', 'success');
    render();
  };

  window.openEditProductDeliveryModal = function (productId) {
    const p = getProduct(productId);
    if (!p) return;
    const store = getStore(p.store_id) || state.stores[0];
    openModal(`
      <div style="padding:6px;">
        <h3 style="font-size:18px;font-weight:800;margin-bottom:12px;"><i class="bi bi-box-seam" style="color:var(--haat-orange);"></i> Edit Product & Delivery Options</h3>
        <form onsubmit="event.preventDefault(); window.handleSaveProductDelivery(this, ${p.id});">
          <div style="margin-bottom:10px;">
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Product Name</label>
            <input type="text" name="name" value="${esc(p.name)}" required style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
            <div>
              <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Regular Price (৳)</label>
              <input type="number" name="price" value="${p.price}" required style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Sale Price (৳, Optional)</label>
              <input type="number" name="sale_price" value="${p.sale_price || ''}" style="width:100%;padding:9px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
          </div>
          <div style="background:#ECFDF5;border:1px solid #86EFAC;border-radius:8px;padding:12px;margin-bottom:14px;">
            <strong style="font-size:12.5px;color:#15803D;display:block;margin-bottom:8px;"><i class="bi bi-truck"></i> Product Delivery Charge & Free Delivery</strong>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;">
              <div>
                <label style="font-size:11.5px;font-weight:700;display:block;margin-bottom:4px;">Custom Delivery Charge (৳)</label>
                <input type="number" min="0" name="delivery_charge" value="${p.delivery_charge != null ? p.delivery_charge : ''}" placeholder="Default Store (${store.delivery_charge ?? 60})" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
              </div>
              <div style="padding-top:14px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:800;color:#15803D;cursor:pointer;">
                  <input type="checkbox" name="free_delivery" ${p.free_delivery ? 'checked' : ''} style="width:16px;height:16px;">
                  Free Delivery on this Product
                </label>
              </div>
            </div>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn-village-primary"><i class="bi bi-save"></i> Save Product & Delivery</button>
          </div>
        </form>
      </div>
    `);
  };

  window.handleSaveProductDelivery = function (form, productId) {
    const p = getProduct(productId);
    if (!p) return;
    const fd = new FormData(form);
    p.name = fd.get('name');
    p.price = parseFloat(fd.get('price')) || p.price;
    p.sale_price = fd.get('sale_price') ? parseFloat(fd.get('sale_price')) : null;
    const customDeliv = fd.get('delivery_charge');
    p.delivery_charge = customDeliv !== '' && customDeliv != null ? Math.max(0, parseFloat(customDeliv)) : null;
    p.free_delivery = fd.get('free_delivery') ? 1 : 0;
    persist();
    closeModal();
    showToast(`Updated "${p.name}" and its delivery settings!`, 'success');
    render();
  };

  window.adjustStock = function (productId, delta) {
    const inv = state.inventory.find((i) => i.product_id === productId);
    if (inv) {
      inv.quantity = Math.max(0, inv.quantity + delta);
      persist();
      render();
    }
  };

  /* =========================================================================
     SELLER INVENTORY, COUPON & DARAZ ORDER STATUS CONTROLLERS
     ========================================================================= */

  window.openEditInventoryModal = function (productId) {
    const p = getProduct(productId);
    const inv = getInventory(productId);
    if (!p) return;

    openModal(`
      <div style="padding:10px;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid #E2E8F0;padding-bottom:12px;">
          <img src="${esc(p.image_1)}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #CBD5E1;">
          <div>
            <span class="role-badge-tag role-badge-seller" style="font-size:10px;">SKU: ${esc(p.sku)}</span>
            <h3 style="font-size:16px;font-weight:800;color:#1E293B;margin:2px 0;">${esc(p.name)}</h3>
            <span style="font-size:12px;color:var(--text-muted);">Current Available: <strong style="color:${inv.available > 0 ? '#10B981' : '#DC2626'}">${inv.available} units</strong></span>
          </div>
        </div>

        <form onsubmit="event.preventDefault(); window.handleSaveInventory(this, ${productId});">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
            <div>
              <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:5px;">Total Physical Stock Quantity</label>
              <input type="number" id="editInvQty" name="quantity" min="0" value="${inv.quantity}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:14px;font-weight:700;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:5px;">Reserved Stock</label>
              <input type="number" name="reserved_quantity" min="0" value="${inv.reserved_quantity}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:14px;">
            </div>
          </div>

          <div style="margin-bottom:14px;">
            <label style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:5px;">Warehouse Shelf / Bin Location</label>
            <input type="text" name="location" value="${esc(inv.location || 'Shelf A-1')}" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;font-size:13px;">
          </div>

          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn-village-primary"><i class="bi bi-check-lg"></i> Update Inventory Stock</button>
          </div>
        </form>
      </div>
    `);
  };

  window.handleSaveInventory = function (form, productId) {
    const fd = new FormData(form);
    const qty = Math.max(0, parseInt(fd.get('quantity'), 10) || 0);
    const resQty = Math.max(0, parseInt(fd.get('reserved_quantity'), 10) || 0);
    const loc = fd.get('location') || 'Warehouse Shelf A-1';

    let inv = state.inventory.find((i) => i.product_id === productId);
    if (inv) {
      inv.quantity = qty;
      inv.reserved_quantity = resQty;
      inv.location = loc;
    } else {
      state.inventory.push({ id: Date.now(), product_id: productId, quantity: qty, reserved_quantity: resQty, location: loc });
    }

    persist();
    closeModal();
    showToast('Inventory stock updated successfully!', 'success');
    render();
  };

  window.openAddCouponModal = function () {
    const store = getSellerOwnStore() || state.stores[0];
    openModal(`
      <div style="padding:10px;">
        <h3 style="font-size:17px;font-weight:800;color:#1E293B;margin:0 0 12px 0;">Create Store Coupon (${esc(store.store_name)})</h3>
        <form onsubmit="event.preventDefault(); window.handleSaveCoupon(this);">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
              <label style="font-size:12px;font-weight:700;">Coupon Code</label>
              <input type="text" name="code" placeholder="e.g. ABC25" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;text-transform:uppercase;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;">Discount Type</label>
              <select name="discount_type" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
                <option value="percent">Percentage Off (%)</option>
                <option value="fixed">Fixed Amount Off (৳)</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
              <label style="font-size:12px;font-weight:700;">Discount Value</label>
              <input type="number" step="0.01" min="1" name="discount_value" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;">Minimum Order Amount (৳)</label>
              <input type="number" min="0" name="min_order" value="1000" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
              <label style="font-size:12px;font-weight:700;">Max Discount Cap (৳)</label>
              <input type="number" min="0" name="max_discount" placeholder="Optional" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;">Expiry Date</label>
              <input type="date" name="expiry_date" value="2026-12-31" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
          </div>
          <input type="hidden" name="usage_limit" value="100">
          <input type="hidden" name="is_active" value="1">
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn-village-primary">Create Store Coupon</button>
          </div>
        </form>
      </div>
    `);
  };

  window.openEditCouponModal = function (couponId) {
    const c = state.coupons.find((x) => x.id === couponId);
    if (!c) return;
    openModal(`
      <div style="padding:10px;">
        <h3 style="font-size:17px;font-weight:800;color:#1E293B;margin:0 0 12px 0;">Edit Coupon: ${esc(c.code)}</h3>
        <form onsubmit="event.preventDefault(); window.handleSaveCoupon(this, ${c.id});">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
              <label style="font-size:12px;font-weight:700;">Coupon Code</label>
              <input type="text" name="code" value="${esc(c.code)}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;text-transform:uppercase;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;">Discount Type</label>
              <select name="discount_type" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
                <option value="percent" ${c.discount_type === 'percent' ? 'selected' : ''}>Percentage Off (%)</option>
                <option value="fixed" ${c.discount_type === 'fixed' ? 'selected' : ''}>Fixed Amount Off (৳)</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
              <label style="font-size:12px;font-weight:700;">Discount Value</label>
              <input type="number" step="0.01" min="1" name="discount_value" value="${c.discount_value}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;">Minimum Order Amount (৳)</label>
              <input type="number" min="0" name="min_order" value="${c.min_order}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
              <label style="font-size:12px;font-weight:700;">Max Discount Cap (৳)</label>
              <input type="number" min="0" name="max_discount" value="${c.max_discount || ''}" style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:700;">Expiry Date</label>
              <input type="date" name="expiry_date" value="${c.expiry_date || '2026-12-31'}" required style="width:100%;padding:10px;border:1.5px solid #CBD5E1;border-radius:6px;">
            </div>
          </div>
          <input type="hidden" name="usage_limit" value="${c.usage_limit || 100}">
          <input type="hidden" name="is_active" value="1">
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn-village-primary">Save Changes</button>
          </div>
        </form>
      </div>
    `);
  };

  window.handleSaveCoupon = function (form, existingCouponId = null) {
    const fd = new FormData(form);
    const code = (fd.get('code') || '').trim().toUpperCase();
    const discountType = fd.get('discount_type');
    const discountValue = parseFloat(fd.get('discount_value')) || 0;
    const minOrder = parseFloat(fd.get('min_order')) || 0;
    const maxDiscount = fd.get('max_discount') ? parseFloat(fd.get('max_discount')) : null;
    const expiryDate = fd.get('expiry_date');
    const usageLimit = parseInt(fd.get('usage_limit'), 10) || 100;
    const isActive = fd.get('is_active') ? 1 : 0;
    const store = getSellerOwnStore() || state.stores[0];

    if (existingCouponId) {
      const c = state.coupons.find((x) => x.id === existingCouponId);
      if (c) {
        c.code = code;
        c.discount_type = discountType;
        c.discount_value = discountValue;
        c.min_order = minOrder;
        c.max_discount = maxDiscount;
        c.expiry_date = expiryDate;
        c.usage_limit = usageLimit;
        c.is_active = isActive;
      }
      showToast(`Coupon "${code}" updated!`, 'success');
    } else {
      state.coupons.push({
        id: Date.now(),
        store_id: store.id,
        category_id: null,
        subcategory_id: null,
        title: `${store.store_name} Voucher`,
        code,
        discount_type: discountType,
        discount_value: discountValue,
        min_order: minOrder,
        max_discount: maxDiscount,
        expiry_date: expiryDate,
        usage_limit: usageLimit,
        used_count: 0,
        is_active: isActive
      });
      showToast(`Coupon "${code}" created!`, 'success');
    }

    persist();
    closeModal();
    render();
  };

  window.toggleCouponStatus = function (couponId) {
    const c = state.coupons.find((x) => x.id === couponId);
    if (c) {
      c.is_active = c.is_active ? 0 : 1;
      persist();
      showToast(`Coupon ${c.code} is now ${c.is_active ? 'Active' : 'Inactive'}.`, 'info');
      render();
    }
  };

  window.deleteCoupon = function (couponId) {
    const c = state.coupons.find((x) => x.id === couponId);
    if (!c) return;
    state.coupons = state.coupons.filter((x) => x.id !== couponId);
    persist();
    showToast(`Coupon "${c.code}" deleted.`, 'info');
    render();
  };

  window.updateSellerOrderStatus = function (sellerOrderId, newStatus) {
    const sId = Number(sellerOrderId);
    if (!newStatus) {
      const selectEl = document.getElementById(`sellerStatusSelect_${sId}`);
      if (selectEl) newStatus = selectEl.value;
    }
    if (!newStatus) {
      showToast('Please select a valid order status.', 'warning');
      return;
    }

    const allowedSellerStatuses = ['pending', 'order_accepted', 'processing', 'packaged'];
    if (!allowedSellerStatuses.includes(newStatus)) {
      showToast('Merchants can only update orders up to "Packaged". Remaining transit is managed by HATEX Logistics.', 'warning');
      return;
    }

    const store = getSellerOwnStore() || state.stores[0];
    let orderFound = false;

    state.orders.forEach((o) => {
      const so = (o.seller_orders || []).find((s) => s.id === sId);
      if (so) {
        orderFound = true;
        const hatexStages = ['packaged', 'reached_hub', 'assigned_to_rider', 'in_transit', 'out_for_delivery', 'delivered'];
        if (hatexStages.includes(so.status) && so.status !== newStatus) {
          showToast('This package has already been handed over to HATEX Logistics and cannot be altered by merchant.', 'info');
          return;
        }

        so.status = newStatus;
        if (!Array.isArray(so.tracking)) so.tracking = [];

        const statusDescriptions = {
          pending: 'Order pending merchant confirmation.',
          order_accepted: 'Order accepted by merchant.',
          processing: 'Merchant is preparing and packing items.',
          packaged: 'Items packaged by merchant. Package handed over to HATEX Logistics for hub collection and delivery.'
        };

        so.tracking.push({
          id: Date.now(),
          status: newStatus,
          location: `${store.store_name}, ${store.district || 'Dhaka'}`,
          latitude: store.latitude,
          longitude: store.longitude,
          note: statusDescriptions[newStatus] || `Merchant updated order status to "${newStatus.replace(/_/g, ' ').toUpperCase()}".`,
          updated_by: store.store_name,
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        if (newStatus === 'packaged') {
          const allPackaged = (o.seller_orders || []).every((s) =>
            ['packaged', 'ready_to_ship', 'reached_hub', 'assigned_to_rider', 'in_transit', 'out_for_delivery', 'delivered'].includes(s.status)
          );
          if (allPackaged) {
            o.order_status = 'packaged';
          }
        } else if (newStatus === 'processing' || newStatus === 'order_accepted') {
          if (o.order_status === 'pending') {
            o.order_status = newStatus;
          }
        }

        window.addNotification({
          title: `Order #${o.order_number} Update`,
          message: `${store.store_name} marked items as "${newStatus.replace(/_/g, ' ')}"`,
          type: 'order',
          target_role: 'customer',
          order_id: o.id,
          link: `#/order-tracking?orderId=${o.id}`
        });

        if (newStatus === 'packaged') {
          window.addNotification({
            title: `Package Ready for HATEX Pickup`,
            message: `${store.store_name} has packaged Order #${o.order_number} (Package #${so.package_id || so.seller_order_number}). Ready for HATEX pickup.`,
            type: 'order',
            target_role: 'hatex',
            order_id: o.id,
            link: `#/dash/hatex`
          });
        }

        if (window.haatApiSync && typeof window.haatApiSync.saveOrder === 'function') {
          window.haatApiSync.saveOrder(o);
        }
      }
    });

    if (!orderFound) {
      showToast('Order not found.', 'danger');
      return;
    }

    persist();
    showToast(`Order status updated to "${newStatus.replace(/_/g, ' ').toUpperCase()}"!`, 'success');
    render();
  };

  window.previewProductPhoto = function (input, num) {
    const previewContainer = document.getElementById(`prodPreview_${num}`);
    if (!previewContainer) return;
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        previewContainer.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
      };
      reader.readAsDataURL(input.files[0]);
    }
  };

  window.openAddProductModal = function () {
    const store = getSellerOwnStore() || state.stores[0];
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:14px;"><i class="bi bi-plus-circle"></i> Add New Product to ${esc(store.store_name)}</h3>
      <form onsubmit="event.preventDefault(); window.handleAddProduct(this);" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;">Product Name</label>
            <input type="text" name="name" placeholder="e.g. Classic Handloom Jamdani Panjabi" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;">SKU Code</label>
            <input type="text" name="sku" value="PRD-${Date.now().toString().slice(-5)}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;font-family:var(--font-mono);">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;">Regular Price (৳)</label>
            <input type="number" name="price" value="1800" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;">Sale Price (৳)</label>
            <input type="number" name="sale_price" value="1500" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;">Subcategory</label>
            <select name="subcategory_id" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
              ${state.categories
                .map((cat) =>
                  cat.subcategories.map((sub) => `<option value="${sub.id}">${esc(cat.name)} → ${esc(sub.name)}</option>`).join('')
                )
                .join('')}
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;">Initial Stock</label>
            <input type="number" name="stock" value="25" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
        </div>
        <div style="background:#ECFDF5;border:1px solid #86EFAC;border-radius:8px;padding:10px;margin-bottom:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;">
          <div>
            <label style="font-size:11.5px;font-weight:700;">Delivery Charge (৳)</label>
            <input type="number" min="0" name="delivery_charge" value="${store.delivery_charge ?? 60}" style="width:100%;padding:7px;border:1px solid #CBD5E1;border-radius:4px;">
          </div>
          <div style="padding-top:14px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:800;color:#15803D;cursor:pointer;">
              <input type="checkbox" name="free_delivery"> Free Delivery Option
            </label>
          </div>
        </div>

        <!-- 3-Photo Upload Section (Stored as Binary MEDIUMBLOB in MySQL) -->
        <div style="margin-bottom:14px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px;">
          <label style="font-size:12px;font-weight:800;color:#1E293B;display:block;margin-bottom:4px;">
            <i class="bi bi-images" style="color:var(--haat-orange);"></i> Product Photos (Up to 3 Photos • Saved as Binary BLOB in MySQL)
          </label>
          <small style="color:var(--text-muted);font-size:11px;display:block;margin-bottom:10px;">Select image files from your computer to store directly in the database table.</small>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
            <div style="border:1.5px dashed #CBD5E1;border-radius:6px;padding:8px;background:#fff;text-align:center;">
              <span style="font-size:11px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">1. Main Photo</span>
              <input type="file" name="image_1" accept="image/*" onchange="window.previewProductPhoto(this, 1)" style="font-size:10px;width:100%;">
              <div id="prodPreview_1" style="margin-top:6px;height:65px;border-radius:4px;overflow:hidden;background:#F1F5F9;display:grid;place-items:center;border:1px solid #E2E8F0;">
                <span style="font-size:10px;color:#94A3B8;"><i class="bi bi-card-image"></i> Photo 1</span>
              </div>
            </div>
            <div style="border:1.5px dashed #CBD5E1;border-radius:6px;padding:8px;background:#fff;text-align:center;">
              <span style="font-size:11px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">2. Angle View</span>
              <input type="file" name="image_2" accept="image/*" onchange="window.previewProductPhoto(this, 2)" style="font-size:10px;width:100%;">
              <div id="prodPreview_2" style="margin-top:6px;height:65px;border-radius:4px;overflow:hidden;background:#F1F5F9;display:grid;place-items:center;border:1px solid #E2E8F0;">
                <span style="font-size:10px;color:#94A3B8;"><i class="bi bi-card-image"></i> Photo 2</span>
              </div>
            </div>
            <div style="border:1.5px dashed #CBD5E1;border-radius:6px;padding:8px;background:#fff;text-align:center;">
              <span style="font-size:11px;font-weight:700;color:#334155;display:block;margin-bottom:4px;">3. Detail / Packaging</span>
              <input type="file" name="image_3" accept="image/*" onchange="window.previewProductPhoto(this, 3)" style="font-size:10px;width:100%;">
              <div id="prodPreview_3" style="margin-top:6px;height:65px;border-radius:4px;overflow:hidden;background:#F1F5F9;display:grid;place-items:center;border:1px solid #E2E8F0;">
                <span style="font-size:10px;color:#94A3B8;"><i class="bi bi-card-image"></i> Photo 3</span>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-village-primary" style="width:100%;padding:10px;font-size:13.5px;font-weight:700;">
          <i class="bi bi-cloud-arrow-up-fill"></i> Upload & Create Product
        </button>
      </form>
    `);
  };

  window.handleAddProduct = function (form) {
    const fd = new FormData(form);
    const store = getSellerOwnStore() || state.stores[0];
    const newId = state.products.reduce((m, p) => Math.max(m, p.id), 0) + 1;
    const name = fd.get('name');

    // Default placeholder fallback or generated preview
    const preview1 = document.querySelector('#prodPreview_1 img')?.src || 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?auto=format&fit=crop&w=800&q=80';
    const preview2 = document.querySelector('#prodPreview_2 img')?.src || '';
    const preview3 = document.querySelector('#prodPreview_3 img')?.src || '';

    const newProd = {
      id: newId,
      store_id: store.id,
      subcategory_id: Number(fd.get('subcategory_id') || 101),
      brand_id: 1,
      collection_id: 1,
      name: name,
      slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
      sku: fd.get('sku'),
      description: 'Verified merchant product in HAAT catalogue.',
      price: parseFloat(fd.get('price')),
      sale_price: parseFloat(fd.get('sale_price')) || null,
      delivery_charge: fd.get('delivery_charge') !== '' ? parseFloat(fd.get('delivery_charge')) : null,
      free_delivery: fd.get('free_delivery') ? 1 : 0,
      is_flash_sale: 0,
      image_1: preview1,
      image_2: preview2,
      image_3: preview3,
      variant_1_name: 'Color',
      variant_1_value: 'Standard',
      is_featured: 1,
      rating: 5.0,
      reviews_count: 1
    };
    state.products.unshift(newProd);
    state.inventory.push({
      id: state.inventory.length + 1,
      product_id: newId,
      quantity: parseInt(fd.get('stock') || 25),
      reserved_quantity: 0,
      location: 'Shelf A-1'
    });
    persist();
    closeModal();
    showToast('New product added with 3 photos and delivery configuration!', 'success');
    render();
  };

  /* =========================================================================
     17. PLATFORM ADMIN DASHBOARD (`#/dash/admin`)
     ========================================================================= */

  function renderAdminDashView(subTab = 'overview') {
    const pendingVerifications = state.stores.filter((s) => s.verification_status === 'pending');
    const pendingPayments = state.orders.filter((o) => o.payment && o.payment.status === 'submitted');
    const totalMarketplaceRevenue = state.orders.reduce((s, o) => s + (Number(o.grand_total) || 0), 0);
    const totalDeliveryRevenue = state.orders.reduce((s, o) => s + (Number(o.shipping_cost) || 0), 0);
    const totalDiscountGiven = state.orders.reduce((s, o) => s + (Number(o.discount_amount) || 0), 0);
    const adminUnreadMsgs = state.messages.filter((m) => m.receiver_role === 'admin' && !m.is_read).length;
    const openReportsCount = (state.reports || []).filter((r) => r.status !== 'resolved').length;
    const reportFilter = state.adminReportRoleFilter || 'all';

    return `
      <div class="container">
        <div class="dash-shell">
          <!-- Admin Sidebar (Left) -->
          <aside class="dash-nav-card">
            <div class="dash-nav-header">
              <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;">ADMIN ACCOUNT</div>
              <strong style="font-size:16px;color:#1E293B;display:block;margin-top:2px;">Admin</strong>
            </div>
            <a class="dash-nav-item ${subTab === 'overview' ? 'active' : ''}" onclick="location.hash='#/dash/admin/overview'">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="dash-nav-item ${subTab === 'analysis' ? 'active' : ''}" onclick="location.hash='#/dash/admin/analysis'">
              <i class="bi bi-graph-up-arrow"></i> Analysis
            </a>
            <a class="dash-nav-item ${subTab === 'sellers' ? 'active' : ''}" onclick="location.hash='#/dash/admin/sellers'">
              <i class="bi bi-shop"></i> Sellers & Store KYC ${pendingVerifications.length ? `<span style="background:#D97706;color:#fff;font-size:9.5px;padding:1px 6px;border-radius:10px;margin-left:auto;font-weight:700;">${pendingVerifications.length} KYC</span>` : ''}
            </a>
            <a class="dash-nav-item ${subTab === 'products' ? 'active' : ''}" onclick="location.hash='#/dash/admin/products'">
              <i class="bi bi-box-seam"></i> Products & Categories
            </a>
            <a class="dash-nav-item ${subTab === 'customers' ? 'active' : ''}" onclick="location.hash='#/dash/admin/customers'">
              <i class="bi bi-people"></i> Customers ${pendingPayments.length ? `<span style="background:#2563EB;color:#fff;font-size:9.5px;padding:1px 6px;border-radius:10px;margin-left:auto;">${pendingPayments.length}</span>` : ''}
            </a>
            <a class="dash-nav-item ${subTab === 'riders' ? 'active' : ''}" onclick="location.hash='#/dash/admin/riders'">
              <i class="bi bi-bicycle"></i> Riders & HATEX
            </a>
            <a class="dash-nav-item ${subTab === 'coupons' ? 'active' : ''}" onclick="location.hash='#/dash/admin/coupons'">
              <i class="bi bi-ticket-perforated"></i> Coupons
            </a>
            <a class="dash-nav-item ${subTab === 'campaigns' ? 'active' : ''}" onclick="location.hash='#/dash/admin/campaigns'">
              <i class="bi bi-megaphone"></i> Campaigns (Flash / Free Del.)
            </a>
            <a class="dash-nav-item ${subTab === 'banners' ? 'active' : ''}" onclick="location.hash='#/dash/admin/banners'">
              <i class="bi bi-images"></i> Banner Slider
            </a>
            <a class="dash-nav-item ${subTab === 'reports' ? 'active' : ''}" onclick="location.hash='#/dash/admin/reports'" style="display:flex;align-items:center;justify-content:space-between;">
              <span><i class="bi bi-flag"></i> Reports</span>
              ${openReportsCount > 0 ? `<span style="background:#DC2626;color:#fff;font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:10px;">${openReportsCount}</span>` : ''}
            </a>
            <a class="dash-nav-item ${subTab === 'messages' ? 'active' : ''}" onclick="location.hash='#/dash/admin/messages'" style="display:flex;align-items:center;justify-content:space-between;">
              <span><i class="bi bi-chat-dots"></i> Messages</span>
              ${adminUnreadMsgs > 0 ? `<span style="background:#DC2626;color:#fff;font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:10px;">${adminUnreadMsgs}</span>` : ''}
            </a>
            <a class="dash-nav-item ${subTab === 'settings' ? 'active' : ''}" onclick="location.hash='#/dash/admin/settings'">
              <i class="bi bi-sliders"></i> App Settings & Performance
            </a>
          </aside>

          <!-- Admin Right Workspace -->
          <main>
            ${
              subTab === 'analysis'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;">Platform Analytics & Financial Performance</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:20px;">Real-time GMV breakdown, store performance, delivery economics, and campaign conversion metrics.</p>

                <div class="kpi-row" style="margin-bottom:20px;">
                  <div class="kpi-stat-card">
                    <div class="kpi-stat-content">
                      <h3>${money(totalMarketplaceRevenue)}</h3>
                      <p>Total Gross Merchandise Value</p>
                    </div>
                    <div class="kpi-stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-cash-coin"></i></div>
                  </div>
                  <div class="kpi-stat-card">
                    <div class="kpi-stat-content">
                      <h3>${money(totalDeliveryRevenue)}</h3>
                      <p>Total Delivery Fees Collected</p>
                    </div>
                    <div class="kpi-stat-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-truck"></i></div>
                  </div>
                  <div class="kpi-stat-card">
                    <div class="kpi-stat-content">
                      <h3>${money(totalDiscountGiven)}</h3>
                      <p>Voucher Discounts Redeemed</p>
                    </div>
                    <div class="kpi-stat-icon" style="background:#FFF7ED;color:#EA580C;"><i class="bi bi-ticket-perforated"></i></div>
                  </div>
                </div>

                <h3 style="font-size:15px;font-weight:800;margin-bottom:12px;">Revenue & Order Volume by Merchant Store</h3>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Store</th>
                        <th>Products Listed</th>
                        <th>Sub-Orders</th>
                        <th>Store Revenue</th>
                        <th>Delivery Mode</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${state.stores
                        .map((st) => {
                          const prodCount = state.products.filter((p) => p.store_id === st.id).length;
                          let soCount = 0;
                          let stRev = 0;
                          state.orders.forEach((o) => {
                            (o.seller_orders || []).forEach((so) => {
                              if (so.store_id === st.id) {
                                soCount++;
                                stRev += Number(so.subtotal) || 0;
                              }
                            });
                          });
                          return `
                            <tr>
                              <td><strong>${esc(st.store_name)}</strong><div style="font-size:11px;color:var(--text-muted);">${esc(st.district)}</div></td>
                              <td>${prodCount} items</td>
                              <td>${soCount} orders</td>
                              <td><strong style="color:var(--haat-primary);">${money(stRev)}</strong></td>
                              <td>${st.free_delivery ? '<span class="delivery-pill free">Free Delivery</span>' : `<span class="delivery-pill paid">৳${st.delivery_charge ?? 60}</span>`}</td>
                            </tr>
                          `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'sellers'
                ? (() => {
                    state.adminStoreFilter = state.adminStoreFilter || 'all';
                    const curFilter = state.adminStoreFilter;

                    const pendingList = state.stores.filter((s) => s.verification_status === 'pending');
                    const verifiedList = state.stores.filter((s) => s.verification_status === 'verified');
                    const unverifiedList = state.stores.filter(
                      (s) => s.verification_status !== 'verified' && s.verification_status !== 'pending'
                    );

                    const filteredStores = state.stores.filter((s) => {
                      if (curFilter === 'pending') return s.verification_status === 'pending';
                      if (curFilter === 'verified') return s.verification_status === 'verified';
                      if (curFilter === 'unverified')
                        return s.verification_status !== 'verified' && s.verification_status !== 'pending';
                      return true;
                    });

                    return `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;color:#1E293B;">Merchant Stores & Business Document Verification</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Sellers open stores immediately without needing prior admin approval. Inspect uploaded business credentials (Trade License, NID, TIN, Bank Documents) to officially verify and badge stores.</p>
                  </div>
                </div>

                <!-- Filter Controls -->
                <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
                  <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;font-weight:700;background:${curFilter === 'all' ? 'var(--haat-primary)' : '#F1F5F9'};color:${curFilter === 'all' ? '#fff' : '#334155'};" onclick="window.setAdminStoreFilter('all')">
                    All Stores (${state.stores.length})
                  </button>
                  <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;font-weight:700;background:${curFilter === 'pending' ? '#D97706' : '#FEF3C7'};color:${curFilter === 'pending' ? '#fff' : '#B45309'};border:1px solid #FCD34D;" onclick="window.setAdminStoreFilter('pending')">
                    <i class="bi bi-hourglass-split"></i> Pending Verification (${pendingList.length})
                  </button>
                  <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;font-weight:700;background:${curFilter === 'verified' ? '#059669' : '#ECFDF5'};color:${curFilter === 'verified' ? '#fff' : '#059669'};border:1px solid #A7F3D0;" onclick="window.setAdminStoreFilter('verified')">
                    <i class="bi bi-patch-check-fill"></i> Verified Stores (${verifiedList.length})
                  </button>
                  <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;font-weight:700;background:${curFilter === 'unverified' ? '#64748B' : '#F8FAFC'};color:${curFilter === 'unverified' ? '#fff' : '#64748B'};" onclick="window.setAdminStoreFilter('unverified')">
                    Unverified (${unverifiedList.length})
                  </button>
                </div>

                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Store & Merchant</th>
                        <th>Location & Delivery</th>
                        <th>Store Status</th>
                        <th>Verification Status</th>
                        <th>Uploaded Credentials</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${
                        filteredStores.length
                          ? filteredStores
                              .map((s) => {
                                const docs = s.verification_documents || {};
                                const hasTrade = !!docs.trade_license_no;
                                const hasNid = !!docs.nid_no;
                                const hasTin = !!docs.tin_no;
                                const hasBank = !!docs.bank_doc;
                                const totalDocs = [hasTrade, hasNid, hasTin, hasBank].filter(Boolean).length;

                                return `
                        <tr>
                          <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                              <div class="user-avatar" style="width:38px;height:38px;background:${s.primary_color || '#1E4332'};color:#fff;font-size:13px;font-weight:800;flex-shrink:0;">
                                ${esc(s.logo_text || s.store_name.slice(0, 3).toUpperCase())}
                              </div>
                              <div>
                                <strong style="font-size:13.5px;color:#1E293B;display:flex;align-items:center;gap:4px;">
                                  ${esc(s.store_name)}
                                  ${s.verification_status === 'verified' ? '<i class="bi bi-patch-check-fill" style="color:#059669;font-size:14px;" title="Officially Verified Store"></i>' : ''}
                                </strong>
                                <div style="font-size:11px;color:var(--text-muted);">
                                  Slug: <code>${esc(s.store_slug)}</code>
                                </div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <div style="font-weight:600;font-size:12px;">${esc(s.district)}, ${esc(s.division)}</div>
                            <div style="margin-top:3px;">
                              ${
                                s.free_delivery
                                  ? '<span class="delivery-pill free" style="font-size:10.5px;"><i class="bi bi-truck"></i> Free Shipping</span>'
                                  : `<span class="delivery-pill paid" style="font-size:10.5px;"><i class="bi bi-truck"></i> ৳${s.delivery_charge ?? 60} Delivery</span>`
                              }
                            </div>
                          </td>
                          <td>
                            <span class="status-badge active" style="font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                              <i class="bi bi-check-circle-fill"></i> ${s.is_published ? 'Active & Live' : 'Unpublished'}
                            </span>
                          </td>
                          <td>
                            ${
                              s.verification_status === 'verified'
                                ? `<span class="status-badge delivered" style="font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                                    <i class="bi bi-patch-check-fill"></i> Verified
                                  </span>
                                  <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Approved</div>`
                                : s.verification_status === 'pending'
                                ? `<span class="status-badge pending" style="background:#FEF3C7;color:#B45309;font-weight:700;font-size:11px;display:inline-flex;align-items:center;gap:4px;border:1px solid #FCD34D;">
                                    <i class="bi bi-hourglass-split"></i> Pending KYC
                                  </span>
                                  <div style="font-size:10px;color:#B45309;font-weight:600;margin-top:2px;">Ready for Review</div>`
                                : s.verification_status === 'rejected'
                                ? `<span class="status-badge cancelled" style="font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                                    <i class="bi bi-x-circle-fill"></i> Rejected
                                  </span>
                                  <div style="font-size:10px;color:#DC2626;margin-top:2px;">Needs Re-upload</div>`
                                : `<span class="status-badge" style="background:#F1F5F9;color:#64748B;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
                                    <i class="bi bi-shield"></i> Unverified
                                  </span>
                                  <div style="font-size:10px;color:var(--text-muted);margin-top:2px;">Store Open</div>`
                            }
                          </td>
                          <td>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;max-width:200px;">
                              <span style="font-size:10.5px;padding:2px 6px;border-radius:4px;border:1px solid ${hasTrade ? '#86EFAC' : '#E2E8F0'};background:${hasTrade ? '#F0FDF4' : '#F8FAFC'};color:${hasTrade ? '#166534' : '#94A3B8'};">
                                <i class="bi ${hasTrade ? 'bi-check2' : 'bi-dash'}"></i> Trade Lic
                              </span>
                              <span style="font-size:10.5px;padding:2px 6px;border-radius:4px;border:1px solid ${hasNid ? '#86EFAC' : '#E2E8F0'};background:${hasNid ? '#F0FDF4' : '#F8FAFC'};color:${hasNid ? '#166534' : '#94A3B8'};">
                                <i class="bi ${hasNid ? 'bi-check2' : 'bi-dash'}"></i> NID
                              </span>
                              <span style="font-size:10.5px;padding:2px 6px;border-radius:4px;border:1px solid ${hasTin ? '#86EFAC' : '#E2E8F0'};background:${hasTin ? '#F0FDF4' : '#F8FAFC'};color:${hasTin ? '#166534' : '#94A3B8'};">
                                <i class="bi ${hasTin ? 'bi-check2' : 'bi-dash'}"></i> TIN
                              </span>
                              <span style="font-size:10.5px;padding:2px 6px;border-radius:4px;border:1px solid ${hasBank ? '#86EFAC' : '#E2E8F0'};background:${hasBank ? '#F0FDF4' : '#F8FAFC'};color:${hasBank ? '#166534' : '#94A3B8'};">
                                <i class="bi ${hasBank ? 'bi-check2' : 'bi-dash'}"></i> Bank
                              </span>
                            </div>
                            <div style="font-size:10px;color:var(--text-muted);margin-top:3px;">${totalDocs} of 4 credentials</div>
                          </td>
                          <td style="white-space:nowrap;">
                            <div style="display:flex;gap:6px;align-items:center;">
                              ${
                                s.verification_status === 'pending'
                                  ? `<button class="btn-village-primary" style="padding:5px 11px;font-size:11px;background:#D97706;border-color:#D97706;" onclick="window.openAdminVerifyStoreModal(${s.id})">
                                      <i class="bi bi-file-earmark-check"></i> Review & Verify
                                    </button>`
                                  : s.verification_status === 'verified'
                                  ? `<button class="btn-village-outline" style="padding:5px 10px;font-size:11px;color:#059669;border-color:#059669;" onclick="window.openAdminVerifyStoreModal(${s.id})">
                                      <i class="bi bi-patch-check-fill"></i> View Docs
                                    </button>`
                                  : `<button class="btn-secondary" style="padding:5px 10px;font-size:11px;" onclick="window.openAdminVerifyStoreModal(${s.id})">
                                      <i class="bi bi-file-earmark"></i> Inspect KYC
                                    </button>`
                              }
                              <button class="btn-secondary" style="padding:5px 8px;font-size:11px;" title="Edit Store Settings" onclick="window.openAdminEditStoreModal(${s.id})">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button class="btn-secondary" style="padding:5px 8px;font-size:11px;" title="${s.is_published ? 'Unpublish Store' : 'Publish Store'}" onclick="window.toggleStorePublish(${s.id})">
                                ${s.is_published ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>'}
                              </button>
                            </div>
                          </td>
                        </tr>
                      `;
                              })
                              .join('')
                          : `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">No stores found under selected filter.</td></tr>`
                      }
                    </tbody>
                  </table>
                </div>
              </div>
            `;
                  })()
                : subTab === 'products'
                ? (() => {
                    state.adminProductSearch = state.adminProductSearch || '';
                    state.adminProductStoreFilter = state.adminProductStoreFilter || 'all';
                    state.adminProductCatFilter = state.adminProductCatFilter || 'all';

                    const filteredProducts = state.products.filter((p) => {
                      if (state.adminProductSearch) {
                        const q = state.adminProductSearch.toLowerCase();
                        if (!p.name.toLowerCase().includes(q) && !(p.sku || '').toLowerCase().includes(q)) return false;
                      }
                      if (state.adminProductStoreFilter && state.adminProductStoreFilter !== 'all') {
                        if (Number(p.store_id) !== Number(state.adminProductStoreFilter)) return false;
                      }
                      if (state.adminProductCatFilter && state.adminProductCatFilter !== 'all') {
                        const cat = getCategoryBySubId(p.subcategory_id);
                        if (!cat || Number(cat.id) !== Number(state.adminProductCatFilter)) return false;
                      }
                      return true;
                    });

                    return `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;color:#1E293B;">Category & Subcategory Hierarchy</h2>
                    <p style="font-size:12px;color:var(--text-muted);">Add, edit, and delete categories and subcategories. Used for marketplace catalogue, coupons, and campaigns.</p>
                  </div>
                  <button class="btn-village-primary" style="font-size:12px;padding:7px 14px;" onclick="window.openAdminAddCategoryModal()">
                    <i class="bi bi-folder-plus"></i> + Add New Category
                  </button>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:14px;">
                  ${state.categories
                    .map(
                      (c) => `
                    <div style="border:1px solid #E2E8F0;border-radius:10px;padding:14px;background:#F8FAFC;display:flex;flex-direction:column;justify-content:space-between;">
                      <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #E2E8F0;">
                          <div style="display:flex;align-items:center;gap:8px;">
                            <i class="bi ${c.icon}" style="font-size:18px;color:var(--haat-orange);"></i>
                            <div>
                              <strong style="font-size:14px;color:#1E293B;">${esc(c.name)}</strong>
                              <span style="font-size:10.5px;color:var(--text-muted);display:block;">${(c.subcategories || []).length} subcategories</span>
                            </div>
                          </div>
                          <div style="display:flex;gap:4px;">
                            <button type="button" class="btn-secondary" style="padding:3px 7px;font-size:11px;" title="Add Subcategory to ${esc(c.name)}" onclick="window.openAdminAddSubcategoryModal(${c.id})"><i class="bi bi-plus-lg"></i> Sub</button>
                            <button type="button" class="btn-secondary" style="padding:3px 7px;font-size:11px;" title="Edit Category" onclick="window.openAdminEditCategoryModal(${c.id})"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn-secondary" style="padding:3px 7px;font-size:11px;color:#DC2626;" title="Delete Category" onclick="window.deleteCategoryAdmin(${c.id})"><i class="bi bi-trash"></i></button>
                          </div>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;min-height:30px;">
                          ${(c.subcategories || [])
                            .map(
                              (sub) => `
                            <span style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;background:#fff;border:1px solid #CBD5E1;padding:3px 8px;border-radius:6px;color:#334155;">
                              <span>${esc(sub.name)}</span>
                              <button type="button" style="border:none;background:transparent;cursor:pointer;padding:0;color:#64748B;" title="Edit Subcategory" onclick="window.openAdminEditSubcategoryModal(${c.id}, ${sub.id})"><i class="bi bi-pencil" style="font-size:10px;"></i></button>
                              <button type="button" style="border:none;background:transparent;cursor:pointer;padding:0;color:#DC2626;" title="Delete Subcategory" onclick="window.deleteSubcategoryAdmin(${c.id}, ${sub.id})"><i class="bi bi-x-lg" style="font-size:10px;"></i></button>
                            </span>
                          `
                            )
                            .join('')}
                          ${!(c.subcategories || []).length ? '<span style="font-size:11px;color:var(--text-muted);font-style:italic;">No subcategories. Click + Sub to add one.</span>' : ''}
                        </div>
                      </div>
                    </div>
                  `
                    )
                    .join('')}
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;color:#1E293B;">All Marketplace Products (${filteredProducts.length} of ${state.products.length})</h2>
                    <p style="font-size:12px;color:var(--text-muted);">Admin can add products, edit details/pricing, toggle flash sale, and manage inventory.</p>
                  </div>
                  <button class="btn-village-primary" style="font-size:12px;padding:7px 14px;" onclick="window.openAdminAddProductModal()">
                    <i class="bi bi-plus-lg"></i> + Add New Product
                  </button>
                </div>

                <!-- Search & Filter Controls -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:10px;margin-bottom:16px;background:#F8FAFC;padding:12px;border-radius:8px;border:1px solid #E2E8F0;">
                  <div>
                    <input type="text" placeholder="Search product name or SKU..." value="${esc(state.adminProductSearch || '')}" oninput="window.setAdminProductSearch(this.value)" style="width:100%;padding:7px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                  </div>
                  <div>
                    <select onchange="window.setAdminProductStoreFilter(this.value)" style="width:100%;padding:7px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                      <option value="all" ${state.adminProductStoreFilter === 'all' || !state.adminProductStoreFilter ? 'selected' : ''}>All Merchant Stores</option>
                      ${state.stores.map((st) => `<option value="${st.id}" ${String(state.adminProductStoreFilter) === String(st.id) ? 'selected' : ''}>${esc(st.store_name)}</option>`).join('')}
                    </select>
                  </div>
                  <div>
                    <select onchange="window.setAdminProductCatFilter(this.value)" style="width:100%;padding:7px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                      <option value="all" ${state.adminProductCatFilter === 'all' || !state.adminProductCatFilter ? 'selected' : ''}>All Categories</option>
                      ${state.categories.map((c) => `<option value="${c.id}" ${String(state.adminProductCatFilter) === String(c.id) ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                    </select>
                  </div>
                </div>

                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th>Store</th>
                        <th>Category / Subcategory</th>
                        <th>Price</th>
                        <th>Delivery</th>
                        <th>Flash Sale</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${
                        filteredProducts.length
                          ? filteredProducts
                              .map((p) => {
                                const st = getStore(p.store_id);
                                const cat = getCategoryBySubId(p.subcategory_id);
                                const sub = cat?.subcategories?.find((s) => s.id === p.subcategory_id);
                                const delInfo = getProductDeliveryInfo(p);
                                return `
                              <tr>
                                <td>
                                  <div style="display:flex;align-items:center;gap:10px;">
                                    <img src="${esc(p.image_1 || 'panjabi.jpg')}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #E2E8F0;">
                                    <div>
                                      <strong>${esc(p.name)}</strong>
                                      <div style="font-size:11px;color:var(--text-muted);">SKU: ${esc(p.sku)}</div>
                                    </div>
                                  </div>
                                </td>
                                <td><strong>${esc(st?.store_name || 'Store')}</strong></td>
                                <td>${esc(cat?.name || '')} &rarr; ${esc(sub?.name || '')}</td>
                                <td><strong>${money(getEffectiveProductPrice(p))}</strong></td>
                                <td><span class="delivery-pill ${delInfo.isFree ? 'free' : 'paid'}">${esc(delInfo.label)}</span></td>
                                <td>
                                  <button class="btn-secondary" style="padding:3px 8px;font-size:11px;background:${p.is_flash_sale ? '#FEF2F2' : '#F1F5F9'};color:${p.is_flash_sale ? '#DC2626' : '#475569'};" onclick="window.toggleProductFlashSale(${p.id})">
                                    ${p.is_flash_sale ? '⚡ Flash Active' : 'Normal'}
                                  </button>
                                </td>
                                <td style="display:flex;gap:6px;">
                                  <button class="btn-secondary" style="padding:3px 8px;font-size:11px;" title="Edit Product" onclick="window.openAdminEditProductModal(${p.id})"><i class="bi bi-pencil"></i> Edit</button>
                                  <button class="btn-secondary" style="padding:3px 8px;font-size:11px;color:#DC2626;" title="Delete Product" onclick="window.deleteProductAdmin(${p.id})"><i class="bi bi-trash"></i></button>
                                </td>
                              </tr>
                            `;
                              })
                              .join('')
                          : '<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">No products match filter criteria.</td></tr>'
                      }
                    </tbody>
                  </table>
                </div>
              </div>
            `;
                  })()
                : subTab === 'customers'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:20px;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:12px;">Registered Customers</h2>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Orders Placed</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${state.users
                        .filter((u) => u.role === 'customer')
                        .map((u) => {
                          const ordCount = state.orders.filter((o) => o.user_id === u.id).length;
                          return `
                            <tr>
                              <td><strong>${esc(u.name)}</strong></td>
                              <td>${esc(u.email)}</td>
                              <td>${esc(u.phone)}</td>
                              <td>${ordCount} orders</td>
                              <td><span class="status-badge active">${esc(u.status || 'active')}</span></td>
                            </tr>
                          `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:4px;">Customer Orders & Payment Verification</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:16px;">Inspect COD and digital payments, delivery charges, and verify submitted TrxIDs.</p>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Method</th>
                        <th>Items + Delivery</th>
                        <th>Grand Total</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${state.orders
                        .map(
                          (o) => `
                        <tr>
                          <td><strong>${esc(o.order_number)}</strong></td>
                          <td>${esc(o.shipping_name)}</td>
                          <td><span class="pay-badge ${o.payment.method}">${o.payment.method.toUpperCase()}</span></td>
                          <td>${money(o.total_amount)} + ${o.shipping_cost === 0 ? '<span style="color:#16A34A;font-weight:700;">FREE</span>' : money(o.shipping_cost)}</td>
                          <td><strong>${money(o.grand_total)}</strong></td>
                          <td><span class="status-badge ${o.payment.status === 'verified' ? 'verified' : 'submitted'}">${o.payment.status}</span></td>
                          <td>
                            ${
                              o.payment.status !== 'verified'
                                ? `<button class="btn-village-primary" style="padding:4px 8px;font-size:11px;" onclick="window.verifyPayment(${o.id})">Verify Payment</button>`
                                : '<span style="color:#10B981;font-size:11px;font-weight:700;"><i class="bi bi-check-circle"></i> Verified</span>'
                            }
                          </td>
                        </tr>
                      `
                        )
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'riders'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">HATEX Logistics & Riders Fleet</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Manage delivery riders, operational zones, and active assignments.</p>
                  </div>
                  <button class="btn-village-primary" style="font-size:12px;padding:8px 14px;" onclick="window.openAddRiderModal()">
                    <i class="bi bi-plus-lg"></i> Add New Rider
                  </button>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Rider Name</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Zone</th>
                        <th>Assigned Parcels</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${state.riders
                        .map((r) => {
                          let assignedCount = 0;
                          state.orders.forEach((o) => {
                            (o.seller_orders || []).forEach((so) => {
                              if (so.assigned_rider_id === r.id) assignedCount++;
                            });
                          });
                          return `
                            <tr>
                              <td><strong>${esc(r.name)}</strong></td>
                              <td>${esc(r.phone)}</td>
                              <td>${esc(r.vehicle || 'Motorcycle')}</td>
                              <td>${esc(r.zone || 'Dhaka Metro')}</td>
                              <td><span class="haversine-pill">${assignedCount} parcels</span></td>
                              <td>
                                <button class="btn-secondary" style="padding:3px 8px;font-size:11px;" onclick="window.toggleRiderStatus(${r.id})">
                                  <span class="status-badge ${r.status === 'active' ? 'active' : 'pending'}">${esc(r.status)}</span>
                                </button>
                              </td>
                            </tr>
                          `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'coupons'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">Admin Category & Subcategory Coupons</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Admin coupons apply exclusively to an Admin-selected Category or Subcategory. Customers collect vouchers to apply instant discounts during checkout.</p>
                  </div>
                  <button class="btn-village-primary" style="font-size:12px;padding:8px 14px;" onclick="window.openAdminCouponModal()">
                    <i class="bi bi-plus-lg"></i> Add Category/Subcategory Coupon
                  </button>
                </div>

                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Min Order</th>
                        <th>Target Scope (Category / Subcategory / Store)</th>
                        <th>Issuer</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${state.coupons
                        .map(
                          (c) => `
                        <tr>
                          <td><strong style="font-family:var(--font-mono);color:var(--haat-orange);">${esc(c.code)}</strong><div style="font-size:11px;color:var(--text-muted);">${esc(c.title || '')}</div></td>
                          <td><strong>${c.discount_type === 'percent' ? `${c.discount_value}% OFF` : `৳${c.discount_value} OFF`}</strong></td>
                          <td>${money(c.min_order)}</td>
                          <td><span class="haversine-pill">${esc(getCouponScopeLabel(c))}</span></td>
                          <td><span class="role-badge-tag ${c.created_by === 'seller' ? 'role-badge-seller' : 'role-badge-admin'}">${esc(c.created_by || 'admin')}</span></td>
                          <td><span class="status-badge ${c.is_active !== 0 ? 'active' : 'pending'}">${c.is_active !== 0 ? 'Active' : 'Paused'}</span></td>
                          <td style="display:flex;gap:6px;">
                            <button class="btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="window.toggleCouponActive(${c.id})">${c.is_active !== 0 ? 'Pause' : 'Activate'}</button>
                            <button class="btn-secondary" style="padding:4px 8px;font-size:11px;color:#DC2626;" onclick="window.deleteCoupon(${c.id})"><i class="bi bi-trash"></i></button>
                          </td>
                        </tr>
                      `
                        )
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'campaigns'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">Admin Campaigns (Category/Subcategory Free Delivery & Flash Sale Discounts)</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Launch Free Delivery or Flash Discount campaigns for selected Categories or Subcategories (applies automatically across matching products).</p>
                  </div>
                  <button class="btn-village-primary" style="font-size:12px;padding:8px 14px;" onclick="window.openAdminCampaignModal()">
                    <i class="bi bi-megaphone-fill"></i> Launch New Campaign
                  </button>
                </div>

                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Campaign Title</th>
                        <th>Type</th>
                        <th>Target Category / Subcategory</th>
                        <th>Benefit</th>
                        <th>Min Spend</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${(state.campaigns || [])
                        .map((camp) => {
                          let scopeName = 'Selected Scope';
                          if (camp.scope_type === 'subcategory' && camp.subcategory_id) {
                            const cat = getCategoryBySubId(camp.subcategory_id);
                            const sub = cat?.subcategories?.find((s) => Number(s.id) === Number(camp.subcategory_id));
                            scopeName = `Subcategory: ${sub ? sub.name : camp.subcategory_id}`;
                          } else if (camp.category_id) {
                            const cat = state.categories.find((x) => Number(x.id) === Number(camp.category_id));
                            scopeName = `Category: ${cat ? cat.name : camp.category_id}`;
                          }
                          return `
                            <tr>
                              <td><strong>${esc(camp.name)}</strong><div style="font-size:11px;color:var(--text-muted);">Badge: ${esc(camp.badge_text || '')}</div></td>
                              <td>
                                ${
                                  camp.type === 'free_delivery'
                                    ? '<span class="delivery-pill free"><i class="bi bi-truck"></i> Free Delivery Campaign</span>'
                                    : '<span class="delivery-pill" style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;"><i class="bi bi-lightning-charge-fill"></i> Flash Discount Campaign</span>'
                                }
                              </td>
                              <td><span class="haversine-pill">${esc(scopeName)}</span></td>
                              <td><strong>${camp.type === 'free_delivery' ? '100% Free Shipping' : `${camp.discount_percent || 10}% Extra Off`}</strong></td>
                              <td>${money(camp.min_order || 0)}</td>
                              <td><span class="status-badge ${camp.is_active ? 'active' : 'pending'}">${camp.is_active ? 'Running' : 'Paused'}</span></td>
                              <td style="display:flex;gap:6px;">
                                <button class="btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="window.toggleCampaignActive(${camp.id})">${camp.is_active ? 'Pause' : 'Activate'}</button>
                                <button class="btn-secondary" style="padding:4px 8px;font-size:11px;color:#DC2626;" onclick="window.deleteCampaign(${camp.id})"><i class="bi bi-trash"></i></button>
                              </td>
                            </tr>
                          `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'banners'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">Homepage Multi-Slide Banner Carousel Manager</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Add, edit, or toggle Mega Sale & Campaign banner slides. The homepage slider automatically cycles through active slides.</p>
                  </div>
                  <div style="display:flex;gap:8px;align-items:center;">
                    <button class="btn-village-primary" style="font-size:12px;padding:8px 14px;" onclick="window.openBannerSlideModal()">
                      <i class="bi bi-plus-lg"></i> Add New Banner Slide
                    </button>
                  </div>
                </div>

                <!-- Auto-slide speed control -->
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                  <div style="font-size:13px;font-weight:700;color:#1E293B;">
                    <i class="bi bi-play-circle-fill" style="color:var(--haat-orange);"></i> Auto-Slide Configuration
                  </div>
                  <div style="display:flex;align-items:center;gap:12px;">
                    <label style="font-size:12px;display:flex;align-items:center;gap:6px;cursor:pointer;">
                      <input type="checkbox" ${state.appSettings?.banner_auto_slide !== false ? 'checked' : ''} onchange="window.updateBannerAutoSlide(this.checked)">
                      Enable Automatic Sliding
                    </label>
                    <select onchange="window.updateBannerInterval(this.value)" style="padding:5px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
                      <option value="3000" ${Number(state.appSettings?.banner_interval_ms) === 3000 ? 'selected' : ''}>Every 3.0 Seconds</option>
                      <option value="4500" ${!state.appSettings?.banner_interval_ms || Number(state.appSettings?.banner_interval_ms) === 4500 ? 'selected' : ''}>Every 4.5 Seconds</option>
                      <option value="6000" ${Number(state.appSettings?.banner_interval_ms) === 6000 ? 'selected' : ''}>Every 6.0 Seconds</option>
                    </select>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:16px;">
                  ${(state.banners || [])
                    .map(
                      (b, idx) => `
                    <div style="border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;background:#fff;box-shadow:var(--shadow-xs);">
                      <div style="height:140px;position:relative;background:url('${esc(b.image)}') center/cover no-repeat;">
                        <div style="position:absolute;inset:0;background:${b.bg_gradient || 'rgba(0,0,0,0.5)'};padding:14px;color:#fff;display:flex;flex-direction:column;justify-content:flex-end;">
                          <span style="font-size:10px;font-weight:800;background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:999px;width:fit-content;margin-bottom:4px;">Slide #${idx + 1} • ${esc(b.badge || 'CAMPAIGN')}</span>
                          <strong style="font-size:15px;line-height:1.2;">${esc(b.title)}</strong>
                        </div>
                      </div>
                      <div style="padding:12px 14px;">
                        <p style="font-size:12px;color:#475569;margin-bottom:10px;min-height:34px;">${esc(b.subtitle)}</p>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                          <span class="status-badge ${b.is_active ? 'active' : 'pending'}">${b.is_active ? 'Active in Slider' : 'Hidden'}</span>
                          <div style="display:flex;gap:6px;">
                            <button class="btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="window.openBannerSlideModal(${b.id})"><i class="bi bi-pencil"></i> Edit</button>
                            <button class="btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="window.toggleBannerSlide(${b.id})">${b.is_active ? 'Hide' : 'Show'}</button>
                            <button class="btn-secondary" style="padding:4px 8px;font-size:11px;color:#DC2626;" onclick="window.deleteBannerSlide(${b.id})"><i class="bi bi-trash"></i></button>
                          </div>
                        </div>
                      </div>
                    </div>
                  `
                    )
                    .join('')}
                </div>
              </div>
            `
                : subTab === 'reports'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">Dynamic Incident Reports Center</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Review and resolve reports submitted by Customers, Sellers, Riders, and HATEX.</p>
                  </div>
                  <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    ${['all', 'customer', 'seller', 'rider', 'hatex']
                      .map(
                        (rf) => `
                      <button type="button" class="btn-secondary" style="padding:5px 12px;font-size:11.5px;background:${reportFilter === rf ? 'var(--haat-primary)' : '#F1F5F9'};color:${reportFilter === rf ? '#fff' : '#334155'};" onclick="window.setAdminReportFilter('${rf}')">
                        ${rf === 'all' ? 'All Roles' : rf.toUpperCase()}
                      </button>
                    `
                      )
                      .join('')}
                  </div>
                </div>

                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Reporter Role & Name</th>
                        <th>Category & Reference</th>
                        <th>Subject & Details</th>
                        <th>Priority</th>
                        <th>Status / Resolution</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${(state.reports || [])
                        .filter((r) => reportFilter === 'all' || r.reporter_role === reportFilter)
                        .map(
                          (r) => `
                        <tr>
                          <td><code>#${r.id}</code><div style="font-size:10px;color:var(--text-muted);">${esc(r.created_at)}</div></td>
                          <td>
                            <span class="role-badge-tag role-badge-${r.reporter_role}">${esc(r.reporter_role.toUpperCase())}</span>
                            <div style="font-weight:700;font-size:12px;margin-top:3px;">${esc(r.reporter_name)}</div>
                          </td>
                          <td>
                            <div style="font-weight:700;font-size:12px;">${esc(r.category)}</div>
                            ${r.target_ref ? `<code style="font-size:11px;color:var(--haat-orange);">${esc(r.target_ref)}</code>` : ''}
                          </td>
                          <td style="max-width:280px;">
                            <div style="font-weight:800;color:#1E293B;font-size:12.5px;">${esc(r.subject)}</div>
                            <div style="font-size:11.5px;color:#475569;margin-top:2px;">${esc(r.details)}</div>
                            ${r.admin_response ? `<div style="margin-top:6px;padding:6px 8px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;font-size:11px;color:#166534;"><strong>Admin Note:</strong> ${esc(r.admin_response)}</div>` : ''}
                          </td>
                          <td><span class="status-badge ${r.priority === 'Critical' || r.priority === 'High' ? 'cancelled' : 'pending'}">${esc(r.priority)}</span></td>
                          <td><span class="status-badge ${r.status === 'resolved' ? 'delivered' : 'pending'}">${esc(r.status)}</span></td>
                          <td>
                            <button class="btn-village-primary" style="padding:4px 10px;font-size:11px;" onclick="window.openResolveReportModal(${r.id})">
                              ${r.status === 'resolved' ? 'Update Note' : 'Resolve'}
                            </button>
                          </td>
                        </tr>
                      `
                        )
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'messages'
                ? renderRoleMessagingPanel('admin')
                : subTab === 'settings'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;">App Settings & Platform Performance Configuration</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">Dynamically control global marketplace parameters, default delivery rates, and slider behavior.</p>

                <form onsubmit="event.preventDefault(); window.handleSaveAdminAppSettings(this);" style="max-width:640px;">
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Platform Name</label>
                      <input type="text" name="platform_name" value="${esc(state.appSettings?.platform_name || 'HAAT Digital Marketplace')}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Support Helpline</label>
                      <input type="text" name="support_phone" value="${esc(state.appSettings?.support_phone || '+880 9612-345678')}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Default Base Delivery Charge (৳)</label>
                      <input type="number" name="default_delivery_charge" value="${state.appSettings?.default_delivery_charge ?? 60}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Marketplace Commission (%)</label>
                      <input type="number" name="commission_rate" value="${state.appSettings?.commission_rate ?? 5}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Banner Slide Interval (ms)</label>
                      <input type="number" step="500" name="banner_interval_ms" value="${state.appSettings?.banner_interval_ms ?? 4500}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div style="padding-top:22px;">
                      <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;cursor:pointer;">
                        <input type="checkbox" name="banner_auto_slide" ${state.appSettings?.banner_auto_slide !== false ? 'checked' : ''}>
                        Enable Automatic Banner Carousel
                      </label>
                    </div>
                  </div>
                  <button type="submit" class="btn-village-primary" style="padding:10px 20px;">Save Global App Settings</button>
                </form>
              </div>
            `
                : `
              <!-- Admin Overview (Default Dashboard) -->
              <div class="kpi-row">
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${money(totalMarketplaceRevenue)}</h3>
                    <p>Total Platform GMV</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-graph-up-arrow"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${state.stores.length}</h3>
                    <p>Merchant Stores</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#FFF7ED;color:#EA580C;"><i class="bi bi-shop"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${state.orders.length}</h3>
                    <p>Customer Orders</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-receipt"></i></div>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;margin-bottom:20px;">
                <div class="page-card" style="background:#fff;padding:20px;border-radius:10px;border:1px solid #E2E8F0;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h3 style="font-size:15px;font-weight:800;">Active Banner Slides (${(state.banners || []).filter((b) => b.is_active).length})</h3>
                    <button class="btn-secondary" style="font-size:11px;padding:4px 10px;" onclick="location.hash='#/dash/admin/banners'">Manage Slider &rarr;</button>
                  </div>
                  <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Interactive multi-slide hero banner carousel controlled by Admin.</p>
                  <div style="display:flex;flex-direction:column;gap:6px;">
                    ${(state.banners || []).slice(0, 3).map((b) => `<div style="font-size:12px;padding:6px 10px;background:#F8FAFC;border-radius:6px;border:1px solid #E2E8F0;display:flex;justify-content:space-between;"><strong>${esc(b.title)}</strong><span class="status-badge ${b.is_active ? 'active' : 'pending'}">${b.is_active ? 'Live' : 'Hidden'}</span></div>`).join('')}
                  </div>
                </div>

                <div class="page-card" style="background:#fff;padding:20px;border-radius:10px;border:1px solid #E2E8F0;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h3 style="font-size:15px;font-weight:800;">Recent Incident Reports (${openReportsCount} Open)</h3>
                    <button class="btn-secondary" style="font-size:11px;padding:4px 10px;" onclick="location.hash='#/dash/admin/reports'">View All &rarr;</button>
                  </div>
                  <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Reports submitted by Customers, Sellers, Riders, and HATEX.</p>
                  <div style="display:flex;flex-direction:column;gap:6px;">
                    ${(state.reports || []).slice(0, 3).map((r) => `<div style="font-size:12px;padding:6px 10px;background:#F8FAFC;border-radius:6px;border:1px solid #E2E8F0;display:flex;justify-content:space-between;"><span><strong>[${esc(r.reporter_role.toUpperCase())}]</strong> ${esc(r.subject)}</span><span class="status-badge ${r.status === 'resolved' ? 'delivered' : 'pending'}">${esc(r.status)}</span></div>`).join('')}
                  </div>
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                  <h3 style="font-size:16px;font-weight:800;">Active HATEX Fleet</h3>
                  <button class="btn-secondary" style="font-size:11px;padding:4px 10px;" onclick="location.hash='#/dash/admin/riders'">Manage Riders &rarr;</button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:12px;">
                  ${state.riders
                    .map(
                      (r) => `
                    <div style="padding:12px 14px;border:1px solid #E2E8F0;border-radius:8px;">
                      <div style="display:flex;justify-content:space-between;align-items:center;">
                        <strong>${esc(r.name)}</strong>
                        <span class="status-badge active">${esc(r.status)}</span>
                      </div>
                      <div style="font-size:12px;color:var(--text-muted);margin:4px 0;">Phone: ${esc(r.phone)} • ${esc(r.vehicle || 'Motorcycle')}</div>
                      <div style="font-size:11.5px;color:var(--haat-primary);font-weight:700;">
                        Zone: ${esc(r.zone || 'Dhaka Metro')}
                      </div>
                    </div>
                  `
                    )
                    .join('')}
                </div>
              </div>
            `
            }
          </main>
        </div>
      </div>
    `;
  }

  window.setAdminStoreFilter = function (filterKey) {
    state.adminStoreFilter = filterKey;
    render();
  };

  window.openAdminVerifyStoreModal = function (storeId) {
    const s = getStore(storeId);
    if (!s) return;
    const owner = state.users.find((u) => u.id === s.user_id) || {
      name: 'Merchant Partner',
      phone: '01700000000',
      email: 'merchant@haat.com.bd'
    };
    const docs = s.verification_documents || {};

    openModal(`
      <div style="padding:6px;max-height:82vh;overflow-y:auto;">
        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;border-bottom:1px solid #F1F5F9;padding-bottom:12px;flex-wrap:wrap;gap:10px;">
          <div style="display:flex;align-items:center;gap:12px;">
            <div class="user-avatar" style="width:44px;height:44px;background:${s.primary_color || '#1E4332'};color:#fff;font-size:15px;font-weight:800;">
              ${esc(s.logo_text || s.store_name.slice(0, 3).toUpperCase())}
            </div>
            <div>
              <h3 style="font-size:19px;font-weight:800;margin:0;color:#1E293B;display:flex;align-items:center;gap:6px;">
                ${esc(s.store_name)}
                ${s.verification_status === 'verified' ? '<i class="bi bi-patch-check-fill" style="color:#059669;font-size:17px;"></i>' : ''}
              </h3>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                Owner: <strong>${esc(owner.name)}</strong> (${esc(owner.phone)}) • ${esc(owner.email)}
              </div>
            </div>
          </div>
          <div>
            ${
              s.verification_status === 'verified'
                ? `<span style="background:#ECFDF5;color:#059669;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;border:1px solid #A7F3D0;"><i class="bi bi-patch-check-fill"></i> Officially Verified Merchant</span>`
                : s.verification_status === 'pending'
                ? `<span style="background:#FEF3C7;color:#B45309;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;border:1px solid #FCD34D;"><i class="bi bi-hourglass-split"></i> Awaiting Admin Verification</span>`
                : s.verification_status === 'rejected'
                ? `<span style="background:#FEF2F2;color:#DC2626;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;border:1px solid #FECACA;"><i class="bi bi-x-circle-fill"></i> Documents Rejected</span>`
                : `<span style="background:#F1F5F9;color:#64748B;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px;"><i class="bi bi-shield"></i> No Verification Submitted</span>`
            }
          </div>
        </div>

        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;">
          <div><strong>Registered Address:</strong> ${esc(s.address)}, ${esc(s.district)}, ${esc(s.division)}</div>
          <div><strong>Store Status:</strong> <span class="status-badge active" style="font-size:10.5px;">Active & Open</span></div>
          <div><strong>Submitted:</strong> ${esc(s.verification_submitted_at || (s.verification_status === 'verified' ? s.verified_at : 'Not yet submitted'))}</div>
        </div>

        ${
          s.rejection_reason && s.verification_status === 'rejected'
            ? `
          <div style="margin-bottom:14px;padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;font-size:12px;color:#991B1B;">
            <strong>Previous Rejection Reason:</strong> ${esc(s.rejection_reason)}
          </div>
        `
            : ''
        }

        <!-- 4 Document Credentials Grid -->
        <h4 style="font-size:14px;font-weight:800;color:#1E293B;margin:0 0 10px 0;"><i class="bi bi-folder-check" style="color:var(--haat-orange);"></i> Submitted Business Credentials</h4>
        
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:12px;margin-bottom:16px;">
          <!-- 1. Trade License -->
          <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;background:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <strong style="font-size:12.5px;color:#1E293B;"><i class="bi bi-file-earmark-text"></i> Trade License</strong>
              ${docs.trade_license_no ? '<span style="color:#059669;font-size:11px;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Uploaded</span>' : '<span style="color:#94A3B8;font-size:11px;">Missing</span>'}
            </div>
            <div style="font-size:11.5px;color:#475569;margin-bottom:8px;">License No: <code>${esc(docs.trade_license_no || 'N/A')}</code></div>
            ${
              docs.trade_license_doc
                ? `<div style="border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;position:relative;height:120px;background:#000;">
                    <img src="${esc(docs.trade_license_doc)}" alt="Trade License" style="width:100%;height:100%;object-fit:cover;opacity:0.9;">
                    <a href="${esc(docs.trade_license_doc)}" target="_blank" style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.7);color:#fff;font-size:10px;padding:3px 8px;border-radius:4px;text-decoration:none;"><i class="bi bi-arrows-fullscreen"></i> View Full</a>
                  </div>`
                : '<div style="height:120px;display:grid;place-items:center;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;color:#94A3B8;font-size:12px;">No document image</div>'
            }
          </div>

          <!-- 2. National ID (NID) -->
          <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;background:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <strong style="font-size:12.5px;color:#1E293B;"><i class="bi bi-person-vcard"></i> National ID (NID)</strong>
              ${docs.nid_no ? '<span style="color:#059669;font-size:11px;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Uploaded</span>' : '<span style="color:#94A3B8;font-size:11px;">Missing</span>'}
            </div>
            <div style="font-size:11.5px;color:#475569;margin-bottom:8px;">NID No: <code>${esc(docs.nid_no || 'N/A')}</code></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
              ${
                docs.nid_doc_front
                  ? `<div style="border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;position:relative;height:120px;background:#000;">
                      <img src="${esc(docs.nid_doc_front)}" alt="NID Front" style="width:100%;height:100%;object-fit:cover;">
                      <span style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.6);color:#fff;font-size:9px;padding:2px 5px;border-radius:3px;">Front</span>
                    </div>`
                  : '<div style="height:120px;display:grid;place-items:center;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;color:#94A3B8;font-size:11px;">Front missing</div>'
              }
              ${
                docs.nid_doc_back
                  ? `<div style="border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;position:relative;height:120px;background:#000;">
                      <img src="${esc(docs.nid_doc_back)}" alt="NID Back" style="width:100%;height:100%;object-fit:cover;">
                      <span style="position:absolute;top:4px;left:4px;background:rgba(0,0,0,0.6);color:#fff;font-size:9px;padding:2px 5px;border-radius:3px;">Back</span>
                    </div>`
                  : '<div style="height:120px;display:grid;place-items:center;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;color:#94A3B8;font-size:11px;">Back missing</div>'
              }
            </div>
          </div>

          <!-- 3. e-TIN Certificate -->
          <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;background:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <strong style="font-size:12.5px;color:#1E293B;"><i class="bi bi-receipt"></i> TIN Certificate</strong>
              ${docs.tin_no ? '<span style="color:#059669;font-size:11px;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Uploaded</span>' : '<span style="color:#94A3B8;font-size:11px;">Optional</span>'}
            </div>
            <div style="font-size:11.5px;color:#475569;margin-bottom:8px;">TIN No: <code>${esc(docs.tin_no || 'N/A')}</code></div>
            ${
              docs.tin_doc
                ? `<div style="border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;position:relative;height:120px;background:#000;">
                    <img src="${esc(docs.tin_doc)}" alt="TIN Document" style="width:100%;height:100%;object-fit:cover;">
                    <a href="${esc(docs.tin_doc)}" target="_blank" style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.7);color:#fff;font-size:10px;padding:3px 8px;border-radius:4px;text-decoration:none;"><i class="bi bi-arrows-fullscreen"></i> View</a>
                  </div>`
                : '<div style="height:120px;display:grid;place-items:center;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;color:#94A3B8;font-size:12px;">No TIN image</div>'
            }
          </div>

          <!-- 4. Bank Account / Cheque Leaf -->
          <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;background:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <strong style="font-size:12.5px;color:#1E293B;"><i class="bi bi-bank"></i> Bank Cheque / Proof</strong>
              ${docs.bank_doc ? '<span style="color:#059669;font-size:11px;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Uploaded</span>' : '<span style="color:#94A3B8;font-size:11px;">Missing</span>'}
            </div>
            <div style="font-size:11.5px;color:#475569;margin-bottom:8px;">Official Settlement Cheque / Statement</div>
            ${
              docs.bank_doc
                ? `<div style="border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;position:relative;height:120px;background:#000;">
                    <img src="${esc(docs.bank_doc)}" alt="Bank Document" style="width:100%;height:100%;object-fit:cover;">
                    <a href="${esc(docs.bank_doc)}" target="_blank" style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.7);color:#fff;font-size:10px;padding:3px 8px;border-radius:4px;text-decoration:none;"><i class="bi bi-arrows-fullscreen"></i> View</a>
                  </div>`
                : '<div style="height:120px;display:grid;place-items:center;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;color:#94A3B8;font-size:12px;">No bank image</div>'
            }
          </div>
        </div>

        ${docs.notes ? `<div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:10px 12px;font-size:12px;margin-bottom:16px;"><strong>Merchant Notes:</strong> ${esc(docs.notes)}</div>` : ''}

        <!-- Decision Box -->
        <div style="background:#F1F5F9;border-radius:8px;padding:14px;border:1px solid #E2E8F0;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;color:#1E293B;">Admin Verification Remark / Rejection Reason</label>
          <input type="text" id="adminVerificationRemark" placeholder="e.g. Valid trade license and NID confirmed. Or specify reason if rejecting." style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:12.5px;margin-bottom:12px;">
          
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
              <button type="button" class="btn-secondary" onclick="closeModal()">Close Window</button>
            </div>
            <div style="display:flex;gap:8px;">
              <button type="button" class="btn-secondary" style="color:#DC2626;border-color:#FCA5A5;" onclick="window.adminRejectStoreVerification(${s.id})">
                <i class="bi bi-x-circle"></i> Reject Documents
              </button>
              <button type="button" class="btn-village-primary" style="background:#059669;border-color:#059669;" onclick="window.adminApproveStoreVerification(${s.id})">
                <i class="bi bi-patch-check-fill"></i> Approve & Verify Store
              </button>
              ${
                s.verification_status === 'verified'
                  ? `<button type="button" class="btn-secondary" style="font-size:11px;" onclick="window.adminRevokeStoreVerification(${s.id})">Revoke Verification</button>`
                  : ''
              }
            </div>
          </div>
        </div>
      </div>
    `);
  };

  window.adminApproveStoreVerification = function (storeId) {
    const s = getStore(storeId);
    if (!s) return;
    const remark = ($('#adminVerificationRemark')?.value || '').trim();

    s.verification_status = 'verified';
    s.verified_at = new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    delete s.rejection_reason;

    // Send congratulatory system message to merchant
    state.messages.push({
      id: state.messages.length + 1,
      order_id: 1,
      channel_id: `admin_store_${s.id}`,
      sender_id: 4,
      sender_name: 'Admin',
      sender_role: 'admin',
      receiver_id: s.user_id,
      receiver_name: s.store_name,
      receiver_role: 'seller',
      message: `🎉 Official Verification Approved! Your store "${s.store_name}" has been verified by HAAT Admin. ${remark ? `Admin note: "${remark}". ` : ''}The Verified Merchant trust badge is now displayed across your storefront and products.`,
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      is_read: 0
    });

    persist();
    closeModal();
    showToast(`Store "${s.store_name}" is now officially verified!`, 'success');
    render();
  };

  window.adminRejectStoreVerification = function (storeId) {
    const s = getStore(storeId);
    if (!s) return;
    const remark = ($('#adminVerificationRemark')?.value || '').trim() || 'Uploaded documents could not be verified. Please provide current trade license and clear NID photos.';

    s.verification_status = 'rejected';
    s.rejection_reason = remark;

    // Send notification to seller
    state.messages.push({
      id: state.messages.length + 1,
      order_id: 1,
      channel_id: `admin_store_${s.id}`,
      sender_id: 4,
      sender_name: 'Admin',
      sender_role: 'admin',
      receiver_id: s.user_id,
      receiver_name: s.store_name,
      receiver_role: 'seller',
      message: `⚠️ Store Verification Update: Your documents could not be verified at this time. Reason: "${remark}". Please update and re-submit your documents in Store Settings.`,
      created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      is_read: 0
    });

    persist();
    closeModal();
    showToast(`Store verification rejected. Reason sent to seller.`, 'warning');
    render();
  };

  window.adminRevokeStoreVerification = function (storeId) {
    const s = getStore(storeId);
    if (!s) return;
    s.verification_status = 'unverified';
    delete s.verified_at;
    persist();
    closeModal();
    showToast(`Verification revoked for "${s.store_name}".`, 'info');
    render();
  };

  // Backward-compatible alias
  window.approveStore = window.adminApproveStoreVerification;

  window.toggleStorePublish = function (storeId) {
    const s = state.stores.find((x) => x.id === storeId);
    if (s) {
      s.is_published = s.is_published ? 0 : 1;
      persist();
      render();
    }
  };

  window.openAdminEditStoreModal = function (storeId) {
    const s = getStore(storeId);
    if (!s) return;
    openModal(`
      <h3 style="font-size:17px;font-weight:800;margin-bottom:12px;"><i class="bi bi-shop"></i> Edit Merchant Store Settings</h3>
      <form onsubmit="event.preventDefault(); window.handleAdminSaveStore(this, ${s.id});">
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Store Name</label>
          <input type="text" name="store_name" value="${esc(s.store_name)}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Delivery Charge (৳)</label>
            <input type="number" min="0" name="delivery_charge" value="${s.delivery_charge ?? 60}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
          <div style="padding-top:20px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer;">
              <input type="checkbox" name="free_delivery" ${s.free_delivery ? 'checked' : ''}> Free Delivery Enabled
            </label>
          </div>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Save Store</button>
      </form>
    `);
  };

  window.handleAdminSaveStore = function (form, storeId) {
    const s = getStore(storeId);
    if (!s) return;
    const fd = new FormData(form);
    s.store_name = fd.get('store_name') || s.store_name;
    s.delivery_charge = Math.max(0, parseFloat(fd.get('delivery_charge') || 0));
    s.free_delivery = fd.get('free_delivery') ? 1 : 0;
    persist();
    closeModal();
    showToast('Merchant store updated by Admin.', 'success');
    render();
  };

  window.verifyPayment = function (orderId) {
    const o = state.orders.find((x) => x.id === orderId);
    if (o) {
      o.payment.status = 'verified';
      persist();
      showToast(`Payment for order #${o.order_number} verified!`, 'success');
      render();
    }
  };

  window.toggleProductFlashSale = function (prodId) {
    const p = getProduct(prodId);
    if (p) {
      p.is_flash_sale = p.is_flash_sale ? 0 : 1;
      persist();
      showToast(`Flash Sale ${p.is_flash_sale ? 'enabled' : 'disabled'} for ${p.name}!`, 'success');
      render();
    }
  };

  window.deleteProductAdmin = function (prodId) {
    state.products = state.products.filter((p) => p.id !== prodId);
    persist();
    showToast('Product removed from catalogue.', 'info');
    render();
  };

  // Admin Category & Subcategory Management
  window.openAdminAddCategoryModal = function () {
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;"><i class="bi bi-folder-plus" style="color:var(--haat-orange);"></i> Add New Category</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Create a new top-level category for the marketplace.</p>
      <form onsubmit="event.preventDefault(); window.handleAdminAddCategory(this);">
        <div style="margin-bottom:12px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Category Name</label>
          <input type="text" name="name" placeholder="e.g. Organic Groceries & Farm Foods" required style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Icon</label>
          <select name="icon" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
            <option value="bi-grid">Grid / General (bi-grid)</option>
            <option value="bi-gem">Fashion & Jewelry (bi-gem)</option>
            <option value="bi-phone">Electronics & Gadgets (bi-phone)</option>
            <option value="bi-basket">Food & Grocery (bi-basket)</option>
            <option value="bi-palette">Crafts & Art (bi-palette)</option>
            <option value="bi-laptop">Computers & Tech (bi-laptop)</option>
            <option value="bi-cup-hot">Beverages & Cafe (bi-cup-hot)</option>
            <option value="bi-flower1">Beauty & Fragrance (bi-flower1)</option>
            <option value="bi-house-heart">Home & Living (bi-house-heart)</option>
            <option value="bi-tag">Promotions & Tags (bi-tag)</option>
          </select>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Create Category</button>
      </form>
    `);
  };

  window.handleAdminAddCategory = function (form) {
    const fd = new FormData(form);
    const name = (fd.get('name') || '').trim();
    const icon = (fd.get('icon') || 'bi-grid').trim();
    if (!name) return;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    const newId = state.categories.reduce((m, c) => Math.max(m, c.id), 0) + 1;
    state.categories.push({
      id: newId,
      name,
      slug,
      icon,
      subcategories: []
    });
    persist();
    closeModal();
    showToast(`Category "${name}" created successfully!`, 'success');
    render();
  };

  window.openAdminEditCategoryModal = function (categoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;"><i class="bi bi-pencil-square" style="color:var(--haat-orange);"></i> Edit Category</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Rename category or change its display icon.</p>
      <form onsubmit="event.preventDefault(); window.handleAdminEditCategory(this, ${cat.id});">
        <div style="margin-bottom:12px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Category Name</label>
          <input type="text" name="name" value="${esc(cat.name)}" required style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Icon</label>
          <select name="icon" style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
            <option value="bi-grid" ${cat.icon === 'bi-grid' ? 'selected' : ''}>Grid / General (bi-grid)</option>
            <option value="bi-gem" ${cat.icon === 'bi-gem' ? 'selected' : ''}>Fashion & Jewelry (bi-gem)</option>
            <option value="bi-phone" ${cat.icon === 'bi-phone' ? 'selected' : ''}>Electronics & Gadgets (bi-phone)</option>
            <option value="bi-basket" ${cat.icon === 'bi-basket' ? 'selected' : ''}>Food & Grocery (bi-basket)</option>
            <option value="bi-palette" ${cat.icon === 'bi-palette' ? 'selected' : ''}>Crafts & Art (bi-palette)</option>
            <option value="bi-laptop" ${cat.icon === 'bi-laptop' ? 'selected' : ''}>Computers & Tech (bi-laptop)</option>
            <option value="bi-cup-hot" ${cat.icon === 'bi-cup-hot' ? 'selected' : ''}>Beverages & Cafe (bi-cup-hot)</option>
            <option value="bi-flower1" ${cat.icon === 'bi-flower1' ? 'selected' : ''}>Beauty & Fragrance (bi-flower1)</option>
            <option value="bi-house-heart" ${cat.icon === 'bi-house-heart' ? 'selected' : ''}>Home & Living (bi-house-heart)</option>
            <option value="bi-tag" ${cat.icon === 'bi-tag' ? 'selected' : ''}>Promotions & Tags (bi-tag)</option>
          </select>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Save Changes</button>
      </form>
    `);
  };

  window.handleAdminEditCategory = function (form, categoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    const fd = new FormData(form);
    const name = (fd.get('name') || '').trim();
    const icon = (fd.get('icon') || 'bi-grid').trim();
    if (!name) return;
    cat.name = name;
    cat.icon = icon;
    cat.slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    persist();
    closeModal();
    showToast(`Category updated to "${name}"!`, 'success');
    render();
  };

  window.deleteCategoryAdmin = function (categoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    if (confirm(`Are you sure you want to delete category "${cat.name}" and all its subcategories?`)) {
      state.categories = state.categories.filter((c) => c.id !== categoryId);
      persist();
      showToast(`Category "${cat.name}" deleted.`, 'info');
      render();
    }
  };

  window.openAdminAddSubcategoryModal = function (categoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;"><i class="bi bi-node-plus" style="color:var(--haat-orange);"></i> Add Subcategory to "${esc(cat.name)}"</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Products will belong to this subcategory under ${esc(cat.name)}.</p>
      <form onsubmit="event.preventDefault(); window.handleAdminAddSubcategory(this, ${cat.id});">
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Subcategory Name</label>
          <input type="text" name="name" placeholder="e.g. Sarees & Kurtis, or Organic Honey" required style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Add Subcategory</button>
      </form>
    `);
  };

  window.handleAdminAddSubcategory = function (form, categoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    const fd = new FormData(form);
    const name = (fd.get('name') || '').trim();
    if (!name) return;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    cat.subcategories = cat.subcategories || [];
    const nextSubId = cat.subcategories.reduce((m, s) => Math.max(m, s.id), categoryId * 100) + 1;
    cat.subcategories.push({ id: nextSubId, name, slug });
    persist();
    closeModal();
    showToast(`Subcategory "${name}" added to ${cat.name}!`, 'success');
    render();
  };

  window.openAdminEditSubcategoryModal = function (categoryId, subcategoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    const sub = (cat.subcategories || []).find((s) => s.id === subcategoryId);
    if (!sub) return;
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;"><i class="bi bi-pencil" style="color:var(--haat-orange);"></i> Edit Subcategory</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Under category: <strong>${esc(cat.name)}</strong></p>
      <form onsubmit="event.preventDefault(); window.handleAdminEditSubcategory(this, ${cat.id}, ${sub.id});">
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Subcategory Name</label>
          <input type="text" name="name" value="${esc(sub.name)}" required style="width:100%;padding:8px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;">
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Save Subcategory</button>
      </form>
    `);
  };

  window.handleAdminEditSubcategory = function (form, categoryId, subcategoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    const sub = (cat.subcategories || []).find((s) => s.id === subcategoryId);
    if (!sub) return;
    const fd = new FormData(form);
    const name = (fd.get('name') || '').trim();
    if (!name) return;
    sub.name = name;
    sub.slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    persist();
    closeModal();
    showToast(`Subcategory updated to "${name}"!`, 'success');
    render();
  };

  window.deleteSubcategoryAdmin = function (categoryId, subcategoryId) {
    const cat = state.categories.find((c) => c.id === categoryId);
    if (!cat) return;
    const sub = (cat.subcategories || []).find((s) => s.id === subcategoryId);
    if (!sub) return;
    if (confirm(`Are you sure you want to delete subcategory "${sub.name}" from ${cat.name}?`)) {
      cat.subcategories = cat.subcategories.filter((s) => s.id !== subcategoryId);
      persist();
      showToast(`Subcategory "${sub.name}" removed.`, 'info');
      render();
    }
  };

  // Admin Product Filter Controls
  window.setAdminProductSearch = function (q) {
    state.adminProductSearch = q || '';
    render();
  };

  window.setAdminProductStoreFilter = function (storeId) {
    state.adminProductStoreFilter = storeId;
    render();
  };

  window.setAdminProductCatFilter = function (catId) {
    state.adminProductCatFilter = catId;
    render();
  };

  // Admin Add Product Modal
  window.openAdminAddProductModal = function () {
    const subcats = [];
    state.categories.forEach((c) => {
      (c.subcategories || []).forEach((s) => {
        subcats.push({ catName: c.name, subId: s.id, subName: s.name });
      });
    });

    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:6px;"><i class="bi bi-box-seam" style="color:var(--haat-orange);"></i> Add New Product (Admin)</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Publish a product to any merchant store catalogue.</p>
      <form onsubmit="event.preventDefault(); window.handleAdminAddProduct(this);" style="max-height:75vh;overflow-y:auto;padding-right:4px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Assign to Store</label>
            <select name="store_id" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              ${state.stores.map((st) => `<option value="${st.id}">${esc(st.store_name)}</option>`).join('')}
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Category / Subcategory</label>
            <select name="subcategory_id" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              ${subcats.map((sc) => `<option value="${sc.subId}">${esc(sc.catName)} &rarr; ${esc(sc.subName)}</option>`).join('')}
            </select>
          </div>
        </div>

        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Product Name</label>
          <input type="text" name="name" placeholder="e.g. Handmade Mustard Honey 500g" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">SKU</label>
            <input type="text" name="sku" placeholder="e.g. HON-500" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Regular Price (৳)</label>
            <input type="number" min="1" name="price" value="500" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Sale Price (৳)</label>
            <input type="number" min="0" name="sale_price" value="450" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Custom Delivery Charge (৳)</label>
            <input type="number" min="0" name="delivery_charge" value="60" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
          <div style="display:flex;align-items:center;gap:14px;padding-top:18px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer;">
              <input type="checkbox" name="free_delivery"> Free Delivery
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer;color:#DC2626;">
              <input type="checkbox" name="is_flash_sale"> ⚡ Flash Sale
            </label>
          </div>
        </div>

        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Product Image URL</label>
          <input type="text" name="image_1" placeholder="https://images.unsplash.com/... or panjabi.jpg" value="panjabi.jpg" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
        </div>

        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Description</label>
          <textarea name="description" rows="3" placeholder="Product details, origins, and specifications..." style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;"></textarea>
        </div>

        <button type="submit" class="btn-village-primary" style="width:100%;padding:10px;">Publish Product</button>
      </form>
    `);
  };

  window.handleAdminAddProduct = function (form) {
    const fd = new FormData(form);
    const store_id = Number(fd.get('store_id'));
    const subcategory_id = Number(fd.get('subcategory_id'));
    const name = (fd.get('name') || '').trim();
    const sku = (fd.get('sku') || '').trim().toUpperCase();
    const price = Number(fd.get('price')) || 0;
    const sale_price = Number(fd.get('sale_price')) || price;
    const delivery_charge = Number(fd.get('delivery_charge')) || 0;
    const free_delivery = fd.get('free_delivery') ? 1 : 0;
    const is_flash_sale = fd.get('is_flash_sale') ? 1 : 0;
    const image_1 = (fd.get('image_1') || '').trim() || 'panjabi.jpg';
    const description = (fd.get('description') || '').trim();

    if (!name || !sku) return;

    const newId = state.products.reduce((m, p) => Math.max(m, p.id), 0) + 1;
    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-');

    state.products.unshift({
      id: newId,
      store_id,
      subcategory_id,
      brand_id: 1,
      collection_id: 1,
      name,
      slug,
      sku,
      description,
      price,
      sale_price,
      delivery_charge,
      free_delivery,
      is_flash_sale,
      image_1,
      rating: 5.0,
      reviews_count: 0
    });

    persist();
    closeModal();
    showToast(`Product "${name}" published!`, 'success');
    render();
  };

  // Admin Edit Product Modal
  window.openAdminEditProductModal = function (productId) {
    const p = getProduct(productId);
    if (!p) return;

    const subcats = [];
    state.categories.forEach((c) => {
      (c.subcategories || []).forEach((s) => {
        subcats.push({ catName: c.name, subId: s.id, subName: s.name });
      });
    });

    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:6px;"><i class="bi bi-pencil-square" style="color:var(--haat-orange);"></i> Edit Product (Admin)</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Modify price, inventory, store assignment, and delivery.</p>
      <form onsubmit="event.preventDefault(); window.handleAdminEditProduct(this, ${p.id});" style="max-height:75vh;overflow-y:auto;padding-right:4px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Store</label>
            <select name="store_id" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              ${state.stores.map((st) => `<option value="${st.id}" ${st.id === p.store_id ? 'selected' : ''}>${esc(st.store_name)}</option>`).join('')}
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Category / Subcategory</label>
            <select name="subcategory_id" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
              ${subcats.map((sc) => `<option value="${sc.subId}" ${sc.subId === p.subcategory_id ? 'selected' : ''}>${esc(sc.catName)} &rarr; ${esc(sc.subName)}</option>`).join('')}
            </select>
          </div>
        </div>

        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Product Name</label>
          <input type="text" name="name" value="${esc(p.name)}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">SKU</label>
            <input type="text" name="sku" value="${esc(p.sku || '')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Regular Price (৳)</label>
            <input type="number" min="1" name="price" value="${p.price}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Sale Price (৳)</label>
            <input type="number" min="0" name="sale_price" value="${p.sale_price || p.price}" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Custom Delivery Charge (৳)</label>
            <input type="number" min="0" name="delivery_charge" value="${p.delivery_charge ?? 60}" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
          </div>
          <div style="display:flex;align-items:center;gap:14px;padding-top:18px;">
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer;">
              <input type="checkbox" name="free_delivery" ${p.free_delivery ? 'checked' : ''}> Free Delivery
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer;color:#DC2626;">
              <input type="checkbox" name="is_flash_sale" ${p.is_flash_sale ? 'checked' : ''}> ⚡ Flash Sale
            </label>
          </div>
        </div>

        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Product Image URL</label>
          <input type="text" name="image_1" value="${esc(p.image_1 || '')}" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">
        </div>

        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Description</label>
          <textarea name="description" rows="3" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;font-size:12px;">${esc(p.description || '')}</textarea>
        </div>

        <button type="submit" class="btn-village-primary" style="width:100%;padding:10px;">Save Product Changes</button>
      </form>
    `);
  };

  window.handleAdminEditProduct = function (form, productId) {
    const p = getProduct(productId);
    if (!p) return;

    const fd = new FormData(form);
    p.store_id = Number(fd.get('store_id')) || p.store_id;
    p.subcategory_id = Number(fd.get('subcategory_id')) || p.subcategory_id;
    p.name = (fd.get('name') || '').trim() || p.name;
    p.sku = (fd.get('sku') || '').trim().toUpperCase() || p.sku;
    p.price = Number(fd.get('price')) || p.price;
    p.sale_price = Number(fd.get('sale_price')) || p.price;
    p.delivery_charge = Number(fd.get('delivery_charge')) ?? p.delivery_charge;
    p.free_delivery = fd.get('free_delivery') ? 1 : 0;
    p.is_flash_sale = fd.get('is_flash_sale') ? 1 : 0;
    p.image_1 = (fd.get('image_1') || '').trim() || p.image_1;
    p.description = (fd.get('description') || '').trim();

    persist();
    closeModal();
    showToast(`Product "${p.name}" updated successfully!`, 'success');
    render();
  };

  window.openAdminCouponModal = function () {
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:6px;"><i class="bi bi-ticket-perforated-fill" style="color:var(--haat-orange);"></i> Create Admin Category/Subcategory Coupon</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Admin coupons must target a specific Category or Subcategory.</p>
      <form onsubmit="event.preventDefault(); window.handleSaveAdminCoupon(this);">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Coupon Code</label>
            <input type="text" name="code" placeholder="e.g. GROCERY100" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;text-transform:uppercase;font-family:var(--font-mono);">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Display Title</label>
            <input type="text" name="title" placeholder="e.g. Grocery Saver Voucher" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Discount Type</label>
            <select name="discount_type" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
              <option value="percent">Percentage (%)</option>
              <option value="fixed">Fixed Amount (৳)</option>
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Discount Value</label>
            <input type="number" min="1" name="discount_value" value="15" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Min Order (৳)</label>
            <input type="number" min="0" name="min_order" value="500" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Target Scope Type</label>
          <select name="scope_type" onchange="document.getElementById('couponCatWrap').style.display=this.value==='category'?'block':'none';document.getElementById('couponSubWrap').style.display=this.value==='subcategory'?'block':'none';" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            <option value="category" selected>Target Entire Category</option>
            <option value="subcategory">Target Specific Subcategory</option>
          </select>
        </div>
        <div id="couponCatWrap" style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Select Category</label>
          <select name="category_id" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            ${state.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}
          </select>
        </div>
        <div id="couponSubWrap" style="margin-bottom:14px;display:none;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Select Subcategory</label>
          <select name="subcategory_id" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            ${state.categories.map((c) => c.subcategories.map((s) => `<option value="${s.id}">${esc(c.name)} &rarr; ${esc(s.name)}</option>`).join('')).join('')}
          </select>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Create Collectible Coupon</button>
      </form>
    `);
  };

  window.handleSaveAdminCoupon = function (form) {
    const fd = new FormData(form);
    const scopeType = fd.get('scope_type') || 'category';
    const catId = Number(fd.get('category_id') || 1);
    const subId = Number(fd.get('subcategory_id') || 101);
    const subCatParent = getCategoryBySubId(subId);

    const newCoupon = {
      id: state.coupons.reduce((m, c) => Math.max(m, c.id), 0) + 1,
      code: (fd.get('code') || '').trim().toUpperCase(),
      title: (fd.get('title') || 'Category Voucher').trim(),
      discount_type: fd.get('discount_type') || 'percent',
      discount_value: parseFloat(fd.get('discount_value') || 10),
      min_order: parseFloat(fd.get('min_order') || 0),
      store_id: null,
      scope_type: scopeType,
      category_id: scopeType === 'category' ? catId : subCatParent?.id || catId,
      subcategory_id: scopeType === 'subcategory' ? subId : null,
      created_by: 'admin',
      is_active: 1
    };
    state.coupons.unshift(newCoupon);
    persist();
    closeModal();
    showToast(`Coupon ${newCoupon.code} published for customers to collect!`, 'success');
    render();
  };

  window.toggleCouponActive = function (couponId) {
    const c = state.coupons.find((x) => x.id === couponId);
    if (c) {
      c.is_active = c.is_active === 0 ? 1 : 0;
      persist();
      render();
    }
  };

  window.deleteCoupon = function (couponId) {
    state.coupons = state.coupons.filter((x) => x.id !== couponId);
    persist();
    showToast('Coupon deleted.', 'info');
    render();
  };

  window.openAdminCampaignModal = function () {
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:6px;"><i class="bi bi-megaphone-fill" style="color:var(--haat-orange);"></i> Launch Admin Category / Subcategory Campaign</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Run Free Delivery or Flash Sale Discount campaigns on selected Categories or Subcategories (never individual products).</p>
      <form onsubmit="event.preventDefault(); window.handleSaveAdminCampaign(this);">
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Campaign Title</label>
          <input type="text" name="name" placeholder="e.g. Fashion & Heritage Free Shipping Week" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Campaign Type</label>
            <select name="type" onchange="document.getElementById('campDiscWrap').style.display=this.value==='flash_sale'?'block':'none';" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
              <option value="free_delivery" selected>Free Delivery Campaign</option>
              <option value="flash_sale">Flash Sale Discount Campaign</option>
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Badge Text</label>
            <input type="text" name="badge_text" value="Admin Free Delivery" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
        </div>
        <div id="campDiscWrap" style="margin-bottom:10px;display:none;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Flash Sale Discount (%)</label>
          <input type="number" min="1" max="80" name="discount_percent" value="15" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Target Scope</label>
            <select name="scope_type" onchange="document.getElementById('campCatWrap').style.display=this.value==='category'?'block':'none';document.getElementById('campSubWrap').style.display=this.value==='subcategory'?'block':'none';" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
              <option value="category" selected>Category</option>
              <option value="subcategory">Subcategory</option>
            </select>
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Min Spend Requirement (৳)</label>
            <input type="number" min="0" name="min_order" value="0" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
        </div>
        <div id="campCatWrap" style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Select Target Category</label>
          <select name="category_id" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            ${state.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('')}
          </select>
        </div>
        <div id="campSubWrap" style="margin-bottom:14px;display:none;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Select Target Subcategory</label>
          <select name="subcategory_id" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            ${state.categories.map((c) => c.subcategories.map((s) => `<option value="${s.id}">${esc(c.name)} &rarr; ${esc(s.name)}</option>`).join('')).join('')}
          </select>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Launch Campaign</button>
      </form>
    `);
  };

  window.handleSaveAdminCampaign = function (form) {
    const fd = new FormData(form);
    const scopeType = fd.get('scope_type') || 'category';
    const catId = Number(fd.get('category_id') || 1);
    const subId = Number(fd.get('subcategory_id') || 101);
    const subCatParent = getCategoryBySubId(subId);

    const newCamp = {
      id: (state.campaigns || []).reduce((m, c) => Math.max(m, c.id), 0) + 1,
      name: (fd.get('name') || 'Admin Campaign').trim(),
      type: fd.get('type') || 'free_delivery',
      scope_type: scopeType,
      category_id: scopeType === 'category' ? catId : subCatParent?.id || catId,
      subcategory_id: scopeType === 'subcategory' ? subId : null,
      discount_percent: parseFloat(fd.get('discount_percent') || 0),
      min_order: parseFloat(fd.get('min_order') || 0),
      badge_text: (fd.get('badge_text') || 'Admin Campaign').trim(),
      is_active: 1
    };
    state.campaigns.unshift(newCamp);
    persist();
    closeModal();
    showToast(`Campaign "${newCamp.name}" is now live!`, 'success');
    render();
  };

  window.toggleCampaignActive = function (campId) {
    const c = (state.campaigns || []).find((x) => x.id === campId);
    if (c) {
      c.is_active = c.is_active ? 0 : 1;
      persist();
      render();
    }
  };

  window.deleteCampaign = function (campId) {
    state.campaigns = (state.campaigns || []).filter((x) => x.id !== campId);
    persist();
    showToast('Campaign removed.', 'info');
    render();
  };

  window.openBannerSlideModal = function (slideId = null) {
    const existing = slideId ? (state.banners || []).find((b) => b.id === slideId) : null;
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:10px;"><i class="bi bi-images" style="color:var(--haat-orange);"></i> ${existing ? 'Edit Banner Slide' : 'Add New Mega Sale / Campaign Slide'}</h3>
      <form onsubmit="event.preventDefault(); window.handleSaveBannerSlide(this, ${existing ? existing.id : 'null'});">
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Headline Title</label>
          <input type="text" name="title" value="${esc(existing?.title || '')}" placeholder="e.g. 11.11 Mega Festival — Up to 50% OFF!" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
        </div>
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Subtitle / Campaign Details</label>
          <textarea name="subtitle" rows="2" required placeholder="Describe vouchers, free delivery categories, and deals..." style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">${esc(existing?.subtitle || '')}</textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Campaign Badge Pill</label>
            <input type="text" name="badge" value="${esc(existing?.badge || 'MEGA SALE CAMPAIGN')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Image Path / URL</label>
            <input type="text" name="image" value="${esc(existing?.image || 'village_banner.webp')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Button CTA Text</label>
            <input type="text" name="cta_text" value="${esc(existing?.cta_text || 'Shop Campaign')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Target Link Hash</label>
            <input type="text" name="cta_link" value="${esc(existing?.cta_link || '#/products')}" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Overlay Theme Gradient</label>
          <select name="bg_gradient" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            <option value="linear-gradient(90deg, rgba(16, 38, 28, 0.92) 0%, rgba(16, 38, 28, 0.72) 55%, rgba(16, 38, 28, 0.20) 100%)">Emerald Village Green</option>
            <option value="linear-gradient(90deg, rgba(124, 45, 18, 0.92) 0%, rgba(194, 65, 12, 0.72) 55%, rgba(248, 86, 6, 0.20) 100%)">Daraz Festival Orange</option>
            <option value="linear-gradient(90deg, rgba(30, 58, 138, 0.92) 0%, rgba(29, 78, 216, 0.72) 55%, rgba(37, 99, 235, 0.20) 100%)">Royal Tech Blue</option>
            <option value="linear-gradient(90deg, rgba(88, 28, 135, 0.92) 0%, rgba(126, 34, 206, 0.72) 55%, rgba(147, 51, 234, 0.20) 100%)">Mega Purple Luxe</option>
          </select>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">${existing ? 'Update Slide' : 'Add Slide to Carousel'}</button>
      </form>
    `);
  };

  window.handleSaveBannerSlide = function (form, slideId) {
    const fd = new FormData(form);
    if (slideId) {
      const b = (state.banners || []).find((x) => x.id === Number(slideId));
      if (b) {
        b.title = fd.get('title');
        b.subtitle = fd.get('subtitle');
        b.badge = fd.get('badge');
        b.image = fd.get('image');
        b.cta_text = fd.get('cta_text');
        b.cta_link = fd.get('cta_link');
        b.bg_gradient = fd.get('bg_gradient');
      }
    } else {
      const newId = (state.banners || []).reduce((m, x) => Math.max(m, x.id), 0) + 1;
      state.banners.push({
        id: newId,
        title: fd.get('title'),
        subtitle: fd.get('subtitle'),
        badge: fd.get('badge'),
        image: fd.get('image'),
        cta_text: fd.get('cta_text'),
        cta_link: fd.get('cta_link'),
        bg_gradient: fd.get('bg_gradient'),
        is_active: 1
      });
    }
    persist();
    closeModal();
    startBannerSliderTimer();
    showToast('Homepage banner carousel updated!', 'success');
    render();
  };

  window.toggleBannerSlide = function (slideId) {
    const b = (state.banners || []).find((x) => x.id === slideId);
    if (b) {
      b.is_active = b.is_active ? 0 : 1;
      state.currentBannerSlide = 0;
      persist();
      startBannerSliderTimer();
      render();
    }
  };

  window.deleteBannerSlide = function (slideId) {
    if ((state.banners || []).length <= 1) {
      showToast('At least one banner slide must remain in the slider.', 'warning');
      return;
    }
    state.banners = (state.banners || []).filter((x) => x.id !== slideId);
    state.currentBannerSlide = 0;
    persist();
    startBannerSliderTimer();
    showToast('Banner slide removed.', 'info');
    render();
  };

  window.updateBannerAutoSlide = function (enabled) {
    state.appSettings = state.appSettings || {};
    state.appSettings.banner_auto_slide = Boolean(enabled);
    persist();
    startBannerSliderTimer();
    showToast(`Auto-slide ${enabled ? 'enabled' : 'paused'}.`, 'info');
  };

  window.updateBannerInterval = function (ms) {
    state.appSettings = state.appSettings || {};
    state.appSettings.banner_interval_ms = Number(ms) || 4500;
    persist();
    startBannerSliderTimer();
    showToast(`Slide speed updated to ${(Number(ms) / 1000).toFixed(1)}s.`, 'info');
  };

  window.setAdminReportFilter = function (roleFilter) {
    state.adminReportRoleFilter = roleFilter;
    render();
  };

  window.openResolveReportModal = function (reportId) {
    const rep = (state.reports || []).find((r) => r.id === reportId);
    if (!rep) return;
    openModal(`
      <h3 style="font-size:17px;font-weight:800;margin-bottom:8px;">Resolve Incident Report #${rep.id}</h3>
      <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">Submitted by <strong>${esc(rep.reporter_name)} (${esc(rep.reporter_role.toUpperCase())})</strong>: ${esc(rep.subject)}</p>
      <form onsubmit="event.preventDefault(); window.handleResolveReport(this, ${rep.id});">
        <div style="margin-bottom:12px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Status</label>
          <select name="status" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
            <option value="resolved" selected>Resolved</option>
            <option value="open">Open / Investigating</option>
          </select>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Official Admin Resolution Note</label>
          <textarea name="admin_response" rows="3" required placeholder="Enter action taken by Admin..." style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">${esc(rep.admin_response || '')}</textarea>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Save Resolution</button>
      </form>
    `);
  };

  window.handleResolveReport = function (form, reportId) {
    const rep = (state.reports || []).find((r) => r.id === reportId);
    if (!rep) return;
    const fd = new FormData(form);
    rep.status = fd.get('status') || 'resolved';
    rep.admin_response = (fd.get('admin_response') || '').trim();

    window.addNotification({
      title: `Report #${rep.id} Updated by Admin`,
      message: `Status: ${rep.status.toUpperCase()}. Note: ${rep.admin_response.length > 60 ? rep.admin_response.slice(0, 60) + '...' : rep.admin_response}`,
      type: 'report',
      target_role: rep.reporter_role,
      link: rep.reporter_role === 'customer' ? '#/account/reports' : '#/messages'
    });

    persist();
    closeModal();
    showToast(`Report #${rep.id} updated!`, 'success');
    render();
  };

  window.openAddRiderModal = function () {
    openModal(`
      <h3 style="font-size:17px;font-weight:800;margin-bottom:12px;"><i class="bi bi-bicycle"></i> Add New HATEX Fleet Rider</h3>
      <form onsubmit="event.preventDefault(); window.handleAddRider(this);">
        <div style="margin-bottom:10px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Rider Full Name</label>
          <input type="text" name="name" placeholder="e.g. Tanvir Hasan" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Phone Number</label>
            <input type="text" name="phone" placeholder="017xxxxxxxx" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Vehicle Type</label>
            <select name="vehicle" style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
              <option value="Motorcycle">Motorcycle</option>
              <option value="Bicycle">Bicycle</option>
              <option value="Delivery Van">Delivery Van</option>
            </select>
          </div>
        </div>
        <div style="margin-bottom:14px;">
          <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Operational Zone</label>
          <input type="text" name="zone" value="Dhaka Metro" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:6px;">
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Register Fleet Rider</button>
      </form>
    `);
  };

  window.handleAddRider = function (form) {
    const fd = new FormData(form);
    const newId = state.riders.reduce((m, r) => Math.max(m, r.id), 0) + 1;
    state.riders.push({
      id: newId,
      name: (fd.get('name') || 'Rider').trim(),
      phone: (fd.get('phone') || '01700000000').trim(),
      vehicle: fd.get('vehicle') || 'Motorcycle',
      zone: fd.get('zone') || 'Dhaka Metro',
      status: 'active',
      current_lat: 23.7590,
      current_lon: 90.3870
    });
    persist();
    closeModal();
    showToast('New rider added to HATEX fleet!', 'success');
    render();
  };

  window.toggleRiderStatus = function (riderId) {
    const r = state.riders.find((x) => x.id === riderId);
    if (r) {
      r.status = r.status === 'active' ? 'offline' : 'active';
      persist();
      render();
    }
  };

  window.handleSaveAdminAppSettings = function (form) {
    const fd = new FormData(form);
    state.appSettings = {
      ...state.appSettings,
      platform_name: fd.get('platform_name') || 'HAAT Digital Marketplace',
      support_phone: fd.get('support_phone') || '+880 9612-345678',
      default_delivery_charge: Math.max(0, parseFloat(fd.get('default_delivery_charge') || 60)),
      commission_rate: Math.max(0, parseFloat(fd.get('commission_rate') || 5)),
      banner_interval_ms: Math.max(2000, parseInt(fd.get('banner_interval_ms') || 4500, 10)),
      banner_auto_slide: Boolean(fd.get('banner_auto_slide'))
    };
    persist();
    startBannerSliderTimer();
    showToast('Global App Settings saved and applied!', 'success');
    render();
  };

  /* =========================================================================
     18. HATEX LOGISTICS DISPATCH HUB (`#/dash/hatex`)
     Left-Sidebar + Right-Workspace Layout (Dashboard, Riders List, Product Track, Messages, Settings)
     ========================================================================= */

  function renderHatexDashView(subTab = 'dashboard') {
    const pendingPickups = [];
    const allSellerOrders = [];
    state.orders.forEach((o) => {
      (o.seller_orders || []).forEach((so) => {
        allSellerOrders.push({ ...so, parentOrder: o });
        if (!so.assigned_rider_id && so.status !== 'delivered' && so.status !== 'cancelled') {
          pendingPickups.push({ ...so, parentOrder: o });
        }
      });
    });

    const hatexUnreadMsgs = state.messages.filter((m) => m.receiver_role === 'hatex' && !m.is_read).length;

    return `
      <div class="container">
        <div class="dash-shell">
          <!-- HATEX Left Sidebar -->
          <aside class="dash-nav-card">
            <div class="dash-nav-header">
              <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;">LOGISTICS WORKSPACE</div>
              <strong style="font-size:16px;color:#1E293B;display:block;margin-top:2px;">HATEX</strong>
            </div>
            <a class="dash-nav-item ${subTab === 'dashboard' || subTab === 'overview' ? 'active' : ''}" onclick="location.hash='#/dash/hatex/dashboard'">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="dash-nav-item ${subTab === 'riders' ? 'active' : ''}" onclick="location.hash='#/dash/hatex/riders'">
              <i class="bi bi-people"></i> Riders List (${state.riders.length})
            </a>
            <a class="dash-nav-item ${subTab === 'track' ? 'active' : ''}" onclick="location.hash='#/dash/hatex/track'">
              <i class="bi bi-geo-alt"></i> Product Track
            </a>
            <a class="dash-nav-item ${subTab === 'messages' ? 'active' : ''}" onclick="location.hash='#/dash/hatex/messages'" style="display:flex;align-items:center;justify-content:space-between;">
              <span><i class="bi bi-chat-dots"></i> Messages</span>
              ${hatexUnreadMsgs > 0 ? `<span style="background:#DC2626;color:#fff;font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:10px;">${hatexUnreadMsgs}</span>` : ''}
            </a>
            <a class="dash-nav-item ${subTab === 'settings' ? 'active' : ''}" onclick="location.hash='#/dash/hatex/settings'">
              <i class="bi bi-gear"></i> Settings
            </a>
          </aside>

          <!-- HATEX Right Workspace -->
          <main>
            ${
              subTab === 'riders'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                  <div>
                    <h2 style="font-size:18px;font-weight:800;margin-bottom:2px;">HATEX Fleet Riders Directory</h2>
                    <p style="font-size:12.5px;color:var(--text-muted);">Manage active riders, assign parcels, and monitor current delivery load.</p>
                  </div>
                  <button class="btn-village-primary" style="font-size:12px;padding:8px 14px;" onclick="window.openAddRiderModal()">
                    <i class="bi bi-plus-lg"></i> Add Rider to Fleet
                  </button>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Rider Name</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Zone</th>
                        <th>Active Assigned Parcels</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${state.riders
                        .map((r) => {
                          const activeParcels = allSellerOrders.filter((so) => so.assigned_rider_id === r.id && so.status !== 'delivered');
                          return `
                            <tr>
                              <td><strong>${esc(r.name)}</strong></td>
                              <td>${esc(r.phone)}</td>
                              <td>${esc(r.vehicle || 'Motorcycle')}</td>
                              <td>${esc(r.zone || 'Dhaka Metro')}</td>
                              <td><span class="haversine-pill">${activeParcels.length} active</span></td>
                              <td><span class="status-badge ${r.status === 'active' ? 'active' : 'pending'}">${esc(r.status)}</span></td>
                            </tr>
                          `;
                        })
                        .join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            `
                : subTab === 'track'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;">Live Product & Parcel Tracking Console</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">Monitor every seller parcel from merchant warehouse to customer doorstep.</p>
                ${allSellerOrders
                  .map((so) => {
                    const store = getStore(so.store_id) || state.stores[0];
                    const rider = state.riders.find((r) => r.id === so.assigned_rider_id);
                    const dist = haversineDistance(store.latitude, store.longitude, so.parentOrder.customer_lat, so.parentOrder.customer_lon);
                    return `
                      <div style="border:1px solid #E2E8F0;border-radius:10px;padding:16px;margin-bottom:14px;background:#F8FAFC;">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                          <div>
                            <strong style="font-size:14.5px;color:var(--haat-primary);">Parcel #${esc(so.seller_order_number)}</strong>
                            <div style="font-size:12px;color:var(--text-muted);">Store: <strong>${esc(store.store_name)}</strong> (${esc(store.district)}) &rarr; Customer: <strong>${esc(so.parentOrder.shipping_name)}</strong> (${esc(so.parentOrder.shipping_address)})</div>
                          </div>
                          <div style="display:flex;align-items:center;gap:8px;">
                            <span class="haversine-pill">${dist} km</span>
                            <span class="status-badge ${so.status}">${esc(so.status.replace(/_/g, ' '))}</span>
                          </div>
                        </div>
                        <div style="font-size:12px;margin-bottom:10px;">
                          <strong>Assigned Rider:</strong> ${rider ? `${esc(rider.name)} (${esc(rider.phone)})` : '<span style="color:#D97706;">Unassigned</span>'}
                        </div>
                        <div style="display:flex;flex-direction:column;gap:6px;background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #E2E8F0;">
                          ${(so.tracking || [])
                            .map(
                              (t) => `
                            <div style="font-size:11.5px;display:flex;justify-content:space-between;gap:8px;">
                              <span><i class="bi bi-check2-circle" style="color:#16A34A;"></i> <strong>${esc(t.status.replace(/_/g, ' ').toUpperCase())}</strong> — ${esc(t.location)}: ${esc(t.note)}</span>
                              <span style="color:var(--text-muted);white-space:nowrap;">${esc(t.created_at)}</span>
                            </div>
                          `
                            )
                            .join('')}
                        </div>
                      </div>
                    `;
                  })
                  .join('')}
              </div>
            `
                : subTab === 'messages'
                ? renderRoleMessagingPanel('hatex')
                : subTab === 'settings'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;">HATEX Central Hub Settings</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">Configure central sorting hub location, dispatch rules, and emergency reporting.</p>
                <form onsubmit="event.preventDefault(); showToast('HATEX Hub settings saved!','success');" style="max-width:540px;">
                  <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Account Identity</label>
                    <input type="text" value="HATEX" readonly style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;background:#F8FAFC;font-weight:700;">
                  </div>
                  <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Central Sorting Hub Location</label>
                    <input type="text" value="HATEX Central Sorting Hub, Farmgate, Dhaka" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                  </div>
                  <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn-village-primary">Save Settings</button>
                    <button type="button" class="btn-secondary" onclick="window.openAdminReportModal()"><i class="bi bi-flag"></i> Submit Report to Admin</button>
                  </div>
                </form>
              </div>
            `
                : `
              <!-- HATEX Default Dashboard -->
              <div class="kpi-row">
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${pendingPickups.length}</h3>
                    <p>Awaiting Rider Dispatch</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#FFF7ED;color:#EA580C;"><i class="bi bi-clock-history"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${state.riders.length}</h3>
                    <p>Active Fleet Riders</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-bicycle"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${allSellerOrders.length}</h3>
                    <p>Total Parcels Monitored</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-box-seam"></i></div>
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                  <h2 style="font-size:16px;font-weight:800;">Orders Awaiting Rider Dispatch</h2>
                  <button type="button" class="btn-secondary" style="font-size:12px;" onclick="location.hash='#/dash/hatex/track'"><i class="bi bi-geo-alt"></i> Open Full Product Track</button>
                </div>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Seller Order #</th>
                        <th>Store / Pickup</th>
                        <th>Customer Destination</th>
                        <th>Distance</th>
                        <th>Status</th>
                        <th>Assign Rider</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${
                        allSellerOrders.length
                          ? allSellerOrders
                              .map((so) => {
                                const store = getStore(so.store_id) || state.stores[0];
                                const dist = haversineDistance(store.latitude, store.longitude, so.parentOrder.customer_lat, so.parentOrder.customer_lon);
                                const assignedRider = state.riders.find((r) => r.id === so.assigned_rider_id);
                                return `
                              <tr>
                                <td><strong>${esc(so.seller_order_number)}</strong></td>
                                <td>
                                  <strong>${esc(store.store_name)}</strong>
                                  <div style="font-size:11px;color:var(--text-muted);">${esc(store.district)}</div>
                                </td>
                                <td>
                                  <strong>${esc(so.parentOrder.shipping_name)}</strong>
                                  <div style="font-size:11px;color:var(--text-muted);">${esc(so.parentOrder.shipping_address)}</div>
                                </td>
                                <td><strong style="color:var(--haat-orange);">${dist} km</strong></td>
                                <td><span class="status-badge ${so.status}">${esc(so.status.replace(/_/g, ' '))}</span></td>
                                <td>
                                  <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                    ${
                                      so.status !== 'reached_hub' && !so.assigned_rider_id
                                        ? `<button type="button" class="btn-secondary" style="padding:4px 8px;font-size:11px;" onclick="window.hatexMarkReachedHub(${so.id})"><i class="bi bi-building-check"></i> Mark at Hub</button>`
                                        : ''
                                    }
                                    <select onchange="if(this.value) window.assignRider(${so.id}, Number(this.value))" style="padding:4px 8px;font-size:11.5px;border:1px solid #CBD5E1;border-radius:6px;">
                                      <option value="">${assignedRider ? `Assigned: ${esc(assignedRider.name)}` : 'Select Rider...'}</option>
                                      ${state.riders.map((r) => `<option value="${r.id}" ${so.assigned_rider_id === r.id ? 'selected' : ''}>${esc(r.name)} (${esc(r.zone || 'Dhaka')})</option>`).join('')}
                                    </select>
                                  </div>
                                </td>
                              </tr>
                            `;
                              })
                              .join('')
                          : '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">No orders found.</td></tr>'
                      }
                    </tbody>
                  </table>
                </div>
              </div>
            `
            }
          </main>
        </div>
      </div>
    `;
  }

  window.hatexMarkReachedHub = function (sellerOrderId) {
    state.orders.forEach((o) => {
      const so = (o.seller_orders || []).find((s) => s.id === Number(sellerOrderId));
      if (so) {
        so.status = 'reached_hub';
        const allReached = o.seller_orders.every((s) => ['reached_hub', 'assigned_to_rider', 'in_transit', 'out_for_delivery', 'delivered'].includes(s.status));
        if (allReached) {
          o.order_status = 'reached_hub';
        }
        so.tracking.push({
          id: Date.now(),
          status: 'reached_hub',
          location: 'HATEX Central Sorting Hub, Farmgate',
          latitude: 23.759000,
          longitude: 90.387000,
          note: 'Parcels collected from merchant and received at HATEX Central Hub.',
          updated_by: 'HATEX',
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        window.addNotification({
          title: `Order #${o.order_number} at Central Hub`,
          message: `Your package #${so.package_id || so.seller_order_number} arrived at HATEX Central Sorting Hub.`,
          type: 'order',
          target_role: 'customer',
          order_id: o.id,
          link: `#/order-tracking?orderId=${o.id}`
        });
      }
    });
    persist();
    showToast('HATEX: Parcel marked as received at Central Hub!', 'success');
    render();
  };

  window.assignRider = function (sellerOrderId, riderId) {
    const rider = state.riders.find((r) => r.id === Number(riderId)) || state.riders[0];
    state.orders.forEach((o) => {
      const so = (o.seller_orders || []).find((s) => s.id === Number(sellerOrderId));
      if (so) {
        so.assigned_rider_id = rider.id;
        so.status = 'assigned_to_rider';
        o.order_status = 'assigned_to_rider';
        so.tracking.push({
          id: Date.now(),
          status: 'assigned_to_rider',
          location: 'HATEX Central Sorting Hub, Farmgate',
          latitude: 23.759000,
          longitude: 90.387000,
          note: `Assigned delivery rider ${rider.name} (Phone: ${rider.phone}). Customer can now chat directly with rider.`,
          updated_by: 'HATEX',
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        window.addNotification({
          title: 'New Delivery Assigned',
          message: `Package #${so.package_id || so.seller_order_number} assigned to you. Destination: ${o.shipping_name}, ${o.district}.`,
          type: 'rider',
          target_role: 'rider',
          order_id: o.id,
          link: '#/dash/rider'
        });

        window.addNotification({
          title: `Rider Assigned for Order #${o.order_number}`,
          message: `Rider ${rider.name} (${rider.phone}) is handling your delivery via HATEX Express.`,
          type: 'rider',
          target_role: 'customer',
          order_id: o.id,
          link: `#/order-tracking?orderId=${o.id}`
        });
      }
    });
    persist();
    showToast(`Rider ${rider.name} assigned to parcel!`, 'success');
    render();
  };

  /* =========================================================================
     19. DELIVERY RIDER WORKSPACE (`#/dash/rider`)
     Left-Sidebar + Right-Workspace Layout (Dashboard, Orders, Order Locations, Messages, Settings)
     ========================================================================= */

  function renderRiderView(subTab = 'dashboard') {
    const curRiderUser = state.currentUser;
    const rider = (state.riders || []).find((r) => String(r.user_id) === String(curRiderUser?.id) || (curRiderUser && r.name === curRiderUser.name)) ||
                  state.riders[0] ||
                  { id: curRiderUser?.id || 1, name: curRiderUser?.name || 'Tareq Ahmed', phone: curRiderUser?.phone || '01711223388', vehicle_type: 'Motorcycle', hub: 'Dhaka Metro', status: 'active' };
    const assigned = [];
    const availablePool = [];

    state.orders.forEach((o) => {
      (o.seller_orders || []).forEach((so) => {
        if (so.assigned_rider_id === rider.id) {
          assigned.push({ ...so, parentOrder: o });
        } else if (!so.assigned_rider_id && so.status !== 'delivered' && so.status !== 'cancelled') {
          availablePool.push({ ...so, parentOrder: o });
        }
      });
    });

    const riderUnreadMsgs = state.messages.filter((m) => m.receiver_role === 'rider' && !m.is_read).length;

    return `
      <div class="container">
        <div class="dash-shell">
          <!-- Rider Left Sidebar -->
          <aside class="dash-nav-card">
            <div class="dash-nav-header">
              <div style="font-size:11px;color:var(--text-muted);font-weight:700;text-transform:uppercase;">RIDER ACCOUNT</div>
              <strong style="font-size:16px;color:#1E293B;display:block;margin-top:2px;">${esc(rider.name)}</strong>
              <small style="color:#059669;font-weight:700;">${esc(rider.phone)}</small>
            </div>
            <a class="dash-nav-item ${subTab === 'dashboard' || subTab === 'overview' ? 'active' : ''}" onclick="location.hash='#/dash/rider/dashboard'">
              <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="dash-nav-item ${subTab === 'orders' ? 'active' : ''}" onclick="location.hash='#/dash/rider/orders'">
              <i class="bi bi-box-seam"></i> Orders (${assigned.length} Assigned / ${availablePool.length} Open)
            </a>
            <a class="dash-nav-item ${subTab === 'locations' ? 'active' : ''}" onclick="location.hash='#/dash/rider/locations'">
              <i class="bi bi-geo-alt"></i> Order Location Details
            </a>
            <a class="dash-nav-item ${subTab === 'messages' ? 'active' : ''}" onclick="location.hash='#/dash/rider/messages'" style="display:flex;align-items:center;justify-content:space-between;">
              <span><i class="bi bi-chat-dots"></i> Messages</span>
              ${riderUnreadMsgs > 0 ? `<span style="background:#DC2626;color:#fff;font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:10px;">${riderUnreadMsgs}</span>` : ''}
            </a>
            <a class="dash-nav-item ${subTab === 'settings' ? 'active' : ''}" onclick="location.hash='#/dash/rider/settings'">
              <i class="bi bi-gear"></i> Settings
            </a>
          </aside>

          <!-- Rider Right Workspace -->
          <main>
            ${
              subTab === 'orders'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;margin-bottom:20px;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:4px;">Available Orders Pool (Self-Choose Delivery)</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">Orders added by customers & merchants ready for rider pickup. Click to accept and assign to yourself.</p>
                <div class="table-wrap">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Order #</th>
                        <th>Pickup Store</th>
                        <th>Customer Destination</th>
                        <th>Distance</th>
                        <th>Status</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${
                        availablePool.length
                          ? availablePool
                              .map((so) => {
                                const store = getStore(so.store_id) || state.stores[0];
                                const dist = haversineDistance(store.latitude, store.longitude, so.parentOrder.customer_lat, so.parentOrder.customer_lon);
                                return `
                              <tr>
                                <td><strong>${esc(so.seller_order_number)}</strong></td>
                                <td>${esc(store.store_name)} (${esc(store.district)})</td>
                                <td>${esc(so.parentOrder.shipping_name)} — ${esc(so.parentOrder.shipping_address)}</td>
                                <td><span class="haversine-pill">${dist} km</span></td>
                                <td><span class="status-badge ${so.status}">${esc(so.status.replace(/_/g, ' '))}</span></td>
                                <td>
                                  <button type="button" class="btn-village-primary" style="padding:5px 12px;font-size:11.5px;" onclick="window.riderAcceptOrder(${so.id})">
                                    <i class="bi bi-check2-circle"></i> Choose & Accept Order
                                  </button>
                                </td>
                              </tr>
                            `;
                              })
                              .join('')
                          : '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:20px;">No unassigned orders in the pool right now.</td></tr>'
                      }
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:14px;">My Assigned Deliveries (${assigned.length})</h2>
                ${
                  assigned.length
                    ? assigned
                        .map((so) => {
                          const store = getStore(so.store_id) || state.stores[0];
                          return `
                        <div style="border:1px solid #E2E8F0;border-radius:10px;padding:16px;margin-bottom:14px;background:#F8FAFC;">
                          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                            <div>
                              <strong style="font-size:15px;color:var(--haat-primary);">Order #${esc(so.seller_order_number)}</strong>
                              <div style="font-size:12px;color:var(--text-muted);">Pickup: <strong>${esc(store.store_name)}</strong> (${esc(store.address)})</div>
                              <div style="font-size:12px;color:#1E293B;">Deliver To: <strong>${esc(so.parentOrder.shipping_name)}</strong> (${esc(so.parentOrder.shipping_phone)}) — ${esc(so.parentOrder.shipping_address)}</div>
                            </div>
                            <span class="status-badge ${so.status}">${esc(so.status.replace(/_/g, ' '))}</span>
                          </div>
                          <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            ${
                              so.status !== 'out_for_delivery' && so.status !== 'delivered'
                                ? `<button class="btn-village-primary" style="font-size:12px;background:#7C3AED;" onclick="window.advanceRiderStatus(${so.id}, 'out_for_delivery')"><i class="bi bi-bicycle"></i> Mark Out for Delivery</button>`
                                : ''
                            }
                            ${
                              so.status !== 'delivered'
                                ? `<button class="btn-village-primary" style="font-size:12px;background:#10B981;" onclick="window.advanceRiderStatus(${so.id}, 'delivered')"><i class="bi bi-check2-circle"></i> Mark Delivered</button>`
                                : '<span style="color:#10B981;font-weight:700;font-size:12.5px;"><i class="bi bi-check-circle-fill"></i> Delivered to Customer</span>'
                            }
                            <button class="btn-secondary" style="font-size:12px;" onclick="location.hash='#/dash/rider/locations'"><i class="bi bi-geo-alt"></i> Location & Checkpoints</button>
                            <button class="btn-secondary" style="font-size:12px;" onclick="location.hash='#/dash/rider/messages'"><i class="bi bi-chat-dots"></i> Message Customer</button>
                          </div>
                        </div>
                      `;
                        })
                        .join('')
                    : '<p style="color:var(--text-muted);font-size:13px;">No deliveries assigned yet. Choose an order from the pool above!</p>'
                }
              </div>
            `
                : subTab === 'locations'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;">Order Location Details & Waypoint Logger</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">Detailed pickup and drop-off addresses, remaining Haversine distance, and live checkpoint updates.</p>
                ${
                  assigned.length
                    ? assigned
                        .map((so) => {
                          const store = getStore(so.store_id) || state.stores[0];
                          const latestTrack = so.tracking[so.tracking.length - 1];
                          const currentLat = latestTrack?.latitude || store.latitude;
                          const currentLon = latestTrack?.longitude || store.longitude;
                          const remaining = haversineDistance(currentLat, currentLon, so.parentOrder.customer_lat, so.parentOrder.customer_lon);
                          return `
                        <div class="order-tracking-card" style="margin-bottom:20px;">
                          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <div>
                              <strong style="font-size:15px;color:var(--haat-primary);">Order #${esc(so.seller_order_number)}</strong>
                              <span class="status-badge ${so.status}" style="margin-left:8px;">${esc(so.status.replace(/_/g, ' '))}</span>
                            </div>
                            <span class="haversine-pill"><i class="bi bi-rulers"></i> ${remaining} km remaining</span>
                          </div>

                          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:12px;margin-bottom:14px;">
                            <div style="padding:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;">
                              <div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase;">Pickup Location (Merchant)</div>
                              <strong style="font-size:13.5px;color:#1E293B;">${esc(store.store_name)}</strong>
                              <div style="font-size:12px;color:#475569;">${esc(store.address)}, ${esc(store.district)}</div>
                              <div style="font-size:11.5px;color:var(--haat-primary);margin-top:4px;">Phone: ${esc(store.phone)}</div>
                            </div>
                            <div style="padding:12px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;">
                              <div style="font-size:11px;font-weight:800;color:#166534;text-transform:uppercase;">Drop-off Location (Customer)</div>
                              <strong style="font-size:13.5px;color:#1E293B;">${esc(so.parentOrder.shipping_name)}</strong>
                              <div style="font-size:12px;color:#475569;">${esc(so.parentOrder.shipping_address)}, ${esc(so.parentOrder.district)}</div>
                              <div style="font-size:11.5px;color:#15803D;margin-top:4px;">Phone: ${esc(so.parentOrder.shipping_phone)} • Payment: ${esc(so.parentOrder.payment.method.toUpperCase())} (${money(so.parentOrder.grand_total)})</div>
                            </div>
                          </div>

                          <div style="background:#FAF8F5;border:1px solid #EBE4D8;border-radius:8px;padding:14px;">
                            <div style="font-size:12.5px;font-weight:700;color:#1E293B;margin-bottom:8px;">
                              <i class="bi bi-pin-map-fill" style="color:var(--haat-orange)"></i> Log Live Delivery Checkpoint
                            </div>
                            <form onsubmit="event.preventDefault(); window.handleLogWaypoint(this, ${so.id});">
                              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:8px;">
                                <input type="text" name="location" placeholder="e.g. Farmgate Hub, Mirpur 10 Circle" required style="padding:8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12.5px;">
                                <input type="text" name="note" placeholder="e.g. Parcel in transit towards customer" required style="padding:8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12.5px;">
                              </div>
                              <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;">
                                <input type="number" step="0.000001" name="lat" value="${(currentLat + 0.005).toFixed(6)}" required style="padding:8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px;font-family:var(--font-mono);">
                                <input type="number" step="0.000001" name="lon" value="${(currentLon - 0.002).toFixed(6)}" required style="padding:8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px;font-family:var(--font-mono);">
                                <button type="submit" class="btn-village-primary" style="padding:8px 14px;font-size:12px;">Update Location</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      `;
                        })
                        .join('')
                    : '<p style="color:var(--text-muted);font-size:13px;">No active assigned orders to display location details for.</p>'
                }
              </div>
            `
                : subTab === 'messages'
                ? renderRoleMessagingPanel('rider')
                : subTab === 'settings'
                ? `
              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <h2 style="font-size:18px;font-weight:800;margin-bottom:6px;">Rider Account Settings</h2>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">Update your rider profile, contact number, vehicle type, or submit an incident report to Admin.</p>
                <form onsubmit="event.preventDefault(); window.handleSaveRiderSettings(this);" style="max-width:540px;">
                  <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Rider Name</label>
                    <input type="text" name="name" value="${esc(rider.name)}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Phone Number</label>
                      <input type="text" name="phone" value="${esc(rider.phone)}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                    <div>
                      <label style="font-size:12px;font-weight:700;display:block;margin-bottom:4px;">Vehicle Type</label>
                      <input type="text" name="vehicle" value="${esc(rider.vehicle || 'Motorcycle')}" required style="width:100%;padding:9px;border:1px solid #CBD5E1;border-radius:6px;">
                    </div>
                  </div>
                  <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn-village-primary">Save Rider Profile</button>
                    <button type="button" class="btn-secondary" onclick="window.openAdminReportModal()"><i class="bi bi-flag"></i> Report Issue to Admin</button>
                  </div>
                </form>
              </div>
            `
                : `
              <!-- Rider Default Dashboard -->
              <div class="kpi-row">
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${assigned.length}</h3>
                    <p>My Assigned Deliveries</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#ECFDF5;color:#059669;"><i class="bi bi-bicycle"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${availablePool.length}</h3>
                    <p>Available Orders to Choose</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#FFF7ED;color:#EA580C;"><i class="bi bi-box-seam"></i></div>
                </div>
                <div class="kpi-stat-card">
                  <div class="kpi-stat-content">
                    <h3>${assigned.filter((s) => s.status === 'delivered').length}</h3>
                    <p>Completed Deliveries</p>
                  </div>
                  <div class="kpi-stat-icon" style="background:#EFF6FF;color:#2563EB;"><i class="bi bi-check2-all"></i></div>
                </div>
              </div>

              <div class="page-card" style="background:#fff;padding:24px;border-radius:10px;border:1px solid #E2E8F0;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
                  <h2 style="font-size:16px;font-weight:800;">Active Deliveries Overview</h2>
                  <div style="display:flex;gap:8px;">
                    <button class="btn-village-primary" style="font-size:12px;padding:6px 12px;" onclick="location.hash='#/dash/rider/orders'">View & Choose Orders (${availablePool.length} Open)</button>
                    <button class="btn-secondary" style="font-size:12px;padding:6px 12px;" onclick="location.hash='#/dash/rider/locations'">Order Locations</button>
                  </div>
                </div>
                ${
                  assigned.length
                    ? assigned
                        .map((so) => {
                          const store = getStore(so.store_id) || state.stores[0];
                          return `
                        <div style="border:1px solid #E2E8F0;border-radius:8px;padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                          <div>
                            <strong>Order #${esc(so.seller_order_number)}</strong>
                            <div style="font-size:12px;color:var(--text-muted);">From: ${esc(store.store_name)} &rarr; To: <strong>${esc(so.parentOrder.shipping_name)}</strong> (${esc(so.parentOrder.shipping_address)})</div>
                          </div>
                          <div style="display:flex;gap:8px;align-items:center;">
                            <span class="status-badge ${so.status}">${esc(so.status.replace(/_/g, ' '))}</span>
                            ${
                              so.status !== 'out_for_delivery' && so.status !== 'delivered'
                                ? `<button class="btn-village-primary" style="padding:4px 10px;font-size:11px;background:#7C3AED;" onclick="window.advanceRiderStatus(${so.id}, 'out_for_delivery')">Start Delivery</button>`
                                : so.status !== 'delivered'
                                ? `<button class="btn-village-primary" style="padding:4px 10px;font-size:11px;background:#10B981;" onclick="window.advanceRiderStatus(${so.id}, 'delivered')">Mark Delivered</button>`
                                : ''
                            }
                          </div>
                        </div>
                      `;
                        })
                        .join('')
                    : '<p style="color:var(--text-muted);font-size:13px;">No active deliveries assigned. Open the Orders tab to choose from available orders!</p>'
                }
              </div>
            `
            }
          </main>
        </div>
      </div>
    `;
  }

  window.riderAcceptOrder = function (sellerOrderId) {
    const rider = state.riders[0] || { id: 1, name: 'Tareq Ahmed', phone: '01711223388' };
    window.assignRider(sellerOrderId, rider.id);
  };

  window.handleSaveRiderSettings = function (form) {
    const fd = new FormData(form);
    const rider = state.riders[0];
    if (rider) {
      rider.name = (fd.get('name') || rider.name).trim();
      rider.phone = (fd.get('phone') || rider.phone).trim();
      rider.vehicle = (fd.get('vehicle') || rider.vehicle).trim();
    }
    if (state.currentUser && state.activeRole === 'rider') {
      state.currentUser.name = rider.name;
      state.currentUser.phone = rider.phone;
    }
    persist();
    updateGlobalHeader();
    showToast('Rider profile updated!', 'success');
    render();
  };

  window.advanceRiderStatus = function (sellerOrderId, newStatus) {
    const rider = state.riders[0] || { id: 1, name: 'Tareq Ahmed' };
    state.orders.forEach((o) => {
      const so = o.seller_orders.find((s) => s.id === sellerOrderId);
      if (so) {
        so.status = newStatus;
        if (newStatus === 'delivered') {
          const allDelivered = o.seller_orders.every((s) => s.id === sellerOrderId || s.status === 'delivered');
          if (allDelivered) o.order_status = 'delivered';
        } else if (newStatus === 'out_for_delivery' || newStatus === 'in_transit') {
          o.order_status = 'out_for_delivery';
        }
        so.tracking.push({
          id: Date.now(),
          status: newStatus,
          location: newStatus === 'delivered' ? 'Customer Doorstep' : 'In Transit Waypoint',
          latitude: 23.780000,
          longitude: 90.370000,
          note: `${rider.name} updated delivery status to ${newStatus.replace(/_/g, ' ')}.`,
          updated_by: rider.name,
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        if (newStatus === 'delivered') {
          window.addNotification({
            title: `Order #${o.order_number} Delivered!`,
            message: `Rider ${rider.name} has safely delivered package #${so.package_id || so.seller_order_number} to your doorstep.`,
            type: 'order',
            target_role: 'customer',
            order_id: o.id,
            link: '#/account/orders'
          });
          window.addNotification({
            title: `Parcel #${so.package_id || so.seller_order_number} Delivered`,
            message: `Delivery completed by Rider ${rider.name} for Order #${o.order_number}.`,
            type: 'order',
            target_role: 'seller',
            order_id: o.id,
            link: '#/dash/seller'
          });
        } else if (newStatus === 'out_for_delivery') {
          window.addNotification({
            title: `Out for Delivery: Order #${o.order_number}`,
            message: `Rider ${rider.name} is on the road heading to your delivery address.`,
            type: 'rider',
            target_role: 'customer',
            order_id: o.id,
            link: `#/order-tracking?orderId=${o.id}`
          });
        }
      }
    });
    persist();
    showToast(`Order status updated to ${newStatus.replace(/_/g, ' ')}!`, 'success');
    render();
  };

  window.handleLogWaypoint = function (form, sellerOrderId) {
    const fd = new FormData(form);
    const loc = fd.get('location');
    const note = fd.get('note');
    const lat = parseFloat(fd.get('lat'));
    const lon = parseFloat(fd.get('lon'));
    const rider = state.riders[0] || { id: 1, name: 'Tareq Ahmed' };

    state.orders.forEach((o) => {
      const so = o.seller_orders.find((s) => s.id === sellerOrderId);
      if (so) {
        so.tracking.push({
          id: Date.now(),
          status: so.status,
          location: loc,
          latitude: lat,
          longitude: lon,
          note: note,
          updated_by: rider.name,
          created_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        window.addNotification({
          title: `Delivery Checkpoint: Order #${o.order_number}`,
          message: `Rider ${rider.name} reached ${loc} — "${note}"`,
          type: 'rider',
          target_role: 'customer',
          order_id: o.id,
          link: `#/order-tracking?orderId=${o.id}`
        });
      }
    });

    persist();
    showToast(`Waypoint logged at ${loc}! Distance recalculated.`, 'success');
    render();
  };

  /* =========================================================================
     20. MODALS: HELP, SETTINGS & REVIEWS
     ========================================================================= */

  window.openHelpModal = function () {
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:12px;"><i class="bi bi-question-circle"></i> HAAT Help & Support Desk</h3>
      <p style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:16px;">
        HAAT (হাট) is Bangladesh's all-variety digital bazaar platform powered by the 21-table normalized schema and HATEX logistics.
      </p>
      <div style="font-size:12.5px;color:#334155;line-height:1.8;">
        <div><strong>Customer Care Helpline:</strong> ${esc(state.appSettings?.support_phone || '+880 9612-345678')} (9 AM – 10 PM)</div>
        <div><strong>Email Support:</strong> ${esc(state.appSettings?.support_email || 'support@haat.com.bd')}</div>
        <div><strong>Logistics Desk:</strong> logistics@hatex.com.bd</div>
      </div>
      <button type="button" class="btn-village-primary" style="width:100%;margin-top:20px;" onclick="closeModal()">Close Help</button>
    `);
  };

  window.openSettingsModal = function () {
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:14px;"><i class="bi bi-gear"></i> System Settings</h3>
      <div style="display:flex;flex-direction:column;gap:12px;font-size:13px;">
        <label style="display:flex;justify-content:space-between;align-items:center;">
          <span>Preferred Language</span>
          <select style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;">
            <option>English (BD)</option>
            <option>বাংলা (Bengali)</option>
          </select>
        </label>
        <label style="display:flex;justify-content:space-between;align-items:center;">
          <span>Currency Display</span>
          <select style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;">
            <option>BDT (৳ - Taka)</option>
          </select>
        </label>
        <label style="display:flex;justify-content:space-between;align-items:center;">
          <span>Geocoding Distance Unit</span>
          <select style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;">
            <option>Kilometers (km)</option>
          </select>
        </label>
      </div>
      <button type="button" class="btn-village-primary" style="width:100%;margin-top:20px;" onclick="closeModal()">Save Preferences</button>
    `);
  };

  window.openReviewModal = function (productId) {
    if (!state.activeRole) {
      showToast('Please log in to your customer account to write a review.', 'info');
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'seller') {
      showToast('Sellers are not permitted to submit product reviews.', 'warning');
      return;
    }
    const eligibility = canCustomerReviewProduct(productId);
    if (!eligibility.eligible) {
      showToast(eligibility.reason, 'warning');
      return;
    }
    const p = getProduct(productId);
    openModal(`
      <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;"><i class="bi bi-star-fill text-warning"></i> Write Verified Product Review</h3>
      <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">Reviewing: <strong>${esc(p?.name || 'Product')}</strong></p>
      <div style="background:#F0FDF4;border:1px solid #86EFAC;color:#15803D;padding:8px 12px;border-radius:6px;font-size:11.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-patch-check-fill" style="font-size:15px;"></i>
        <span>Verified Purchase: Delivered via HATEX (${eligibility.order ? `Order #${esc(eligibility.order.order_number)}` : 'Verified'})</span>
      </div>
      <form onsubmit="event.preventDefault(); window.handleSubmitReview(this, ${productId});">
        <div style="margin-bottom:12px;">
          <label style="font-size:12px;font-weight:700;">Star Rating (1 to 5)</label>
          <select name="rating" required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;">
            <option value="5">★★★★★ (5 - Excellent)</option>
            <option value="4">★★★★☆ (4 - Very Good)</option>
            <option value="3">★★★☆☆ (3 - Average)</option>
            <option value="2">★★☆☆☆ (2 - Poor)</option>
            <option value="1">★☆☆☆☆ (1 - Terrible)</option>
          </select>
        </div>
        <div style="margin-bottom:16px;">
          <label style="font-size:12px;font-weight:700;">Review Comments</label>
          <textarea name="comment" rows="4" placeholder="Describe quality, packaging, and fulfillment experience..." required style="width:100%;padding:8px;border:1px solid #CBD5E1;border-radius:4px;"></textarea>
        </div>
        <button type="submit" class="btn-village-primary" style="width:100%;">Submit Verified Review</button>
      </form>
    `);
  };

  window.handleSubmitReview = function (form, productId) {
    if (!state.activeRole) {
      closeModal();
      location.hash = '#/auth';
      return;
    }
    if (state.activeRole === 'seller') {
      showToast('Sellers are not permitted to submit product reviews.', 'warning');
      closeModal();
      return;
    }
    const eligibility = canCustomerReviewProduct(productId);
    if (!eligibility.eligible) {
      showToast(eligibility.reason, 'warning');
      closeModal();
      return;
    }
    const fd = new FormData(form);
    const newRev = {
      id: state.reviews.length + 1,
      product_id: productId,
      user_id: state.currentUser?.id || 1,
      user_name: state.currentUser?.name || 'Rahim Sakib',
      rating: parseInt(fd.get('rating') || 5),
      comment: fd.get('comment'),
      order_number: eligibility.order?.order_number || '',
      verified_purchase: 1,
      created_at: new Date().toISOString().slice(0, 10)
    };
    state.reviews.unshift(newRev);
    persist();
    closeModal();
    showToast('Thank you! Your verified review has been published.', 'success');
    render();
  };

  /* =========================================================================
     20.5 DEDICATED AUTHENTICATION & REGISTRATION PORTAL (`#/auth`)
     ========================================================================= */

  function renderAuthView() {
    const activeTab = state.authTab || 'login';
    const regRole = state.registerRole || 'customer';

    return `
      <div class="auth-page-wrapper">
        <div class="auth-page-shell">
          <!-- Visual Heritage Column -->
          <div class="auth-photo-side">
            <img src="village_banner.webp" alt="HAAT Digital Marketplace" class="auth-village-photo-img">
            <div class="auth-photo-overlay">
              <a href="javascript:void(0)" onclick="window.handleLogoClick()" class="auth-photo-back-btn"><i class="bi bi-arrow-left"></i> Back to HAAT</a>
              <div style="margin-top:auto;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.7);pointer-events:auto;">
                <h2 style="font-size:22px;font-weight:800;line-height:1.2;margin:0;">Bangladesh's All-Variety Digital Marketplace</h2>
              </div>
            </div>
          </div>

          <!-- Interactive Form Column -->
          <div class="auth-main-side">
            <!-- Segmented Tab Header: Log In vs Register -->
            <div class="auth-tabs-segmented">
              <button type="button" class="auth-tab-segment ${activeTab === 'login' ? 'active' : ''}" onclick="window.switchAuthTab('login')">
                <i class="bi bi-box-arrow-in-right"></i> Log In
              </button>
              <button type="button" class="auth-tab-segment ${activeTab === 'register' ? 'active' : ''}" onclick="window.switchAuthTab('register')">
                <i class="bi bi-person-plus"></i> Register
              </button>
            </div>

            ${
              activeTab === 'login'
                ? `
              <!-- LOG IN TAB -->
              <div style="margin-bottom:16px;">
                <h1 style="font-size:20px;font-weight:800;color:#1E293B;margin-bottom:4px;">Sign In to Your Account</h1>
                <p style="font-size:12.5px;color:var(--text-muted);">Enter your credentials or choose an instant one-click demo persona below.</p>
              </div>

              <form onsubmit="event.preventDefault(); window.handleAuthLogin(this);">
                <div class="form-group-auth">
                  <label>Email Address</label>
                  <div class="input-with-icon">
                    <i class="bi bi-envelope"></i>
                    <input type="email" name="email" value="${esc(state.lastAuthEmail || '')}" required placeholder="name@domain.com">
                  </div>
                </div>

                <div class="form-group-auth">
                  <label>Password</label>
                  <div class="input-with-icon">
                    <i class="bi bi-lock"></i>
                    <input type="password" name="password" value="" required placeholder="••••••••">
                  </div>
                </div>

                <div class="form-group-auth">
                  <label>Select Role / Portal Destination</label>
                  <div class="input-with-icon">
                    <i class="bi bi-person-badge"></i>
                    <select name="role">
                      <option value="customer" selected>Customer</option>
                      <option value="seller">Seller Store</option>
                      <option value="rider">Rider</option>
                      <option value="admin">Admin</option>
                      <option value="hatex">HATEX</option>
                    </select>
                  </div>
                </div>

                <div class="auth-form-meta">
                  <label class="remember-label">
                    <input type="checkbox" checked> Remember me
                  </label>
                  <a href="#/auth" onclick="showToast('Password recovery link sent to registered email!','info');" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-auth-submit">
                  <i class="bi bi-box-arrow-in-right"></i> Log In to Dashboard
                </button>
              </form>

              <!-- ONE-CLICK DEMO ACCOUNTS -->
              <div class="quick-demo-logins-box">
                <div class="quick-demos-head">
                  <span class="quick-logins-title">
                    <i class="bi bi-lightning-charge-fill" style="color:var(--haat-orange)"></i>
                    <strong>One-Click Instant Demo Logins</strong>
                  </span>
                  <span class="quick-demos-hint">(No typing needed • 1-click enter)</span>
                </div>

                <div class="quick-demo-grid">
                  <!-- Customer Demo -->
                  <button type="button" class="btn-quick-login" onclick="window.instantDemoLogin('customer')">
                    <div class="demo-badge-avatar demo-avatar-cust">C</div>
                    <div class="demo-btn-content">
                      <span class="demo-pill-tag" style="color:#2563EB;">Customer Account</span>
                      <strong class="demo-user-name">Rahim Sakib</strong>
                      <span class="demo-sub-role">Orders, Cart & Addresses</span>
                    </div>
                    <span class="demo-action-arrow">Log In &rarr;</span>
                  </button>

                  <!-- Seller Demo -->
                  <button type="button" class="btn-quick-login" onclick="window.instantDemoLogin('seller')">
                    <div class="demo-badge-avatar demo-avatar-sell">S</div>
                    <div class="demo-btn-content">
                      <span class="demo-pill-tag" style="color:#F85606;">Seller Store</span>
                      <strong class="demo-user-name">ABC Fashion Store</strong>
                      <span class="demo-sub-role">Products, Stock & Orders</span>
                    </div>
                    <span class="demo-action-arrow">Log In &rarr;</span>
                  </button>

                  <!-- Rider Demo -->
                  <button type="button" class="btn-quick-login" onclick="window.instantDemoLogin('rider')">
                    <div class="demo-badge-avatar demo-avatar-ride">R</div>
                    <div class="demo-btn-content">
                      <span class="demo-pill-tag" style="color:#059669;">Rider Account</span>
                      <strong class="demo-user-name">Tareq Ahmed</strong>
                      <span class="demo-sub-role">Orders, Locations & Chat</span>
                    </div>
                    <span class="demo-action-arrow">Log In &rarr;</span>
                  </button>

                  <!-- Admin Demo (Fixed) -->
                  <button type="button" class="btn-quick-login" onclick="window.instantDemoLogin('admin')">
                    <div class="demo-badge-avatar demo-avatar-adm">A</div>
                    <div class="demo-btn-content">
                      <div style="display:flex;align-items:center;gap:4px;">
                        <span class="demo-pill-tag" style="color:#1E4332;">Admin Account</span>
                      </div>
                      <strong class="demo-user-name">Admin</strong>
                      <span class="demo-sub-role">Campaigns, Banners, Coupons & Reports</span>
                    </div>
                    <span class="demo-action-arrow">Log In &rarr;</span>
                  </button>

                  <!-- HATEX Central Demo (Fixed) -->
                  <button type="button" class="btn-quick-login" onclick="window.instantDemoLogin('hatex')" style="grid-column: span 2;">
                    <div class="demo-badge-avatar demo-avatar-hub">H</div>
                    <div class="demo-btn-content">
                      <div style="display:flex;align-items:center;gap:4px;">
                        <span class="demo-pill-tag" style="color:#7C3AED;">HATEX Account</span>
                      </div>
                      <strong class="demo-user-name">HATEX</strong>
                      <span class="demo-sub-role">Dashboard, Riders List, Product Track, Messages & Settings</span>
                    </div>
                    <span class="demo-action-arrow">Log In &rarr;</span>
                  </button>
                </div>
              </div>
            `
                : `
              <!-- REGISTER TAB -->
              <div style="margin-bottom:16px;">
                <h1 style="font-size:20px;font-weight:800;color:#1E293B;margin-bottom:4px;">Register a New Account</h1>
                <p style="font-size:12.5px;color:var(--text-muted);">Choose whether you want to shop as Customer, sell as Merchant, or deliver as Rider.</p>
              </div>

              <!-- Role Selector Pills for Registration -->
              <div class="auth-role-picker-section">
                <div class="role-picker-label-row">
                  <span class="picker-label">Choose Registration Role:</span>
                  <span class="auth-secure-tag"><i class="bi bi-patch-check-fill"></i> Instant Activation</span>
                </div>

                <div class="role-picker-cards">
                  <div class="role-picker-btn ${regRole === 'customer' ? 'active' : ''}" onclick="window.switchRegisterRole('customer')">
                    <i class="bi bi-person-fill role-picker-icon"></i>
                    <span class="role-picker-title">Customer</span>
                    <span class="role-picker-sub">Buy & Track</span>
                    <i class="bi bi-check-circle-fill role-selected-check"></i>
                  </div>

                  <div class="role-picker-btn ${regRole === 'seller' ? 'active' : ''}" onclick="window.switchRegisterRole('seller')">
                    <i class="bi bi-shop role-picker-icon" style="color:var(--haat-orange)"></i>
                    <span class="role-picker-title">Seller Store</span>
                    <span class="role-picker-sub">Sell & Catalog</span>
                    <i class="bi bi-check-circle-fill role-selected-check"></i>
                  </div>

                  <div class="role-picker-btn ${regRole === 'rider' ? 'active' : ''}" onclick="window.switchRegisterRole('rider')">
                    <i class="bi bi-bicycle role-picker-icon" style="color:#059669"></i>
                    <span class="role-picker-title">HATEX Rider</span>
                    <span class="role-picker-sub">Deliver & Earn</span>
                    <i class="bi bi-check-circle-fill role-selected-check"></i>
                  </div>
                </div>
              </div>

              <form onsubmit="event.preventDefault(); window.handleAuthRegister(this);">
                ${
                  regRole === 'customer'
                    ? `
                  <!-- CUSTOMER REGISTRATION FIELDS -->
                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Full Name</label>
                      <div class="input-with-icon">
                        <i class="bi bi-person"></i>
                        <input type="text" name="name" placeholder="e.g. Tanzim Ahmed" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Phone Number</label>
                      <div class="input-with-icon">
                        <i class="bi bi-telephone"></i>
                        <input type="text" name="phone" placeholder="017xxxxxxxx" required>
                      </div>
                    </div>
                  </div>

                  <div class="form-group-auth">
                    <label>Email Address</label>
                    <div class="input-with-icon">
                      <i class="bi bi-envelope"></i>
                      <input type="email" name="email" placeholder="customer@domain.com" required>
                    </div>
                  </div>

                  <div class="form-group-auth">
                    <label>Delivery Address (Street, House, Road)</label>
                    <div class="input-with-icon">
                      <i class="bi bi-geo-alt"></i>
                      <input type="text" name="address" placeholder="e.g. Flat 4B, House 12, Road 7, Dhanmondi" required>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>District</label>
                      <div class="input-with-icon">
                        <i class="bi bi-geo-alt"></i>
                        <select name="district">
                          <option value="Dhaka" selected>Dhaka</option>
                          <option value="Chittagong">Chittagong</option>
                          <option value="Sylhet">Sylhet</option>
                          <option value="Rajshahi">Rajshahi</option>
                        </select>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Division</label>
                      <div class="input-with-icon">
                        <i class="bi bi-map"></i>
                        <select name="division">
                          <option value="Dhaka" selected>Dhaka</option>
                          <option value="Chittagong">Chittagong</option>
                          <option value="Sylhet">Sylhet</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Password</label>
                      <div class="input-with-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Confirm Password</label>
                      <div class="input-with-icon">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" name="cpassword" placeholder="••••••••" required>
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn-auth-submit" style="margin-top:8px;">
                    <i class="bi bi-person-check-fill"></i> Create Customer Account
                  </button>
                `
                    : regRole === 'seller'
                    ? `
                  <!-- SELLER REGISTRATION FIELDS -->
                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Store / Brand Name</label>
                      <div class="input-with-icon">
                        <i class="bi bi-shop"></i>
                        <input type="text" name="store_name" placeholder="e.g. Bengal Heritage Crafts" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Merchant / Artisan Name</label>
                      <div class="input-with-icon">
                        <i class="bi bi-person"></i>
                        <input type="text" name="name" placeholder="e.g. Kamal Hossain" required>
                      </div>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Business Email</label>
                      <div class="input-with-icon">
                        <i class="bi bi-envelope"></i>
                        <input type="email" name="email" placeholder="store@haat.com" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Contact Phone</label>
                      <div class="input-with-icon">
                        <i class="bi bi-telephone"></i>
                        <input type="text" name="phone" placeholder="018xxxxxxxx" required>
                      </div>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Primary Specialty</label>
                      <div class="input-with-icon">
                        <i class="bi bi-tag"></i>
                        <select name="category">
                          <option value="Fashion & Apparel">Fashion & Apparel</option>
                          <option value="Electronics & Gadgets">Electronics & Gadgets</option>
                          <option value="Groceries & Organic Food">Groceries & Organic Food</option>
                          <option value="Artisanal & Traditional Crafts" selected>Artisanal & Traditional Crafts</option>
                        </select>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Warehouse District</label>
                      <div class="input-with-icon">
                        <i class="bi bi-geo-alt"></i>
                        <select name="district">
                          <option value="Dhaka" selected>Dhaka</option>
                          <option value="Narayanganj">Narayanganj</option>
                          <option value="Gazipur">Gazipur</option>
                          <option value="Tangail">Tangail</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="form-group-auth">
                    <label>Store Physical / Pickup Address</label>
                    <div class="input-with-icon">
                      <i class="bi bi-geo-alt"></i>
                      <input type="text" name="address" placeholder="e.g. Shop 12, Anam Rangs Plaza, Dhanmondi" required>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Password</label>
                      <div class="input-with-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Confirm Password</label>
                      <div class="input-with-icon">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" name="cpassword" placeholder="••••••••" required>
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn-auth-submit" style="margin-top:8px;background:linear-gradient(135deg, #1E4332 0%, #11281E 100%);">
                    <i class="bi bi-shop-window"></i> Register Merchant Store & Enter Studio
                  </button>
                `
                    : `
                  <!-- RIDER REGISTRATION FIELDS -->
                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Rider Full Name</label>
                      <div class="input-with-icon">
                        <i class="bi bi-person"></i>
                        <input type="text" name="name" placeholder="e.g. Jamil Hossain" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Contact Phone</label>
                      <div class="input-with-icon">
                        <i class="bi bi-telephone"></i>
                        <input type="text" name="phone" placeholder="019xxxxxxxx" required>
                      </div>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Email Address</label>
                      <div class="input-with-icon">
                        <i class="bi bi-envelope"></i>
                        <input type="email" name="email" placeholder="rider@hatex.com" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Vehicle Type</label>
                      <div class="input-with-icon">
                        <i class="bi bi-bicycle"></i>
                        <select name="vehicle_type">
                          <option value="Motorcycle" selected>Motorcycle</option>
                          <option value="Bicycle">Bicycle</option>
                          <option value="Electric Scooter">Electric Scooter</option>
                          <option value="Delivery Van">Delivery Van</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>NID / Driving License Number</label>
                      <div class="input-with-icon">
                        <i class="bi bi-card-heading"></i>
                        <input type="text" name="nid" placeholder="1992xxxxxxxxxx" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Operational City / Hub</label>
                      <div class="input-with-icon">
                        <i class="bi bi-building"></i>
                        <select name="district">
                          <option value="Dhaka" selected>Dhaka Metro</option>
                          <option value="Chittagong">Chittagong Metro</option>
                          <option value="Sylhet">Sylhet Metro</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <div class="auth-form-grid">
                    <div class="form-group-auth">
                      <label>Password</label>
                      <div class="input-with-icon">
                        <i class="bi bi-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                      </div>
                    </div>
                    <div class="form-group-auth">
                      <label>Confirm Password</label>
                      <div class="input-with-icon">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" name="cpassword" placeholder="••••••••" required>
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn-auth-submit" style="margin-top:8px;background:linear-gradient(135deg, #059669 0%, #047857 100%);">
                    <i class="bi bi-bicycle"></i> Register as HATEX Delivery Rider
                  </button>
                `
                }
              </form>
            `
            }
          </div>
        </div>
      </div>
    `;
  }

  /* =========================================================================
     21. CLIENT-SIDE ROUTER
     ========================================================================= */

  function render() {
    const raw = location.hash.slice(1) || '/';
    const [pathPart, queryPart] = raw.split('?');
    const searchParams = new URLSearchParams(queryPart || '');
    const parts = (pathPart || '/').split('/');
    const route = parts[1] || '';
    const param = parts[2] || searchParams.get('orderId') || searchParams.get('id') || '';
    const subParam = parts[3] || '';

    const app = $('#app');
    if (!app) return;

    updateGlobalHeader();

    // Requirement 9: HATEX account cannot access landing page or web app — locked to working page
    if (state.activeRole === 'hatex') {
      if (route !== 'auth' && !(route === 'dash' && param === 'hatex')) {
        location.hash = '#/dash/hatex/dashboard';
        return;
      }
    }

    // Requirement 10: Rider account cannot access landing page or web app — locked to working page
    if (state.activeRole === 'rider') {
      if (route !== 'auth' && !(route === 'dash' && param === 'rider')) {
        location.hash = '#/dash/rider/dashboard';
        return;
      }
    }

    // Requirement 1: Guest restrictions — no cart, wishlist, checkout, account, or dash without logging in
    if (!state.activeRole) {
      if (['cart', 'checkout', 'account', 'wishlist', 'messages', 'dash', 'order', 'tracking'].includes(route)) {
        showToast('Please log in to your account to continue.', 'info');
        location.hash = '#/auth';
        return;
      }
    }

    try {
      if (route === '' || raw === '/') {
        app.innerHTML = renderHomeView();
        startBannerSliderTimer();
        startCouponSliderInterval();
      } else if (route === 'auth' || route === 'login' || route === 'register') {
        if (route === 'register') state.authTab = 'register';
        else if (route === 'login') state.authTab = 'login';
        app.innerHTML = renderAuthView();
      } else if (route === 'products') {
        state.selectedCategory = null;
        app.innerHTML = renderProductsView();
      } else if (route === 'category') {
        state.selectedCategory = param || null;
        app.innerHTML = renderProductsView();
      } else if (route === 'product') {
        app.innerHTML = renderProductDetailView(param);
      } else if (route === 'store') {
        app.innerHTML = renderStoreView(param);
      } else if (route === 'stores') {
        const ownStore = state.activeRole === 'seller' ? getSellerOwnStore() : null;
        const visibleStores = state.stores;
        app.innerHTML = `
          <div class="container" style="margin-top:24px;margin-bottom:60px;">
            <h1 style="font-size:22px;font-weight:800;color:#1E293B;margin-bottom:6px;">Verified HAAT Merchant Stores</h1>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">${state.activeRole === 'seller' ? 'Browse merchant stores across Bangladesh. Click your store to view your live storefront.' : 'Explore official merchant storefronts across Bangladesh.'}</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:20px;">
              ${visibleStores
                .map(
                  (s) => {
                    const isOwn = ownStore && ownStore.id === s.id;
                    return `
                <div class="haat-item-card" onclick="location.hash='#/store/${s.store_slug}'" style="padding:20px;cursor:pointer;${isOwn ? 'border:2px solid #3B82F6;background:#F8FAFC;' : ''}">
                  <div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;">
                    <div style="width:52px;height:52px;border-radius:12px;background:${s.primary_color};color:#fff;display:grid;place-items:center;font-weight:800;font-size:20px;">
                      ${esc(s.logo_text)}
                    </div>
                    <div>
                      <div style="display:flex;align-items:center;gap:6px;">
                        <h3 style="font-size:16px;font-weight:800;color:#1E293B;margin:0;">${esc(s.store_name)}</h3>
                        ${isOwn ? '<span class="role-badge-tag role-badge-seller" style="font-size:9.5px;padding:2px 6px;">Your Store</span>' : ''}
                      </div>
                      <small style="color:var(--text-muted);"><i class="bi bi-geo-alt"></i> ${esc(s.district)}, ${esc(s.division)}</small>
                    </div>
                  </div>
                  <p style="font-size:12.5px;color:#475569;margin-bottom:14px;line-height:1.5;">${esc(s.description)}</p>
                  <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;border-top:1px solid #F1F5F9;padding-top:10px;">
                    <span class="delivery-pill ${s.free_delivery ? 'free' : 'paid'}"><i class="bi bi-truck"></i> ${s.free_delivery ? 'Free Delivery' : `Delivery ৳${s.delivery_charge ?? 60}`}</span>
                    <strong style="color:var(--haat-orange);">${isOwn ? 'View Your Storefront &rarr;' : state.activeRole === 'seller' ? 'View Store (View Only) &rarr;' : 'Enter Store &rarr;'}</strong>
                  </div>
                </div>
              `;
                  }
                )
                .join('')}
            </div>
          </div>
        `;
      } else if (route === 'collections') {
        app.innerHTML = `
          <div class="container" style="margin-top:24px;margin-bottom:60px;">
            <h1 style="font-size:22px;font-weight:800;color:#1E293B;margin-bottom:16px;">Curated Seasonal Collections</h1>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
              ${state.collections
                .map((col) => {
                  const st = getStore(col.store_id) || state.stores[0];
                  const prods = state.products.filter((p) => p.collection_id === col.id);
                  return `
                  <div class="haat-item-card" onclick="location.hash='#/store/${st.store_slug}'" style="padding:20px;cursor:pointer;">
                    <span class="role-badge-tag role-badge-seller">${esc(st.store_name)}</span>
                    <h3 style="font-size:16px;font-weight:800;color:var(--haat-primary);margin:8px 0;">${esc(col.name)}</h3>
                    <p style="font-size:12.5px;color:#64748B;">Specialty collection with ${prods.length} items.</p>
                    <div style="margin-top:14px;color:var(--haat-orange);font-weight:700;font-size:12.5px;">View Collection &rarr;</div>
                  </div>
                `;
                })
                .join('')}
            </div>
          </div>
        `;
      } else if (route === 'deals') {
        const activeCoupons = (state.coupons || []).filter((c) => c.is_active !== 0);
        app.innerHTML = `
          <div class="container" style="margin-top:24px;margin-bottom:60px;">
            <h1 style="font-size:22px;font-weight:800;color:#1E293B;margin-bottom:6px;">Exclusive Marketplace Vouchers & Category Campaigns</h1>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:18px;">Collect exclusive marketplace vouchers for selected categories, subcategories, and merchant stores.</p>
            <div class="voucher-strip-grid">
              ${activeCoupons.map((c) => renderDarazVoucherCard(c)).join('')}
            </div>
          </div>
        `;
      } else if (route === 'cart') {
        app.innerHTML = renderCartView();
      } else if (route === 'checkout') {
        app.innerHTML = renderCheckoutView();
      } else if (route === 'order' || route === 'tracking' || route === 'order-tracking') {
        app.innerHTML = renderTrackingView(param);
      } else if (route === 'account') {
        app.innerHTML = renderAccountView(param || 'profile');
      } else if (route === 'messages') {
        if (!state.activeRole) {
          location.hash = '#/auth';
          return;
        } else if (state.activeRole === 'customer') {
          location.hash = '#/account/messages';
          return;
        } else if (state.activeRole === 'seller') {
          location.hash = '#/dash/seller/messages';
          return;
        } else if (state.activeRole === 'admin') {
          location.hash = '#/dash/admin/messages';
          return;
        } else if (state.activeRole === 'hatex') {
          location.hash = '#/dash/hatex/messages';
          return;
        } else if (state.activeRole === 'rider') {
          location.hash = '#/dash/rider/messages';
          return;
        } else {
          app.innerHTML = renderMessagesView();
        }
      } else if (route === 'wishlist') {
        if (state.activeRole === 'customer') {
          location.hash = '#/account/wishlist';
          return;
        } else {
          app.innerHTML = renderWishlistTableView(false);
        }
      } else if (route === 'dash') {
        if (param === 'seller') app.innerHTML = renderSellerDashView(subParam || 'overview');
        else if (param === 'admin') app.innerHTML = renderAdminDashView(subParam || 'overview');
        else if (param === 'hatex') app.innerHTML = renderHatexDashView(subParam || 'dashboard');
        else if (param === 'rider') app.innerHTML = renderRiderView(subParam || 'dashboard');
        else app.innerHTML = renderSellerDashView('overview');
      } else {
        app.innerHTML = renderHomeView();
        startBannerSliderTimer();
        startCouponSliderInterval();
      }

      window.scrollTo(0, 0);

      // Enforce footerPropsBar visibility strictly on landing page only
      const postHash = (typeof window !== 'undefined' && window.location?.hash ? window.location.hash : (typeof location !== 'undefined' ? location.hash : '#/')).toLowerCase();
      const postRoute = postHash.replace(/^#\/?/, '').split('?')[0].split('/')[0];
      const isHomeRoute = postRoute === '' || postRoute === '#' || postHash === '#/' || postHash === '' || postHash === '#';
      const fpBar = document.getElementById('footerPropsBar');
      if (fpBar) {
        fpBar.style.setProperty('display', isHomeRoute ? 'block' : 'none', 'important');
      }
    } catch (err) {
      console.error('HAAT rendering error:', err);
      app.innerHTML = `
        <div class="container" style="padding:60px 16px;text-align:center;">
          <h2>Unable to load page</h2>
          <p style="color:var(--text-muted);">${esc(err.message)}</p>
          <a href="javascript:void(0)" onclick="window.handleLogoClick()" class="btn-village-primary" style="margin-top:16px;display:inline-block;">Back to Workspace</a>
        </div>
      `;
    }
  }

  window.render = render;
  window.addEventListener('hashchange', render);

  // Real-time live synchronization across multiple tabs/windows
  window.addEventListener('storage', (e) => {
    if (e.key && e.key.startsWith(STORAGE_KEY)) {
      state.orders = readStorage('orders', defaultOrders);
      state.inventory = readStorage('inventory', defaultInventory);
      state.messages = readStorage('messages', defaultMessages);
      state.notifications = readStorage('notifications', defaultNotifications);
      state.coupons = readStorage('coupons', defaultCoupons);
      state.campaigns = readStorage('campaigns', defaultCampaigns);
      state.banners = readStorage('banners', defaultBanners);
      state.reports = readStorage('reports', defaultReports);
      updateGlobalHeader();
      render();
    }
  });

  // Real-time live polling for active tracking views
  setInterval(() => {
    const raw = location.hash.slice(1) || '/';
    if (raw.startsWith('/order') || raw.startsWith('/tracking') || raw.startsWith('/dash')) {
      const storedOrders = readStorage('orders', null);
      if (storedOrders && JSON.stringify(storedOrders) !== JSON.stringify(state.orders)) {
        state.orders = storedOrders;
        render();
      }
    }
  }, 2000);

  // Close menus on outside click
  document.addEventListener('click', (e) => {
    const wrap = $('#accountDropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
      window.toggleAccountMenu(false);
    }
    const notifWrap = $('#notificationDropdownWrap');
    if (notifWrap && !notifWrap.contains(e.target)) {
      window.toggleNotificationMenu(false);
    }
  });

  // App initialization
  document.addEventListener('DOMContentLoaded', () => {
    updateGlobalHeader();
    render();
  });

  if (document.readyState !== 'loading') {
    updateGlobalHeader();
    render();
  }
})();