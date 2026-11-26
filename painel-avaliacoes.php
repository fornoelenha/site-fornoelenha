<?php
// Painel para aprovar, reprovar ou apagar as avaliações enviadas pelo site.
require __DIR__ . '/api/_lib.php';
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Strict']);
session_name('flpainel'); session_start();
header('X-Frame-Options: DENY'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$cfg = fl_read('painel.json', []);
$msg = ''; $erro = '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !hash_equals($csrf, $_POST['csrf'] ?? '')) { $erro = 'Sessão expirada. Tente de novo.'; $post = false; }

// Espera entre tentativas erradas de senha
function fl_falhou() { fl_update('tentativas.json', [], function ($t) { $t[] = time(); return [array_slice($t, -20), null]; }); }
function fl_bloqueado(): bool { $t = fl_read('tentativas.json', []); $rec = array_filter($t, fn($x) => $x > time() - 900); return count($rec) >= 8; }

// 1) Primeira vez: criar a senha usando o código de configuração
if (empty($cfg['senha'])) {
    if ($post && ($_POST['acao'] ?? '') === 'criar') {
        if (fl_bloqueado()) $erro = 'Muitas tentativas. Espere 15 minutos.';
        elseif (!password_verify(strtoupper(trim($_POST['codigo'] ?? '')), FL_SETUP_HASH)) { fl_falhou(); $erro = 'Código de configuração incorreto.'; }
        elseif (mb_strlen($_POST['senha'] ?? '') < 8) $erro = 'A senha precisa ter pelo menos 8 caracteres.';
        elseif (($_POST['senha'] ?? '') !== ($_POST['senha2'] ?? '')) $erro = 'As duas senhas não são iguais.';
        else { fl_write('painel.json', ['senha' => password_hash($_POST['senha'], PASSWORD_DEFAULT)]); session_regenerate_id(true); $_SESSION['ok'] = 1; header('Location: painel-avaliacoes.php'); exit; }
    }
    $tela = 'criar';
}
// 2) Login
elseif (empty($_SESSION['ok'])) {
    if ($post && ($_POST['acao'] ?? '') === 'entrar') {
        if (fl_bloqueado()) $erro = 'Muitas tentativas. Espere 15 minutos.';
        elseif (password_verify($_POST['senha'] ?? '', $cfg['senha'])) { session_regenerate_id(true); $_SESSION['ok'] = 1; header('Location: painel-avaliacoes.php'); exit; }
        else { fl_falhou(); $erro = 'Senha incorreta.'; }
    }
    $tela = 'entrar';
}
// 3) Painel
else {
    $tela = 'painel';
    if ($post) {
        $acao = $_POST['acao'] ?? ''; $id = $_POST['id'] ?? '';
        if ($acao === 'sair') { $_SESSION = []; session_destroy(); header('Location: painel-avaliacoes.php'); exit; }
        if ($acao === 'senha') {
            if (!password_verify($_POST['atual'] ?? '', $cfg['senha'])) $erro = 'Senha atual incorreta.';
            elseif (mb_strlen($_POST['nova'] ?? '') < 8) $erro = 'A nova senha precisa ter pelo menos 8 caracteres.';
            else { fl_write('painel.json', ['senha' => password_hash($_POST['nova'], PASSWORD_DEFAULT)]); $msg = 'Senha trocada.'; }
        }
        if ($acao === 'frete') {
            $f = fl_read('frete.json', []);
            $tok = trim($_POST['token'] ?? '');
            if ($tok !== '') $f['token'] = $tok;
            if (!empty($_POST['apagar_token'])) unset($f['token']);
            $f['cep_origem'] = preg_replace('/\D/', '', $_POST['cep_origem'] ?? '');
            $f['email'] = trim($_POST['email'] ?? '');
            $f['acrescimo'] = (float)str_replace(',', '.', $_POST['acrescimo'] ?? '0');
            $f['dias_extra'] = (int)($_POST['dias_extra'] ?? 0);
            $f['sandbox'] = !empty($_POST['sandbox']);
            if ($f['cep_origem'] !== '' && strlen($f['cep_origem']) !== 8) $erro = 'O CEP de origem precisa ter 8 números.';
            else { fl_write('frete.json', $f); $msg = 'Configuração de frete salva.'; }
        }
        if (in_array($acao, ['aprovar', 'ocultar', 'apagar'], true)) {
            fl_update('avaliacoes.json', [], function ($all) use ($acao, $id) {
                foreach ($all as $k => $r) if (($r['id'] ?? '') === $id) {
                    if ($acao === 'apagar') unset($all[$k]);
                    else $all[$k]['status'] = $acao === 'aprovar' ? 'aprovada' : 'pendente';
                }
                return [array_values($all), null];
            });
            $msg = ['aprovar' => 'Avaliação publicada no site.', 'ocultar' => 'Avaliação tirada do site (voltou para pendentes).', 'apagar' => 'Avaliação apagada.'][$acao];
        }
    }
    $all = fl_read('avaliacoes.json', []);
    usort($all, fn($a, $b) => strcmp($b['criado'], $a['criado']));
    $pend = array_filter($all, fn($r) => $r['status'] !== 'aprovada');
    $apr = array_filter($all, fn($r) => $r['status'] === 'aprovada');
}

function card($r, $csrf, $aprovada) {
    $st = str_repeat('★', $r['nota']) . str_repeat('☆', 5 - $r['nota']);
    $mel = FL_MELS[$r['mel']] ?? '';
    $data = date('d/m/Y H:i', strtotime($r['criado']));
    $b = '<article class="card"><div class="top"><span class="st">' . $st . '</span><span class="dt">' . h($data) . '</span></div>'
       . '<p>“' . nl2br(h($r['texto'])) . '”</p><div class="who"><b>' . h($r['nome']) . '</b>' . ($r['cidade'] ? ' · ' . h($r['cidade']) : '') . ($mel ? ' <span class="tag">' . h($mel) . '</span>' : '') . '</div><div class="acts">';
    $f = fn($acao, $txt, $cls, $conf = '') => '<form method="post"' . ($conf ? ' onsubmit="return confirm(\'' . $conf . '\')"' : '') . '><input type="hidden" name="csrf" value="' . h($csrf) . '"><input type="hidden" name="id" value="' . h($r['id']) . '"><button name="acao" value="' . $acao . '" class="' . $cls . '">' . $txt . '</button></form>';
    $b .= $aprovada ? $f('ocultar', 'Tirar do site', 'line') : $f('aprovar', 'Publicar no site', 'red');
    $b .= $f('apagar', 'Apagar', 'ghost', 'Apagar esta avaliação de vez?');
    return $b . '</div></article>';
}
?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Painel de avaliações · Forno & Lenha</title><link rel="icon" href="img/favicon.png">
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#FFF8EA;color:#2B1A0E}
header{background:#FFC61A;border-bottom:3px solid #D3262E;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px}
header b{font-size:18px}main{max-width:900px;margin:0 auto;padding:24px 16px 60px;display:flex;flex-direction:column;gap:22px}
h2{margin:6px 0 0;font-size:20px}.box{background:#fff;border-radius:20px;padding:22px;box-shadow:0 1px 0 #EAD9B8,0 10px 30px rgba(60,36,12,.07);display:flex;flex-direction:column;gap:12px;max-width:440px;margin:30px auto 0;width:100%}
label{display:flex;flex-direction:column;gap:6px;font-weight:600;font-size:14px}input{min-height:46px;border:2px solid #EAD9B8;border-radius:12px;padding:0 14px;font-size:16px}
button{min-height:42px;border-radius:999px;border:2px solid transparent;padding:0 18px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
.red{background:#D3262E;color:#fff}.line{background:#fff;border-color:#2B1A0E;color:#2B1A0E}.ghost{background:none;color:#A81C22}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.card{background:#fff;border-radius:18px;padding:18px;display:flex;flex-direction:column;gap:10px;box-shadow:0 1px 0 #EAD9B8}
.card p{margin:0;line-height:1.5}.top{display:flex;justify-content:space-between;font-size:13px;color:#6B4A2E}.st{color:#F2A900;font-size:18px;letter-spacing:1px}
.tag{font-size:12px;background:#FFF4D6;border-radius:999px;padding:3px 9px}.acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto}.acts form{margin:0}
.msg{background:#E8F6EA;color:#1F6B2A;padding:12px 16px;border-radius:12px}.err{background:#FDE7E7;color:#A81C22;padding:12px 16px;border-radius:12px}
.empty{color:#6B4A2E}details{background:#fff;border-radius:16px;padding:14px 18px}summary{cursor:pointer;font-weight:600}
</style></head><body>
<header><b>Forno &amp; Lenha · Avaliações</b><?php if ($tela === 'painel'): ?><form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><button name="acao" value="sair" class="line">Sair</button></form><?php endif; ?></header>
<main>
<?php if ($erro): ?><div class="err"><?= h($erro) ?></div><?php endif; ?>
<?php if ($msg): ?><div class="msg"><?= h($msg) ?></div><?php endif; ?>

<?php if ($tela === 'criar'): ?>
<form method="post" class="box"><input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<h2>Primeiro acesso</h2><span>Digite o código de configuração e crie a senha do painel.</span>
<label>Código de configuração<input name="codigo" autocomplete="off" required></label>
<label>Nova senha (mínimo 8 caracteres)<input type="password" name="senha" autocomplete="new-password" required></label>
<label>Repita a senha<input type="password" name="senha2" autocomplete="new-password" required></label>
<button name="acao" value="criar" class="red">Criar senha e entrar</button></form>

<?php elseif ($tela === 'entrar'): ?>
<form method="post" class="box"><input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<h2>Entrar</h2><label>Senha<input type="password" name="senha" autocomplete="current-password" required autofocus></label>
<button name="acao" value="entrar" class="red">Entrar</button></form>

<?php else: ?>
<section><h2>Esperando aprovação (<?= count($pend) ?>)</h2><p class="empty" style="margin:4px 0 12px">Só aparecem no site depois de você clicar em “Publicar no site”.</p>
<div class="grid"><?php foreach ($pend as $r) echo card($r, $csrf, false); ?></div>
<?php if (!$pend): ?><p class="empty">Nenhuma avaliação nova.</p><?php endif; ?></section>
<section><h2>No site (<?= count($apr) ?>)</h2><div class="grid" style="margin-top:12px"><?php foreach ($apr as $r) echo card($r, $csrf, true); ?></div>
<?php if (!$apr): ?><p class="empty">Nenhuma publicada ainda.</p><?php endif; ?></section>
<?php $fr = fl_read('frete.json', []); ?>
<details<?= empty($fr['token']) ? ' open' : '' ?>><summary>Frete (Melhor Envio) · <?= !empty($fr['token']) && !empty($fr['cep_origem']) ? 'ligado' : 'desligado' ?></summary>
<form method="post" style="display:flex;flex-direction:column;gap:10px;margin-top:12px;max-width:460px"><input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<p class="empty" style="margin:0">Vale só para fora da capital de SP. Na capital o site usa a entrega própria (R$ 20, grátis acima de R$ 100).</p>
<label>Token do Melhor Envio <?= !empty($fr['token']) ? '(já salvo; deixe em branco para manter)' : '' ?><input type="password" name="token" autocomplete="off"></label>
<?php if (!empty($fr['token'])): ?><label style="flex-direction:row;align-items:center;gap:8px;font-weight:400"><input type="checkbox" name="apagar_token" value="1" style="min-height:0"> Apagar o token salvo</label><?php endif; ?>
<label>CEP de onde o mel sai<input name="cep_origem" inputmode="numeric" value="<?= h($fr['cep_origem'] ?? '') ?>" placeholder="00000000"></label>
<label>E-mail da conta do Melhor Envio<input type="email" name="email" value="<?= h($fr['email'] ?? '') ?>"></label>
<label>Somar ao frete (R$, para embalagem)<input name="acrescimo" inputmode="decimal" value="<?= h($fr['acrescimo'] ?? '0') ?>"></label>
<label>Dias a mais no prazo (tempo para despachar)<input name="dias_extra" inputmode="numeric" value="<?= h($fr['dias_extra'] ?? '1') ?>"></label>
<label style="flex-direction:row;align-items:center;gap:8px;font-weight:400"><input type="checkbox" name="sandbox" value="1" style="min-height:0" <?= !empty($fr['sandbox']) ? 'checked' : '' ?>> Token de teste (sandbox)</label>
<button name="acao" value="frete" class="red">Salvar frete</button></form></details>
<details><summary>Trocar senha</summary><form method="post" style="display:flex;flex-direction:column;gap:10px;margin-top:12px;max-width:360px"><input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<label>Senha atual<input type="password" name="atual" autocomplete="current-password"></label><label>Nova senha<input type="password" name="nova" autocomplete="new-password"></label>
<button name="acao" value="senha" class="line">Trocar senha</button></form></details>
<?php endif; ?>
</main></body></html>
