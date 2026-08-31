<?php
/**
 * ianselp — Affichage des scores en direct
 * Écran plein écran destiné à un vidéoprojecteur ou une TV.
 *
 * Page autonome : elle n'inclut pas le template IANSEO (ni menu, ni CSS du
 * logiciel) et interroge data.php en boucle.
 *
 * Paramètres :
 *   ?tour=<ToId>  concours à afficher (sinon celui ouvert en session)
 *   ?demo=1       jeu de données factice, pour régler l'écran avant le concours
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once('Common/Fun_Various.inc.php');
require_once(dirname(__FILE__) . '/Lib/Fun_ianselp.php');

$TourId = ianselp_TourId();
if (!$TourId) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><body style="font:16px sans-serif;padding:2em">'
       . 'Aucun concours sélectionné. Ouvrez un concours dans IANSEO, ou appelez cette page '
       . 'avec <code>?tour=&lt;numéro du concours&gt;</code>.</body>';
    exit;
}

$demo    = !empty($_REQUEST['demo']) ? 1 : 0;
$dataUrl = $CFG->ROOT_DIR . 'Modules/Custom/' . basename(dirname(__FILE__))
         . '/data.php?tour=' . $TourId . ($demo ? '&demo=1' : '');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scores en direct</title>
<style>
:root {
    --bg:      #0b1220;
    --bg-alt:  #131d31;
    --panel:   #16223a;
    --line:    #24344f;
    --fg:      #eef3fb;
    --muted:   #8ea3c4;
    --accent:  #ffc043;
    --podium1: #ffd75e;
    --podium2: #cfd8e3;
    --podium3: #d99a5b;
    --ok:      #4ade80;
    --ko:      #f87171;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    background: var(--bg);
    color: var(--fg);
    font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: none;
}

header {
    display: flex;
    align-items: center;
    gap: 1.5vw;
    padding: 1.2vh 2vw;
    background: var(--panel);
    border-bottom: 0.3vh solid var(--line);
}
.title { font-size: 2.4vh; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; white-space: nowrap; }
.event { flex: 1; font-size: 3.6vh; font-weight: 700; color: var(--accent); text-align: center;
         overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.meta  { display: flex; align-items: center; gap: 1vw; font-size: 2.4vh; color: var(--muted); white-space: nowrap; }
.dot   { width: 1.2vh; height: 1.2vh; border-radius: 50%; background: var(--ok); }
.dot.stale { background: var(--ko); }
.badge { font-size: 1.8vh; padding: .3vh .8vh; border-radius: .5vh; background: var(--ko); color: #fff; letter-spacing: .05em; }

main { flex: 1; display: flex; flex-direction: column; padding: 0 2vw; min-height: 0; }

table { width: 100%; border-collapse: collapse; table-layout: fixed; }
thead th {
    font-size: 2.2vh; text-transform: uppercase; letter-spacing: .06em; color: var(--muted);
    text-align: left; padding: 1.2vh .8vw; border-bottom: .3vh solid var(--line); font-weight: 600;
}
tbody td { padding: 0 .8vw; border-bottom: .1vh solid var(--line); white-space: nowrap;
           overflow: hidden; text-overflow: ellipsis; }
tbody tr { height: var(--row-h); }
tbody tr:nth-child(even) { background: var(--bg-alt); }

.c-rank    { width: 8%;  text-align: center; font-weight: 700; }
.c-ath     { width: 40%; font-weight: 600; }
.c-club    { width: 26%; color: var(--muted); }
.c-score   { width: 12%; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
.c-g, .c-x { width: 7%;  text-align: right; color: var(--muted); font-variant-numeric: tabular-nums; }

tbody td.c-rank, tbody td.c-ath, tbody td.c-club { font-size: var(--font-row); }
tbody td.c-score { font-size: calc(var(--font-row) * 1.05); }
tbody td.c-g, tbody td.c-x { font-size: calc(var(--font-row) * 0.85); }

tr.p1 .c-rank { color: var(--podium1); }
tr.p2 .c-rank { color: var(--podium2); }
tr.p3 .c-rank { color: var(--podium3); }

footer {
    display: flex; align-items: center; gap: 1.5vw;
    padding: 1vh 2vw; background: var(--panel); border-top: .3vh solid var(--line);
    font-size: 2vh; color: var(--muted);
}
.progress { flex: 1; height: .8vh; background: var(--line); border-radius: .4vh; overflow: hidden; }
.progress > i { display: block; height: 100%; width: 0; background: var(--accent); }
body.paused .progress > i { background: var(--muted); }

.empty { flex: 1; display: flex; align-items: center; justify-content: center;
         font-size: 4vh; color: var(--muted); text-align: center; }
</style>
</head>
<body>

<header>
    <div class="title" id="title">&nbsp;</div>
    <div class="event" id="event">Chargement…</div>
    <div class="meta">
        <span class="badge" id="demoBadge" hidden>DÉMO</span>
        <span class="dot" id="dot"></span>
        <span id="clock">--:--</span>
    </div>
</header>

<main id="main"></main>

<footer>
    <span id="pageInfo">&nbsp;</span>
    <span class="progress"><i id="bar"></i></span>
    <span>Espace : pause &middot; &larr; &rarr; : écran &middot; F : plein écran</span>
</footer>

<script>
(function () {
    'use strict';

    var DATA_URL = <?php echo json_encode($dataUrl); ?>;

    var cfg     = { rotate: 12, refresh: 10, rows: 12 };
    var labels  = { gold: '10+', xnine: 'X' };
    var pages   = [];      // écrans prêts à afficher
    var current = 0;
    var paused  = false;
    var elapsed = 0;       // secondes passées sur l'écran courant
    var lastOk  = 0;       // date de la dernière réponse valide
    var refreshTimer = null;

    var $main = document.getElementById('main');

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function tickClock() {
        var d = new Date();
        document.getElementById('clock').textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    // Découpe chaque épreuve en écrans de cfg.rows lignes.
    function buildPages(json) {
        var out = [];
        (json.sections || []).forEach(function (section) {
            var n = Math.max(1, Math.ceil(section.items.length / cfg.rows));
            for (var p = 0; p < n; p++) {
                out.push({
                    key:   section.event + '#' + p,
                    descr: section.descr,
                    part:  n > 1 ? (p + 1) + '/' + n : '',
                    items: section.items.slice(p * cfg.rows, (p + 1) * cfg.rows)
                });
            }
        });
        return out;
    }

    // Hauteur de ligne calée sur le nombre de lignes CONFIGURÉ, pas sur le
    // nombre réellement présent : un écran à 2 archers doit garder la même
    // typographie qu'un écran plein, pas afficher deux lignes géantes.
    function fitRows() {
        var head = $main.querySelector('thead');
        var free = $main.clientHeight - (head ? head.offsetHeight : 0) - 8;
        var h    = Math.max(24, Math.floor(free / Math.max(cfg.rows, 1)));

        // La police est bornée par la hauteur de ligne ET par la largeur : avec
        // peu de lignes, les lignes sont hautes, mais un texte calé sur leur
        // hauteur tronquerait les noms et surtout les scores.
        var f = Math.max(12, Math.min(Math.floor(h * 0.52),
                                      Math.floor($main.clientWidth * 0.028)));

        $main.style.setProperty('--row-h', h + 'px');
        $main.style.setProperty('--font-row', f + 'px');
    }

    function render() {
        if (!pages.length) {
            document.getElementById('event').textContent = 'Scores en direct';
            document.getElementById('pageInfo').textContent = '';
            $main.innerHTML = '<div class="empty">En attente des premiers scores…</div>';
            return;
        }
        if (current >= pages.length) { current = 0; }

        var page = pages[current];
        document.getElementById('event').textContent = page.descr + (page.part ? '  (' + page.part + ')' : '');
        document.getElementById('pageInfo').textContent = 'Écran ' + (current + 1) + ' / ' + pages.length;

        var html = '<table><thead><tr>'
            + '<th class="c-rank">#</th>'
            + '<th class="c-ath">Archer</th>'
            + '<th class="c-club">Club</th>'
            + '<th class="c-score">Score</th>'
            + '<th class="c-g">' + esc(labels.gold) + '</th>'
            + '<th class="c-x">' + esc(labels.xnine) + '</th>'
            + '</tr></thead><tbody>';

        page.items.forEach(function (it) {
            var r = parseInt(it.rank, 10);
            var cls = (r === 1 ? 'p1' : r === 2 ? 'p2' : r === 3 ? 'p3' : '');
            html += '<tr class="' + cls + '">'
                 +  '<td class="c-rank">'  + esc(it.rank)    + '</td>'
                 +  '<td class="c-ath">'   + esc(it.athlete) + '</td>'
                 +  '<td class="c-club">'  + esc(it.club)    + '</td>'
                 +  '<td class="c-score">' + esc(it.score)   + '</td>'
                 +  '<td class="c-g">'     + esc(it.gold)    + '</td>'
                 +  '<td class="c-x">'     + esc(it.xnine)   + '</td>'
                 +  '</tr>';
        });
        html += '</tbody></table>';

        $main.innerHTML = html;
        fitRows();
    }

    function armRefresh() {
        if (refreshTimer) { clearInterval(refreshTimer); }
        refreshTimer = setInterval(load, cfg.refresh * 1000);
    }

    function load() {
        var sep = DATA_URL.indexOf('?') >= 0 ? '&' : '?';
        fetch(DATA_URL + sep + '_=' + Date.now(), { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.error) { throw new Error(json.error); }

                var oldRefresh = cfg.refresh;
                cfg.rotate   = json.rotate  || cfg.rotate;
                cfg.refresh  = json.refresh || cfg.refresh;
                cfg.rows     = json.rows    || cfg.rows;
                labels.gold  = json.goldLabel  || labels.gold;
                labels.xnine = json.xnineLabel || labels.xnine;
                if (cfg.refresh !== oldRefresh) { armRefresh(); }

                document.getElementById('title').textContent = json.title || '';
                document.getElementById('demoBadge').hidden  = !json.demo;

                var keyBefore = pages.length ? pages[current].key : null;
                pages = buildPages(json);

                // Après un rafraîchissement, on reste sur l'épreuve affichée.
                if (keyBefore) {
                    var idx = -1;
                    for (var i = 0; i < pages.length; i++) {
                        if (pages[i].key === keyBefore) { idx = i; break; }
                    }
                    current = idx >= 0 ? idx : Math.min(current, Math.max(0, pages.length - 1));
                }

                lastOk = Date.now();
                document.getElementById('dot').classList.remove('stale');
                render();
            })
            .catch(function () {
                document.getElementById('dot').classList.add('stale');
            });
    }

    function nextPage(step) {
        if (!pages.length) { return; }
        current = (current + step + pages.length) % pages.length;
        elapsed = 0;
        render();
    }

    // Battement d'une seconde : horloge, rotation, détection de coupure.
    setInterval(function () {
        tickClock();

        if (lastOk && Date.now() - lastOk > cfg.refresh * 3000) {
            document.getElementById('dot').classList.add('stale');
        }

        if (paused) { return; }
        elapsed++;
        document.getElementById('bar').style.width = Math.min(100, (elapsed / cfg.rotate) * 100) + '%';
        if (elapsed >= cfg.rotate) { nextPage(1); }
    }, 1000);

    document.addEventListener('keydown', function (e) {
        if (e.key === ' ' || e.key === 'Spacebar' || e.code === 'Space') {
            paused = !paused;
            document.body.classList.toggle('paused', paused);
            e.preventDefault();
        } else if (e.key === 'ArrowRight') {
            nextPage(1);
        } else if (e.key === 'ArrowLeft') {
            nextPage(-1);
        } else if (e.key === 'f' || e.key === 'F') {
            if (document.fullscreenElement) { document.exitFullscreen(); }
            else { document.documentElement.requestFullscreen(); }
        }
    });

    window.addEventListener('resize', render);

    tickClock();
    load();
    armRefresh();
})();
</script>
</body>
</html>
