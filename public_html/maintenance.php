<?php
/**
 * Maintenance Mode Page — Space Runner Game
 * 503 response + retry header + fun game
 */
require_once __DIR__ . '/includes/functions.php';
$siteName = getSetting('site_name', 'KhatiBangla');
$siteLogo = getSetting('site_logo', '');
$maintMsg = getSetting('maintenance_message', '');
$maintEta = getSetting('maintenance_eta', '');
if ($siteLogo && !str_starts_with($siteLogo, 'http')) {
    $siteLogo = rtrim(SITE_URL, '/') . '/' . ltrim($siteLogo, '/');
}
http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($siteName) ?> — রক্ষণাবেক্ষণ চলছে</title>
<style>
:root{--primary:#6366f1;--bg:#0b0f1a;--surface:rgba(255,255,255,.04)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;min-height:100vh;overflow:hidden}

/* ── Stars ── */
.stars-layer{position:fixed;inset:0;pointer-events:none;z-index:0}
.sl{position:absolute;border-radius:50%;background:#fff}
.sl-1{animation:drift 80s linear infinite}
.sl-2{animation:drift 50s linear infinite}
.sl-3{animation:drift 30s linear infinite}
@keyframes drift{from{transform:translateX(0)}to{transform:translateX(-1500px)}}

/* ── Layout ── */
.wrap{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:16px}
.logo-area{text-align:center;margin-bottom:20px}
.logo-area img{height:52px;border-radius:12px;background:rgba(255,255,255,.08);padding:6px}
.site-name{font-size:22px;font-weight:800;letter-spacing:-.5px;margin-top:8px;background:linear-gradient(135deg,#e2e8f0,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.badge{display:inline-block;padding:5px 14px;border-radius:20px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;font-size:12px;font-weight:600;margin-bottom:12px}
.message{font-size:14px;color:#94a3b8;line-height:1.7;max-width:440px;text-align:center;margin-bottom:24px}
.eta{font-size:12px;color:#64748b;margin-bottom:8px}

/* ── Game Container ── */
.game-box{background:var(--surface);border:1px solid rgba(255,255,255,.06);border-radius:18px;padding:10px;width:100%;max-width:600px;backdrop-filter:blur(10px)}
.game-hud{display:flex;justify-content:space-between;align-items:center;padding:2px 8px 8px;font-size:13px;color:#64748b}
.game-hud .val{color:#fff;font-weight:800;font-size:15px;margin-left:4px}
#gameCanvas{display:block;width:100%;border-radius:12px;background:#080c16;image-rendering:pixelated}
.game-start-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(8,12,22,.75);border-radius:12px;z-index:5;cursor:pointer}
.game-start-overlay .icon{font-size:48px;margin-bottom:10px;animation:bob 2s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.game-start-overlay .txt{font-size:14px;color:#94a3b8}
.game-start-overlay .key{display:inline-block;padding:3px 10px;border:1px solid #475569;border-radius:6px;color:#e2e8f0;font-size:12px;font-weight:700;margin-top:8px;background:rgba(255,255,255,.05)}
.canvas-wrap{position:relative}

.footer-note{font-size:11px;color:#334155;margin-top:20px;text-align:center}
.footer-note a{color:#6366f1;text-decoration:none}
</style>
</head>
<body>

<!-- Parallax Stars -->
<div class="stars-layer" id="starsLayer"></div>

<div class="wrap">
    <div class="logo-area">
        <?php if ($siteLogo): ?>
            <img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>">
        <?php endif; ?>
        <div class="site-name"><?= htmlspecialchars($siteName) ?></div>
    </div>

    <div class="badge">🔧 রক্ষণাবেক্ষণ চলছে</div>

    <p class="message"><?= $maintMsg
        ? nl2br(htmlspecialchars($maintMsg))
        : 'আমাদের সাইটটি এই মুহূর্তে আপডেট হচ্ছে।<br>কিছুক্ষণের মধ্যেই ফিরে আসবে। ততক্ষণ গেমটি উপভোগ করুন! 🎮' ?></p>

    <?php if ($maintEta): ?>
        <div class="eta">🕐 আনুমানিক সময়: <?= htmlspecialchars($maintEta) ?></div>
    <?php endif; ?>

    <div class="game-box">
        <div class="game-hud">
            <div>🚀 দূরত্ব: <span class="val" id="hScore">0</span></div>
            <div>⚡ গতি: <span class="val" id="hSpeed">1</span>x</div>
            <div>🏆 সেরা: <span class="val" id="hBest">0</span></div>
        </div>
        <div class="canvas-wrap" id="canvasWrap">
            <canvas id="gameCanvas" width="600" height="200"></canvas>
            <div class="game-start-overlay" id="startOverlay">
                <div class="icon">🚀</div>
                <div class="txt">স্পেস রানার</div>
                <div class="key">SPACE / TAP</div>
            </div>
        </div>
    </div>

    <div class="footer-note">শীঘ্রই ফিরে আসছি ❤️</div>
</div>

<script>
// ═══════════════════════════════════════════
//  🚀 SPACE RUNNER — Chrome Dino style game
// ═══════════════════════════════════════════
(function(){

const cvs = document.getElementById('gameCanvas');
const ctx = cvs.getContext('2d');
const W = 600, H = 200;
cvs.width = W; cvs.height = H;

// Responsive scaling
function fitCanvas(){
    const p = cvs.parentElement;
    const s = Math.min(1, (p.clientWidth - 20) / W);
    cvs.style.width = (W*s)+'px';
    cvs.style.height = (H*s)+'px';
}
fitCanvas();
window.addEventListener('resize', fitCanvas);

// ── Constants ──
const GROUND_Y = H - 24;
const GRAVITY = 0.6;
const JUMP_FORCE = -11;
const DOUBLE_JUMP_FORCE = -9;

// ── State ──
let state = 'idle'; // idle | running | dead
let score = 0, best = parseInt(localStorage.getItem('sr_best')||'0', 10);
let speed = 4, speedMul = 1;
let frame = 0;

document.getElementById('hBest').textContent = best;

// ── Rocket Player ──
const rocket = {
    x: 60, y: GROUND_Y, w: 28, h: 28,
    vy: 0, grounded: true, jumps: 0,
    tilt: 0, flame: 0
};

// ── Obstacles ──
let obstacles = [];
let nextObstacle = 80;

// ── Stars (bg) ──
let bgStars = [];
for(let i=0;i<40;i++) bgStars.push({x:Math.random()*W, y:Math.random()*(H-30), s:Math.random()*1.5+.5, sp:Math.random()*0.5+0.2, a:Math.random()});

// ── Ground particles ──
let groundDots = [];
for(let i=0;i<25;i++) groundDots.push({x:Math.random()*W, sp:Math.random()*1+1});

// ── Explosion particles ──
let expParts = [];

// ── Obstacle types ──
function spawnObstacle(){
    const types = ['asteroid','asteroid','asteroid','satellite','ufo'];
    if(speedMul >= 2) types.push('ufo','double');
    const t = types[Math.floor(Math.random()*types.length)];
    
    if(t === 'double'){
        obstacles.push({x:W+10,  y:GROUND_Y,     w:18, h:18, type:'asteroid', passed:false});
        obstacles.push({x:W+40, y:GROUND_Y,     w:18, h:18, type:'asteroid', passed:false});
    } else if(t === 'ufo'){
        const fy = GROUND_Y - 30 - Math.random()*40;
        obstacles.push({x:W+10, y:fy, w:30, h:14, type:'ufo', passed:false, baseY:fy, phase:Math.random()*6});
    } else if(t === 'satellite'){
        obstacles.push({x:W+10, y:GROUND_Y - 26 - Math.random()*20, w:14, h:28, type:'satellite', passed:false, angle:0});
    } else {
        const big = Math.random() < 0.3;
        const sz = big ? 24 : 16;
        obstacles.push({x:W+10, y:GROUND_Y, w:sz, h:sz, type:'asteroid', passed:false});
    }
    
    nextObstacle = 50 + Math.random()*60 / Math.min(speedMul, 2.5);
}

// ── Input ──
function jump(){
    if(state === 'idle'){
        startGame();
        return;
    }
    if(state === 'dead'){
        startGame();
        return;
    }
    if(rocket.jumps < 2){
        rocket.vy = rocket.jumps === 0 ? JUMP_FORCE : DOUBLE_JUMP_FORCE;
        rocket.grounded = false;
        rocket.jumps++;
        rocket.flame = 8;
    }
}

document.addEventListener('keydown', e => {
    if(e.code === 'Space' || e.code === 'ArrowUp'){
        e.preventDefault();
        jump();
    }
    // Duck
    if(e.code === 'ArrowDown' && state === 'running'){
        rocket.h = 16;
        rocket.tilt = 0.3;
    }
});
document.addEventListener('keyup', e => {
    if(e.code === 'ArrowDown'){
        rocket.h = 28;
        rocket.tilt = 0;
    }
});
cvs.addEventListener('touchstart', e => { e.preventDefault(); jump(); }, {passive:false});
cvs.addEventListener('mousedown', e => { jump(); });
document.getElementById('startOverlay').addEventListener('click', () => jump());

// ── Game Control ──
function startGame(){
    document.getElementById('startOverlay').style.display = 'none';
    state = 'running';
    score = 0; speed = 4; speedMul = 1; frame = 0;
    rocket.y = GROUND_Y; rocket.vy = 0; rocket.grounded = true; rocket.jumps = 0;
    rocket.h = 28; rocket.tilt = 0;
    obstacles = []; expParts = [];
    nextObstacle = 60;
}

function die(){
    state = 'dead';
    if(score > best){
        best = score;
        try{ localStorage.setItem('sr_best', best); }catch(e){}
        document.getElementById('hBest').textContent = best;
    }
    // Explosion
    for(let i=0;i<25;i++){
        const a = Math.random()*Math.PI*2;
        const sp = 1+Math.random()*4;
        expParts.push({x:rocket.x+rocket.w/2, y:rocket.y-rocket.h/2, vx:Math.cos(a)*sp, vy:Math.sin(a)*sp-2, r:2+Math.random()*3, life:1, c:['#f59e0b','#ef4444','#f97316','#fff'][Math.floor(Math.random()*4)]});
    }
}

// ── Update ──
function update(){
    if(state !== 'running') return;
    frame++;
    
    // Score & speed
    score = Math.floor(frame * speedMul * 0.3);
    speedMul = 1 + Math.floor(score / 100) * 0.15;
    speed = 4 * speedMul;
    
    document.getElementById('hScore').textContent = score;
    document.getElementById('hSpeed').textContent = speedMul.toFixed(1);
    
    // Rocket physics
    rocket.vy += GRAVITY;
    rocket.y += rocket.vy;
    if(rocket.y >= GROUND_Y){
        rocket.y = GROUND_Y;
        rocket.vy = 0;
        rocket.grounded = true;
        rocket.jumps = 0;
    }
    if(rocket.flame > 0) rocket.flame--;
    
    // Obstacles
    nextObstacle--;
    if(nextObstacle <= 0) spawnObstacle();
    
    for(let i = obstacles.length-1; i >= 0; i--){
        const o = obstacles[i];
        o.x -= speed;
        
        // UFO hover
        if(o.type === 'ufo'){
            o.y = o.baseY + Math.sin(frame*0.08 + (o.phase||0)) * 10;
        }
        // Satellite spin
        if(o.type === 'satellite'){
            o.angle = (o.angle||0) + 0.05;
        }
        
        // Remove offscreen
        if(o.x + o.w < -10){
            obstacles.splice(i, 1);
            continue;
        }
        
        // Score when passed
        if(!o.passed && o.x + o.w < rocket.x){
            o.passed = true;
        }
        
        // Collision (slightly forgiving hitbox)
        const pad = 4;
        const rx = rocket.x + pad;
        const ry = rocket.y - rocket.h + pad;
        const rw = rocket.w - pad*2;
        const rh = rocket.h - pad*2;
        const ox = o.x + 2;
        const oy = o.y - o.h + 2;
        const ow = o.w - 4;
        const oh = o.h - 4;
        
        if(rx < ox+ow && rx+rw > ox && ry < oy+oh && ry+rh > oy){
            die();
            return;
        }
    }
    
    // BG stars
    bgStars.forEach(s => {
        s.x -= s.sp * speedMul;
        if(s.x < -5) { s.x = W+5; s.y = Math.random()*(H-30); }
    });
    
    // Ground dots
    groundDots.forEach(d => {
        d.x -= d.sp * speedMul;
        if(d.x < -5) d.x = W + Math.random()*20;
    });
}

// ── Draw ──
function draw(){
    ctx.clearRect(0,0,W,H);
    
    // Sky gradient
    const sky = ctx.createLinearGradient(0,0,0,H);
    sky.addColorStop(0,'#050816');
    sky.addColorStop(0.7,'#0b1026');
    sky.addColorStop(1,'#111833');
    ctx.fillStyle = sky;
    ctx.fillRect(0,0,W,H);
    
    // BG stars
    bgStars.forEach(s => {
        const pulse = 0.5 + 0.5*Math.sin(frame*0.03 + s.a*10);
        ctx.globalAlpha = 0.3 + pulse*0.5;
        ctx.fillStyle = '#fff';
        ctx.fillRect(Math.floor(s.x), Math.floor(s.y), Math.ceil(s.s), Math.ceil(s.s));
    });
    ctx.globalAlpha = 1;
    
    // Ground line
    ctx.fillStyle = '#1e293b';
    ctx.fillRect(0, GROUND_Y + 1, W, H - GROUND_Y);
    ctx.strokeStyle = '#334155';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, GROUND_Y + 1);
    ctx.lineTo(W, GROUND_Y + 1);
    ctx.stroke();
    
    // Ground dots (dust)
    ctx.fillStyle = '#334155';
    groundDots.forEach(d => {
        ctx.fillRect(Math.floor(d.x), GROUND_Y + 4 + Math.floor(Math.random()*8), 2, 1);
    });
    
    // ── Obstacles ──
    obstacles.forEach(o => {
        ctx.save();
        if(o.type === 'asteroid') drawAsteroid(o);
        else if(o.type === 'ufo') drawUFO(o);
        else if(o.type === 'satellite') drawSatellite(o);
        ctx.restore();
    });
    
    // ── Rocket ──
    if(state !== 'dead'){
        drawRocket();
    }
    
    // ── Explosion ──
    expParts = expParts.filter(p => p.life > 0);
    expParts.forEach(p => {
        p.x += p.vx; p.y += p.vy; p.vy += 0.12; p.life -= 0.025;
        ctx.globalAlpha = p.life;
        ctx.fillStyle = p.c;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r*p.life, 0, Math.PI*2);
        ctx.fill();
    });
    ctx.globalAlpha = 1;
    
    // ── Dead overlay ──
    if(state === 'dead'){
        ctx.fillStyle = 'rgba(5,8,22,0.6)';
        ctx.fillRect(0,0,W,H);
        
        ctx.fillStyle = '#fff';
        ctx.font = 'bold 22px "Segoe UI",system-ui,sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('💥 গেম ওভার!', W/2, H/2 - 18);
        
        ctx.fillStyle = '#94a3b8';
        ctx.font = '13px "Segoe UI",system-ui,sans-serif';
        ctx.fillText('স্কোর: ' + score + '  |  সেরা: ' + best, W/2, H/2 + 8);
        
        ctx.fillStyle = '#6366f1';
        ctx.font = 'bold 13px "Segoe UI",system-ui,sans-serif';
        ctx.fillText('আবার খেলতে SPACE / TAP করুন', W/2, H/2 + 32);
        ctx.textAlign = 'left';
    }
    
    // Speed milestone flash
    if(state === 'running' && score > 0 && score % 100 < 3 && frame % 4 < 2){
        ctx.fillStyle = 'rgba(99,102,241,0.08)';
        ctx.fillRect(0,0,W,H);
    }
}

// ── Draw Rocket ──
function drawRocket(){
    const x = rocket.x, y = rocket.y, w = rocket.w, h = rocket.h;
    ctx.save();
    ctx.translate(x + w/2, y - h/2);
    ctx.rotate(rocket.tilt + (rocket.grounded ? 0 : rocket.vy * 0.015));
    
    // Flame
    if(!rocket.grounded || rocket.flame > 0 || frame % 6 < 3){
        const fl = 6 + Math.random()*8 + (rocket.flame > 0 ? 6 : 0);
        ctx.fillStyle = '#f59e0b';
        ctx.beginPath();
        ctx.moveTo(-w/2 - 2, 2);
        ctx.lineTo(-w/2 - fl, 0);
        ctx.lineTo(-w/2 - 2, -2);
        ctx.fill();
        ctx.fillStyle = '#ef4444';
        ctx.beginPath();
        ctx.moveTo(-w/2 - 2, 1);
        ctx.lineTo(-w/2 - fl*0.6, 0);
        ctx.lineTo(-w/2 - 2, -1);
        ctx.fill();
    }
    
    // Body
    ctx.fillStyle = '#e2e8f0';
    ctx.beginPath();
    ctx.moveTo(w/2 + 4, 0);                // nose
    ctx.lineTo(w/4, -h/2 + 2);             // top
    ctx.lineTo(-w/2, -h/3);
    ctx.lineTo(-w/2, h/3);
    ctx.lineTo(w/4, h/2 - 2);              // bottom
    ctx.closePath();
    ctx.fill();
    
    // Stripe
    ctx.fillStyle = '#6366f1';
    ctx.fillRect(-2, -h/3 + 2, 6, h/1.6 - 4);
    
    // Window
    ctx.fillStyle = '#38bdf8';
    ctx.beginPath();
    ctx.arc(w/6, 0, 4, 0, Math.PI*2);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.4)';
    ctx.beginPath();
    ctx.arc(w/6 - 1, -1, 1.5, 0, Math.PI*2);
    ctx.fill();
    
    // Fins
    ctx.fillStyle = '#ef4444';
    ctx.beginPath();
    ctx.moveTo(-w/2, -h/3);
    ctx.lineTo(-w/2 - 5, -h/2 - 2);
    ctx.lineTo(-w/4, -h/3);
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(-w/2, h/3);
    ctx.lineTo(-w/2 - 5, h/2 + 2);
    ctx.lineTo(-w/4, h/3);
    ctx.fill();
    
    ctx.restore();
}

// ── Draw Asteroid ──
function drawAsteroid(o){
    const cx = o.x + o.w/2, cy = o.y - o.h/2;
    const r = o.w/2;
    
    // Glow
    ctx.fillStyle = 'rgba(239,68,68,0.1)';
    ctx.beginPath();
    ctx.arc(cx, cy, r*1.8, 0, Math.PI*2);
    ctx.fill();
    
    // Rocky body
    ctx.fillStyle = '#78350f';
    ctx.beginPath();
    const pts = 8;
    for(let i=0;i<pts;i++){
        const a = (i/pts)*Math.PI*2;
        const rr = r * (0.7 + 0.3*Math.sin(i*2.7+o.x*0.1));
        const px = cx + Math.cos(a)*rr;
        const py = cy + Math.sin(a)*rr;
        i===0 ? ctx.moveTo(px,py) : ctx.lineTo(px,py);
    }
    ctx.closePath();
    ctx.fill();
    
    // Highlight
    ctx.fillStyle = '#92400e';
    ctx.beginPath();
    ctx.arc(cx-r*0.2, cy-r*0.2, r*0.5, 0, Math.PI*2);
    ctx.fill();
    
    // Crater
    ctx.fillStyle = '#451a03';
    ctx.beginPath();
    ctx.arc(cx+r*0.15, cy+r*0.1, r*0.25, 0, Math.PI*2);
    ctx.fill();
}

// ── Draw UFO ──
function drawUFO(o){
    const cx = o.x + o.w/2, cy = o.y - o.h/2;
    
    // Beam
    ctx.fillStyle = 'rgba(16,185,129,0.06)';
    ctx.beginPath();
    ctx.moveTo(cx-8, cy+6);
    ctx.lineTo(cx+8, cy+6);
    ctx.lineTo(cx+18, GROUND_Y);
    ctx.lineTo(cx-18, GROUND_Y);
    ctx.fill();
    
    // Saucer bottom
    ctx.fillStyle = '#475569';
    ctx.beginPath();
    ctx.ellipse(cx, cy+2, o.w/2, 4, 0, 0, Math.PI*2);
    ctx.fill();
    
    // Dome
    ctx.fillStyle = '#10b981';
    ctx.beginPath();
    ctx.ellipse(cx, cy-2, 8, 6, 0, Math.PI, 0);
    ctx.fill();
    
    // Light
    ctx.fillStyle = '#34d399';
    ctx.beginPath();
    ctx.arc(cx, cy-4, 2, 0, Math.PI*2);
    ctx.fill();
    
    // Blinking lights
    const blink = Math.sin(frame*0.15) > 0;
    if(blink){
        ctx.fillStyle = '#f59e0b';
        ctx.beginPath();
        ctx.arc(cx - o.w/2 + 4, cy+2, 1.5, 0, Math.PI*2);
        ctx.fill();
        ctx.beginPath();
        ctx.arc(cx + o.w/2 - 4, cy+2, 1.5, 0, Math.PI*2);
        ctx.fill();
    }
}

// ── Draw Satellite ──
function drawSatellite(o){
    const cx = o.x + o.w/2, cy = o.y - o.h/2;
    ctx.save();
    ctx.translate(cx, cy);
    ctx.rotate(o.angle || 0);
    
    // Body
    ctx.fillStyle = '#64748b';
    ctx.fillRect(-4, -6, 8, 12);
    
    // Panels
    ctx.fillStyle = '#3b82f6';
    ctx.fillRect(-14, -3, 9, 6);
    ctx.fillRect(5, -3, 9, 6);
    
    // Panel lines
    ctx.strokeStyle = '#1e40af';
    ctx.lineWidth = 0.5;
    ctx.beginPath();
    ctx.moveTo(-14,0); ctx.lineTo(-5,0);
    ctx.moveTo(5,0); ctx.lineTo(14,0);
    ctx.stroke();
    
    // Antenna
    ctx.strokeStyle = '#94a3b8';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0,-6); ctx.lineTo(0,-12);
    ctx.stroke();
    ctx.fillStyle = '#ef4444';
    ctx.beginPath();
    ctx.arc(0, -12, 1.5, 0, Math.PI*2);
    ctx.fill();
    
    ctx.restore();
}

// ── Parallax stars background (HTML) ──
(function(){
    const el = document.getElementById('starsLayer');
    const layers = [{c:50,s:1,cls:'sl-1'},{c:30,s:1.5,cls:'sl-2'},{c:15,s:2,cls:'sl-3'}];
    layers.forEach(l => {
        for(let i=0;i<l.c;i++){
            const d = document.createElement('div');
            d.className = 'sl ' + l.cls;
            d.style.cssText = 'left:'+(Math.random()*150)+'%;top:'+(Math.random()*100)+'%;width:'+l.s+'px;height:'+l.s+'px;opacity:'+(0.2+Math.random()*0.5);
            el.appendChild(d);
        }
    });
})();

// ── Game Loop ──
function loop(){
    update();
    draw();
    requestAnimationFrame(loop);
}
loop();

})();
</script>
</body>
</html>
