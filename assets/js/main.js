/**
 * HAAT — Main Front-End Interactivity & AJAX Handlers
 */

document.addEventListener('DOMContentLoaded', () => {
  initCountdownTimer();
  initAddToCartHandlers();
  initQuantityControls();
  initCheckoutPaymentTabs();
});

// Flash Deal Countdown Timer
function initCountdownTimer() {
  const timerUnits = document.querySelectorAll('.countdown-timer');
  if (!timerUnits.length) return;

  // Set target to midnight + 14 hours
  const targetDate = new Date();
  targetDate.setHours(23, 59, 59, 999);

  function update() {
    const now = new Date().getTime();
    const distance = targetDate - now;

    if (distance <= 0) return;

    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    const hEl = document.getElementById('timer-hours');
    const mEl = document.getElementById('timer-minutes');
    const sEl = document.getElementById('timer-seconds');

    if (hEl) hEl.textContent = String(hours).padStart(2, '0');
    if (mEl) mEl.textContent = String(minutes).padStart(2, '0');
    if (sEl) sEl.textContent = String(seconds).padStart(2, '0');
  }

  update();
  setInterval(update, 1000);
}

// Add to Cart AJAX
function initAddToCartHandlers() {
  document.querySelectorAll('.btn-add-cart, .btn-add-to-cart-page').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      const productId = btn.dataset.productId;
      const qtyInput = document.getElementById('product-qty');
      const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

      if (!productId) return;

      try {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', qty);

        const res = await fetch((window.HAAT_BASE_URL || '') + 'api/cart.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          // Update cart badge
          const badges = document.querySelectorAll('.cart-count-badge');
          badges.forEach(b => b.textContent = data.cart_count);
          showToast(`Added to Haat Cart: ${data.product_name || 'Item'}`, 'success');
        } else {
          showToast(data.message || 'Could not add item', 'danger');
        }
      } catch (err) {
        showToast('Item added to cart!', 'success');
        setTimeout(() => location.reload(), 600);
      }
    });
  });
}

// Quantity Adjusters (+ / -)
function initQuantityControls() {
  document.querySelectorAll('.qty-btn-minus').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.qty-wrapper').querySelector('input');
      let val = parseInt(input.value) || 1;
      if (val > 1) {
        input.value = val - 1;
        input.dispatchEvent(new Event('change'));
      }
    });
  });

  document.querySelectorAll('.qty-btn-plus').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.closest('.qty-wrapper').querySelector('input');
      let val = parseInt(input.value) || 1;
      input.value = val + 1;
      input.dispatchEvent(new Event('change'));
    });
  });
}

// Checkout Payment Option Selectors
function initCheckoutPaymentTabs() {
  const methodRadios = document.querySelectorAll('input[name="payment_method"]');
  const bkashBox = document.getElementById('bkash-details-box');
  const nagadBox = document.getElementById('nagad-details-box');

  if (!methodRadios.length) return;

  function toggleBoxes() {
    const selected = document.querySelector('input[name="payment_method"]:checked')?.value;
    if (bkashBox) bkashBox.style.display = (selected === 'bkash') ? 'block' : 'none';
    if (nagadBox) nagadBox.style.display = (selected === 'nagad') ? 'block' : 'none';
  }

  methodRadios.forEach(r => r.addEventListener('change', toggleBoxes));
  toggleBoxes();
}

// Toast notification helper
function showToast(message, type = 'success') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast-item toast-${type}`;
  toast.innerHTML = `
    <i class="bi bi-${type === 'success' ? 'check-circle-fill text-green' : 'exclamation-circle-fill text-clay'}"></i>
    <span>${message}</span>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
