/* ════════════════════════════════════════════════════════════
   Sistema Specter — app.js
   DM Sans + Space Mono | 3 temas
════════════════════════════════════════════════════════════ */

// ── Tema ──────────────────────────────────────────────────────
function setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    document.cookie = `tema=${theme};path=/;max-age=${60*60*24*365}`;
}
(function () {
    const m = document.cookie.match(/tema=([^;]+)/);
    if (m) document.documentElement.setAttribute('data-theme', m[1]);
})();

// ── Sidebar ───────────────────────────────────────────────────
(function () {
    if (localStorage.getItem('sidebar-collapsed') === '1')
        document.body.classList.add('sidebar-collapsed');
})();
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed',
        document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
}

// ── CSRF helper ───────────────────────────────────────────────
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
function csrfForm() {
    const fd = new FormData();
    fd.append('csrf_token', csrfToken());
    return fd;
}

// ── Hero Slider ───────────────────────────────────────────────
let heroIndex = 0;
function goToSlide(i) {
    const slides = document.querySelectorAll('.hero-slide');
    const dots   = document.querySelectorAll('.hero-dot');
    if (!slides.length) return;
    slides[heroIndex].classList.remove('active');
    if (dots[heroIndex]) dots[heroIndex].classList.remove('active');
    heroIndex = ((i % slides.length) + slides.length) % slides.length;
    slides[heroIndex].classList.add('active');
    if (dots[heroIndex]) dots[heroIndex].classList.add('active');
}
function nextSlide() { goToSlide(heroIndex + 1); }
function prevSlide() { goToSlide(heroIndex - 1); }

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelectorAll('.hero-slide').length > 1)
        setInterval(nextSlide, 5000);
});

// ── Modal detalle ─────────────────────────────────────────────
async function openModal(id) {
    const overlay = document.getElementById('modalOverlay');
    const box     = document.getElementById('modalBox');
    if (!overlay || !box) return;
    overlay.style.display = 'flex';
    box.innerHTML = '<div class="modal-loading">Cargando...</div>';

    try {
        const data = await (await fetch(`/specter/api/catalogo.php?action=detalle&id=${id}`)).json();
        if (!data.success) { box.innerHTML = '<p style="padding:20px;color:var(--red)">Error.</p>'; return; }

        const j        = data.juego;
        const inLib    = !!j.estado;
        const inCart   = data.in_cart;
        const loggedIn = document.body.dataset.loggedin === '1';
        const stars    = j.mi_cal
            ? '★'.repeat(parseInt(j.mi_cal)) + '☆'.repeat(5 - parseInt(j.mi_cal))
            : '';

        box.innerHTML = `
          <button class="modal-close" onclick="closeModal()">✕</button>
          <div class="modal-hero"
               style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/${j.steam_id}/capsule_616x353.jpg')">
            <div class="modal-hero-gradient"></div>
            <img class="modal-cover-img"
                 src="https://cdn.akamai.steamstatic.com/steam/apps/${j.steam_id}/library_600x900.jpg"
                 alt="${esc(j.titulo)}" onerror="this.style.display='none'">
          </div>
          <div class="modal-body">
            <h2 class="modal-title">${esc(j.titulo)}</h2>
            <div class="modal-meta">
              <span class="genre-badge">${esc(j.genero || '')}</span>
              <span class="rating">★ ${j.calificacion}</span>
              <span class="modal-year">${j.anio}</span>
              ${j.estado ? `<span class="status-badge status-${j.estado}">${j.estado}</span>` : ''}
            </div>
            <p class="modal-desc">${esc(j.descripcion || '')}</p>
            ${j.progreso > 0 ? `
              <div class="progress-row">
                <span class="progress-label">PROGRESO — ${j.progreso}%</span>
                <div class="progress-bar">
                  <div class="progress-fill" style="width:${j.progreso}%;background:${j.estado==='Completado'?'var(--green)':'var(--accent)'}"></div>
                </div>
              </div>` : ''}
            ${j.mi_resena ? `
              <div class="modal-review">
                <div style="color:var(--amber);margin-bottom:4px">${stars}</div>
                <p>${esc(j.mi_resena)}</p>
              </div>` : ''}
            <div class="modal-actions">
              ${!inLib ? `
                <span class="modal-price">$${parseFloat(j.precio).toFixed(2)}</span>
                ${loggedIn
                  ? `<button class="btn-primary ${inCart ? 'btn-cart-remove' : ''}" id="modalCartBtn"
                             onclick="toggleCartFromModal(${j.id_videojuego}, this)">
                       ${inCart ? 'Quitar del carrito' : 'Agregar al carrito'}
                     </button>`
                  : `<a href="/specter/login.php?redirect=%2Fspecter%2Fcarrito.php" class="btn-primary">
                       Agregar al carrito
                     </a>`}
              ` : `
                <span class="in-lib-label">En tu biblioteca</span>
                ${loggedIn ? `
                  <button class="btn-ghost"
                          style="border-color:var(--purple);color:var(--purple)"
                          onclick="regaloFromModal(${j.id_videojuego})">
                    Comprar como regalo
                  </button>` : ''}
              `}
              ${j.estado && !j.mi_resena && loggedIn
                ? `<a href="/specter/resenas.php?juego=${j.id_videojuego}" class="btn-ghost">Reseñar</a>`
                : ''}
            </div>
          </div>`;
    } catch { box.innerHTML = '<p style="padding:20px;color:var(--red)">Error de red.</p>'; }
}
function closeModal(event) {
    const o = document.getElementById('modalOverlay');
    if (o && (!event || event.target === o)) o.style.display = 'none';
}

async function toggleCartFromModal(id, btn) {
    const fd = csrfForm();
    fd.append('action', 'toggle_carrito');
    fd.append('id_videojuego', id);
    const data = await (await fetch('/specter/api/catalogo.php', { method:'POST', body:fd })).json();
    if (data.success) {
        if (data.action === 'added') { btn.textContent = 'Quitar del carrito'; btn.classList.add('btn-cart-remove'); }
        else { btn.textContent = 'Agregar al carrito'; btn.classList.remove('btn-cart-remove'); }
        updateCartBadge(data.cart_count);
    }
}
async function regaloFromModal(id) {
    const fd = csrfForm();
    fd.append('action', 'toggle_carrito');
    fd.append('id_videojuego', id);
    fd.append('es_regalo', '1');
    const data = await (await fetch('/specter/api/catalogo.php', { method:'POST', body:fd })).json();
    if (data.success) { closeModal(); window.location = '/specter/carrito.php'; }
}

// ── Catalog cart buttons ──────────────────────────────────────
async function toggleCarrito(id, inCart, btn) {
    if (document.body.dataset.loggedin !== '1') {
        window.location = '/specter/login.php?redirect=%2Fspecter%2Fcarrito.php'; return;
    }
    const fd = csrfForm();
    fd.append('action', 'toggle_carrito');
    fd.append('id_videojuego', id);
    const data = await (await fetch('/specter/api/catalogo.php', { method:'POST', body:fd })).json();
    if (data.success) {
        if (data.action === 'added') { btn.textContent = 'Quitar'; btn.classList.add('btn-cart-remove'); btn.onclick = () => toggleCarrito(id, true, btn); }
        else { btn.textContent = 'Agregar'; btn.classList.remove('btn-cart-remove'); btn.onclick = () => toggleCarrito(id, false, btn); }
        updateCartBadge(data.cart_count);
    }
}
async function agregarRegalo(id, btn) {
    btn.disabled = true;
    const fd = csrfForm();
    fd.append('action', 'toggle_carrito');
    fd.append('id_videojuego', id);
    fd.append('es_regalo', '1');
    const data = await (await fetch('/specter/api/catalogo.php', { method:'POST', body:fd })).json();
    if (data.success) btn.textContent = 'Regalo agregado';
    else { btn.disabled = false; alert(data.error || 'Error.'); }
}

// ── Biblioteca ────────────────────────────────────────────────
async function actualizarProgreso(el) {
    const val = el.value;
    const bar = el.parentElement.querySelector('.progress-fill');
    const lbl = el.parentElement.querySelector('.progress-label');
    if (bar) bar.style.width = val + '%';
    if (lbl) lbl.textContent = `PROGRESO — ${val}%`;
    const fd = csrfForm();
    fd.append('action', 'update_bib');
    fd.append('id_videojuego', el.dataset.id);
    fd.append('progreso', val);
    await fetch('/specter/api/catalogo.php', { method:'POST', body:fd });
}
async function actualizarEstado(el) {
    const fd = csrfForm();
    fd.append('action', 'update_bib');
    fd.append('id_videojuego', el.dataset.id);
    fd.append('estado', el.value);
    await fetch('/specter/api/catalogo.php', { method:'POST', body:fd });
}

// ── Cart badge ────────────────────────────────────────────────
function updateCartBadge(count) {
    const badge = document.getElementById('cartNavBadge');
    if (!badge) return;
    badge.textContent = count;
    badge.style.display = count > 0 ? '' : 'none';
}

// ── Util ──────────────────────────────────────────────────────
function esc(s) {
    return String(s||'').replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
}
