<?php
// GET  -> lista das avaliações aprovadas (JSON)
// POST -> recebe uma avaliação nova; ela fica PENDENTE até ser aprovada no painel
require __DIR__ . '/_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = fl_read('avaliacoes.json', []);
    $ok = array_values(array_filter($all, fn($r) => ($r['status'] ?? '') === 'aprovada'));
    usort($ok, fn($a, $b) => strcmp($b['criado'], $a['criado']));
    echo json_encode(array_map('fl_public', $ok), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$in = json_decode(file_get_contents('php://input', false, null, 0, 20000), true);
if (!is_array($in)) $in = $_POST;

// Armadilha para robôs: campo invisível que pessoas não preenchem.
if (!empty($in['site'])) { echo '{"ok":true}'; exit; }
// Robôs costumam enviar em menos de 3 segundos depois de abrir o formulário.
if (isset($in['t']) && is_numeric($in['t']) && (int)$in['t'] < 3000) { echo '{"ok":true}'; exit; }

$nome   = fl_clean($in['nome'] ?? '', 60);
$cidade = fl_clean($in['cidade'] ?? '', 60);
$mel    = is_string($in['mel'] ?? null) && isset(FL_MELS[$in['mel']]) ? $in['mel'] : '';
$nota   = (int)($in['nota'] ?? 0);
$texto  = fl_clean($in['texto'] ?? '', 600);

$erros = [];
if (mb_strlen($nome) < 2) $erros[] = 'nome';
if ($nota < 1 || $nota > 5) $erros[] = 'nota';
if (mb_strlen($texto) < 10) $erros[] = 'texto';
if (preg_match('~https?://|www\.~i', $texto . $nome)) $erros[] = 'link';
if ($erros) { http_response_code(422); echo json_encode(['ok' => false, 'erros' => $erros]); exit; }

// Limite: 3 avaliações por hora por conexão.
$ip = fl_ip_hash(); $agora = time();
$bloq = fl_update('limite.json', [], function ($l) use ($ip, $agora) {
    $novo = [];
    foreach ((array)$l as $k => $ts) {
        $ts = array_values(array_filter((array)$ts, fn($t) => $t > $agora - 3600));
        if ($ts) $novo[$k] = $ts;
    }
    if (count($novo[$ip] ?? []) >= 3) return [$novo, true];
    $novo[$ip][] = $agora;
    return [$novo, false];
});
if ($bloq) { http_response_code(429); echo '{"ok":false,"erros":["limite"]}'; exit; }

fl_update('avaliacoes.json', [], function ($all) use ($nome, $cidade, $mel, $nota, $texto) {
    $all[] = ['id' => bin2hex(random_bytes(6)), 'nome' => $nome, 'cidade' => $cidade, 'mel' => $mel, 'nota' => $nota,
              'texto' => $texto, 'status' => 'pendente', 'criado' => date('c')];
    return [$all, null];
});
echo '{"ok":true}';
