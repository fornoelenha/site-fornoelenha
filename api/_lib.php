<?php
// Biblioteca das avaliações. Os dados ficam FORA do repositório e fora do Git,
// numa pasta fl-data ao lado do public_html (ou, se não der, numa pasta protegida).
if (!defined('FL_LIB')) { define('FL_LIB', 1); } else { return; }

// Código de primeira configuração do painel (só serve até a senha ser criada).
const FL_SETUP_HASH = '$2y$10$js7w/TuA6PX8uevJdYmyOOM6Z/bV718O91cSX1m0M1bz5.pXSk4g2';
date_default_timezone_set('America/Sao_Paulo');
const FL_MELS = ['silvestre' => 'Mel Silvestre', 'organico' => 'Mel Silvestre Orgânico', 'melato' => 'Melato da Bracatinga'];

function fl_data_dir(): string {
    static $dir = null;
    if ($dir !== null) return $dir;
    $root = dirname(__DIR__);               // public_html
    $cands = [dirname($root) . '/fl-data', $root . '/.fl-data'];
    foreach ($cands as $c) {
        if (!is_dir($c)) @mkdir($c, 0750, true);
        if (is_dir($c) && is_writable($c)) {
            if (strpos($c, $root) === 0 && !file_exists($c . '/.htaccess')) {
                @file_put_contents($c . '/.htaccess', "Require all denied\nDeny from all\n");
            }
            return $dir = $c;
        }
    }
    http_response_code(500); exit('Sem pasta de dados gravável.');
}

function fl_read(string $name, $default) {
    $f = fl_data_dir() . '/' . $name;
    if (!file_exists($f)) return $default;
    $h = fopen($f, 'r'); if (!$h) return $default;
    flock($h, LOCK_SH); $raw = stream_get_contents($h); flock($h, LOCK_UN); fclose($h);
    $v = json_decode($raw, true);
    return $v === null ? $default : $v;
}

function fl_write(string $name, $value): void {
    $f = fl_data_dir() . '/' . $name;
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $f);
}

// Atualiza um arquivo com trava exclusiva (evita perder avaliação com dois envios ao mesmo tempo).
function fl_update(string $name, $default, callable $fn) {
    $f = fl_data_dir() . '/' . $name;
    $h = fopen($f, 'c+'); flock($h, LOCK_EX);
    $raw = stream_get_contents($h);
    $v = $raw === '' ? $default : (json_decode($raw, true) ?? $default);
    $out = $fn($v);
    ftruncate($h, 0); rewind($h);
    fwrite($h, json_encode($out[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($h); flock($h, LOCK_UN); fclose($h);
    return $out[1];
}

function fl_clean($s, int $max): string {
    $s = is_string($s) ? $s : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    $s = trim(preg_replace('/[ \t]+/u', ' ', $s) ?? '');
    return mb_substr($s, 0, $max);
}

function fl_ip_hash(): string {
    $salt = fl_read('salt.json', null);
    if (!$salt) { $salt = bin2hex(random_bytes(16)); fl_write('salt.json', $salt); }
    return hash('sha256', $salt . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function fl_public(array $r): array {
    return ['nome' => $r['nome'], 'cidade' => $r['cidade'], 'mel' => $r['mel'], 'nota' => $r['nota'], 'texto' => $r['texto'], 'data' => substr($r['criado'], 0, 10)];
}
