<?php
http_response_code(404);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Página não encontrada · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg"/>
<link rel="stylesheet" href="/assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
body{background:var(--gray-100);display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border-radius:18px;padding:48px 40px;text-align:center;max-width:420px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.icon{font-size:56px;color:var(--pacific);margin-bottom:16px}
h1{font-size:28px;font-weight:800;color:var(--prussian);margin:0 0 8px}
p{color:var(--gray-600);margin:0 0 28px;line-height:1.6}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--prussian);color:#fff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:700;font-size:14px;transition:background .2s}
.btn:hover{background:#012235}
</style>
</head>
<body>
<div class="box">
    <div class="icon"><i class="fa-solid fa-map-location-dot"></i></div>
    <h1>Página não encontrada</h1>
    <p>O endereço que você acessou não existe ou foi removido. Verifique o link e tente novamente.</p>
    <a href="/" class="btn"><i class="fa-solid fa-house"></i> Voltar ao início</a>
</div>
</body>
</html>
