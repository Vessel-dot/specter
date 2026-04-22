<?php
// ── resenas.php (CU-03) ───────────────────────────────────────
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/helpers/auth_guard.php';
require_auth('jugador');

$pdo       = getDB();
$jugadorId = current_id();
$preJuego  = (int)($_GET['juego'] ?? 0);

// Mis reseñas
$stmtMis = $pdo->prepare(
    'SELECT r.id_resena, r.calificacion, r.texto, r.fecha, r.estado_moderacion,
            v.titulo, v.steam_id, v.genero
     FROM resena r JOIN videojuego v ON v.id_videojuego = r.id_videojuego
     WHERE r.id_jugador = :id ORDER BY r.fecha DESC'
);
$stmtMis->execute([':id' => $jugadorId]);
$misResenas = $stmtMis->fetchAll();

// Todas las reseñas aprobadas
$todasResenas = $pdo->query(
    "SELECT r.id_resena, r.calificacion, r.texto, r.fecha,
            v.titulo, v.steam_id, j.nombre AS autor
     FROM resena r
     JOIN videojuego v ON v.id_videojuego = r.id_videojuego
     JOIN jugador j    ON j.id_jugador    = r.id_jugador
     WHERE r.estado_moderacion = 'LIMPIO'
     ORDER BY r.fecha DESC LIMIT 50"
)->fetchAll();

// Juegos disponibles para reseñar (en biblioteca pero sin reseña)
// Primero obtenemos los que ya tienen reseña
$stmtYaRes = $pdo->prepare(
    'SELECT id_videojuego FROM resena WHERE id_jugador = :id'
);
$stmtYaRes->execute([':id' => $jugadorId]);
$yaResenados = array_column($stmtYaRes->fetchAll(), 'id_videojuego');

// Luego los de biblioteca excluyendo los ya reseñados
$stmtDisp = $pdo->prepare(
    'SELECT v.id_videojuego, v.titulo, v.steam_id
     FROM biblioteca b JOIN videojuego v ON v.id_videojuego = b.id_videojuego
     WHERE b.id_jugador = :id ORDER BY v.titulo'
);
$stmtDisp->execute([':id' => $jugadorId]);
$todosEnBib = $stmtDisp->fetchAll();
$juegosDisponibles = array_filter($todosEnBib, fn($j) => !in_array($j['id_videojuego'], $yaResenados));

// Reputación
$stmtRep = $pdo->prepare('SELECT * FROM reputacion_usuario WHERE id_jugador = :id');
$stmtRep->execute([':id' => $jugadorId]);
$reputacion = $stmtRep->fetch();

$active_page = 'resenas';
$page_title  = 'Reseñas · CU-03';
require_once __DIR__ . '/../../../backend/helpers/header.php';
?>

<section class="resenas-section">

  <div class="section-header-row">
    <h1 class="page-heading">Gestión de Comunidad</h1>
    <button class="btn-primary" id="btnNuevaResena" onclick="toggleFormResena()">
      ✎ Nueva reseña
    </button>
  </div>

  <!-- Formulario nueva reseña -->
  <div class="resena-form-panel" id="resenaForm" style="display:none">
    <h3>Publicar reseña</h3>
    <?php if (empty($juegosDisponibles)): ?>
      <p class="form-hint">Ya reseñaste todos los juegos de tu biblioteca, o está vacía.</p>
    <?php else: ?>
    <div class="form-group">
      <label class="form-label">Selecciona el juego</label>
      <select id="selectJuego" class="form-input">
        <option value="">Elige un juego de tu biblioteca...</option>
        <?php foreach ($juegosDisponibles as $j): ?>
          <option value="<?= $j['id_videojuego'] ?>"
            <?= $preJuego === $j['id_videojuego'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($j['titulo']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Calificación</label>
      <div class="star-picker">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <button type="button" class="star" data-val="<?= $i ?>"
                  onclick="setStar(<?= $i ?>)">★</button>
        <?php endfor; ?>
        <span class="star-label" id="starLabel">Sin calificación</span>
      </div>
      <input type="hidden" id="calificacion" value="0">
    </div>

    <div class="form-group">
      <label class="form-label">Tu reseña</label>
      <textarea id="textoResena" class="form-input form-textarea"
                placeholder="Escribe tu opinión sobre el juego..."
                maxlength="1000"
                oninput="document.getElementById('charCount').textContent=this.value.length">
      </textarea>
      <div class="char-count"><span id="charCount">0</span>/1000</div>
    </div>

    <div class="mod-notice" id="modNotice" style="display:none"></div>

    <div class="form-actions">
      <button type="button" class="btn-ghost" onclick="toggleFormResena()">Cancelar</button>
      <button type="button" class="btn-primary" onclick="submitResena()">Publicar reseña</button>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="resenas-tabs">
    <button class="tab-btn active" onclick="switchTab('mis',this)">
      Mis reseñas (<?= count($misResenas) ?>)
    </button>
    <button class="tab-btn" onclick="switchTab('todas',this)">Todas</button>
  </div>

  <!-- Tab: Mis reseñas -->
  <div id="tab-mis">
    <?php if (empty($misResenas)): ?>
      <div class="empty-state">
        <p>Aún no has escrito ninguna reseña.</p>
        <button onclick="toggleFormResena()" class="btn-primary btn-sm">Escribir mi primera reseña</button>
      </div>
    <?php else: ?>
    <div class="resenas-grid">
      <?php foreach ($misResenas as $r): ?>
      <div class="resena-card estado-<?= strtolower($r['estado_moderacion']) ?>">
        <div class="resena-cover"
             style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $r['steam_id'] ?>/library_600x900.jpg')">
        </div>
        <div class="resena-body">
          <div class="resena-game"><?= htmlspecialchars($r['titulo']) ?></div>
          <div class="resena-stars">
            <?= str_repeat('★', (int)$r['calificacion']) . str_repeat('☆', 5 - (int)$r['calificacion']) ?>
          </div>
          <p class="resena-text"><?= htmlspecialchars($r['texto']) ?></p>
          <div class="resena-footer">
            <span class="resena-date"><?= date('d M Y', strtotime($r['fecha'])) ?></span>
            <span class="resena-status status-mod-<?= strtolower($r['estado_moderacion']) ?>">
              <?= $r['estado_moderacion'] ?>
            </span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tab: Todas -->
  <div id="tab-todas" style="display:none">
    <div class="resenas-grid">
      <?php foreach ($todasResenas as $r): ?>
      <div class="resena-card">
        <div class="resena-cover"
             style="background-image:url('https://cdn.akamai.steamstatic.com/steam/apps/<?= $r['steam_id'] ?>/library_600x900.jpg')">
        </div>
        <div class="resena-body">
          <div class="resena-game"><?= htmlspecialchars($r['titulo']) ?></div>
          <div class="resena-author">por <?= htmlspecialchars($r['autor']) ?></div>
          <div class="resena-stars"><?= str_repeat('★', (int)$r['calificacion']) ?></div>
          <p class="resena-text"><?= htmlspecialchars(substr($r['texto'], 0, 200)) ?>...</p>
          <div class="resena-date"><?= date('d M Y', strtotime($r['fecha'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</section>

<script>
const STAR_LABELS  = ['','Muy malo','Malo','Regular','Bueno','Excelente'];
const BANNED_WORDS = ['spam','fraude','fake','estafa'];

function toggleFormResena() {
    const f = document.getElementById('resenaForm');
    const b = document.getElementById('btnNuevaResena');
    const open = f.style.display === 'none';
    f.style.display = open ? 'block' : 'none';
    b.textContent = open ? '✕ Cerrar' : '✎ Nueva reseña';
}

function setStar(n) {
    document.getElementById('calificacion').value = n;
    document.getElementById('starLabel').textContent = STAR_LABELS[n];
    document.querySelectorAll('.star').forEach((s, i) => s.classList.toggle('active', i < n));
}

function switchTab(tab, btn) {
    document.getElementById('tab-mis').style.display   = tab === 'mis'   ? 'block' : 'none';
    document.getElementById('tab-todas').style.display = tab === 'todas' ? 'block' : 'none';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

async function submitResena() {
    const idJuego = document.getElementById('selectJuego').value;
    const cal     = document.getElementById('calificacion').value;
    const texto   = document.getElementById('textoResena').value.trim();

    if (!idJuego || !cal || cal < 1 || !texto) {
        showNotice('Completa todos los campos.', 'warn'); return;
    }

    showNotice('Analizando contenido...', 'info');
    await new Promise(r => setTimeout(r, 700));

    const banWord = BANNED_WORDS.find(w => texto.toLowerCase().includes(w));
    if (banWord) {
        showNotice('⚠ INFRACCION — Tu reseña infringe las normas de la comunidad.', 'error'); return;
    }

    const fd = csrfForm();
    fd.append('id_videojuego', idJuego);
    fd.append('calificacion', cal);
    fd.append('texto', texto);

    const data = await (await fetch('/specter/api/resenas.php', { method:'POST', body:fd })).json();

    if (data.success) {
        showNotice('✓ Reseña publicada con éxito. Recarga la página para verla.', 'success');
        document.getElementById('btnNuevaResena').disabled = true;
        setTimeout(() => toggleFormResena(), 1400);
    } else {
        showNotice(data.error || 'Error al publicar.', 'error');
    }
}

function showNotice(msg, type) {
    const n = document.getElementById('modNotice');
    n.textContent  = msg;
    n.className    = 'mod-notice mod-' + type;
    n.style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../../../backend/helpers/footer.php'; ?>
