<?php
require __DIR__.'/app/auth.php';requireLogin();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Referrer-Policy: same-origin');
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Audita · Tienda Tecnológica</title><meta name="description" content="Espacio de auditoría guiada: evidencia, comprobación y revisión humana."><link rel="icon" href="assets/mark.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__.'/assets/style.css') ?>"></head>
<body>
<a class="skip" href="#main">Saltar al contenido</a>
<aside class="sidebar">
 <a class="brand" href="./"><img src="assets/mark.svg" alt="" width="38" height="38"><span>audita<span class="brand-dot">.</span><small>EVIDENCIA ANTES QUE SUPOSICIONES</small></span></a>
 <div class="space-label">ESPACIO DE AUDITORÍA</div>
 <nav aria-label="Menú principal">
  <button class="nav-item active" data-nav="overview"><span class="nav-symbol">◈</span><span>Vista general<small>Alcance y próximos pasos</small></span></button>
  <button class="nav-item" data-nav="matrix"><span class="nav-symbol">▦</span><span>Matriz de hallazgos<small>Evidencia y clasificación</small></span><b id="nav-count">—</b></button>
  <button class="nav-item" data-nav="work"><span class="nav-symbol">✓</span><span>Pruebas y revisión<small>Comprueba, justifica y decide</small></span></button>
  <button class="nav-item" data-nav="report"><span class="nav-symbol">↗</span><span>Informe y exportación<small>Decisiones con trazabilidad</small></span></button>
 </nav>
 <div class="sidebar-note"><span class="small-icon">◎</span><strong>Tú haces la auditoría.</strong><p>Este espacio organiza la evidencia y apoya tu criterio profesional.</p></div>
 <div class="environment"><i></i> XAMPP · MySQL <span>PROTOTIPO ACADÉMICO</span></div>
</aside>
<div class="shell">
 <header class="topbar"><div class="breadcrumb">Auditorías <span>/</span> <strong>Tienda Tecnológica</strong></div><div class="top-right"><span class="local-pill"><?= getenv('AUDITORIA_ENV')==='production'?'Acceso privado':'Entorno local' ?></span><span class="signed-user"><?= htmlspecialchars($_SESSION['display_name']??'Auditor',ENT_QUOTES,'UTF-8') ?></span><form action="logout.php" method="post" id="logout-form"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8') ?>"><button class="logout-button" type="submit">Salir ↗</button></form></div></header>
 <main id="main" tabindex="-1"><div id="notice" aria-live="polite"></div><div id="view"><div class="loading"><span class="spinner"></span>Preparando tu espacio de auditoría…</div></div></main>
 <footer><span>Audita · Prototipo de apoyo a la decisión</span><span>OWASP Top 10:2025 <i>·</i> Revisión humana</span></footer>
</div>
<dialog id="history-dialog" aria-labelledby="history-title"><div class="dialog-head"><div><span class="eyebrow">TRAZABILIDAD</span><h2 id="history-title">Historial del hallazgo</h2></div><button class="icon-button" id="close-history" aria-label="Cerrar historial">×</button></div><div id="history-content"></div></dialog>
<script src="assets/app.js?v=<?= filemtime(__DIR__.'/assets/app.js') ?>" defer></script>
</body></html>
