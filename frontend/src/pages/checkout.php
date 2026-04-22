<?php
// ── checkout.php — Stepper (resumen → pago → confirmación) ────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';
require_once __DIR__ . '/../../../backend/helpers/bundle.php';
require_once __DIR__ . '/../../../backend/helpers/carrito_helper.php';
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();
$carritoId = get_or_create_carrito($pdo, $jugadorId);

// Obtener ítems del carrito
$stmt = $pdo->prepare(
    'SELECT ci.id_item, ci.id_videojuego, ci.precio_item, ci.es_bundle, ci.es_regalo,
            v.titulo, v.genero, v.steam_id
     FROM carrito_item ci JOIN videojuego v ON v.id_videojuego = ci.id_videojuego
     WHERE ci.id_carrito = :cid'
);
$stmt->execute([':cid' => $carritoId]);
$items = $stmt->fetchAll();

if (empty($items)) {
    header('Location: /specter/carrito.php'); exit;
}

$bundle = calcular_bundle($items);

// Saldo disponible
$sStmt = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
$sStmt->execute([':jid' => $jugadorId]);
$saldoDisponible = (float)$sStmt->fetchColumn();

// Tarjetas guardadas
$tStmt = $pdo->prepare(
    'SELECT id_tarjeta, ultimos4, marca, nombre_titular, mes_exp, anio_exp, es_principal
     FROM tarjeta_pago WHERE id_jugador = :jid ORDER BY es_principal DESC, fecha_guardada DESC'
);
$tStmt->execute([':jid' => $jugadorId]);
$tarjetasGuardadas = $tStmt->fetchAll();

$active_page = 'carrito';
$page_title  = 'Checkout';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="checkout-section">
  <div class="checkout-header">
    <h1 class="page-heading">Checkout</h1>
    <a href="/specter/carrito.php" class="btn-ghost btn-sm">← Volver al carrito</a>
  </div>

  <!-- Stepper -->
  <div class="stepper">
    <div class="step active" id="step-indicator-1">
      <div class="step-num">1</div>
      <div class="step-label">Resumen</div>
    </div>
    <div class="step-line"></div>
    <div class="step" id="step-indicator-2">
      <div class="step-num">2</div>
      <div class="step-label">Pago</div>
    </div>
    <div class="step-line"></div>
    <div class="step" id="step-indicator-3">
      <div class="step-num">3</div>
      <div class="step-label">Confirmación</div>
    </div>
  </div>

  <div class="checkout-body">

    <!-- PASO 1: Resumen ────────────────────────────────────── -->
    <div class="checkout-step" id="checkout-step-1">
      <h2 class="step-title">Resumen del pedido</h2>
      <div class="checkout-items">
        <?php foreach ($items as $it): ?>
        <div class="checkout-item">
          <div class="checkout-item-cover"
               style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $it['steam_id'] ?>/library_600x900.jpg')">
          </div>
          <div class="checkout-item-info">
            <div class="checkout-item-title"><?= htmlspecialchars($it['titulo']) ?></div>
            <div class="checkout-item-meta">
              <?= htmlspecialchars($it['genero']) ?>
              <?php if ($it['es_bundle']): ?><span class="bundle-tag">Bundle</span><?php endif; ?>
              <?php if ($it['es_regalo']): ?><span class="gift-tag">Regalo</span><?php endif; ?>
            </div>
          </div>
          <div class="checkout-item-price">$<?= number_format((float)$it['precio_item'], 2) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="checkout-totals">
        <div class="total-row"><span>Subtotal</span><span>$<?= number_format($bundle['subtotal'], 2) ?></span></div>
        <?php if ($bundle['descuento'] > 0): ?>
        <div class="total-row total-discount">
          <span>Descuento bundle (<?= $bundle['pct'] ?>%)</span>
          <span>−$<?= number_format($bundle['descuento'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row total-final">
          <span>Total</span>
          <span id="totalAmt">$<?= number_format($bundle['total'], 2) ?></span>
        </div>
      </div>

      <button class="btn-primary btn-next" onclick="goStep(2)">
        Continuar al pago →
      </button>
    </div>

    <!-- PASO 2: Pago ───────────────────────────────────────── -->
    <div class="checkout-step" id="checkout-step-2" style="display:none">
      <h2 class="step-title">Método de pago</h2>

      <!-- Saldo Specter -->
      <div class="pay-section">
        <div class="pay-section-header">
          <label class="pay-toggle">
            <input type="checkbox" id="usarSaldo"
                   <?= $saldoDisponible <= 0 ? 'disabled' : '' ?>
                   onchange="actualizarMetodoPago()">
            <span>Usar saldo Specter</span>
          </label>
          <span class="saldo-badge">
            Disponible: <strong>$<?= number_format($saldoDisponible, 2) ?></strong>
          </span>
        </div>
        <div class="pay-saldo-info" id="saldoInfo" style="display:none">
          <div class="saldo-breakdown" id="saldoBreakdown"></div>
        </div>
      </div>

      <!-- Tarjeta -->
      <div class="pay-section" id="seccionTarjeta">
        <div class="pay-section-header">
          <span class="pay-section-title">Tarjeta de pago</span>
          <div class="card-brands">
            <span class="card-brand">VISA</span>
            <span class="card-brand">MC</span>
            <span class="card-brand">AMEX</span>
          </div>
        </div>

        <?php if (!empty($tarjetasGuardadas)): ?>
        <div class="saved-cards">
          <div class="saved-cards-label">Tarjetas guardadas</div>
          <?php foreach ($tarjetasGuardadas as $tc): ?>
          <label class="saved-card-option">
            <input type="radio" name="tarjeta_sel" value="<?= $tc['id_tarjeta'] ?>"
                   onchange="seleccionarTarjetaGuardada(<?= $tc['id_tarjeta'] ?>)"
                   <?= $tc['es_principal'] ? 'checked' : '' ?>>
            <div class="saved-card">
              <span class="card-marca"><?= $tc['marca'] ?></span>
              <span class="card-num">**** <?= $tc['ultimos4'] ?></span>
              <span class="card-exp"><?= $tc['mes_exp'] ?>/<?= $tc['anio_exp'] ?></span>
              <span class="card-nombre"><?= htmlspecialchars($tc['nombre_titular']) ?></span>
            </div>
          </label>
          <?php endforeach; ?>
          <label class="saved-card-option">
            <input type="radio" name="tarjeta_sel" value="nueva"
                   onchange="seleccionarTarjetaGuardada('nueva')">
            <div class="saved-card">+ Nueva tarjeta</div>
          </label>
        </div>
        <?php endif; ?>

        <div id="nuevaTarjetaForm" <?= !empty($tarjetasGuardadas) ? 'style="display:none"' : '' ?>>
          <div class="card-form">
            <div class="form-group">
              <label class="form-label">Número de tarjeta</label>
              <div class="card-input-wrap">
                <input type="text" id="cardNumber" class="form-input" maxlength="19"
                       placeholder="0000 0000 0000 0000"
                       oninput="formatCardNumber(this); validarLuhnLive(this)">
                <span class="card-marca-live" id="cardMarcaLive"></span>
              </div>
              <div class="luhn-status" id="luhnStatus"></div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Nombre del titular</label>
                <input type="text" id="cardNombre" class="form-input" placeholder="Como aparece en la tarjeta">
              </div>
              <div class="form-group">
                <label class="form-label">Expiración</label>
                <div style="display:flex;gap:8px">
                  <input type="text" id="cardMes"  class="form-input" maxlength="2" placeholder="MM" style="width:60px">
                  <input type="text" id="cardAnio" class="form-input" maxlength="4" placeholder="AAAA" style="width:80px">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">CVV</label>
                <input type="password" id="cardCvv" class="form-input" maxlength="4" placeholder="•••" style="width:70px">
                <span class="form-hint" style="font-size:10px">No se almacena</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="checkout-totals pay-summary">
        <div class="total-row"><span>Total a pagar</span><span id="totalFinal">$<?= number_format($bundle['total'], 2) ?></span></div>
        <div class="total-row" id="rowSaldo" style="display:none;color:var(--green)"><span>Con saldo Specter</span><span id="montSaldo"></span></div>
        <div class="total-row" id="rowTarjeta" style="display:none"><span>Con tarjeta</span><span id="montTarjeta"></span></div>
      </div>

      <div class="pay-nav">
        <button class="btn-ghost" onclick="goStep(1)">← Volver</button>
        <button class="btn-primary" onclick="confirmarPago()" id="btnConfirmar">
          Confirmar pago $<?= number_format($bundle['total'], 2) ?>
        </button>
      </div>
    </div>

    <!-- PASO 3: Confirmación ────────────────────────────────── -->
    <div class="checkout-step" id="checkout-step-3" style="display:none">
      <div class="confirm-success">
        <div class="confirm-icon">✓</div>
        <h2>¡Pago completado!</h2>
        <p class="confirm-sub" id="confirmMsg"></p>
        <div class="confirm-covers" id="confirmCovers"></div>
        <div class="confirm-actions">
          <a id="btnPDF" href="#" class="btn-ghost" target="_blank">Descargar comprobante PDF</a>
          <a href="/specter/biblioteca.php" class="btn-primary">Ir a mi biblioteca</a>
        </div>
      </div>
    </div>

  </div><!-- /.checkout-body -->
</section>

<script>
const TOTAL        = <?= $bundle['total'] ?>;
const SALDO_MAX    = <?= $saldoDisponible ?>;
let   tarjetaSelId = <?= !empty($tarjetasGuardadas) && $tarjetasGuardadas[0]['es_principal'] ? $tarjetasGuardadas[0]['id_tarjeta'] : 0 ?>;
let   pasoActual   = 1;

function goStep(n) {
    document.querySelectorAll('.checkout-step').forEach((s,i) => s.style.display = i+1===n ? 'block' : 'none');
    document.querySelectorAll('.step').forEach((s,i) => {
        s.classList.toggle('active', i+1 <= n);
        s.classList.toggle('done', i+1 < n);
    });
    pasoActual = n;
}

function seleccionarTarjetaGuardada(id) {
    tarjetaSelId = id;
    document.getElementById('nuevaTarjetaForm').style.display = id === 'nueva' ? 'block' : 'none';
}

function actualizarMetodoPago() {
    const usar = document.getElementById('usarSaldo').checked;
    const saldoUsado   = usar ? Math.min(SALDO_MAX, TOTAL) : 0;
    const tarjetaUsada = Math.max(0, TOTAL - saldoUsado);

    document.getElementById('saldoInfo').style.display = usar ? 'block' : 'none';
    document.getElementById('rowSaldo').style.display   = usar ? 'flex' : 'none';
    document.getElementById('rowTarjeta').style.display = tarjetaUsada > 0 ? 'flex' : 'none';
    document.getElementById('seccionTarjeta').style.display = tarjetaUsada > 0 ? 'block' : 'none';

    if (usar) {
        document.getElementById('montSaldo').textContent   = '−$' + saldoUsado.toFixed(2);
        document.getElementById('montTarjeta').textContent = '$' + tarjetaUsada.toFixed(2);
        document.getElementById('btnConfirmar').textContent =
            tarjetaUsada > 0
                ? `Confirmar pago $${tarjetaUsada.toFixed(2)} + saldo`
                : `Confirmar pago con saldo ($${saldoUsado.toFixed(2)})`;
    } else {
        document.getElementById('btnConfirmar').textContent = `Confirmar pago $${TOTAL.toFixed(2)}`;
    }
}

function formatCardNumber(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.match(/.{1,4}/g)?.join(' ') || v;
}

function validarLuhnLive(el) {
    const n = el.value.replace(/\D/g,'');
    const status = document.getElementById('luhnStatus');
    const marca  = document.getElementById('cardMarcaLive');
    if (n.length < 13) { status.textContent = ''; marca.textContent = ''; return; }
    // Luhn en JS
    let sum = 0, alt = false;
    for (let i = n.length-1; i >= 0; i--) {
        let d = parseInt(n[i]);
        if (alt) { d *= 2; if (d > 9) d -= 9; }
        sum += d; alt = !alt;
    }
    const ok = sum % 10 === 0;
    status.textContent  = ok ? '✓ Tarjeta válida' : '✕ Número inválido';
    status.className    = 'luhn-status ' + (ok ? 'luhn-ok' : 'luhn-err');
    // Detectar marca
    const marcas = { '^4':'VISA', '^5[1-5]':'MC', '^2[2-7]':'MC', '^3[47]':'AMEX' };
    let m = '';
    for (const [rx, lbl] of Object.entries(marcas))
        if (new RegExp(rx).test(n)) { m = lbl; break; }
    marca.textContent = m;
}

async function confirmarPago() {
    const btn = document.getElementById('btnConfirmar');
    btn.disabled = true; btn.textContent = 'Procesando...';

    const usarSaldo   = document.getElementById('usarSaldo').checked;
    const saldoUsado  = usarSaldo ? Math.min(SALDO_MAX, TOTAL) : 0;
    const tarjetaUsada = Math.max(0, TOTAL - saldoUsado);
    let metodo = 'tarjeta';
    if (usarSaldo && tarjetaUsada > 0) metodo = 'mixto';
    else if (usarSaldo) metodo = 'saldo';

    const fd = csrfForm ? csrfForm() : new FormData();
    fd.append('action', 'procesar_pago');
    fd.append('metodo_pago', metodo);

    if (tarjetaUsada > 0) {
        if (tarjetaSelId && tarjetaSelId !== 'nueva') {
            fd.append('id_tarjeta', tarjetaSelId);
        } else {
            const num = document.getElementById('cardNumber')?.value.replace(/\D/g,'') || '';
            if (!num) { alert('Ingresa el número de tarjeta.'); btn.disabled=false; btn.textContent='Confirmar pago'; return; }
            fd.append('numero_tarjeta', num);
            fd.append('nombre_titular', document.getElementById('cardNombre')?.value || '');
            fd.append('mes_exp',  document.getElementById('cardMes')?.value  || '');
            fd.append('anio_exp', document.getElementById('cardAnio')?.value || '');
        }
    }

    try {
        const data = await (await fetch('/specter/api/pago.php', { method:'POST', body:fd })).json();
        if (data.success) {
            goStep(3);
            document.getElementById('confirmMsg').textContent =
                `Compra #${String(data.id_compra).padStart(8,'0')} — Total pagado: $${data.total}`;
            const covers = document.getElementById('confirmCovers');
            covers.innerHTML = (data.juegos||[]).map(j =>
                `<img src="https://cdn.akamai.steamstatic.com/steam/apps/${j.steam_id}/library_600x900.jpg"
                      class="confirm-cover-img" alt="${j.titulo}"
                      onerror="this.style.display='none'">`
            ).join('');
            document.getElementById('btnPDF').href =
                `/specter/api/comprobante_pdf.php?id=${data.id_compra}`;
            // Auto-descarga del comprobante
            const dlLink = document.createElement('a');
            dlLink.href = `/specter/api/comprobante_pdf.php?id=${data.id_compra}&download=1`;
            dlLink.download = '';
            document.body.appendChild(dlLink);
            dlLink.click();
            document.body.removeChild(dlLink);
        } else {
            alert(data.error || 'Error al procesar el pago.');
            btn.disabled = false;
            btn.textContent = 'Confirmar pago';
        }
    } catch {
        alert('Error de red.');
        btn.disabled = false;
        btn.textContent = 'Confirmar pago';
    }
}
</script>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
