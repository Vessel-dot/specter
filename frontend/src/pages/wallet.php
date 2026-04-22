<?php
// ── wallet.php — Cartera Specter ─────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();

// Saldo
$sStmt = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
$sStmt->execute([':jid' => $jugadorId]);
$saldo = (float)$sStmt->fetchColumn();

// Movimientos
$mStmt = $pdo->prepare(
    'SELECT tipo, monto, descripcion, fecha FROM movimiento_saldo
     WHERE id_jugador = :jid ORDER BY fecha DESC LIMIT 30'
);
$mStmt->execute([':jid' => $jugadorId]);
$movimientos = $mStmt->fetchAll();

$active_page = 'wallet';
$page_title  = 'Cartera Specter';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="wallet-section">
  <div class="section-header-row">
    <h1 class="page-heading">Cartera Specter</h1>
    <a href="/specter/tarjetas_specter.php" class="btn-ghost btn-sm">Comprar saldo</a>
  </div>

  <!-- Saldo card -->
  <div class="wallet-card">
    <div class="wallet-logo">◇ SPECTER</div>
    <div class="wallet-label">Saldo disponible</div>
    <div class="wallet-amount" id="walletAmount">$<?= number_format($saldo, 2) ?></div>
    <div class="wallet-actions">
      <button class="btn-primary btn-sm" onclick="toggleRecarga()">Recargar con tarjeta</button>
      <a href="/specter/canjear.php" class="btn-ghost btn-sm">Canjear código</a>
    </div>
  </div>

  <!-- Formulario recarga -->
  <div class="recarga-panel" id="recargaPanel" style="display:none">
    <h3 class="step-title">Recargar saldo</h3>
    <div class="recarga-form">
      <div class="form-group">
        <label class="form-label">Monto a recargar</label>
        <div class="monto-presets">
          <?php foreach ([100, 250, 500] as $m): ?>
          <button class="monto-btn" onclick="setMonto(<?= $m ?>)">$<?= $m ?></button>
          <?php endforeach; ?>
        </div>
        <input type="number" id="recargaMonto" class="form-input" placeholder="Otro monto" min="10" max="5000" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Número de tarjeta</label>
        <input type="text" id="recargaNum" class="form-input" maxlength="19"
               placeholder="0000 0000 0000 0000"
               oninput="formatCardNumber(this); validarLuhnLive(this)">
        <div class="luhn-status" id="luhnStatusWallet"></div>
      </div>
      <div class="form-actions">
        <button class="btn-ghost" onclick="toggleRecarga()">Cancelar</button>
        <button class="btn-primary" onclick="procesarRecarga()" id="btnRecargar">Recargar</button>
      </div>
    </div>
  </div>

  <!-- Historial -->
  <h2 class="mod-section-title">Historial de movimientos</h2>
  <div class="movimientos-list">
    <?php if (empty($movimientos)): ?>
      <div class="empty-state"><p>Aún no tienes movimientos.</p></div>
    <?php else: ?>
      <?php foreach ($movimientos as $mv): ?>
      <?php
        $esGasto = in_array($mv['tipo'], ['gasto']);
        $signo   = $esGasto ? '−' : '+';
        $colorCls= $esGasto ? 'mov-gasto' : 'mov-entrada';
      ?>
      <div class="movimiento-item">
        <div class="mov-icon mov-<?= $mv['tipo'] ?>">
          <?= match($mv['tipo']) { 'recarga'=>'↑', 'gasto'=>'↓', 'canje'=>'◇', 'devolucion'=>'↩', default=>'·' } ?>
        </div>
        <div class="mov-info">
          <div class="mov-desc"><?= htmlspecialchars($mv['descripcion']) ?></div>
          <div class="mov-fecha"><?= date('d M Y H:i', strtotime($mv['fecha'])) ?></div>
        </div>
        <div class="mov-monto <?= $colorCls ?>">
          <?= $signo ?>$<?= number_format((float)$mv['monto'], 2) ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<script>
function toggleRecarga() {
    const p = document.getElementById('recargaPanel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
}
function setMonto(m) { document.getElementById('recargaMonto').value = m; }

function formatCardNumber(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.match(/.{1,4}/g)?.join(' ') || v;
}
function validarLuhnLive(el) {
    const n = el.value.replace(/\D/g,'');
    const s = document.getElementById('luhnStatusWallet');
    if (n.length < 13) { s.textContent=''; return; }
    let sum=0,alt=false;
    for(let i=n.length-1;i>=0;i--){let d=parseInt(n[i]);if(alt){d*=2;if(d>9)d-=9;}sum+=d;alt=!alt;}
    const ok = sum%10===0;
    s.textContent = ok ? '✓ Válida' : '✕ Inválida';
    s.className   = 'luhn-status ' + (ok ? 'luhn-ok' : 'luhn-err');
}

async function procesarRecarga() {
    const btn   = document.getElementById('btnRecargar');
    const monto = document.getElementById('recargaMonto').value;
    const num   = document.getElementById('recargaNum').value.replace(/\D/g,'');
    if (!monto || parseFloat(monto) <= 0) { alert('Ingresa un monto válido.'); return; }
    if (num.length < 13) { alert('Ingresa el número de tarjeta.'); return; }

    btn.disabled = true; btn.textContent = 'Procesando...';
    const fd = csrfForm ? csrfForm() : new FormData();
    fd.append('action', 'recargar');
    fd.append('monto', monto);
    fd.append('numero_tarjeta', num);

    const data = await (await fetch('/specter/api/wallet.php', { method:'POST', body:fd })).json();
    if (data.success) {
        document.getElementById('walletAmount').textContent = '$' + data.nuevo_saldo;
        toggleRecarga();
        // Agregar movimiento al historial
        const li = document.createElement('div');
        li.className = 'movimiento-item';
        li.innerHTML = `<div class="mov-icon mov-recarga">↑</div>
          <div class="mov-info"><div class="mov-desc">Recarga con tarjeta *${num.slice(-4)}</div>
          <div class="mov-fecha">Ahora</div></div>
          <div class="mov-monto mov-entrada">+$${parseFloat(monto).toFixed(2)}</div>`;
        document.querySelector('.movimientos-list').prepend(li);
    } else {
        alert(data.error || 'Error al recargar.');
    }
    btn.disabled = false; btn.textContent = 'Recargar';
}
</script>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
