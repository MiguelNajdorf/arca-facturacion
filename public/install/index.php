<?php
declare(strict_types=1);

use ArcaFacturacion\Arca;
use ArcaFacturacion\Diagnostics\Doctor;
use ArcaFacturacion\Installation\InstallationGuide;
use ArcaFacturacion\Installation\Installer;
use ArcaFacturacion\Installation\LocalWebInstallerAccess;

require dirname(__DIR__, 2) . '/bootstrap.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

if (!(new LocalWebInstallerAccess())->isAllowed($_SERVER)) {
    http_response_code(403);
    exit('El instalador web solo puede abrirse desde esta misma computadora mediante localhost, 127.0.0.1, .local o .test.');
}

$root = dirname(__DIR__, 2);
session_name('arca_facturacion_install');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']), 'use_strict_mode' => true]);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

$installer = new Installer($root);
$store = $installer->state();
$guide = new InstallationGuide();
$message = '';
$error = '';
$onlineReport = null;
$recoveryError = null;
try { $initialEnvironment = $store->activeEnvironment(); }
catch (Throwable $exception) {
    $initialEnvironment = null;
    $recoveryError = $exception instanceof ArcaFacturacion\Exception\ArcaFacturacionException ? $exception->mensajeHumano() : 'No fue posible leer el estado de instalacion.';
}
$requested = strtolower((string) ($_GET['environment'] ?? $_POST['environment'] ?? $initialEnvironment ?? 'homologacion'));
$environment = in_array($requested, ['homologacion', 'produccion'], true) ? $requested : 'homologacion';
$action = '';
$actionSucceeded = false;

if ($recoveryError === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) { throw new RuntimeException('La sesion vencio. Recarga la pagina y volve a intentar.'); }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'start' || $action === 'resume') {
            $_SESSION['wizard_started'] = true;
            $actionSucceeded = true;
        } elseif ($action === 'navigate') {
            $_SESSION['wizard_started'] = true;
            $_SESSION['wizard_step'] = max(1, min(6, (int) ($_POST['step'] ?? 1)));
            $actionSucceeded = true;
        } elseif ($action === 'configure') {
            if ($store->environment($environment) !== null) { throw new RuntimeException('Ya existe una preparacion para ese ambiente. Se conservaron todos sus archivos; elegi Retomar instalacion.'); }
            $result = $installer->configure($_POST);
            $environment = $result['environment'];
            $_SESSION['wizard_started'] = true;
            $message = 'Configuracion, clave privada, CSR y SQLite creados. Ahora continua con el certificado.';
            $actionSucceeded = true;
        } elseif ($action === 'switch_environment') {
            $installer->activateEnvironment($environment);
            $message = 'Ambiente activo cambiado a ' . $environment . '. El otro perfil no fue modificado.';
            $actionSucceeded = true;
        } elseif ($action === 'certificate') {
            if ($store->activeEnvironment() !== $environment) { $installer->activateEnvironment($environment); }
            $upload = $_FILES['certificate'] ?? null;
            $extension = is_array($upload) ? strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION)) : '';
            if (!is_array($upload) || ($upload['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? '')) || (int) ($upload['size'] ?? 0) > 65536 || !in_array($extension, ['crt', 'cer', 'pem'], true)) {
                throw new RuntimeException('Selecciona un certificado .crt, .cer o .pem de hasta 64 KB. Nunca cargues la clave privada ni el CSR.');
            }
            $info = $installer->installCertificate((string) $upload['tmp_name']);
            $message = 'Certificado instalado y validado hasta ' . $info->validUntil->format('d/m/Y') . '. Ahora falta autorizar WSFE dentro de ARCA.';
            $actionSucceeded = true;
        } elseif ($action === 'verify_online') {
            if ($store->activeEnvironment() !== $environment) { $installer->activateEnvironment($environment); }
            $onlineReport = (new Doctor($root))->run(true);
            if (!$onlineReport->ready()) { throw new RuntimeException('ARCA todavia no autentico esta instalacion. Revisa cada control mostrado abajo.'); }
            $installer->markOnlineValidated();
            $message = 'ARCA autentico el certificado y reconocio el CUIT y el punto de venta.';
            $actionSucceeded = true;
        } elseif ($action === 'simulate') {
            if ($store->activeEnvironment() !== $environment) { $installer->activateEnvironment($environment); }
            $total = (float) ($_POST['total'] ?? 0);
            $arca = Arca::desdeEntorno($root);
            $arca->simular($arca->facturaC()->consumidorFinal()->conceptoProductos()->importeTotal($total));
            $installer->markSimulation();
            $message = 'Simulacion correcta. No se envio ningun comprobante a ARCA.';
            $actionSucceeded = true;
        } elseif ($action === 'prepare_production') {
            if ($store->environment('produccion') !== null) { throw new RuntimeException('Produccion ya esta preparada. No se sobrescribio ningun archivo.'); }
            if ((string) ($_POST['confirmation'] ?? '') !== 'PREPARAR') { throw new RuntimeException('Confirma que produccion usa credenciales y comprobantes reales.'); }
            $installer->configure(['environment' => 'produccion', 'cuit' => $_POST['cuit'] ?? '', 'point_of_sale' => $_POST['point_of_sale'] ?? '', 'alias' => $_POST['alias'] ?? 'arca-produccion'], true, null, false);
            $environment = $store->activeEnvironment() ?? 'homologacion';
            $message = 'Produccion quedo preparada por separado. Homologacion sigue activa e intacta.';
            $actionSucceeded = true;
        }
    } catch (Throwable $exception) {
        $error = $exception instanceof ArcaFacturacion\Exception\ArcaFacturacionException || $exception instanceof RuntimeException
            ? ($exception instanceof ArcaFacturacion\Exception\ArcaFacturacionException ? $exception->mensajeHumano() : $exception->getMessage())
            : 'Ocurrio un error inesperado. Ningun archivo existente fue reemplazado.';
    }
}

$error = $recoveryError ?? $error;
$state = $recoveryError === null ? $store->environment($environment) : null;
$activeEnvironment = $recoveryError === null ? $store->activeEnvironment() : null;
$currentStep = $recoveryError === null ? $store->currentStep($environment) : 1;
$complete = $state !== null && $currentStep === 6;
$started = (bool) ($_SESSION['wizard_started'] ?? false);
$maxViewStep = $state === null ? 2 : $currentStep;
if ($actionSucceeded && $action !== 'navigate') { $_SESSION['wizard_step'] = $currentStep; }
$viewStep = max(1, min($maxViewStep, (int) ($_SESSION['wizard_step'] ?? $currentStep)));
$report = (new Doctor($root))->run(false);
$installPath = __DIR__;
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$text = static fn (string $value): string => nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar arca_facturacion</title><link rel="icon" href="../assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="../assets/app.css"></head>
<body><main class="container compact">
<p class="eyebrow">Asistente local</p>

<?php if (!$started && !$complete): ?>
<section class="card welcome">
  <h1>Bienvenido a arca_facturacion</h1>
  <p class="lead">Este asistente te ayudara a preparar la facturacion electronica de ARCA.</p>
  <p>No necesitas conocer WSAA, WSFE, SOAP ni XML.</p>
  <p>Podes cerrar el navegador y continuar mas tarde. La CLI y esta pagina comparten exactamente el mismo progreso.</p>
  <?php if ($state !== null): ?>
    <div class="notice"><strong>Encontramos una instalacion en curso.</strong><br><?= $h(ucfirst($environment)) ?> esta en el paso <?= $currentStep ?> de 6. No se volveran a pedir los datos ya guardados.</div>
    <ul class="progress-list"><?php foreach ($store->checklist($environment) as $check): ?><li class="<?= $h($check['status']) ?>"><span><?= $check['status'] === 'ok' ? 'OK' : 'PENDIENTE' ?></span><?= $h($check['label']) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $state === null ? 'start' : 'resume' ?>"><button type="submit"><?= $state === null ? 'Comenzar' : 'Retomar instalacion' ?></button></form>
</section>

<?php elseif ($complete): ?>
<section class="card completion">
  <p class="step-label">Finalizado</p>
  <h1>Instalacion completada</h1>
  <p class="lead">arca_facturacion ya esta configurado.</p>
  <p>La configuracion local, la autenticacion con ARCA y la simulacion quedaron verificadas.</p>
  <div class="warning"><strong>Por seguridad, elimina ahora la carpeta del instalador web:</strong><pre><code><?= $h($installPath) ?></code></pre><p>Tambien podes renombrarla temporalmente si queres conservarla como respaldo, pero no debe quedar accesible en produccion.</p></div>
  <label class="confirm done-confirm"><input type="checkbox"> Ya elimine o renombre la carpeta install</label>
  <p>Eliminar esta carpeta no afecta la facturacion, los certificados, la configuracion, SQLite, la CLI, WSAA, WSFE ni la emision de comprobantes.</p>
  <p>La CLI seguira disponible con <code>php bin/arca instalacion:continuar</code>.</p>
</section>
<?php if ($environment === 'homologacion' && $store->environment('produccion') === null): ?>
<details class="card production"><summary>Preparar produccion por separado antes de cerrar el instalador</summary><div class="explanation"><?= $text($guide->productionInstructions()) ?></div>
<form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="prepare_production"><div class="grid"><label>CUIT de produccion<input name="cuit" value="<?= $h($state['cuit'] ?? '') ?>" required></label><label>Punto de venta Web Services real<input name="point_of_sale" type="number" min="1" max="99999" required></label><label>Alias de produccion<input name="alias" value="arca-produccion" required></label><label class="confirm"><input type="checkbox" name="confirmation" value="PREPARAR" required> Entiendo que produccion usa credenciales y comprobantes reales.</label></div><button type="submit">Preparar produccion</button></form></details>
<?php endif; ?>

<?php else: ?>
<header class="wizard-header">
  <div><p class="step-label">Paso <?= $viewStep ?> de 6</p><h1>Instalar arca_facturacion</h1></div>
  <?php if ($state !== null): ?><span class="badge active"><?= $h(ucfirst($environment)) ?> · progreso compartido</span><?php endif; ?>
</header>
<p class="lead">Podes continuar desde esta pagina o desde la CLI. Ambas usan los mismos archivos, validaciones y estado.</p>
<?php if ($message): ?><div class="notice success"><strong>Listo.</strong> <?= $h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><strong>No se completo el paso.</strong> <?= $h($error) ?></div><?php endif; ?>

<?php if ($state !== null): ?>
<section class="card status-card">
  <div class="card-heading"><h2>Estado actual</h2><span class="badge"><?= $activeEnvironment === $environment ? 'Ambiente activo' : 'Perfil guardado' ?></span></div>
  <dl class="summary"><div><dt>CUIT</dt><dd>terminado en <?= $h(substr((string) $state['cuit'], -4)) ?></dd></div><div><dt>Punto</dt><dd><?= $h($state['point_of_sale']) ?></dd></div><div><dt>Alias</dt><dd><?= $h($state['alias']) ?></dd></div></dl>
</section>
<?php endif; ?>

<?php if ($viewStep === 1): ?>
<section class="card current-step"><p class="step-label">Paso 1 de 6</p><h2>Comprobar el entorno</h2><p>Antes de crear archivos verificamos PHP, Composer y las extensiones necesarias.</p><ul class="check-list"><?php foreach ($report->checks as $check): ?><?php if ($check->section === 'Entorno' && ($check->label === 'PHP' || $check->label === 'Composer' || str_starts_with($check->label, 'Extension'))): ?><li><strong>[<?= $h($check->displayStatus()) ?>]</strong> <?= $h($check->label . ': ' . $check->message) ?></li><?php endif; ?><?php endforeach; ?></ul></section>
<?php elseif ($viewStep === 2): ?>
<section class="card current-step"><p class="step-label">Paso 2 de 6</p><h2>Elegir ambiente y crear la solicitud</h2><div class="explanation"><?= $text($guide->environmentExplanation()) ?></div><div class="explanation"><?= $text($guide->aliasExplanation()) ?></div>
<?php if ($state === null): ?><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="configure"><div class="grid">
<label>Ambiente<select name="environment"><option value="homologacion">Homologacion, recomendado para empezar</option><option value="produccion">Produccion, comprobantes reales</option></select><small>Los certificados de un ambiente no sirven en el otro.</small></label>
<label>CUIT representado<input name="cuit" inputmode="numeric" required><small>Persona o empresa por la que se emitiran comprobantes.</small></label>
<label>Punto de venta Web Services<input name="point_of_sale" type="number" min="1" max="99999" value="1" required><small>Debe estar habilitado para Web Services.</small></label>
<label>Alias descriptivo<input name="alias" value="arca" required><small>Es un nombre libre; no es una contrasena.</small></label>
</div><button type="submit">Guardar y continuar</button><p class="form-note">Se generaran la clave privada, el CSR, el perfil y SQLite mediante el mismo nucleo que usa la CLI. Nunca se reemplazan archivos existentes.</p></form>
<?php else: ?><div class="notice success">Este paso ya esta guardado. La configuracion no se volvera a generar.</div><?php endif; ?></section>
<?php elseif ($viewStep === 3 && $state !== null): ?>
<section class="card current-step"><p class="step-label">Paso 3 de 6</p><h2>Obtener el certificado de homologacion</h2><div class="explanation"><?= $text($guide->certificateInstructions($state, InstallationGuide::WEB)) ?></div>
<?php if ($currentStep === 3): ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="certificate"><input type="hidden" name="environment" value="<?= $h($environment) ?>"><label>Certificado descargado de ARCA<input type="file" name="certificate" accept=".crt,.cer,.pem" required><small>Solo .crt, .cer o .pem, hasta 64 KB. Nunca selecciones privada.key ni solicitud.csr.</small></label><button type="submit">Guardar y continuar</button></form><?php else: ?><div class="notice success">El certificado ya esta instalado y corresponde a la clave privada.</div><?php endif; ?></section>
<?php elseif ($viewStep === 4 && $state !== null): ?>
<section class="card current-step"><p class="step-label">Paso 4 de 6</p><h2>Autorizar WSFE</h2><div class="explanation"><?= $text($guide->authorizationInstructions($state, InstallationGuide::WEB)) ?></div>
<?php if ($currentStep === 4): ?><form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="verify_online"><input type="hidden" name="environment" value="<?= $h($environment) ?>"><button type="submit">Comprobar con ARCA y continuar</button></form><?php else: ?><div class="notice success">ARCA ya autentico esta instalacion y reconocio la configuracion.</div><?php endif; ?>
<?php if ($onlineReport): ?><ul class="check-list"><?php foreach ($onlineReport->checks as $check): ?><li><strong>[<?= $h($check->displayStatus()) ?>]</strong> <?= $h($check->label . ': ' . $check->message) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php elseif ($viewStep === 5 && $state !== null): ?>
<section class="card current-step"><p class="step-label">Paso 5 de 6</p><h2>Validar y simular</h2><div class="explanation"><?= $text($guide->validationInstructions($state, InstallationGuide::WEB)) ?></div>
<form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="simulate"><input type="hidden" name="environment" value="<?= $h($environment) ?>"><label>Importe de prueba<input name="total" type="number" min="0.01" step="0.01" value="15000" required><small>La simulacion es local y no emite ningun comprobante.</small></label><button type="submit">Simular y finalizar</button></form></section>
<?php endif; ?>

<nav class="wizard-nav">
<?php if ($viewStep > 1): ?><form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="navigate"><input type="hidden" name="step" value="<?= $viewStep - 1 ?>"><button class="secondary" type="submit">Anterior</button></form><?php endif; ?>
<?php if ($viewStep < $maxViewStep || ($state === null && $viewStep === 1)): ?><form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="navigate"><input type="hidden" name="step" value="<?= $viewStep + 1 ?>"><button type="submit">Siguiente</button></form><?php endif; ?>
</nav>
<aside class="warning"><strong>Como retomar:</strong> volve a esta URL o ejecuta <code>php bin/arca instalacion:continuar</code>. El progreso compartido esta en <code>storage/instalacion.json</code>.</aside>
<?php endif; ?>
</main></body></html>
