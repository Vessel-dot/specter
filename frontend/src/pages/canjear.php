<?php
// ── canjear.php ───────────────────────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';
require_auth('jugador');

$active_page = 'wallet';
$page_title  = 'Canjear código';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="canjear-section">
  <div class="section-header-row">
    <h1 class="page-heading">Canjear código Specter</h1>
    <a href="/specter/wallet.php" class="btn-ghost btn-sm">← Mi cartera</a>
  </div>

  <div class="canjear-card">
    <div class="canjear-logo">◇</div>
    <h2 class="canjear-title">Ingresa tu código</h2>
    <p class="canjear-sub">Formato: XXXX-XXXX-XXXX</p>

    <div class="canjear-form">
      <div class="codigo-inputs">
        <input type="text" class="codigo-part" id="part1" maxlength="4"
               oninput="autoTab(this,'part2')" onkeydown="backTab(event,null,'part1')"
               placeholder="XXXX">
        <span class="codigo-sep">—</span>
        <input type="text" class="codigo-part" id="part2" maxlength="4"
               oninput="autoTab(this,'part3')" onkeydown="backTab(event,'part1','part2')"
               placeholder="XXXX">
        <span class="codigo-sep">—</span>
        <input type="text" class="codigo-part" id="part3" maxlength="4"
               oninput="autoTab(this,null)" onkeydown="backTab(event,'part2','part3')"
               placeholder="XXXX">
      </div>

      <div class="canjear-msg" id="canjearMsg" style="display:none"></div>

      <button class="btn-primary btn-canjear" onclick="canjearCodigo()" id="btnCanjear">
        Canjear código
      </button>
    </div>

    <p class="canjear-hint">
      Los códigos Specter se adquieren en
      <a href="/specter/tarjetas_specter.php">Tarjetas Specter</a>.
      Cada código es de uso único.
    </p>
  </div>

  <!-- Resultado exitoso -->
  <div class="canjear-success" id="canjearSuccess" style="display:none">
    <div class="confirm-icon">◇</div>
    <h2>Código canjeado</h2>
    <p id="successMsg"></p>
    <div style="margin-top:20px;display:flex;gap:10px;justify-content:center">
      <a href="/specter/wallet.php" class="btn-primary">Ver mi cartera</a>
      <a href="/specter/canjear.php" class="btn-ghost">Canjear otro</a>
    </div>
  </div>
</section>

<script>
function autoTab(el, nextId) {
    el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
    if (el.value.length === 4 && nextId)
        document.getElementById(nextId)?.focus();
}
function backTab(e, prevId, curId) {
    document.getElementById(curId).value =
        document.getElementById(curId).value.toUpperCase().replace(/[^A-Z0-9]/g,'');
    if (e.key === 'Backspace' && document.getElementById(curId).value === '' && prevId)
        document.getElementById(prevId)?.focus();
}

async function canjearCodigo() {
    const p1 = document.getElementById('part1').value.trim().toUpperCase();
    const p2 = document.getElementById('part2').value.trim().toUpperCase();
    const p3 = document.getElementById('part3').value.trim().toUpperCase();
    const codigo = `${p1}-${p2}-${p3}`;

    if (p1.length < 4 || p2.length < 4 || p3.length < 4) {
        showMsg('Completa los 12 caracteres del código.', 'error'); return;
    }

    const btn = document.getElementById('btnCanjear');
    btn.disabled = true; btn.textContent = 'Verificando...';

    const fd = csrfForm ? csrfForm() : new FormData();
    fd.append('action', 'canjear');
    fd.append('codigo', codigo);

    const data = await (await fetch('/specter/api/codigo.php', { method:'POST', body:fd })).json();

    if (data.success) {
        document.querySelector('.canjear-card').style.display = 'none';
        const s = document.getElementById('canjearSuccess');
        s.style.display = 'block';
        document.getElementById('successMsg').textContent =
            `Se acreditaron $${data.valor} a tu cartera. Nuevo saldo: $${data.nuevo_saldo}`;
    } else {
        showMsg(data.error || 'Error al canjear.', 'error');
        btn.disabled = false; btn.textContent = 'Canjear código';
    }
}

function showMsg(msg, type) {
    const el = document.getElementById('canjearMsg');
    el.textContent = msg;
    el.className = 'canjear-msg mod-notice mod-' + (type === 'error' ? 'error' : 'success');
    el.style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
