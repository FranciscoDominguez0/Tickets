<?php
/**
 * Herramienta visual de prueba para previsualización de Open Graph (WhatsApp / Redes) en Local
 */
require_once __DIR__ . '/../config.php';

$pages = [
    'Login de Cliente'       => APP_URL . '/upload/login.php',
    'Crear Nuevo Ticket'     => APP_URL . '/upload/open.php',
    'Portal de Tickets'      => APP_URL . '/upload/tickets.php',
    'Registro de Cliente'    => APP_URL . '/upload/registrar.php',
    'Recuperar Contraseña'   => APP_URL . '/upload/forgot.php',
    'Login de Agentes (SCP)' => APP_URL . '/upload/scp/login.php',
];

// Si se pasa una URL personalizada
$selectedUrl = isset($_GET['url']) && $_GET['url'] !== '' ? $_GET['url'] : reset($pages);

// Obtener contenido HTML de la página seleccionada
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $selectedUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$html = curl_exec($ch);
curl_close($ch);

// Extraer meta tags Open Graph
$og = [
    'title'       => '',
    'description' => '',
    'image'       => '',
    'site_name'   => '',
    'url'         => $selectedUrl,
];

if ($html) {
    if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $m)) {
        $og['title'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $m)) {
        $og['description'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
    if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $m)) {
        $og['image'] = $m[1];
    }
    if (preg_match('/<meta\s+property=["\']og:site_name["\']\s+content=["\'](.*?)["\']/i', $html, $m)) {
        $og['site_name'] = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Simulador de Previsualización WhatsApp / Open Graph</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    body { background: #0f172a; color: #f1f5f9; font-family: system-ui, -apple-system, sans-serif; padding: 24px 12px; }
    .card-preview { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
    
    /* WhatsApp Bubble Simulation */
    .wa-chat-bg { background: #0b141a; border-radius: 12px; padding: 20px; border: 1px solid #222e35; }
    .wa-bubble {
      background: #005c4b;
      color: #e9edef;
      border-radius: 8px 8px 0px 8px;
      max-width: 420px;
      margin-left: auto;
      box-shadow: 0 1px 2px rgba(0,0,0,0.3);
      overflow: hidden;
    }
    .wa-card {
      background: #025144;
      border-radius: 6px;
      margin: 4px;
      overflow: hidden;
      display: block;
      text-decoration: none;
      color: inherit;
    }
    .wa-card:hover { color: inherit; }
    .wa-image-container { width: 100%; aspect-ratio: 1.91 / 1; background: #000; overflow: hidden; }
    .wa-image-container img { width: 100%; height: 100%; object-fit: cover; }
    .wa-info { padding: 10px 12px; }
    .wa-title { font-size: 15px; font-weight: 600; line-height: 1.3; color: #e9edef; margin-bottom: 4px; }
    .wa-desc { font-size: 13px; color: #8696a0; line-height: 1.35; max-height: 38px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 4px; }
    .wa-domain { font-size: 11px; color: #8696a0; text-transform: lowercase; }
    .wa-link-text { padding: 6px 12px 8px; font-size: 14px; color: #53bdeb; word-break: break-all; }
    .wa-time { float: right; font-size: 11px; color: #8696a0; margin-left: 8px; margin-top: 4px; }

    /* Twitter / X Card Simulation */
    .tw-card { background: #000; border: 1px solid #2f3336; border-radius: 16px; overflow: hidden; max-width: 460px; }
    .tw-card img { width: 100%; aspect-ratio: 1.91 / 1; object-fit: cover; }
    .tw-info { padding: 12px 14px; }
    .tw-domain { font-size: 13px; color: #71767b; }
    .tw-title { font-size: 15px; font-weight: 700; color: #e7e9ea; margin: 3px 0; }
    .tw-desc { font-size: 14px; color: #71767b; line-height: 1.3; }

    .tag-badge { font-family: monospace; font-size: 12px; background: #0f172a; padding: 2px 6px; border-radius: 4px; border: 1px solid #334155; }
  </style>
</head>
<body>
<div class="container" style="max-width: 980px;">
  
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
    <div>
      <h1 class="h4 fw-bold mb-1"><i class="bi bi-whatsapp text-success me-2"></i>Simulador de Vista Previa (WhatsApp & Redes)</h1>
      <p class="text-muted small mb-0">Verifica cómo se renderizan las tarjetas y enlaces de tu sistema de tickets.</p>
    </div>
    <a href="<?= APP_URL ?>/upload/tickets.php" class="btn btn-sm btn-outline-light">
      <i class="bi bi-arrow-left me-1"></i> Ir al Portal
    </a>
  </div>

  <!-- Selector de página -->
  <div class="card card-preview p-3 mb-4">
    <label class="form-label small fw-semibold text-muted mb-2">Selecciona la página a inspeccionar:</label>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($pages as $label => $pUrl): ?>
        <a href="?url=<?= urlencode($pUrl) ?>" class="btn btn-sm <?= ($selectedUrl === $pUrl) ? 'btn-primary' : 'btn-outline-secondary' ?>">
          <?= htmlspecialchars($label) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="row g-4">
    <!-- WhatsApp Mockup -->
    <div class="col-lg-6">
      <div class="card card-preview p-3 h-100">
        <h6 class="fw-semibold text-success mb-3"><i class="bi bi-whatsapp me-1"></i> Así se ve en WhatsApp</h6>
        
        <div class="wa-chat-bg">
          <div class="wa-bubble">
            <a href="<?= htmlspecialchars($selectedUrl) ?>" target="_blank" class="wa-card">
              <div class="wa-image-container">
                <?php if ($og['image']): ?>
                  <img src="<?= htmlspecialchars($og['image']) ?>" alt="Preview">
                <?php else: ?>
                  <div class="text-center text-muted pt-4"><i class="bi bi-image" style="font-size: 2rem;"></i><br>Sin imagen</div>
                <?php endif; ?>
              </div>
              <div class="wa-info">
                <div class="wa-title"><?= htmlspecialchars($og['title'] ?: 'Título no encontrado') ?></div>
                <div class="wa-desc"><?= htmlspecialchars($og['description'] ?: 'Sin descripción') ?></div>
                <div class="wa-domain"><?= parse_url($selectedUrl, PHP_URL_HOST) ?></div>
              </div>
            </a>
            <div class="wa-link-text">
              <?= htmlspecialchars($selectedUrl) ?>
              <span class="wa-time"><?= date('H:i') ?> <i class="bi bi-check2-all text-info"></i></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Twitter / X Mockup -->
    <div class="col-lg-6">
      <div class="card card-preview p-3 h-100">
        <h6 class="fw-semibold text-info mb-3"><i class="bi bi-twitter-x me-1"></i> Así se ve en Twitter / Facebook / LinkedIn</h6>
        
        <div class="p-3 bg-black rounded-3 d-flex justify-content-center">
          <div class="tw-card">
            <?php if ($og['image']): ?>
              <img src="<?= htmlspecialchars($og['image']) ?>" alt="Preview">
            <?php endif; ?>
            <div class="tw-info">
              <div class="tw-domain"><?= parse_url($selectedUrl, PHP_URL_HOST) ?></div>
              <div class="tw-title"><?= htmlspecialchars($og['title']) ?></div>
              <div class="tw-desc"><?= htmlspecialchars($og['description']) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Detalle de etiquetas detectadas -->
  <div class="card card-preview p-4 mt-4">
    <h6 class="fw-semibold mb-3"><i class="bi bi-code-slash me-2 text-warning"></i>Meta Tags Open Graph detectados en el HTML</h6>
    
    <div class="table-responsive">
      <table class="table table-dark table-sm table-bordered mb-0">
        <thead>
          <tr class="text-muted small">
            <th style="width: 200px;">Propiedad</th>
            <th>Valor Detectado</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="tag-badge text-info">og:title</span></td>
            <td><strong><?= htmlspecialchars($og['title']) ?></strong></td>
          </tr>
          <tr>
            <td><span class="tag-badge text-info">og:description</span></td>
            <td><?= htmlspecialchars($og['description']) ?></td>
          </tr>
          <tr>
            <td><span class="tag-badge text-info">og:image</span></td>
            <td>
              <a href="<?= htmlspecialchars($og['image']) ?>" target="_blank" class="text-warning text-decoration-none small">
                <?= htmlspecialchars($og['image']) ?> <i class="bi bi-box-arrow-up-right ms-1"></i>
              </a>
            </td>
          </tr>
          <tr>
            <td><span class="tag-badge text-info">og:site_name</span></td>
            <td><?= htmlspecialchars($og['site_name']) ?></td>
          </tr>
          <tr>
            <td><span class="tag-badge text-info">og:url</span></td>
            <td><span class="text-muted small"><?= htmlspecialchars($selectedUrl) ?></span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
