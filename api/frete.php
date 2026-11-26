<?php
// Cotação de frete pelo Melhor Envio (só para fora da capital de SP; a capital tem entrega própria no site).
// O token e o CEP de origem ficam no servidor (painel-avaliacoes.php > Frete), nunca no site.
require __DIR__ . '/_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
$in = json_decode(file_get_contents('php://input', false, null, 0, 5000), true);
if (!is_array($in)) { http_response_code(400); echo '{"ok":false}'; exit; }

$cep = preg_replace('/\D/', '', (string)($in['cep'] ?? ''));
if (strlen($cep) !== 8) { http_response_code(422); echo '{"ok":false,"motivo":"cep"}'; exit; }

$cfg = fl_read('frete.json', []);
if (empty($cfg['token']) || empty($cfg['cep_origem'])) { echo '{"ok":false,"motivo":"nao_configurado"}'; exit; }

// Monta os volumes a partir dos itens do carrinho
$produtos = [];
foreach ((array)($in['itens'] ?? []) as $it) {
    $sku = (string)($it['sku'] ?? '');
    $qtd = (int)($it['qtd'] ?? 0);
    if (!isset(FL_PACOTES[$sku]) || $qtd < 1 || $qtd > 50) continue;
    $p = FL_PACOTES[$sku];
    $produtos[] = ['id' => $sku, 'width' => $p['l'], 'height' => $p['a'], 'length' => $p['c'], 'weight' => $p['kg'],
                   'insurance_value' => $p['valor'], 'quantity' => $qtd];
}
if (!$produtos) { http_response_code(422); echo '{"ok":false,"motivo":"itens"}'; exit; }

// Cache de 10 minutos por CEP + carrinho
$chave = 'frete_' . md5($cep . json_encode($produtos)) . '.json';
$cache = fl_read($chave, null);
if ($cache && ($cache['t'] ?? 0) > time() - 600) { echo json_encode($cache['r'], JSON_UNESCAPED_UNICODE); exit; }

$base = !empty($cfg['sandbox']) ? 'https://sandbox.melhorenvio.com.br' : 'https://www.melhorenvio.com.br';
$ch = curl_init($base . '/api/v2/me/shipment/calculate');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['token'],
        'User-Agent: Forno e Lenha (' . ($cfg['email'] ?? 'contato@fornoelenhaalimentos.com.br') . ')',
    ],
    CURLOPT_POSTFIELDS => json_encode(['from' => ['postal_code' => $cfg['cep_origem']], 'to' => ['postal_code' => $cep], 'products' => $produtos]),
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$lista = json_decode((string)$raw, true);
if ($code !== 200 || !is_array($lista)) {
    $det = is_array($lista) ? ($lista['message'] ?? '') : '';
    if (is_array($lista) && isset($lista['errors'])) $det .= ' ' . json_encode($lista['errors'], JSON_UNESCAPED_UNICODE);
    fl_write('frete_ultimo_erro.json', ['quando' => date('c'), 'cep' => $cep, 'http' => $code, 'detalhe' => mb_substr(trim($det), 0, 400)]);
    http_response_code(502); echo json_encode(['ok' => false, 'motivo' => 'melhor_envio', 'http' => $code, 'detalhe' => mb_substr(trim($det), 0, 300)], JSON_UNESCAPED_UNICODE); exit;
}

$opcoes = [];
foreach ($lista as $s) {
    if (!empty($s['error']) || !isset($s['price'])) continue;
    $preco = isset($s['custom_price']) ? (float)$s['custom_price'] : (float)$s['price'];
    $prazo = (int)($s['custom_delivery_time'] ?? $s['delivery_time'] ?? 0);
    $opcoes[] = ['id' => (string)$s['id'], 'nome' => trim(($s['company']['name'] ?? '') . ' ' . ($s['name'] ?? '')),
                 'preco' => round($preco + (float)($cfg['acrescimo'] ?? 0), 2), 'prazo' => $prazo + (int)($cfg['dias_extra'] ?? 0)];
}
usort($opcoes, fn($a, $b) => $a['preco'] <=> $b['preco']);
$resp = ['ok' => true, 'opcoes' => array_slice($opcoes, 0, 4)];
fl_write($chave, ['t' => time(), 'r' => $resp]);
echo json_encode($resp, JSON_UNESCAPED_UNICODE);
