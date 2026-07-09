<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$cid    = adminCompanyId();
$quizId = (int) ($_GET['id'] ?? 0);
$quiz   = null;

if ($quizId > 0) {
    $quiz = dbRow("SELECT * FROM quizzes WHERE id = ? AND company_id = ?", [$quizId, $cid]);
} else {
    $quizzes = dbRows("SELECT * FROM quizzes WHERE active = 1 AND company_id = ? ORDER BY created_at DESC", [$cid]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title><?= $quiz ? 'LIVE: ' . htmlspecialchars($quiz['title']) : 'Modo Ao Vivo' ?> · PageQuiz</title>
    <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg"/>
    <link rel="stylesheet" href="../assets/style.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --bg-live: #0d1b35;
            --card-bg: rgba(255, 255, 255, 0.05);
            --accent: #008bcd;
        }

        body {
            background: var(--bg-live);
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            overflow-x: hidden;
            margin: 0;
            min-height: 100vh;
        }

        h1,
        h2,
        h3,
        .font-display {
            font-family: 'Syne', sans-serif;
        }

        .live-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .live-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
        }

        .live-header h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -1px;
        }

        .live-header .quiz-info {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            margin-top: 4px;
        }

        @media (max-width: 600px) {
            .live-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .live-header h1 {
                font-size: 22px;
            }
        }

        /* Selection Styles */
        .selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .selection-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            text-decoration: none;
            color: #fff;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .selection-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-5px);
            border-color: var(--accent);
        }

        .selection-card h3 {
            margin: 0;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .selection-card p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            margin: 0;
        }

        .selection-card .btn-go {
            margin-top: 20px;
            background: var(--accent);
            color: #fff;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
        }

        /* Dashboard Styles */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-item {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item .val {
            font-size: 32px;
            font-weight: 800;
            color: var(--accent);
            font-family: 'Syne';
        }

        .stat-item .lbl {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .stats-bar {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-item {
                padding: 15px;
            }

            .stat-item .val {
                font-size: 24px;
            }
        }

        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 60px;
            height: 300px;
        }

        .podium-place {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 200px;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .podium-box {
            width: 100%;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 12px 12px 0 0;
            position: relative;
            border: 2px solid transparent;
        }

        .p-1 {
            height: 100%;
            z-index: 2;
        }

        .p-1 .podium-box {
            height: 180px;
            background: linear-gradient(180deg, #f9a825 0%, #ff6f00 100%);
            box-shadow: 0 10px 30px rgba(249, 168, 37, 0.3);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .p-2 {
            height: 85%;
        }

        .p-2 .podium-box {
            height: 140px;
            background: linear-gradient(180deg, #bdbdbd 0%, #757575 100%);
        }

        .p-3 {
            height: 70%;
        }

        .p-3 .podium-box {
            height: 100px;
            background: linear-gradient(180deg, #a1887f 0%, #5d4037 100%);
        }

        @media (max-width: 600px) {
            .podium {
                display: flex;
                flex-direction: column;
                height: auto;
                align-items: stretch;
                gap: 10px;
            }

            .podium-place {
                width: 100%;
                height: auto !important;
                flex-direction: row;
                justify-content: space-between;
                padding: 15px;
                background: var(--card-bg);
                border-radius: 12px;
            }

            .podium-box {
                height: 40px !important;
                width: 60px !important;
                margin: 0;
                border-radius: 8px;
            }

            .podium-name {
                flex-grow: 1;
                text-align: left;
                font-size: 16px;
                margin: 0 15px;
            }

            .medal {
                width: 30px;
                height: 30px;
                font-size: 14px;
                margin: 0;
            }

            .podium-score {
                font-size: 14px;
                color: #fff;
                font-weight: 700;
                margin-left: 10px;
            }

            .p-1 {
                order: 1;
                border: 1px solid var(--accent);
            }

            .p-2 {
                order: 2;
            }

            .p-3 {
                order: 3;
            }
        }

        .medal {
            width: 50px;
            height: 50px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            color: #1a1b1e;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .leaderboard {
            position: relative;
            width: 100%;
            margin-top: 20px;
        }

        @media (max-width: 860px) {
            .participant-card {
                width: 100% !important;
                transform: none !important;
                position: relative !important;
                margin-bottom: 12px;
            }

            .leaderboard {
                height: auto !important;
            }
        }

        .participant-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 12px 20px;
            /* More compact padding */
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            position: absolute;
            box-sizing: border-box;
        }

        .participant-rank {
            font-family: 'Syne';
            font-size: 16px;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.25);
            min-width: 25px;
            flex-shrink: 0;
        }

        .participant-info {
            flex-grow: 1;
            min-width: 0;
            padding-right: 10px;
        }

        .participant-name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .participant-sector {
            font-size: 9px;
            color: rgba(255, 255, 255, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-wrap {
            width: 90px;
            flex-shrink: 0;
        }

        .progress-bg {
            background: rgba(255, 255, 255, 0.1);
            height: 5px;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            background: var(--accent);
            height: 100%;
            transition: width 0.8s ease;
        }

        .participant-score {
            font-family: 'Syne';
            font-size: 16px;
            font-weight: 800;
            color: #00b894;
            width: 50px;
            text-align: right;
            flex-shrink: 0;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .dot-online {
            background: #00b894;
            box-shadow: 0 0 8px #00b894;
            animation: dotPulse 1.5s infinite;
        }

        .dot-offline {
            background: #718096;
        }

        .dot-finished {
            background: var(--accent);
        }

        @keyframes dotPulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.3);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .live-container {
            animation: fadeInUp 0.8s ease;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(1.1);
            }

            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>

<body>

    <div class="live-container">
        <?php if (!$quiz): ?>
            <!-- SELECTION VIEW -->
            <header class="live-header">
                <div>
                    <h1>MODO AO VIVO</h1>
                    <div class="quiz-info">Selecione um quiz para acompanhar em tempo real</div>
                </div>
                <div style="text-align: right">
                    <a href="index.php"
                        style="color: rgba(255,255,255,0.4); text-decoration: none; font-size: 12px; font-weight: 600;">←
                        Área Administrativa</a>
                </div>
            </header>

            <div class="selection-grid">
                <?php if (empty($quizzes)): ?>
                    <div class="empty-state">Nenhum quiz ativo encontrado.</div>
                <?php else: ?>
                    <?php foreach ($quizzes as $q): ?>
                        <a href="?id=<?= $q['id'] ?>" class="selection-card">
                            <div>
                                <h3><?= htmlspecialchars($q['title']) ?></h3>
                                <p><i class="fa-solid fa-sitemap" style="margin-right:6px"></i>
                                    <?= htmlspecialchars($q['sector']) ?></p>
                            </div>
                            <div class="btn-go">
                                <i class="fa-solid fa-play" style="margin-right:8px"></i> Monitorar
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- DASHBOARD VIEW -->
            <header class="live-header">
                <div>
                    <h1>ACOMPANHAMENTO AO VIVO</h1>
                    <div class="quiz-info"><?= htmlspecialchars($quiz['title']) ?> &nbsp;·&nbsp;
                        <?= htmlspecialchars($quiz['sector']) ?></div>
                </div>
                <div style="text-align: right">
                    <div id="live-indicator"
                        style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,0,0,0.15); color: #ff4d4d; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                        <span
                            style="width: 8px; height: 8px; background: #ff4d4d; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite;"></span>
                        AO VIVO
                    </div>
                    <div style="margin-top: 10px;">
                        <a href="live.php"
                            style="color: rgba(255,255,255,0.4); text-decoration: none; font-size: 12px; font-weight: 600;">←
                            Trocar Quiz</a>
                    </div>
                </div>
            </header>

            <div class="stats-bar" id="stats-bar">
                <div class="stat-item">
                    <div class="val" id="stat-total">0</div>
                    <div class="lbl">Participantes</div>
                </div>
                <div class="stat-item">
                    <div class="val" id="stat-passed">0</div>
                    <div class="lbl">Aprovados</div>
                </div>
                <div class="stat-item">
                    <div class="val" id="stat-avg">0%</div>
                    <div class="lbl">Nota Média</div>
                </div>
            </div>

            <div id="podium-wrap">
                <h2 style="text-align: center; margin-bottom: 40px; letter-spacing: 2px; color: rgba(255,255,255,0.5);">
                    Primeiros Colocados</h2>
                <div class="podium" id="podium-list">
                    <!-- Fallback Podium -->
                    <div class="podium-place p-2">
                        <div class="podium-name">---</div>
                        <div class="podium-box">
                            <div class="medal">2</div>
                        </div>
                    </div>
                    <div class="podium-place p-1">
                        <div class="podium-name">---</div>
                        <div class="podium-box">
                            <div class="medal">1</div>
                        </div>
                    </div>
                    <div class="podium-place p-3">
                        <div class="podium-name">---</div>
                        <div class="podium-box">
                            <div class="medal">3</div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="leaderboard-wrap">
                <h2 style="margin-bottom: 24px; font-size: 18px; color: rgba(255,255,255,0.7);">LIDERANÇA EM TEMPO REAL</h2>
                <div class="leaderboard" id="leaderboard-list">
                    <div class="empty-state">Aguardando participantes iniciarem o quiz...</div>
                </div>
            </div>

            <script>
                const QUIZ_ID = <?= $quizId ?>;
                let firstPlaceId = null;

                async function updateLive() {
                    try {
                        const res = await fetch(`../api/live-stats.php?quiz_id=${QUIZ_ID}`);
                        const data = await res.json();
                        if (!data.success) return;

                        renderStats(data.stats);
                        renderLeaderboard(data.participants);
                        renderPodium(data.participants.slice(0, 3));

                        if (data.participants.length > 0) {
                            const top = data.participants[0];
                            if (firstPlaceId && firstPlaceId !== top.id && top.score > 0) {
                                triggerConfetti();
                            }
                            firstPlaceId = top.id;
                        }
                    } catch (e) {
                        console.error("Live update error:", e);
                    }
                }

                function renderStats(stats) {
                    document.getElementById('stat-total').textContent = stats.total;
                    document.getElementById('stat-passed').textContent = stats.passed;
                    document.getElementById('stat-avg').textContent = stats.avg_score + '%';
                }

                function renderLeaderboard(participants) {
                    const list = document.getElementById('leaderboard-list');
                    if (participants.length === 0) {
                        if (!list.querySelector('.empty-state')) {
                            list.innerHTML = '<div class="empty-state">Aguardando participantes iniciarem o quiz...</div>';
                        }
                        list.style.height = 'auto';
                        return;
                    }

                    const emptyS = list.querySelector('.empty-state');
                    if (emptyS) emptyS.remove();

                    // Responsive Column Calculation
                    const winWidth = window.innerWidth;
                    const count = participants.length;
                    let cols = 1;
                    if (winWidth > 1200 && count > 24) cols = 3;
                    else if (winWidth > 860 && count > 12) cols = 2;

                    const cardHeight = 75;
                    const gap = 12;
                    const containerWidth = list.offsetWidth;

                    // Percentage positioning for auto-resize stability
                    const colWidthPct = 100 / cols;

                    const rowsPerCol = Math.ceil(count / cols);
                    list.style.height = (rowsPerCol * (cardHeight + gap)) + 'px';

                    const currentIds = participants.map(p => p.id);
                    const existingCards = Array.from(list.querySelectorAll('.participant-card'));
                    existingCards.forEach(card => {
                        if (!currentIds.includes(parseInt(card.dataset.id))) card.remove();
                    });
                    participants.forEach((p, index) => {
                        let card = list.querySelector(`.participant-card[data-id="${p.id}"]`);
                        const statusDot = p.is_finished ? 'dot-finished' : (p.is_online ? 'dot-online' : 'dot-offline');
                        const statusTitle = p.is_finished ? 'Finalizado' : (p.is_online ? 'Jogando agora' : 'Inativo');

                        if (!card) {
                            card = document.createElement('div');
                            card.className = 'participant-card';
                            card.dataset.id = p.id;
                            card.innerHTML = `
                        <div class="participant-rank">#${index + 1}</div>
                        <div class="participant-info">
                            <div class="participant-name"><span class="status-dot ${statusDot}" title="${statusTitle}"></span>${p.name}</div>
                            <div class="participant-sector">${p.sector}</div>
                        </div>
                        <div class="progress-wrap">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:10px;">
                                <span class="lbl-prog">${cols > 1 ? '' : 'Progresso'}</span><span class="pct-val">${p.progress}%</span>
                            </div>
                            <div class="progress-bg"><div class="progress-fill" style="width: ${p.progress}%"></div></div>
                        </div>
                        <div class="participant-score">${p.score} <span style="font-size:10px; color:rgba(255,255,255,0.2)">pts</span></div>
                    `;
                            list.appendChild(card);
                        } else {
                            card.querySelector('.participant-rank').textContent = '#' + (index + 1);
                            card.querySelector('.progress-fill').style.width = p.progress + '%';
                            card.querySelector('.pct-val').textContent = p.progress + '%';
                            const lblProg = card.querySelector('.lbl-prog');
                            if (lblProg) lblProg.textContent = cols > 1 ? '' : 'Progresso';
                            card.querySelector('.participant-score').innerHTML = `${p.score} <span style="font-size:10px; color:rgba(255,255,255,0.2)">pts</span>`;
                            const dot = card.querySelector('.status-dot');
                            if (dot) {
                                dot.className = `status-dot ${statusDot}`;
                                dot.title = statusTitle;
                            }
                        }

                        // Percentage-based Grid position
                        const colIndex = index % cols;
                        const rowIndex = Math.floor(index / cols);

                        if (winWidth > 860) {
                            card.style.width = `calc(${colWidthPct}% - ${gap}px)`;
                            card.style.left = (colIndex * colWidthPct) + '%';
                            card.style.top = (rowIndex * (cardHeight + gap)) + 'px';
                            card.style.transform = 'none'; // Use absolute top/left for responsive stability
                        } else {
                            card.style.width = '100%';
                            card.style.left = '0';
                            card.style.top = '0';
                            card.style.transform = 'none';
                            card.style.position = 'relative';
                        }
                    });
                }

                function renderPodium(top3) {
                    const list = document.getElementById('podium-list');
                    const visualOrder = [
                        { rank: 2, class: 'p-2', p: top3[1] || null },
                        { rank: 1, class: 'p-1', p: top3[0] || null },
                        { rank: 3, class: 'p-3', p: top3[2] || null }
                    ];

                    list.innerHTML = visualOrder.map(item => `
                <div class="podium-place ${item.class}">
                    <div class="podium-name">${item.p ? item.p.name : '---'}</div>
                    <div class="podium-box">
                        <div class="medal">${item.rank}</div>
                        ${item.p ? `<div class="podium-score">${item.p.score}</div>` : ''}
                    </div>
                </div>
            `).join('');
                }

                function triggerConfetti() {
                    confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 }, colors: ['#008bcd', '#00b894', '#f9a825'] });
                }

                updateLive();
                setInterval(updateLive, 3000);
            </script>
        <?php endif; ?>
    </div>

</body>

</html>