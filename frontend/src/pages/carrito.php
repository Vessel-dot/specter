<?php
// ── carrito.php (CU-04) ───────────────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();

// Obtener o crear carrito del jugador
$stmt = $pdo->prepare('SELECT id_carrito FROM carrito WHERE id_jugador = :id');
$stmt->execute([':id' => $jugadorId]);
$carrito = $stmt->fetch();
if (!$carrito) {
    $ins = $pdo->prepare('INSERT INTO carrito (id_jugador) VALUES (:id) RETURNING id_carrito');
    $ins->execute([':id' => $jugadorId]);
    $carritoId = (int)$ins->fetchColumn();
} else {
    $carritoId = (int)$carrito['id_carrito'];
}

// Items del carrito con info del juego
$items = $pdo->prepare(
    'SELECT ci.id_item, ci.precio_item, ci.es_bundle, ci.es_regalo,
            v.id_videojuego, v.titulo, v.genero, v.anio, v.steam_id,
            b.estado AS en_biblioteca
     FROM carrito_item ci
     JOIN videojuego v ON v.id_videojuego = ci.id_videojuego
     LEFT JOIN biblioteca b ON b.id_videojuego = ci.id_videojuego
                            AND b.id_jugador = :jid
     WHERE ci.id_carrito = :cid
     ORDER BY ci.id_item'
);
$items->execute([':jid' => $jugadorId, ':cid' => $carritoId]);
$items = $items->fetchAll();

// Calcular totales (ReglaBundle)
$subtotal = array_sum(array_column($items, 'precio_item'));
$bundleCount = count(array_filter($items, fn($i) => $i['es_bundle']));
$descPct = match(true) {
    $bundleCount >= 4 => 30,
    $bundleCount >= 3 => 20,
    $bundleCount >= 2 => 10,
    default           => 0,
};
$descuento = round($subtotal * $descPct / 100, 2);
$total     = round($subtotal - $descuento, 2);

// Duplicados en biblioteca
$duplicados = array_filter($items, fn($i) => $i['en_biblioteca'] && !$i['es_regalo']);

// Sugerencias (juegos no en carrito ni biblioteca)
$sugerencias = $pdo->prepare(
    'SELECT v.* FROM videojuego v
     WHERE v.id_videojuego NOT IN (
         SELECT ci.id_videojuego FROM carrito_item ci WHERE ci.id_carrito = :cid
     )
     AND v.id_videojuego NOT IN (
         SELECT b.id_videojuego FROM biblioteca b WHERE b.id_jugador = :jid
     )
     ORDER BY v.calificacion DESC LIMIT 6'
);
$sugerencias->execute([':cid' => $carritoId, ':jid' => $jugadorId]);
$sugerencias = $sugerencias->fetchAll();

$active_page = 'carrito';
$page_title  = 'Carrito · CU-04';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="carrito-section">

  <div class="section-header-row">
    <h1 class="page-heading">Carrito de Compra</h1>
    <span class="subhead">CU-04 · InterfazVistaCarritoBundle · GestorCompra</span>
  </div>

  <!-- Advertencia duplicados -->
  <?php if (!empty($duplicados)): ?>
  <div class="alert-dup">
    ⚠ Algunos juegos del carrito ya están en tu biblioteca.
    Puedes mantenerlos como <strong>regalo</strong> o eliminarlos.
  </div>
  <?php endif; ?>

  <div class="carrito-layout">

    <!-- Items + bundle tabs -->
    <div class="carrito-main">
      <div class="carrito-tabs">
        <button class="tab-btn active" onclick="switchCartTab('items', this)">
          Artículos del carrito <span class="badge"><?= count($items) ?></span>
        </button>
        <button class="tab-btn" onclick="switchCartTab('bundle', this)">
          ○ Crear Bundle
        </button>
      </div>

      <!-- Tab Items -->
      <div id="cart-tab-items">
        <?php if (empty($items)): ?>
          <div class="empty-cart">
            <div class="empty-cart-icon">⊡</div>
            <p>Tu carrito está vacío</p>
            <a href="/specter/catalogo.php" class="btn-primary">Explorar catálogo</a>
          </div>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
          <div class="cart-item" id="item-<?= $item['id_item'] ?>">
            <div class="cart-item-cover"
                 style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $item['steam_id'] ?>/library_600x900.jpg')">
            </div>
            <div class="cart-item-info">
              <div class="cart-item-title"><?= htmlspecialchars($item['titulo']) ?></div>
              <div class="cart-item-meta">
                <?= htmlspecialchars($item['genero']) ?> · <?= $item['anio'] ?>
                <?php if ($item['es_bundle']): ?>
                  <span class="bundle-tag">Bundle</span>
                <?php endif; ?>
                <?php if ($item['es_regalo']): ?>
                  <span class="gift-tag">🎁 Regalo</span>
                <?php elseif ($item['en_biblioteca']): ?>
                  <span class="dup-tag">Ya en biblioteca</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="cart-item-price">$<?= number_format((float)$item['precio_item'], 2) ?></div>
            <button class="cart-item-remove"
                    onclick="quitarItem(<?= $item['id_item'] ?>, this)"
                    title="Quitar">✕</button>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Sugerencias -->
        <?php if (!empty($sugerencias)): ?>
        <div class="sugerencias">
          <div class="sug-title">TAMBIÉN TE PUEDE INTERESAR</div>
          <div class="sug-list">
            <?php foreach ($sugerencias as $s): ?>
            <div class="sug-item">
              <div class="sug-cover"
                   style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $s['steam_id'] ?>/library_600x900.jpg')">
              </div>
              <div class="sug-info">
                <div class="sug-name"><?= htmlspecialchars($s['titulo']) ?></div>
                <div class="sug-price">$<?= number_format((float)$s['precio'], 2) ?></div>
              </div>
              <button class="btn-sug-add" onclick="agregarSugerencia(<?= $s['id_videojuego'] ?>, this)">+</button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Tab Bundle -->
      <div id="cart-tab-bundle" style="display:none">
        <div class="bundle-panel">
          <?php if ($descPct > 0): ?>
            <div class="bundle-discount-badge">−<?= $descPct ?>% activo</div>
          <?php endif; ?>
          <p class="bundle-desc">Combina juegos del catálogo para obtener descuento automático</p>
          <div class="bundle-slots" id="bundleSlots">
            <!-- Slots se gestionan via JS -->
          </div>
          <div class="bundle-rules">
            <span class="bundle-rule">2 juegos → 10% dto</span>
            <span class="bundle-rule">3 juegos → 20% dto</span>
            <span class="bundle-rule">4+ juegos → 30% dto</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Resumen del pedido -->
    <aside class="carrito-resumen">
      <h3>Resumen del pedido</h3>
      <div class="resumen-row">
        <span>Subtotal</span>
        <span id="subtotalVal">$<?= number_format($subtotal, 2) ?></span>
      </div>
      <div class="resumen-row resumen-discount" id="descuentoRow"
           <?= $descuento > 0 ? '' : 'style="display:none"' ?>>
        <span id="descuentoLabel">Descuento bundle (−<?= $descPct ?>%)</span>
        <span id="descuentoVal">−$<?= number_format($descuento, 2) ?></span>
      </div>
      <div class="resumen-divider" id="resumenDivider"></div>
      <div class="resumen-row resumen-total">
        <span>Total</span>
        <span id="totalVal">$<?= number_format($total, 2) ?></span>
      </div>
      <?php if (!empty($duplicados)): ?>
      <div class="resumen-dup-warn">
        ⚠ <?= count($duplicados) ?> juego(s) ya en tu biblioteca
      </div>
      <?php else: ?>
      <div class="resumen-ok">✓ Sin duplicados en tu biblioteca</div>
      <?php endif; ?>
      <button class="btn-checkout"
              id="checkoutBtn"
              <?= empty($items) ? 'disabled' : '' ?>
              onclick="irCheckout()">
        🖥 Procesar pago
      </button>
      <p class="resumen-secure">
        🔒 Transacción segura · executarTransaccionDigital()
      </p>
    </aside>
  </div>

</section>

<!-- Modal ticket de compra -->
<div class="modal-overlay" id="ticketOverlay" style="display:none">
  <div class="ticket-modal" id="ticketBox"></div>
</div>

<script>
function switchCartTab(tab, btn) {
  document.getElementById('cart-tab-items').style.display  = tab === 'items'  ? 'block' : 'none';
  document.getElementById('cart-tab-bundle').style.display = tab === 'bundle' ? 'block' : 'none';
  document.querySelectorAll('.carrito-tabs .tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function quitarItem(idItem, btn) {
  const fd = csrfForm();
  fd.append('action', 'quitar');
  fd.append('id_item', idItem);
  const data = await (await fetch('/specter/api/carrito.php', { method:'POST', body:fd })).json();
  if (data.success) {
    const el = document.getElementById('item-' + idItem);
    if (el) {
      el.style.transition = 'opacity .25s';
      el.style.opacity = '0';
      setTimeout(() => { el.remove(); aplicarResumen(data); }, 260);
    } else {
      aplicarResumen(data);
    }
    updateCartBadge(data.item_count);
  }
}

async function agregarSugerencia(idJuego, btn) {
  const fd = csrfForm();
  fd.append('action', 'agregar');
  fd.append('id_videojuego', idJuego);
  const data = await (await fetch('/specter/api/carrito.php', { method:'POST', body:fd })).json();
  if (data.success) {
    btn.textContent = 'Agregado';
    btn.disabled = true;
    insertarItemDOM(data.item);
    aplicarResumen(data);
    updateCartBadge(data.item_count);
  } else {
    alert(data.error || 'Error al agregar.');
  }
}

function insertarItemDOM(item) {
  const tabItems = document.getElementById('cart-tab-items');
  // Si hay estado vacío, limpiar primero
  const emptyEl = tabItems.querySelector('.empty-cart');
  if (emptyEl) emptyEl.remove();

  const html = `
    <div class="cart-item" id="item-${item.id_item}">
      <div class="cart-item-cover"
           style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/${item.steam_id}/library_600x900.jpg')">
      </div>
      <div class="cart-item-info">
        <div class="cart-item-title">${esc(item.titulo)}</div>
        <div class="cart-item-meta">${esc(item.genero)} · ${item.anio}
          ${item.es_regalo ? '<span class="gift-tag">🎁 Regalo</span>' : ''}
          ${item.es_bundle ? '<span class="bundle-tag">Bundle</span>' : ''}
        </div>
      </div>
      <div class="cart-item-price">$${parseFloat(item.precio_item).toFixed(2)}</div>
      <button class="cart-item-remove"
              onclick="quitarItem(${item.id_item}, this)"
              title="Quitar">✕</button>
    </div>`;
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  const sugsDiv = tabItems.querySelector('.sugerencias');
  if (sugsDiv) tabItems.insertBefore(tmp.firstElementChild, sugsDiv);
  else tabItems.appendChild(tmp.firstElementChild);
}

function aplicarResumen(data) {
  // Subtotal
  document.getElementById('subtotalVal').textContent =
    '$' + parseFloat(data.subtotal).toFixed(2);

  // Fila de descuento (mostrar/ocultar según si hay bundle)
  const descRow = document.getElementById('descuentoRow');
  if (data.descuento > 0) {
    document.getElementById('descuentoLabel').textContent =
      `Descuento bundle (−${data.pct}%)`;
    document.getElementById('descuentoVal').textContent =
      '−$' + parseFloat(data.descuento).toFixed(2);
    descRow.style.display = '';
  } else {
    descRow.style.display = 'none';
  }

  // Total
  document.getElementById('totalVal').textContent =
    '$' + parseFloat(data.total).toFixed(2);

  // Tab badge
  const tabBadge = document.querySelector('.carrito-tabs .badge');
  if (tabBadge) tabBadge.textContent = data.item_count;

  // Botón checkout
  document.getElementById('checkoutBtn').disabled = data.item_count === 0;

  // Estado vacío
  if (data.item_count === 0) {
    const tabItems = document.getElementById('cart-tab-items');
    tabItems.innerHTML = `
      <div class="empty-cart">
        <div class="empty-cart-icon">⊡</div>
        <p>Tu carrito está vacío</p>
        <a href="/specter/catalogo.php" class="btn-primary">Explorar catálogo</a>
      </div>`;
  }
}

function irCheckout() {
  if (document.getElementById('checkoutBtn').disabled) return;
  window.location = '/specter/checkout.php';
}

async function procesarPago() {
  const btn = document.getElementById('checkoutBtn');
  btn.disabled = true; btn.textContent = 'Procesando...';
  const fd = csrfForm();
  fd.append('action', 'comprar');
  const res  = await fetch('/specter/api/carrito.php', {
    method:'POST',
    body: fd
  });
  const data = await res.json();
  if (data.success) {
    document.getElementById('ticketOverlay').style.display = 'flex';
    document.getElementById('ticketBox').innerHTML = `
      <div class="ticket-check">✓</div>
      <h2>¡Compra completada!</h2>
      <div class="ticket-covers">
        ${(data.juegos || []).map(j =>
          `<img src="https://cdn.akamai.steamstatic.com/steam/apps/${j.steam_id}/library_600x900.jpg"
               class="ticket-cover" alt="${j.titulo}"
               onerror="this.style.display='none'">`
        ).join('')}
      </div>
      <p class="ticket-total">Total pagado: <strong>$${data.total}</strong></p>
      <button class="btn-primary" onclick="window.location='/specter/biblioteca.php'">
        Ir a mi biblioteca
      </button>`;
  } else {
    btn.disabled = false; btn.textContent = '🖥 Procesar pago';
    alert(data.error || 'Error al procesar el pago.');
  }
}
</script>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
