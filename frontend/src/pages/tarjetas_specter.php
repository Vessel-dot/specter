<?php
// ── tarjetas_specter.php — Catálogo de denominaciones ─────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';

$pdo       = getDB();
$loggedIn  = is_logged();
$jugadorId = current_id();

$productos = $pdo->query(
    'SELECT * FROM tarjeta_specter_producto WHERE activo = TRUE ORDER BY denominacion'
)->fetchAll();

$saldo = 0;
if ($loggedIn) {
    $s = $pdo->prepare('SELECT saldo_specter FROM jugador WHERE id_jugador = :jid');
    $s->execute([':jid' => $jugadorId]);
    $saldo = (float)$s->fetchColumn();
}

$active_page = 'tarjetas';
$page_title  = 'Tarjetas Specter';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="tarjetas-section">
  <div class="section-header-row">
    <h1 class="page-heading">Tarjetas Specter</h1>
    <?php if ($loggedIn): ?>
    <div class="saldo-badge-header">
      Saldo: <strong>$<?= number_format($saldo, 2) ?></strong>
      <a href="/specter/wallet.php" class="btn-ghost btn-sm" style="margin-left:8px">Ver cartera</a>
    </div>
    <?php endif; ?>
  </div>

  <p class="page-sub">Compra una tarjeta Specter y obtén un código para regalar o usar en tu cuenta.</p>

  <!-- Grid de productos -->
  <div class="tarjetas-grid">
    <?php foreach ($productos as $p): ?>
    <div class="tarjeta-card">
      <div class="tarjeta-visual">
        <div class="tarjeta-logo">◇ SPECTER</div>
        <div class="tarjeta-denom">$<?= number_format((float)$p['denominacion'], 0) ?></div>
        <div class="tarjeta-chip"></div>
      </div>
      <div class="tarjeta-info">
        <div class="tarjeta-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
        <div class="tarjeta-desc"><?= htmlspecialchars($p['descripcion']) ?></div>
        <div class="tarjeta-precio">$<?= number_format((float)$p['denominacion'], 2) ?></div>
        <?php if ($loggedIn): ?>
        <button class="btn-primary btn-comprar-tarjeta"
                data-id="<?= $p['id_producto'] ?>"
                data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                onclick="comprarTarjeta(this)">
          Comprar
        </button>
        <?php else: ?>
        <a href="/specter/login.php?redirect=%2Fspecter%2Ftarjetas_specter.php"
           class="btn-primary">Iniciar sesión para comprar</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Modal de compra -->
<div class="modal-overlay" id="tarjetaModal" style="display:none" onclick="closeTarjetaModal(event)">
  <div class="modal-box" style="max-width:440px">
    <button class="modal-close" onclick="closeTarjetaModal()">✕</button>
    <div class="modal-body">
      <h2 class="modal-title" id="modalTarjetaNombre"></h2>
      <p style="color:var(--text-3);font-size:13px;margin-bottom:20px">
        Se generará un código único que podrás canjear o regalar.
      </p>

      <div class="form-group">
        <label class="form-label">Número de tarjeta de pago</label>
        <input type="text" id="modalCardNum" class="form-input" maxlength="19"
               placeholder="0000 0000 0000 0000"
               oninput="formatCardNumber(this); validarLuhnLiveModal(this)">
        <div class="luhn-status" id="luhnModal"></div>
      </div>

      <div id="codigoResult" style="display:none" class="codigo-result">
        <div class="codigo-generated-label">Tu código</div>
        <div class="codigo-generated" id="codigoGenerado"></div>
        <p class="codigo-instruccion">
          Guarda este código. Para canjearlo ve a
          <a href="/specter/canjear.php">Canjear código</a>.
        </p>
        <button class="btn-ghost btn-sm" onclick="copiarCodigo()">Copiar código</button>
      </div>

      <div id="btnComprarWrap">
        <button class="btn-primary" style="width:100%;margin-top:16px"
                onclick="confirmarCompraTarjeta()" id="btnComprarConf">
          Pagar y obtener código
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let modalProductoId = 0;

function comprarTarjeta(btn) {
    modalProductoId = btn.dataset.id;
    document.getElementById('modalTarjetaNombre').textContent = btn.dataset.nombre;
    document.getElementById('codigoResult').style.display = 'none';
    document.getElementById('btnComprarWrap').style.display = 'block';
    document.getElementById('modalCardNum').value = '';
    document.getElementById('luhnModal').textContent = '';
    document.getElementById('tarjetaModal').style.display = 'flex';
}
function closeTarjetaModal(e) {
    if (!e || e.target === document.getElementById('tarjetaModal'))
        document.getElementById('tarjetaModal').style.display = 'none';
}

function validarLuhnLiveModal(el) {
    const n = el.value.replace(/\D/g,'');
    const s = document.getElementById('luhnModal');
    if (n.length < 13) { s.textContent=''; return; }
    let sum=0,alt=false;
    for(let i=n.length-1;i>=0;i--){let d=parseInt(n[i]);if(alt){d*=2;if(d>9)d-=9;}sum+=d;alt=!alt;}
    const ok = sum%10===0;
    s.textContent = ok ? '✓ Válida' : '✕ Inválida';
    s.className   = 'luhn-status ' + (ok ? 'luhn-ok' : 'luhn-err');
}

function formatCardNumber(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.match(/.{1,4}/g)?.join(' ') || v;
}

async function confirmarCompraTarjeta() {
    const num = document.getElementById('modalCardNum').value.replace(/\D/g,'');
    if (num.length < 13) { alert('Ingresa un número de tarjeta válido.'); return; }

    const btn = document.getElementById('btnComprarConf');
    btn.disabled = true; btn.textContent = 'Procesando...';

    const fd = csrfForm ? csrfForm() : new FormData();
    fd.append('action', 'comprar_tarjeta');
    fd.append('id_producto', modalProductoId);
    fd.append('numero_tarjeta', num);

    const data = await (await fetch('/specter/api/codigo.php', { method:'POST', body:fd })).json();
    if (data.success) {
        document.getElementById('btnComprarWrap').style.display = 'none';
        document.getElementById('codigoGenerado').textContent = data.codigo;
        document.getElementById('codigoResult').style.display = 'block';
    } else {
        alert(data.error || 'Error al comprar.');
        btn.disabled = false; btn.textContent = 'Pagar y obtener código';
    }
}

function copiarCodigo() {
    const c = document.getElementById('codigoGenerado').textContent;
    navigator.clipboard.writeText(c).then(() => alert('Código copiado: ' + c));
}
</script>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
