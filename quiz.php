<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$quiz = dbRow("SELECT * FROM quizzes WHERE id = ? AND active = 1", [$id]);
if (!$quiz) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="theme-color" content="#023047"/>
    <title><?= e($quiz['title']) ?> · PageQuiz</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body class="quiz-body">

<!-- Loading Overlay -->
<div id="loading-overlay">
    <div class="spinner"></div>
    <p style="color:rgba(255,255,255,.7);font-size:14px">Carregando quiz…</p>
</div>

<!-- Quiz pausado -->
<div class="quiz-paused-banner" id="quiz-paused">
    <i class="fa-solid fa-pause-circle" aria-hidden="true"></i>
    <span>Quiz pausado</span>
    <p style="font-size:13px;font-weight:400;color:rgba(255,255,255,.6)">Volte à aba para continuar</p>
</div>

<!-- SCREEN: LOGIN -->
<div class="screen active" id="screen-login">
    <div class="brand">
        <img src="assets/logo-white.svg" alt="PageUp"/>
        <h1><?= e($quiz['title']) ?></h1>
        <p><?= e($quiz['sector']) ?></p>
    </div>
    <div class="quiz-card-white">
        <h2>
            <i class="fa-solid fa-user" aria-hidden="true"></i>
            Identificação do Participante
        </h2>
        <div class="info-box">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <div>
                <?= $quiz['description'] ? e($quiz['description']) : 'Responda todas as questões com atenção.' ?>
                <br/><br/>
                <i class="fa-solid fa-clock" aria-hidden="true"></i> <strong><?= $quiz['time_per_question'] ?>s</strong> por questão
                &nbsp;·&nbsp;
                <i class="fa-solid fa-bullseye" aria-hidden="true"></i> Aprovação: <strong><?= $quiz['pass_percentage'] ?>%</strong>
            </div>
        </div>
        <div id="login-error" class="login-error">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span id="login-error-msg"></span>
        </div>
        <div class="field">
            <label for="inp-name">Nome Completo *</label>
            <input type="text" id="inp-name" placeholder="Seu nome completo" autocomplete="name" aria-required="true"/>
        </div>
        <div class="field">
            <label for="inp-sector">Setor *</label>
            <?php $sectors = dbRows("SELECT name FROM sectors ORDER BY name ASC"); ?>
            <select id="inp-sector" aria-required="true">
                <option value="">— Selecione seu setor —</option>
                <?php if (empty($sectors)): ?>
                    <option>Geral</option>
                <?php else: ?>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="field">
            <label for="inp-email">E-mail <span style="font-weight:400;text-transform:none">(opcional)</span></label>
            <input type="email" id="inp-email" placeholder="seunome@empresa.com.br" autocomplete="email"/>
        </div>
        <button class="btn-start" onclick="startQuiz()">
            <i class="fa-solid fa-play" aria-hidden="true"></i>
            <span>Iniciar Quiz</span>
        </button>
    </div>
</div>

<!-- SCREEN: QUIZ -->
<div class="screen" id="screen-quiz">
    <div class="quiz-header">
        <div class="qh-left">
            <h2 id="qh-title">Questão 1</h2>
            <p id="qh-sub">PageQuiz</p>
            <div class="progress-track">
                <div class="progress-fill" id="prog-fill" style="width:0%"></div>
            </div>
        </div>
        <div class="svg-timer" id="timer-ring" role="timer" aria-label="Tempo restante">
            <svg width="66" height="66" viewBox="0 0 66 66">
                <circle class="track" cx="33" cy="33" r="28"/>
                <circle class="arc" id="timer-arc" cx="33" cy="33" r="28"
                    stroke-dasharray="175.9" stroke-dashoffset="0"/>
            </svg>
            <div class="timer-label">
                <span class="timer-num" id="timer-num">30</span>
                <span class="timer-lbl">seg</span>
            </div>
        </div>
    </div>

    <div class="q-card">
        <div class="q-meta">
            <span class="q-num-label" id="q-num-lbl">QUESTÃO 1 / ?</span>
            <span class="q-cat" id="q-cat">Categoria</span>
        </div>
        <div class="q-text" id="q-text">Carregando…</div>
        <div class="options" id="q-opts"></div>
        <div class="feedback" id="q-feedback" role="alert"></div>
        <div class="clearfix">
            <button class="btn-next" id="btn-next" onclick="nextQ()">
                Próxima <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</div>

<!-- SCREEN: RESULT -->
<div class="screen" id="screen-result">
    <div class="result-card">
        <div class="score-circle" id="res-circle">
            <span id="res-pct">0%</span>
        </div>
        <div class="result-title" id="res-title">Resultado</div>
        <div class="result-sub" id="res-sub">–</div>
        <div class="score-grid">
            <div class="score-item">
                <div class="val" id="res-ok">0</div>
                <div class="lbl">Acertos</div>
            </div>
            <div class="score-item">
                <div class="val red" id="res-err">0</div>
                <div class="lbl">Erros</div>
            </div>
            <div class="score-item">
                <div class="val" id="res-time">0s</div>
                <div class="lbl">Tempo médio</div>
            </div>
        </div>
        <div id="res-action"></div>

        <!-- Hall of Fame -->
        <div class="res-podium">
            <div class="res-podium-title">
                <i class="fa-solid fa-trophy" aria-hidden="true"></i> Hall da Fama (Top 3)
            </div>
            <div class="res-podium-list" id="res-podium-list">
                <div style="font-size:12px;color:rgba(255,255,255,0.3);text-align:center;padding:10px">Carregando ranking…</div>
            </div>
            <div id="res-user-rank" style="margin-top:15px;font-size:12px;color:rgba(255,255,255,0.5);text-align:center;border-top:1px solid rgba(255,255,255,.05);padding-top:10px"></div>
        </div>
    </div>

    <div class="section-title">
        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
        Revisão das Respostas
    </div>
    <div class="review-list" id="review-list"></div>
    <div style="text-align:center;margin-top:16px">
        <a href="index.php" class="btn-retry">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao Início
        </a>
    </div>
</div>

<!-- SCREEN: CERTIFICATE -->
<div class="screen" id="screen-cert">
    <div class="cert-wrap">
        <div class="cert" id="cert-print">
            <div style="display:flex;flex-direction:column;align-items:center;width:100%">
                <img src="assets/logo.svg" class="cert-logo" alt="PageUp Sistemas"/>
                <h1>Certificado de Conclusão</h1>
                <h2 id="cert-quiz-title"><?= e($quiz['title']) ?></h2>
            </div>

            <div style="flex-grow:1;width:100%;display:flex;flex-direction:column;justify-content:center">
                <div class="cert-label">Certificamos que</div>
                <div class="cert-name" id="cert-name">–</div>
                <div class="cert-sector" id="cert-sector">–</div>
                <div class="cert-divider"></div>
                <div class="cert-label">concluiu com</div>
                <div class="cert-score" id="cert-score">0%</div>
                <div class="cert-score-lbl">de aproveitamento</div>
                <div style="margin-top:8px">
                    <span style="font-size:9px;color:var(--gray-400);text-transform:uppercase;letter-spacing:1px">ID Verificação:</span>
                    <strong id="cert-verify-id" style="font-size:10px;color:var(--prussian)">–</strong>
                </div>
                <div>
                    <div class="cert-badge" id="cert-badge">APROVADO <i class="fa-solid fa-check" aria-hidden="true"></i></div>
                </div>
            </div>

            <div style="width:100%">
                <div class="cert-details" id="cert-details"></div>
                <div style="margin-top:15px;display:flex;align-items:flex-end;justify-content:space-between">
                    <div style="text-align:left">
                        <div class="cert-footer">
                            PageUp Sistemas · Plataforma de Treinamento<br/>
                            Válido como evidência de treinamento e capacitação profissional.
                        </div>
                    </div>
                    <div id="cert-qr-wrap" style="padding:4px;background:#fff;border:1px solid var(--gray-200);border-radius:6px">
                        <!-- QR Code injected via JS -->
                    </div>
                </div>
            </div>
        </div>
        <div class="cert-actions">
            <button class="btn-print" onclick="window.print()">
                <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir / Salvar PDF
            </button>
            <button id="btn-wa-share" class="btn-cert" style="background:#25D366">
                <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Compartilhar
            </button>
        </div>
        <div style="text-align:center;margin-top:20px">
            <a href="index.php" class="btn-retry">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao Início
            </a>
        </div>
    </div>
</div>

<script>
const QUIZ_ID = <?= $quiz['id'] ?>;
let QUIZ = null, QUESTIONS = [], currentQ = 0;
let answers = [], timesUsed = [];
let timerInterval, timeLeft, startTime, pausedAt = null;
let userName = '', userEmail = '', userSector = '';
let participantId = null;

const letters = ['A','B','C','D'];

/* ── Boot ─────────────────────────── */
window.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch(`api/quiz.php?id=${QUIZ_ID}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        QUIZ = data.quiz;
        QUESTIONS = data.questions;
        document.getElementById('loading-overlay').style.display = 'none';
    } catch(e) {
        document.getElementById('loading-overlay').innerHTML =
            `<div style="color:#ff6b6b;font-size:15px;text-align:center;padding:20px">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:32px;margin-bottom:12px;display:block"></i>
                ${escHtml(e.message)}<br/><br/>
                <a href="index.php" style="color:var(--pacific)">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
             </div>`;
    }
});

/* ── Visibility (pause on tab hide) ── */
document.addEventListener('visibilitychange', () => {
    const banner = document.getElementById('quiz-paused');
    if (document.hidden) {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
            pausedAt = timeLeft;
        }
        banner.classList.add('visible');
    } else {
        banner.classList.remove('visible');
        if (pausedAt !== null && currentQ < (QUESTIONS?.length ?? 0)) {
            timeLeft = pausedAt;
            pausedAt = null;
            startTime = Date.now() - ((QUIZ.timer - timeLeft) * 1000);
            updateTimer();
            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimer();
                if (timeLeft <= 0) { clearInterval(timerInterval); autoTimeout(); }
            }, 1000);
        }
    }
});

/* ── Enter key advances question ──── */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const btn = document.getElementById('btn-next');
        if (btn && btn.style.display !== 'none') nextQ();
    }
});

/* ── Field validation ────────────── */
function setFieldError(inputId, msg) {
    const el = document.getElementById(inputId);
    if (!el) return;
    el.setAttribute('aria-invalid', 'true');
    let hint = el.parentElement.querySelector('.field-error');
    if (!hint) {
        hint = document.createElement('p');
        hint.className = 'field-error';
        hint.innerHTML = '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> ';
        el.after(hint);
    }
    hint.childNodes[hint.childNodes.length - 1].textContent = msg;
    el.focus();
}

function clearFieldError(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    el.removeAttribute('aria-invalid');
    const hint = el.parentElement.querySelector('.field-error');
    if (hint) hint.remove();
}

/* ── Start Quiz ────────────────────── */
function startQuiz() {
    userName   = document.getElementById('inp-name').value.trim();
    userSector = document.getElementById('inp-sector').value;
    userEmail  = document.getElementById('inp-email').value.trim();

    clearFieldError('inp-name');
    clearFieldError('inp-sector');
    document.getElementById('login-error').style.display = 'none';

    let valid = true;
    if (!userName)   { setFieldError('inp-name',   'Informe seu nome completo.'); valid = false; }
    if (!userSector) { setFieldError('inp-sector', 'Selecione seu setor.'); valid = false; }
    if (!valid) return;

    answers = []; timesUsed = []; currentQ = 0;

    fetch('api/start-session.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ quiz_id: QUIZ_ID, name: userName, email: userEmail, sector: userSector })
    })
    .then(r => r.json())
    .then(data => { if (data.participant_id) participantId = parseInt(data.participant_id, 10); })
    .catch(e => console.error('Error starting session:', e));

    show('screen-quiz');
    renderQ();
}

/* ── Render Question ──────────────── */
function renderQ() {
    const q = QUESTIONS[currentQ];
    const total = QUESTIONS.length;

    document.getElementById('qh-title').textContent  = `Questão ${currentQ+1} de ${total}`;
    document.getElementById('qh-sub').textContent    = QUIZ.title;
    document.getElementById('q-num-lbl').textContent = `QUESTÃO ${currentQ+1} / ${total}`;
    document.getElementById('q-cat').textContent     = q.cat || 'Geral';
    document.getElementById('q-cat').style.display   = q.cat ? '' : 'none';
    document.getElementById('q-text').textContent    = q.q;

    const card = document.querySelector('.q-card');
    card.classList.remove('animating');
    void card.offsetWidth;
    card.classList.add('animating');

    const pct = Math.round((currentQ / total) * 100);
    document.getElementById('prog-fill').style.width = pct + '%';

    const optsEl = document.getElementById('q-opts');
    optsEl.innerHTML = '';
    q.opts.forEach((opt, i) => {
        const div = document.createElement('div');
        div.className = 'opt';
        div.id = `opt-${i}`;
        div.setAttribute('role', 'button');
        div.setAttribute('tabindex', '0');
        div.innerHTML = `<span class="letter">${letters[i]}</span><span>${escHtml(opt)}</span>`;
        div.onclick = () => selectOpt(i);
        div.onkeydown = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectOpt(i); } };
        optsEl.appendChild(div);
    });

    const fb = document.getElementById('q-feedback');
    fb.style.display = 'none';
    fb.className = 'feedback';
    document.getElementById('btn-next').style.display = 'none';

    clearInterval(timerInterval);
    pausedAt = null;
    timeLeft  = QUIZ.timer;
    startTime = Date.now();
    updateTimer();
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimer();
        if (timeLeft <= 0) { clearInterval(timerInterval); autoTimeout(); }
    }, 1000);
}

function updateTimer() {
    const ring  = document.getElementById('timer-ring');
    const num   = document.getElementById('timer-num');
    const arc   = document.getElementById('timer-arc');
    const total = QUIZ.timer;
    const circ  = 175.9;

    num.textContent = timeLeft;
    const offset = circ - (timeLeft / total) * circ;
    if (arc) arc.setAttribute('stroke-dashoffset', offset);

    ring.className = 'svg-timer';
    const pct = timeLeft / total;
    if (pct <= 0.15)      ring.classList.add('danger');
    else if (pct <= 0.40) ring.classList.add('warn');

    ring.setAttribute('aria-label', `${timeLeft} segundos restantes`);
}

function autoTimeout() {
    const elapsed = QUIZ.timer;
    answers.push({ q_id: QUESTIONS[currentQ].id, selected: -1, correct: 0, time: elapsed });
    timesUsed.push(elapsed);

    if (participantId) {
        fetch('api/save-answer.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ participant_id: participantId, question_id: QUESTIONS[currentQ].id, selected_answer: -1, is_correct: 0, time_taken: elapsed })
        });
    }

    document.querySelectorAll('.opt').forEach(o => o.classList.add('disabled'));
    document.getElementById(`opt-${QUESTIONS[currentQ].correct}`)?.classList.add('reveal');
    showFeedback('timeout', QUESTIONS[currentQ]);
}

function selectOpt(idx) {
    clearInterval(timerInterval);
    const elapsed = Math.round((Date.now() - startTime) / 1000);
    const q = QUESTIONS[currentQ];
    const correct = idx === q.correct;

    answers.push({ q_id: q.id, selected: idx, correct: correct ? 1 : 0, time: elapsed });
    timesUsed.push(elapsed);

    if (participantId) {
        fetch('api/save-answer.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ participant_id: participantId, question_id: q.id, selected_answer: idx, is_correct: correct ? 1 : 0, time_taken: elapsed })
        });
    }

    document.querySelectorAll('.opt').forEach(o => o.classList.add('disabled'));
    if (correct) {
        const el = document.getElementById(`opt-${idx}`);
        el.classList.add('correct', 'correct-animate');
        try { confetti({ particleCount:60, spread:70, origin:{ y:.75 }, colors:['#219EBC','#00b894','#FFB703'], zIndex:1000 }); } catch(e){}
    } else {
        document.getElementById(`opt-${idx}`)?.classList.add('wrong');
        document.getElementById(`opt-${q.correct}`)?.classList.add('reveal');
    }
    showFeedback(correct ? 'correct' : 'wrong', q);
}

function showFeedback(type, q) {
    if (!QUIZ.feedback) {
        document.getElementById('btn-next').style.display = 'block';
        return;
    }
    const fb = document.getElementById('q-feedback');
    if (type === 'timeout') {
        fb.className = 'feedback timeout';
        fb.innerHTML = `<i class="fa-solid fa-hourglass-end" aria-hidden="true"></i><span><strong>Tempo esgotado!</strong> Resposta correta: <strong>${escHtml(q.opts[q.correct])}</strong>${q.exp ? '<br/><br/>' + escHtml(q.exp) : ''}</span>`;
    } else if (type === 'correct') {
        fb.className = 'feedback correct';
        fb.innerHTML = `<i class="fa-solid fa-circle-check" aria-hidden="true"></i><span><strong>Correto!</strong>${q.exp ? ' ' + escHtml(q.exp) : ''}</span>`;
    } else {
        fb.className = 'feedback wrong';
        fb.innerHTML = `<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i><span><strong>Incorreto.</strong> Resposta correta: <strong>${escHtml(q.opts[q.correct])}</strong>${q.exp ? '<br/><br/>' + escHtml(q.exp) : ''}</span>`;
    }
    fb.style.display = 'flex';
    document.getElementById('btn-next').style.display = 'block';
}

function nextQ() {
    currentQ++;
    if (currentQ >= QUESTIONS.length) showResult();
    else renderQ();
}

async function showResult() {
    clearInterval(timerInterval);
    document.getElementById('prog-fill').style.width = '100%';

    const total   = answers.length;
    const ok      = answers.filter(a => a.correct).length;
    const pct     = total > 0 ? Math.round((ok / total) * 100) : 0;
    const avgTime = timesUsed.length > 0 ? (timesUsed.reduce((a,b)=>a+b,0)/timesUsed.length).toFixed(1) : 0;
    const passed  = pct >= QUIZ.pass_percentage;
    let verifyCode = '';

    try {
        const resp = await fetch('api/result.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ quiz_id: QUIZ_ID, participant_id: participantId })
        });
        const data = await resp.json();
        if (data.verify_code) verifyCode = data.verify_code;
    } catch(e) { console.error('Finalize error', e); }

    show('screen-result');
    loadPodium();

    if (passed) {
        try { confetti({ particleCount:150, spread:80, origin:{ y:.6 }, colors:['#219EBC','#023047','#ffffff'], zIndex:1000 }); } catch(e){}
    }

    const circle = document.getElementById('res-circle');
    circle.className = 'score-circle ' + (passed ? 'pass' : 'fail');
    document.getElementById('res-pct').textContent = pct + '%';

    const titleEl = document.getElementById('res-title');
    if (passed) {
        titleEl.innerHTML = '<i class="fa-solid fa-trophy" aria-hidden="true"></i> Parabéns, você foi aprovado!';
    } else {
        titleEl.innerHTML = '<i class="fa-solid fa-book-open" aria-hidden="true"></i> Não foi dessa vez…';
    }

    document.getElementById('res-sub').textContent =
        `${ok} de ${total} acertos — ${passed ? 'Aprovado' : 'Reprovado (Mínimo: ' + QUIZ.pass_percentage + '%)'}`;
    document.getElementById('res-ok').textContent   = ok;
    document.getElementById('res-err').textContent  = total - ok;
    document.getElementById('res-time').textContent = avgTime + 's';

    const action = document.getElementById('res-action');
    if (passed && QUIZ.has_certificate) {
        action.innerHTML = `<button class="btn-cert" onclick="showCert(${pct}, '${escHtml(verifyCode)}')">
            <i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Ver Certificado
        </button>`;
    } else if (passed) {
        action.innerHTML = `<div style="color:var(--green);font-weight:700;margin-top:10px;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-circle-check"></i> Quiz concluído com sucesso!
        </div>`;
    } else {
        action.innerHTML = `<a href="quiz.php?id=${QUIZ_ID}" class="btn-retry">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Tentar Novamente
        </a>`;
    }

    const rl = document.getElementById('review-list');
    rl.innerHTML = '';
    answers.forEach((a, i) => {
        const q = QUESTIONS[i];
        if (!q) return;
        const div = document.createElement('div');
        div.className = `review-item ${a.correct ? 'ok' : 'fail'}`;
        const selText = a.selected === -1
            ? '<i class="fa-solid fa-hourglass-end" aria-hidden="true"></i> Tempo esgotado'
            : `${letters[a.selected]}) ${escHtml(q.opts[a.selected])}`;
        div.innerHTML = `
            <div class="rq">Q${i+1}: ${escHtml(q.q)}</div>
            <div class="ra">
                <span>Sua resposta: ${selText}</span>
                ${!a.correct
                    ? `<span class="ra-correct"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Correta: ${letters[q.correct]}) ${escHtml(q.opts[q.correct])}</span>`
                    : `<span class="ra-correct"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Resposta correta!</span>`
                }
            </div>`;
        rl.appendChild(div);
    });
}

function showCert(pct, verifyCode) {
    const now = new Date();
    const okCount = answers.filter(a => a.correct).length;
    const total = QUESTIONS.length;
    const avgTime = timesUsed.length > 0 ? (timesUsed.reduce((a,b)=>a+b,0)/timesUsed.length).toFixed(0) : 0;

    document.getElementById('cert-name').textContent     = userName;
    document.getElementById('cert-sector').textContent   = userSector;
    document.getElementById('cert-score').textContent    = pct + '%';
    document.getElementById('cert-verify-id').textContent = verifyCode || 'N/A';

    document.getElementById('cert-details').innerHTML = `
        <div>Data de conclusão: <strong>${now.toLocaleDateString('pt-BR')}</strong></div>
        <div>E-mail: <strong>${escHtml(userEmail || 'Não informado')}</strong></div>
        <div>Acertos: <strong>${okCount} de ${total} questões</strong></div>
        <div>Tempo médio: <strong>${avgTime}s por questão</strong></div>
    `;

    const verUrl = window.location.origin + window.location.pathname.replace('quiz.php','verify.php') + '?code=' + verifyCode;
    document.getElementById('cert-qr-wrap').innerHTML =
        `<img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&margin=0&color=023047&data=${encodeURIComponent(verUrl)}"
              width="70" height="70" style="display:block" alt="QR Code de verificação"/>`;

    const msg = `*Passei no Treinamento!*\n\n✅ Quiz: ${QUIZ.title}\nAproveitamento: *${pct}%*\nVerificação: ${verifyCode}\n\nValide meu certificado: ${verUrl}`;
    document.getElementById('btn-wa-share').onclick = () => {
        window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
    };

    show('screen-cert');
}

function show(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    window.scrollTo(0, 0);
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function loadPodium() {
    try {
        const res = await fetch(`api/live-stats.php?quiz_id=${QUIZ_ID}`);
        const data = await res.json();
        if (!data.success) return;

        const list = document.getElementById('res-podium-list');
        const top3 = data.participants.slice(0, 3);

        if (top3.length === 0) {
            list.innerHTML = '<div style="font-size:12px;color:rgba(255,255,255,0.2);text-align:center;">Nenhum resultado ainda.</div>';
            return;
        }

        list.innerHTML = top3.map((p, i) => `
            <div class="res-podium-item">
                <span class="res-podium-rank ${i === 0 ? 'gold' : ''}">#${i+1}</span>
                <span class="res-podium-name">${escHtml(p.name)}</span>
                <span class="res-podium-score">${p.score} pts</span>
            </div>
        `).join('');

        const userInList = data.participants.findIndex(p => p.id === participantId);
        const rankEl = document.getElementById('res-user-rank');
        if (userInList !== -1) {
            const rank = userInList + 1;
            rankEl.innerHTML = `Sua posição: <strong>${rank}º lugar</strong> de ${data.participants.length} participantes`;
            if (rank <= 3) {
                setTimeout(() => {
                    confetti({ particleCount:200, spread:100, origin:{ y:.5 }, colors:['#FFB703','#ffffff'], zIndex:1000 });
                }, 1000);
            }
        }
    } catch(e) { console.error('Error loading podium:', e); }
}
</script>
</body>
</html>
