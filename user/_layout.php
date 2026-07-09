<?php
function userPageHead(string $title, string $desc = ''): void { ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<meta name="theme-color" content="#023047"/>
<?php if ($desc): ?><meta name="description" content="<?= htmlspecialchars($desc) ?>"/><?php endif; ?>
<title><?= htmlspecialchars($title) ?> · PageQuiz</title>
<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{box-sizing:border-box}
body{min-height:100vh;background:#eef4f7;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;font-family:'DM Sans',sans-serif}
.u-box{width:100%;max-width:440px}
.u-card{background:#fff;border-radius:20px;padding:36px;box-shadow:0 2px 8px rgba(2,48,71,.06),0 12px 32px rgba(2,48,71,.10),0 28px 56px rgba(2,48,71,.08)}
.u-brand{text-align:center;margin-bottom:24px;padding-bottom:22px;border-bottom:1px solid #e8eef2}
.u-brand img{display:block;margin:0 auto 12px}
.u-brand-name{font-size:20px;font-weight:700;color:var(--prussian)}
.u-brand-name span{color:var(--pacific)}
.u-brand-sub{font-size:11px;color:var(--gray-400);letter-spacing:.5px;margin-top:2px}
.u-title{font-size:16px;font-weight:700;color:var(--gray-700);margin-bottom:20px;display:flex;align-items:center;gap:8px}
.u-title i{color:var(--pacific)}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--gray-500);margin-bottom:6px}
.form-control{width:100%;padding:12px 14px;border:1.5px solid #dce8ef;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;color:var(--prussian);outline:none;transition:.2s;background:#fff}
.form-control:focus{border-color:var(--pacific);box-shadow:0 0 0 3px rgba(33,158,188,.10)}
.form-control::placeholder{color:var(--gray-300)}
.btn-u{width:100%;padding:13px;background:var(--pacific);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px;transition:background .2s,transform .15s;letter-spacing:.3px}
.btn-u:hover{background:var(--prussian)}
.btn-u:active{transform:scale(.98)}
.btn-u.outline{background:transparent;border:2px solid var(--pacific);color:var(--pacific)}
.btn-u.outline:hover{background:var(--pacific);color:#fff}
.u-alert{display:flex;align-items:flex-start;gap:10px;border-radius:10px;padding:12px 14px;margin-bottom:18px;font-size:13px}
.u-alert.err{background:#fff5f5;border:1px solid #fed7d7;color:#c53030}
.u-alert.ok{background:#f0fff4;border:1px solid #9ae6b4;color:#276749}
.u-alert i{flex-shrink:0;margin-top:1px}
.u-links{text-align:center;margin-top:20px;font-size:13px;color:var(--gray-500)}
.u-links a{color:var(--pacific);font-weight:600;text-decoration:none}
.u-links a:hover{color:var(--prussian)}
.u-divider{border:none;border-top:1px solid #e8eef2;margin:18px 0}
.u-footer{text-align:center;margin-top:18px;font-size:12px}
.u-footer a{color:var(--pacific);text-decoration:none;opacity:.75}
.u-footer a:hover{opacity:1}
</style>
</head>
<body>
<?php } ?>
<?php function userPageFoot(): void { ?>
</body>
</html>
<?php } ?>
