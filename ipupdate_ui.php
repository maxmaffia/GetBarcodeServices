<?php
require_once __DIR__ . '/ipupdate_config.php';

/**
 * Restituisce tutti gli indirizzi IPv4 locali (escluso loopback).
 * Priorità: net_get_interfaces (PHP 8.3+) → ipconfig (Windows) → gethostbyname.
 */
function getLocalIPs(): array
{
    $ips = [];

    if (function_exists('net_get_interfaces')) {
        foreach (net_get_interfaces() as $iface) {
            foreach ($iface['unicast'] ?? [] as $addr) {
                $ip = $addr['address'] ?? '';
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && !str_starts_with($ip, '127.')) {
                    $ips[] = $ip;
                }
            }
        }
    }

    if (empty($ips) && PHP_OS_FAMILY === 'Windows') {
        $out = @shell_exec('ipconfig 2>&1');
        if ($out) {
            preg_match_all('/IPv4[^:]*:\s*(\d+\.\d+\.\d+\.\d+)/i', $out, $m);
            foreach ($m[1] as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    && !str_starts_with($ip, '127.')) {
                    $ips[] = $ip;
                }
            }
        }
    }

    if (empty($ips)) {
        $ip = gethostbyname(gethostname());
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !str_starts_with($ip, '127.')) {
            $ips[] = $ip;
        }
    }

    return array_values(array_unique($ips));
}

/* ── AJAX PROXY ──────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'update') {
    header('Content-Type: application/json; charset=utf-8');

    $id_azienda = (int)   ($_POST['id_azienda'] ?? 0);
    $azienda    = trim(    $_POST['azienda']    ?? '');
    $server     = trim(    $_POST['server']     ?? '');
    $porta      = (int)   ($_POST['porta']      ?? 0);

    if ($id_azienda <= 0 || $azienda === ''
        || !filter_var($server, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        || $porta < 1 || $porta > 65535) {
        http_response_code(400);
        echo json_encode(['result' => false, 'error' => 'Dati non validi. Verificare i campi.']);
        exit;
    }

    $payload  = json_encode(
        ['id_azienda' => $id_azienda, 'azienda' => $azienda, 'server' => $server, 'porta' => $porta],
        JSON_UNESCAPED_UNICODE
    );
    $endpoint = 'https://www.godrop.me/services/';
    $hdrs     = ['Content-Type: application/json', 'X-API-Key: ' . IPUPDATE_API_KEY, 'Content-Length: ' . strlen($payload)];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $hdrs,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp !== false && $code >= 200 && $code < 300) {
            echo $resp;
        } else {
            http_response_code(502);
            echo json_encode(['result' => false, 'error' => $err ?: 'HTTP ' . $code]);
        }
    } else {
        $ctx  = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $hdrs) . "\r\n",
            'content' => $payload,
            'timeout' => 15,
        ]]);
        $resp = @file_get_contents($endpoint, false, $ctx);
        echo $resp !== false
            ? $resp
            : json_encode(['result' => false, 'error' => 'Impossibile contattare il servizio']);
    }
    exit;
}

/* ── DATI PAGINA ─────────────────────────────────────────────────────────── */
$localIPs  = getLocalIPs();
$defaultIp = $localIPs[0] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GetBarcodeServices — Configurazione Server</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          crossorigin="anonymous">
    <style>
        body {
            background: linear-gradient(135deg, #dbeafe 0%, #ede9fe 100%);
            min-height: 100vh;
        }
        .main-card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 16px 48px rgba(13,110,253,.14), 0 2px 8px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .card-hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #6d28d9 100%);
            padding: 2.25rem 1.5rem 2rem;
        }
        .hero-icon-wrap {
            width: 68px; height: 68px;
            background: rgba(255,255,255,.18);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .form-control, .form-select {
            border-radius: .6rem;
            border-color: #e2e8f0;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            border-color: #6d28d9;
            box-shadow: 0 0 0 .22rem rgba(109,40,217,.14);
        }
        .input-group .form-control:not(:last-child) { border-radius: .6rem 0 0 .6rem; }
        .input-group .btn-ip-picker {
            border-radius: 0 .6rem .6rem 0;
            border-color: #e2e8f0;
            background: #f8fafc;
            color: #6366f1;
            transition: background .15s, color .15s;
        }
        .input-group .btn-ip-picker:hover { background: #ede9fe; color: #4f46e5; }
        .form-label { font-weight: 600; font-size: .875rem; color: #374151; margin-bottom: .4rem; }
        .section-divider {
            color: #94a3b8;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-bottom: .75rem;
        }
        .port-preset {
            font-size: .78rem;
            padding: .2rem .55rem;
            border-radius: .45rem;
            border-color: #e2e8f0;
            color: #6366f1;
        }
        .port-preset:hover { background: #ede9fe; border-color: #a5b4fc; color: #4f46e5; }
        .btn-submit {
            background: linear-gradient(135deg, #1d4ed8 0%, #6d28d9 100%);
            border: none;
            border-radius: .7rem;
            font-weight: 600;
            letter-spacing: .03em;
            padding: .75rem;
            transition: opacity .2s, transform .1s;
        }
        .btn-submit:hover:not(:disabled) { opacity: .92; transform: translateY(-1px); }
        .btn-submit:disabled { opacity: .65; }
        #resultBox { display: none; border-radius: .75rem; font-size: .9rem; }
        .ip-item-badge { font-size: .7rem; padding: .15rem .4rem; border-radius: .3rem; }
        .dropdown-menu { border: none; box-shadow: 0 4px 24px rgba(0,0,0,.12); border-radius: .75rem; }
        .dropdown-item { border-radius: .5rem; margin: 0 .25rem; width: calc(100% - .5rem); }
        .dropdown-item:active { background-color: #ede9fe; color: #4f46e5; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5 px-3">

<div style="width: 100%; max-width: 520px;">

    <div class="main-card">

        <!-- ── Hero ── -->
        <div class="card-hero text-white text-center">
            <div class="hero-icon-wrap mb-3 mx-auto">
                <i class="bi bi-hdd-network-fill" style="font-size:1.85rem;"></i>
            </div>
            <h4 class="fw-bold mb-1">Configurazione Server</h4>
            <p class="mb-0 small" style="opacity:.75;">GetBarcodeServices &mdash; Registrazione IP</p>
        </div>

        <!-- ── Body ── -->
        <div class="card-body bg-white p-4">
            <form id="configForm" novalidate autocomplete="off">

                <!-- Azienda -->
                <p class="section-divider"><i class="bi bi-building me-1"></i>Dati Azienda</p>
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <label class="form-label" for="id_azienda">
                            <i class="bi bi-hash text-primary me-1"></i>ID
                        </label>
                        <input type="number" class="form-control" id="id_azienda" name="id_azienda"
                               value="<?= htmlspecialchars((string) IPUPDATE_ID_AZIENDA, ENT_QUOTES) ?>"
                               min="1" required placeholder="1">
                        <div class="invalid-feedback">Obbligatorio</div>
                    </div>
                    <div class="col-8">
                        <label class="form-label" for="azienda">
                            <i class="bi bi-briefcase text-primary me-1"></i>Ragione sociale
                        </label>
                        <input type="text" class="form-control" id="azienda" name="azienda"
                               value="<?= htmlspecialchars((string) IPUPDATE_AZIENDA, ENT_QUOTES) ?>"
                               required placeholder="Es. Acme Srl">
                        <div class="invalid-feedback">Campo obbligatorio</div>
                    </div>
                </div>

                <!-- Server -->
                <p class="section-divider"><i class="bi bi-hdd me-1"></i>Indirizzo di rete</p>

                <!-- IP -->
                <div class="mb-3">
                    <label class="form-label" for="server">
                        <i class="bi bi-ethernet text-primary me-1"></i>Server IP
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace" id="server" name="server"
                               value="<?= htmlspecialchars($defaultIp, ENT_QUOTES) ?>"
                               placeholder="192.168.1.100" autocomplete="off">
                        <button class="btn btn-ip-picker border" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false"
                                title="Scegli un IP rilevato automaticamente">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end py-2" style="min-width:230px;">
                            <?php if (!empty($localIPs)): ?>
                                <li>
                                    <span class="dropdown-header small text-muted fw-semibold">
                                        <i class="bi bi-laptop me-1"></i>IP rilevati sulla macchina
                                    </span>
                                </li>
                                <?php foreach ($localIPs as $ip): ?>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 ip-pick py-2"
                                            type="button"
                                            data-ip="<?= htmlspecialchars($ip, ENT_QUOTES) ?>">
                                        <span class="badge bg-primary bg-opacity-10 text-primary ip-item-badge">IPv4</span>
                                        <span class="font-monospace"><?= htmlspecialchars($ip, ENT_QUOTES) ?></span>
                                    </button>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li>
                                    <span class="dropdown-item disabled text-muted small">
                                        <i class="bi bi-exclamation-circle me-1"></i>Nessun IP rilevato
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="text-danger small mt-1 d-none" id="server-error">
                        <i class="bi bi-exclamation-circle-fill me-1"></i>Inserisci un indirizzo IPv4 valido (es.&nbsp;192.168.1.100)
                    </div>
                </div>

                <!-- Porta -->
                <div class="mb-4">
                    <label class="form-label" for="porta">
                        <i class="bi bi-plug text-primary me-1"></i>Porta di ascolto
                    </label>
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <input type="number" class="form-control" id="porta" name="porta"
                                   value="<?= htmlspecialchars((string) IPUPDATE_PORTA, ENT_QUOTES) ?>"
                                   min="1" max="65535" required placeholder="80"
                                   style="width:110px;">
                            <div class="invalid-feedback">Valore 1&ndash;65535</div>
                        </div>
                        <div class="col">
                            <div class="d-flex flex-wrap gap-1">
                                <button type="button" class="btn port-preset" data-port="80">80</button>
                                <button type="button" class="btn port-preset" data-port="443">443</button>
                                <button type="button" class="btn port-preset" data-port="8080">8080</button>
                                <button type="button" class="btn port-preset" data-port="8443">8443</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-text text-muted mt-1">Porta TCP (1&nbsp;&ndash;&nbsp;65535)</div>
                </div>

                <!-- Result -->
                <div id="resultBox" class="alert mb-3" role="alert"></div>

                <!-- Submit -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-submit text-white btn-lg" id="submitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="spinner" role="status"></span>
                        <i class="bi bi-cloud-upload-fill me-2" id="submitIcon"></i>
                        Aggiorna configurazione
                    </button>
                </div>

            </form>
        </div>

        <!-- ── Footer ── -->
        <div class="bg-light text-center py-2 px-3 border-top">
            <small class="text-muted">
                <i class="bi bi-shield-lock-fill text-success me-1"></i>
                <code class="text-primary small">https://www.godrop.me/services/</code>
            </small>
        </div>

    </div><!-- /main-card -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
<script>
/* ── IP picker ───────────────────────────────────────────────────────────── */
document.querySelectorAll('.ip-pick').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('server').value = btn.dataset.ip;
        document.getElementById('server').classList.remove('is-invalid');
        document.getElementById('server-error').classList.add('d-none');
    });
});

/* ── Preset porte ────────────────────────────────────────────────────────── */
document.querySelectorAll('.port-preset').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('porta').value = btn.dataset.port;
        document.getElementById('porta').classList.remove('is-invalid');
    });
});

/* ── Validazione IPv4 ────────────────────────────────────────────────────── */
function isValidIPv4(v) {
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(v.trim())) return false;
    return v.trim().split('.').every(n => Number(n) >= 0 && Number(n) <= 255);
}

/* ── Submit ──────────────────────────────────────────────────────────────── */
document.getElementById('configForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const form        = this;
    const serverInput = document.getElementById('server');
    const serverError = document.getElementById('server-error');
    const resultBox   = document.getElementById('resultBox');

    // Reset
    form.classList.remove('was-validated');
    serverInput.classList.remove('is-invalid');
    serverError.classList.add('d-none');
    resultBox.style.display = 'none';

    // Validate IP
    let ipOk = isValidIPv4(serverInput.value);
    if (!ipOk) {
        serverInput.classList.add('is-invalid');
        serverError.classList.remove('d-none');
    }

    // Bootstrap native validation per gli altri campi
    form.classList.add('was-validated');
    if (!form.checkValidity() || !ipOk) {
        serverInput.focus();
        return;
    }

    // Loading
    const btn     = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');
    const icon    = document.getElementById('submitIcon');
    btn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');

    try {
        const fd = new FormData();
        fd.append('_action',    'update');
        fd.append('id_azienda', document.getElementById('id_azienda').value);
        fd.append('azienda',    document.getElementById('azienda').value.trim());
        fd.append('server',     serverInput.value.trim());
        fd.append('porta',      document.getElementById('porta').value);

        const resp = await fetch(window.location.pathname, { method: 'POST', body: fd });
        const data = await resp.json();

        resultBox.style.display = '';
        if (data.result) {
            resultBox.className = 'alert alert-success mb-3';
            resultBox.innerHTML =
                '<i class="bi bi-check-circle-fill me-2"></i>' +
                '<strong>Configurazione aggiornata</strong> correttamente sul server remoto.';
            form.classList.remove('was-validated');
        } else {
            resultBox.className = 'alert alert-danger mb-3';
            resultBox.innerHTML =
                '<i class="bi bi-exclamation-triangle-fill me-2"></i>' +
                (data.error ?? 'Errore sconosciuto');
        }
        resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    } catch {
        resultBox.style.display = '';
        resultBox.className = 'alert alert-danger mb-3';
        resultBox.innerHTML =
            '<i class="bi bi-wifi-off me-2"></i>Impossibile contattare il servizio locale.';
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
    }
});
</script>
</body>
</html>
