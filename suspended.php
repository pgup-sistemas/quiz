<?php
http_response_code(403);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Conta suspensa · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg"/>
<link rel="stylesheet" href="/assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body{background:var(--gray-100);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border-radius:18px;padding:48px 40px;text-align:center;max-width:440px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.icon{font-size:56px;color:var(--orange);margin-bottom:16px}
h1{font-size:26px;font-weight:800;color:var(--prussian);margin:0 0 8px}
p{color:var(--gray-600);margin:0 0 24px;line-height:1.6}
.note{background:#fff8f0;border:1px solid #ffe0b2;border-radius:10px;padding:14px 16px;font-size:13.5px;color:#7a4f10;margin-bottom:28px;text-align:left}
.note strong{display:block;margin-bottom:4px}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--prussian);color:#fff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:700;font-size:14px;transition:background .2s}
.btn:hover{background:#012235}
</style>
</head>
<body>
<div class="box">
    <div class="icon"><i class="fa-solid fa-circle-pause"></i></div>
    <h1>Conta suspensa</h1>
    <p>O acesso a este portal está temporariamente suspenso.</p>
    <div class="note">
        <strong><i class="fa-solid fa-circle-info"></i> O que aconteceu?</strong>
        A conta da empresa foi suspensa por falta de pagamento ou por solicitação administrativa.
        Entre em contato com o responsável pela sua empresa para regularizar a situação.
    </div>
    <a href="mailto:suporte@pageup.net.br" class="btn">
        <i class="fa-solid fa-envelope"></i> Contatar suporte
    </a>
</div>
</body>
</html>
