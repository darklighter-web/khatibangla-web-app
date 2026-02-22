<?php
/**
 * Maintenance Mode Page — Dual Game Support
 * Game 1: Space Runner (dark) — Chrome dino style
 * Game 2: Banana Jump (light) — Monkey platformer
 */
require_once __DIR__ . '/includes/functions.php';
$siteName   = getSetting('site_name', 'KhatiBangla');
$siteLogo   = getSetting('site_logo', '');
$maintMsg   = getSetting('maintenance_message', '');
$maintEta   = getSetting('maintenance_eta', '');
$maintTitle = getSetting('maintenance_title', '');
$maintSub   = getSetting('maintenance_subtitle', '');
$gameType   = getSetting('maintenance_game', 'space'); // space | monkey
if ($siteLogo && !str_starts_with($siteLogo, 'http'))
    $siteLogo = rtrim(SITE_URL, '/') . '/' . ltrim($siteLogo, '/');
http_response_code(503);
header('Retry-After: 3600');
$isDark = ($gameType === 'space');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($maintTitle ?: $siteName) ?> — রক্ষণাবেক্ষণ চলছে</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;min-height:100vh;overflow-x:hidden;
  background:<?= $isDark ? '#0b0f1a' : '#fef9ef' ?>;
  color:<?= $isDark ? '#e2e8f0' : '#3d2c1e' ?>;
}

/* ── Shared Layout ── */
.wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:16px;position:relative;z-index:1}
.logo-area{text-align:center;margin-bottom:16px}
.logo-area img{height:52px;border-radius:12px;padding:6px;background:<?= $isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.04)' ?>}
.site-name{font-size:22px;font-weight:800;letter-spacing:-.5px;margin-top:8px;
  <?php if($isDark): ?>background:linear-gradient(135deg,#e2e8f0,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent<?php else: ?>color:#5b3a1a<?php endif; ?>}
.badge{display:inline-block;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:12px;
  background:<?= $isDark ? 'rgba(239,68,68,.15)' : 'rgba(245,158,11,.12)' ?>;
  border:1px solid <?= $isDark ? 'rgba(239,68,68,.3)' : 'rgba(245,158,11,.3)' ?>;
  color:<?= $isDark ? '#fca5a5' : '#b45309' ?>}
.message{font-size:14px;line-height:1.7;max-width:440px;text-align:center;margin-bottom:20px;
  color:<?= $isDark ? '#94a3b8' : '#78716c' ?>}
.eta{font-size:12px;margin-bottom:8px;color:<?= $isDark ? '#64748b' : '#a8a29e' ?>}

/* ── Game Box ── */
.game-box{border-radius:18px;padding:10px;width:100%;max-width:600px;
  background:<?= $isDark ? 'rgba(255,255,255,.04)' : '#fff' ?>;
  border:1px solid <?= $isDark ? 'rgba(255,255,255,.06)' : '#e7e5e4' ?>;
  <?php if(!$isDark): ?>box-shadow:0 4px 24px rgba(0,0,0,.06)<?php endif; ?>}
.game-hud{display:flex;justify-content:space-between;align-items:center;padding:2px 8px 8px;font-size:13px;
  color:<?= $isDark ? '#64748b' : '#a8a29e' ?>}
.game-hud .val{font-weight:800;font-size:15px;margin-left:4px;color:<?= $isDark ? '#fff' : '#3d2c1e' ?>}
.canvas-wrap{position:relative}
#gameCanvas{display:block;width:100%;border-radius:12px;
  background:<?= $isDark ? '#080c16' : '#f0fdf4' ?>}

/* ── Overlays ── */
.g-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:12px;z-index:5;cursor:pointer;transition:opacity .3s}
.g-overlay.hidden{opacity:0;pointer-events:none}
#startOverlay{background:<?= $isDark ? 'rgba(8,12,22,.75)' : 'rgba(240,253,244,.85)' ?>}
#deadOverlay{background:<?= $isDark ? 'rgba(8,12,22,.8)' : 'rgba(254,249,239,.88)' ?>}
.g-overlay .icon{font-size:48px;margin-bottom:8px;animation:bob 2s ease-in-out infinite}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.g-overlay .txt{font-size:15px;font-weight:700;color:<?= $isDark ? '#e2e8f0' : '#3d2c1e' ?>}
.g-overlay .sub{font-size:12px;margin-top:4px;color:<?= $isDark ? '#94a3b8' : '#78716c' ?>}
.g-overlay .key{display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;margin-top:10px;
  border:1px solid <?= $isDark ? '#475569' : '#d6d3d1' ?>;color:<?= $isDark ? '#e2e8f0' : '#57534e' ?>;
  background:<?= $isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.03)' ?>}
.restart-btn{margin-top:14px;padding:10px 28px;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:transform .15s;
  background:<?= $isDark ? 'linear-gradient(135deg,#6366f1,#8b5cf6)' : 'linear-gradient(135deg,#f59e0b,#f97316)' ?>;color:#fff}
.restart-btn:hover{transform:scale(1.05)}
.dead-score{font-size:24px;font-weight:900;margin:6px 0;color:<?= $isDark ? '#fff' : '#3d2c1e' ?>}
.dead-best{font-size:12px;color:<?= $isDark ? '#94a3b8' : '#a8a29e' ?>}

.footer-note{font-size:11px;margin-top:20px;text-align:center;color:<?= $isDark ? '#334155' : '#d6d3d1' ?>}

/* ── Stars (dark theme) ── */
<?php if($isDark): ?>
.stars-layer{position:fixed;inset:0;pointer-events:none;z-index:0}
.sl{position:absolute;border-radius:50%;background:#fff}
.sl-1{animation:drift 80s linear infinite}
.sl-2{animation:drift 50s linear infinite}
@keyframes drift{from{transform:translateX(0)}to{transform:translateX(-1500px)}}
<?php endif; ?>

/* ── Clouds (light theme) ── */
<?php if(!$isDark): ?>
.cloud-layer{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}
.cloud{position:absolute;background:#fff;border-radius:50%;opacity:.5}
.cloud::before,.cloud::after{content:'';position:absolute;background:#fff;border-radius:50%}
.cl-1{width:80px;height:30px;top:10%;animation:floatC 40s linear infinite}
.cl-1::before{width:40px;height:40px;top:-20px;left:15px}
.cl-1::after{width:50px;height:35px;top:-15px;left:35px}
.cl-2{width:60px;height:22px;top:25%;animation:floatC 55s linear infinite;animation-delay:-15s}
.cl-2::before{width:30px;height:30px;top:-15px;left:10px}
.cl-2::after{width:35px;height:25px;top:-10px;left:25px}
.cl-3{width:90px;height:28px;top:6%;animation:floatC 45s linear infinite;animation-delay:-25s}
.cl-3::before{width:45px;height:45px;top:-22px;left:20px}
.cl-3::after{width:55px;height:38px;top:-18px;left:40px}
@keyframes floatC{from{left:110%}to{left:-200px}}
<?php endif; ?>
</style>
</head>
<body>

<?php if($isDark): ?>
<div class="stars-layer" id="bgLayer"></div>
<?php else: ?>
<div class="cloud-layer" id="bgLayer">
    <div class="cloud cl-1" style="left:20%"></div>
    <div class="cloud cl-2" style="left:60%"></div>
    <div class="cloud cl-3" style="left:90%"></div>
    <div class="cloud cl-1" style="left:140%;top:18%"></div>
    <div class="cloud cl-2" style="left:180%;top:8%"></div>
</div>
<?php endif; ?>

<div class="wrap">
    <div class="logo-area">
        <?php if ($siteLogo): ?><img src="<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>"><?php endif; ?>
        <div class="site-name"><?= htmlspecialchars($siteName) ?></div>
    </div>
    <div class="badge"><?= $isDark ? '🔧' : '🍌' ?> <?= htmlspecialchars($maintTitle ?: 'রক্ষণাবেক্ষণ চলছে') ?></div>
    <p class="message"><?= $maintMsg ? nl2br(htmlspecialchars($maintMsg)) : ($maintSub ?: 'আমাদের সাইটটি এই মুহূর্তে আপডেট হচ্ছে।<br>কিছুক্ষণের মধ্যেই ফিরে আসবে। ততক্ষণ গেমটি উপভোগ করুন! 🎮') ?></p>
    <?php if ($maintEta): ?><div class="eta">🕐 আনুমানিক সময়: <?= htmlspecialchars($maintEta) ?></div><?php endif; ?>

    <div class="game-box">
        <div class="game-hud">
            <div><?= $isDark ? '🚀' : '🐒' ?> স্কোর: <span class="val" id="hScore">0</span></div>
            <div>⚡ গতি: <span class="val" id="hSpeed">1</span>x</div>
            <div>🏆 সেরা: <span class="val" id="hBest">0</span></div>
        </div>
        <div class="canvas-wrap" id="canvasWrap">
            <canvas id="gameCanvas" width="600" height="200"></canvas>
            <div class="g-overlay" id="startOverlay">
                <div class="icon"><?= $isDark ? '🚀' : '🐒' ?></div>
                <div class="txt"><?= $isDark ? 'স্পেস রানার' : 'বানানা জাম্প' ?></div>
                <div class="sub"><?= $isDark ? 'মহাকাশে বাধা এড়িয়ে চলুন' : 'কলা সংগ্রহ করুন, বাধা এড়ান!' ?></div>
                <div class="key">SPACE / TAP</div>
            </div>
            <div class="g-overlay hidden" id="deadOverlay">
                <div class="icon">💥</div>
                <div class="txt">গেম ওভার!</div>
                <div class="dead-score" id="deadScore">0</div>
                <div class="dead-best" id="deadBest">সেরা: 0</div>
                <button class="restart-btn" id="restartBtn">🔄 আবার খেলুন</button>
                <div class="key" style="margin-top:8px">SPACE / TAP</div>
            </div>
        </div>
    </div>
    <div class="footer-note">শীঘ্রই ফিরে আসছি ❤️</div>
</div>

<script>
const GAME = '<?= $gameType ?>';
const cvs = document.getElementById('gameCanvas');
const ctx = cvs.getContext('2d');
const W = 600, H = 200;
cvs.width = W; cvs.height = H;

function fitCanvas(){
    const p = cvs.parentElement;
    const s = Math.min(1, (p.clientWidth - 20) / W);
    cvs.style.width = (W*s)+'px';
    cvs.style.height = (H*s)+'px';
}
fitCanvas();
window.addEventListener('resize', fitCanvas);

const GROUND = H - 24;
const GRAV = 0.6;
const JUMP = -11;
const DJUMP = -9;
let state = 'idle', score = 0, best = 0, speed = 4, speedMul = 1, frame = 0;
try { best = parseInt(localStorage.getItem('maint_best_'+GAME)||'0',10); } catch(e){}
document.getElementById('hBest').textContent = best;

// Player
let P = {x:60, y:GROUND, w:28, h:28, vy:0, grounded:true, jumps:0, flame:0, anim:0};
// Obstacles + collectibles
let OBS = [], COINS = [], PARTS = [];
let nextObs = 80, nextCoin = 40;
// BG
let bgItems = [];
for(let i=0;i<40;i++) bgItems.push({x:Math.random()*W, y:Math.random()*(H-30), s:Math.random()*1.5+.5, sp:Math.random()*.5+.2, a:Math.random()});
let groundDots = [];
for(let i=0;i<25;i++) groundDots.push({x:Math.random()*W, sp:Math.random()+1});

// ── DOM ──
const startOv = document.getElementById('startOverlay');
const deadOv = document.getElementById('deadOverlay');
const restartBtn = document.getElementById('restartBtn');

function showDead(){
    deadOv.classList.remove('hidden');
    document.getElementById('deadScore').textContent = score;
    document.getElementById('deadBest').textContent = 'সেরা: ' + best;
}
function hideDead(){ deadOv.classList.add('hidden'); }
function hideStart(){ startOv.style.display = 'none'; }

// ── Controls ──
function doJump(){
    if(state==='idle'){ startGame(); return; }
    if(state==='dead'){ startGame(); return; }
    if(P.jumps<2){
        P.vy = P.jumps===0 ? JUMP : DJUMP;
        P.grounded=false; P.jumps++; P.flame=8;
    }
}
document.addEventListener('keydown', e => {
    if(e.code==='Space'||e.code==='ArrowUp'){e.preventDefault(); doJump();}
    if(e.code==='ArrowDown'&&state==='running'){P.h=16;}
});
document.addEventListener('keyup', e => { if(e.code==='ArrowDown'){P.h=28;} });
cvs.addEventListener('touchstart', e => {e.preventDefault(); doJump();}, {passive:false});
cvs.addEventListener('mousedown', () => doJump());
startOv.addEventListener('click', () => doJump());
deadOv.addEventListener('click', e => { if(e.target===deadOv||e.target===restartBtn) doJump(); });
restartBtn.addEventListener('click', e => { e.stopPropagation(); doJump(); });

function startGame(){
    hideStart(); hideDead();
    state='running'; score=0; speed=4; speedMul=1; frame=0;
    P.y=GROUND; P.vy=0; P.grounded=true; P.jumps=0; P.h=28; P.flame=0; P.anim=0;
    OBS=[]; COINS=[]; PARTS=[]; nextObs=60; nextCoin=30;
}
function die(){
    state='dead';
    if(score>best){ best=score; try{localStorage.setItem('maint_best_'+GAME,best);}catch(e){} document.getElementById('hBest').textContent=best; }
    for(let i=0;i<20;i++){
        const a=Math.random()*Math.PI*2, sp=1+Math.random()*4;
        PARTS.push({x:P.x+P.w/2, y:P.y-P.h/2, vx:Math.cos(a)*sp, vy:Math.sin(a)*sp-2, r:2+Math.random()*3, life:1,
            c:GAME==='space'?['#f59e0b','#ef4444','#fff'][~~(Math.random()*3)]:['#f59e0b','#84cc16','#a16207'][~~(Math.random()*3)]});
    }
    showDead();
}

// ── Spawners ──
function spawnObs(){
    if(GAME==='space'){
        const types=['ast','ast','ast','sat','ufo'];
        if(speedMul>=1.5) types.push('ufo','dbl');
        const t=types[~~(Math.random()*types.length)];
        if(t==='dbl'){
            OBS.push({x:W+10,y:GROUND,w:18,h:18,type:'ast',passed:false});
            OBS.push({x:W+40,y:GROUND,w:18,h:18,type:'ast',passed:false});
        } else if(t==='ufo'){
            const fy=GROUND-30-Math.random()*40;
            OBS.push({x:W+10,y:fy,w:30,h:14,type:'ufo',passed:false,baseY:fy,ph:Math.random()*6});
        } else if(t==='sat'){
            OBS.push({x:W+10,y:GROUND-26-Math.random()*20,w:14,h:28,type:'sat',passed:false,ang:0});
        } else {
            const big=Math.random()<.3, sz=big?24:16;
            OBS.push({x:W+10,y:GROUND,w:sz,h:sz,type:'ast',passed:false});
        }
    } else { // monkey
        const types=['cactus','cactus','rock','bird'];
        if(speedMul>=1.5) types.push('bird','dbl');
        const t=types[~~(Math.random()*types.length)];
        if(t==='dbl'){
            OBS.push({x:W+10,y:GROUND,w:16,h:24,type:'cactus',passed:false});
            OBS.push({x:W+42,y:GROUND,w:16,h:20,type:'cactus',passed:false});
        } else if(t==='bird'){
            const fy=GROUND-28-Math.random()*35;
            OBS.push({x:W+10,y:fy,w:26,h:16,type:'bird',passed:false,baseY:fy,ph:Math.random()*6,wing:0});
        } else if(t==='rock'){
            OBS.push({x:W+10,y:GROUND,w:22,h:16,type:'rock',passed:false});
        } else {
            OBS.push({x:W+10,y:GROUND,w:16,h:20+Math.random()*12,type:'cactus',passed:false});
        }
    }
    nextObs = 50 + Math.random()*60/Math.min(speedMul,2.5);
}
function spawnCoin(){
    if(GAME==='monkey'){
        const yOff = Math.random()<.4 ? 40+Math.random()*30 : 10+Math.random()*15;
        COINS.push({x:W+10, y:GROUND-yOff, r:7, alive:true, bob:Math.random()*6});
    }
    nextCoin = 30+Math.random()*50;
}

// ── Update ──
function update(){
    if(state!=='running') return;
    frame++;
    score = Math.floor(frame*speedMul*0.3) + COINS.filter(c=>!c.alive).length*5;
    speedMul = 1+Math.floor(score/100)*0.15;
    speed = 4*speedMul;
    document.getElementById('hScore').textContent = score;
    document.getElementById('hSpeed').textContent = speedMul.toFixed(1);

    P.vy += GRAV; P.y += P.vy; P.anim += speedMul*0.15;
    if(P.y>=GROUND){P.y=GROUND;P.vy=0;P.grounded=true;P.jumps=0;}
    if(P.flame>0) P.flame--;

    nextObs--; if(nextObs<=0) spawnObs();
    if(GAME==='monkey'){nextCoin--; if(nextCoin<=0) spawnCoin();}

    for(let i=OBS.length-1;i>=0;i--){
        const o=OBS[i]; o.x-=speed;
        if(o.type==='ufo'||o.type==='bird') o.y=o.baseY+Math.sin(frame*.08+(o.ph||0))*10;
        if(o.type==='sat') o.ang=(o.ang||0)+.05;
        if(o.type==='bird') o.wing=(o.wing||0)+.2;
        if(o.x+o.w<-10){OBS.splice(i,1);continue;}
        // Collision
        const pad=4;
        if(P.x+pad<o.x+o.w-2 && P.x+P.w-pad>o.x+2 && P.y-P.h+pad<o.y-2 && P.y-pad>o.y-o.h+2){ die(); return; }
    }
    // Coins
    COINS.forEach(c=>{
        if(!c.alive) return;
        c.x-=speed;
        if(Math.hypot(P.x+P.w/2-c.x, P.y-P.h/2-c.y)<P.w/2+c.r){
            c.alive=false;
            for(let j=0;j<8;j++){const a=Math.random()*Math.PI*2;PARTS.push({x:c.x,y:c.y,vx:Math.cos(a)*2,vy:Math.sin(a)*2-1,r:2,life:1,c:'#f59e0b'});}
        }
    });
    COINS = COINS.filter(c=>c.x>-20);

    bgItems.forEach(s=>{s.x-=s.sp*speedMul;if(s.x<-5){s.x=W+5;s.y=Math.random()*(H-30);}});
    groundDots.forEach(d=>{d.x-=d.sp*speedMul;if(d.x<-5)d.x=W+Math.random()*20;});
}

// ── Draw ──
function draw(){
    ctx.clearRect(0,0,W,H);
    if(GAME==='space') drawSpaceBG(); else drawJungleBG();
    // Particles
    PARTS=PARTS.filter(p=>p.life>0);
    PARTS.forEach(p=>{p.x+=p.vx;p.y+=p.vy;p.vy+=.12;p.life-=.025;ctx.globalAlpha=p.life;ctx.fillStyle=p.c;ctx.beginPath();ctx.arc(p.x,p.y,p.r*p.life,0,Math.PI*2);ctx.fill();});
    ctx.globalAlpha=1;
    // Coins (monkey)
    COINS.forEach(c=>{
        if(!c.alive) return;
        const bobY = c.y + Math.sin(frame*.06+(c.bob||0))*4;
        // Banana
        ctx.save(); ctx.translate(c.x, bobY); ctx.rotate(-.3);
        ctx.fillStyle='#facc15';
        ctx.beginPath(); ctx.ellipse(0,0,c.r,c.r*.45,0,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#a16207';
        ctx.fillRect(-1,-c.r*.45,2,2);
        ctx.restore();
    });
    // Obstacles
    OBS.forEach(o=>{ctx.save(); if(GAME==='space') drawSpaceObs(o); else drawJungleObs(o); ctx.restore();});
    // Player
    if(state!=='dead'){if(GAME==='space') drawRocket(); else drawMonkey();}
}

// ═══ SPACE THEME ═══
function drawSpaceBG(){
    const sky=ctx.createLinearGradient(0,0,0,H); sky.addColorStop(0,'#050816'); sky.addColorStop(.7,'#0b1026'); sky.addColorStop(1,'#111833');
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
    bgItems.forEach(s=>{ctx.globalAlpha=.3+.5*Math.sin(frame*.03+s.a*10);ctx.fillStyle='#fff';ctx.fillRect(~~s.x,~~s.y,Math.ceil(s.s),Math.ceil(s.s));});
    ctx.globalAlpha=1;
    ctx.fillStyle='#1e293b'; ctx.fillRect(0,GROUND+1,W,H-GROUND);
    ctx.strokeStyle='#334155'; ctx.lineWidth=1; ctx.beginPath(); ctx.moveTo(0,GROUND+1); ctx.lineTo(W,GROUND+1); ctx.stroke();
    ctx.fillStyle='#334155'; groundDots.forEach(d=>{ctx.fillRect(~~d.x,GROUND+4+~~(Math.random()*8),2,1);});
}
function drawSpaceObs(o){
    const cx=o.x+o.w/2, cy=o.y-o.h/2;
    if(o.type==='ast'){
        ctx.fillStyle='rgba(239,68,68,.08)'; ctx.beginPath(); ctx.arc(cx,cy,o.w,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#78350f'; ctx.beginPath();
        for(let i=0;i<8;i++){const a=(i/8)*Math.PI*2,r=o.w/2*(0.7+.3*Math.sin(i*2.7+o.x*.1));i===0?ctx.moveTo(cx+Math.cos(a)*r,cy+Math.sin(a)*r):ctx.lineTo(cx+Math.cos(a)*r,cy+Math.sin(a)*r);}
        ctx.closePath(); ctx.fill();
        ctx.fillStyle='#92400e'; ctx.beginPath(); ctx.arc(cx-o.w*.1,cy-o.w*.1,o.w*.25,0,Math.PI*2); ctx.fill();
    } else if(o.type==='ufo'){
        ctx.fillStyle='rgba(16,185,129,.05)'; ctx.beginPath(); ctx.moveTo(cx-8,cy+6); ctx.lineTo(cx+8,cy+6); ctx.lineTo(cx+18,GROUND); ctx.lineTo(cx-18,GROUND); ctx.fill();
        ctx.fillStyle='#475569'; ctx.beginPath(); ctx.ellipse(cx,cy+2,o.w/2,4,0,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#10b981'; ctx.beginPath(); ctx.ellipse(cx,cy-2,8,6,0,Math.PI,0); ctx.fill();
    } else if(o.type==='sat'){
        ctx.translate(cx,cy); ctx.rotate(o.ang||0);
        ctx.fillStyle='#64748b'; ctx.fillRect(-4,-6,8,12);
        ctx.fillStyle='#3b82f6'; ctx.fillRect(-14,-3,9,6); ctx.fillRect(5,-3,9,6);
        ctx.fillStyle='#ef4444'; ctx.beginPath(); ctx.arc(0,-12,1.5,0,Math.PI*2); ctx.fill();
    }
}
function drawRocket(){
    ctx.save(); ctx.translate(P.x+P.w/2, P.y-P.h/2);
    ctx.rotate(P.grounded?0:P.vy*.015);
    // Flame
    if(!P.grounded||P.flame>0||frame%6<3){
        const fl=6+Math.random()*8+(P.flame>0?6:0);
        ctx.fillStyle='#f59e0b'; ctx.beginPath(); ctx.moveTo(-P.w/2-2,2); ctx.lineTo(-P.w/2-fl,0); ctx.lineTo(-P.w/2-2,-2); ctx.fill();
    }
    // Body
    ctx.fillStyle='#e2e8f0'; ctx.beginPath(); ctx.moveTo(P.w/2+4,0); ctx.lineTo(P.w/4,-P.h/2+2); ctx.lineTo(-P.w/2,-P.h/3); ctx.lineTo(-P.w/2,P.h/3); ctx.lineTo(P.w/4,P.h/2-2); ctx.closePath(); ctx.fill();
    ctx.fillStyle='#6366f1'; ctx.fillRect(-2,-P.h/3+2,6,P.h/1.6-4);
    ctx.fillStyle='#38bdf8'; ctx.beginPath(); ctx.arc(P.w/6,0,4,0,Math.PI*2); ctx.fill();
    // Fins
    ctx.fillStyle='#ef4444';
    ctx.beginPath(); ctx.moveTo(-P.w/2,-P.h/3); ctx.lineTo(-P.w/2-5,-P.h/2-2); ctx.lineTo(-P.w/4,-P.h/3); ctx.fill();
    ctx.beginPath(); ctx.moveTo(-P.w/2,P.h/3); ctx.lineTo(-P.w/2-5,P.h/2+2); ctx.lineTo(-P.w/4,P.h/3); ctx.fill();
    ctx.restore();
}

// ═══ MONKEY/JUNGLE THEME ═══
function drawJungleBG(){
    const sky=ctx.createLinearGradient(0,0,0,H); sky.addColorStop(0,'#dbeafe'); sky.addColorStop(.5,'#ecfccb'); sky.addColorStop(1,'#f0fdf4');
    ctx.fillStyle=sky; ctx.fillRect(0,0,W,H);
    // Distant trees
    ctx.fillStyle='#86efac'; for(let i=0;i<8;i++){const tx=((i*90+frame*.3*bgItems[i].sp)%750)-50;ctx.beginPath();ctx.moveTo(tx,GROUND-10);ctx.lineTo(tx+12,GROUND-35-bgItems[i].s*10);ctx.lineTo(tx+24,GROUND-10);ctx.fill();}
    // Ground
    ctx.fillStyle='#84cc16'; ctx.fillRect(0,GROUND+1,W,4);
    ctx.fillStyle='#65a30d'; ctx.fillRect(0,GROUND+5,W,H-GROUND-5);
    // Grass tufts
    ctx.fillStyle='#4ade80'; groundDots.forEach(d=>{ctx.fillRect(~~d.x,GROUND-2,2,4);});
}
function drawJungleObs(o){
    const cx=o.x+o.w/2, cy=o.y-o.h/2;
    if(o.type==='cactus'){
        // Thorny bush
        ctx.fillStyle='#dc2626'; ctx.beginPath();
        ctx.moveTo(o.x+o.w/2, o.y-o.h);
        ctx.lineTo(o.x+o.w, o.y); ctx.lineTo(o.x, o.y); ctx.closePath(); ctx.fill();
        ctx.fillStyle='#b91c1c'; ctx.beginPath();
        ctx.moveTo(o.x+o.w/2, o.y-o.h+4); ctx.lineTo(o.x+o.w-3, o.y); ctx.lineTo(o.x+3, o.y); ctx.closePath(); ctx.fill();
        // Thorns
        ctx.strokeStyle='#fca5a5'; ctx.lineWidth=1;
        ctx.beginPath(); ctx.moveTo(cx,o.y-o.h); ctx.lineTo(cx,o.y-o.h-5); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(cx-5,o.y-o.h+6); ctx.lineTo(cx-9,o.y-o.h+3); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(cx+5,o.y-o.h+6); ctx.lineTo(cx+9,o.y-o.h+3); ctx.stroke();
    } else if(o.type==='rock'){
        ctx.fillStyle='#78716c'; ctx.beginPath();
        ctx.ellipse(cx, o.y-o.h/2, o.w/2, o.h/2, 0, 0, Math.PI*2); ctx.fill();
        ctx.fillStyle='#57534e'; ctx.beginPath(); ctx.arc(cx-3,cy-2,4,0,Math.PI*2); ctx.fill();
    } else if(o.type==='bird'){
        ctx.fillStyle='#dc2626';
        ctx.beginPath(); ctx.ellipse(cx, cy, 10, 6, 0, 0, Math.PI*2); ctx.fill(); // body
        // Wings
        const wAngle = Math.sin(o.wing||0)*.5;
        ctx.fillStyle='#ef4444';
        ctx.beginPath(); ctx.ellipse(cx-3, cy-5, 8, 3, wAngle, 0, Math.PI*2); ctx.fill();
        // Eye
        ctx.fillStyle='#fff'; ctx.beginPath(); ctx.arc(cx+6,cy-2,2,0,Math.PI*2); ctx.fill();
        ctx.fillStyle='#000'; ctx.beginPath(); ctx.arc(cx+6.5,cy-2,1,0,Math.PI*2); ctx.fill();
        // Beak
        ctx.fillStyle='#f59e0b';
        ctx.beginPath(); ctx.moveTo(cx+11,cy); ctx.lineTo(cx+16,cy+1); ctx.lineTo(cx+11,cy+2); ctx.fill();
    }
}
function drawMonkey(){
    ctx.save();
    ctx.translate(P.x+P.w/2, P.y-P.h/2);
    const bounce = P.grounded ? Math.sin(P.anim*2)*2 : 0;
    ctx.translate(0, bounce);
    ctx.rotate(P.grounded ? 0 : P.vy*.01);

    // Body
    ctx.fillStyle='#a16207'; ctx.beginPath(); ctx.ellipse(0,2,10,12,0,0,Math.PI*2); ctx.fill();
    // Belly
    ctx.fillStyle='#fbbf24'; ctx.beginPath(); ctx.ellipse(0,5,7,8,0,0,Math.PI*2); ctx.fill();
    // Head
    ctx.fillStyle='#a16207'; ctx.beginPath(); ctx.arc(0,-12,9,0,Math.PI*2); ctx.fill();
    // Face
    ctx.fillStyle='#fbbf24'; ctx.beginPath(); ctx.ellipse(0,-10,6,5,0,0,Math.PI*2); ctx.fill();
    // Eyes
    ctx.fillStyle='#fff'; ctx.beginPath(); ctx.arc(-3,-13,2.5,0,Math.PI*2); ctx.fill(); ctx.beginPath(); ctx.arc(3,-13,2.5,0,Math.PI*2); ctx.fill();
    ctx.fillStyle='#1c1917'; ctx.beginPath(); ctx.arc(-2.5,-13,1.2,0,Math.PI*2); ctx.fill(); ctx.beginPath(); ctx.arc(3.5,-13,1.2,0,Math.PI*2); ctx.fill();
    // Mouth
    ctx.strokeStyle='#78350f'; ctx.lineWidth=1; ctx.beginPath(); ctx.arc(0,-8,3,0.2,Math.PI-0.2); ctx.stroke();
    // Ears
    ctx.fillStyle='#fbbf24'; ctx.beginPath(); ctx.arc(-9,-12,3,0,Math.PI*2); ctx.fill(); ctx.beginPath(); ctx.arc(9,-12,3,0,Math.PI*2); ctx.fill();
    // Tail
    ctx.strokeStyle='#a16207'; ctx.lineWidth=2.5; ctx.lineCap='round';
    ctx.beginPath(); ctx.moveTo(-8,10);
    const tailWag = Math.sin(P.anim*3)*5;
    ctx.quadraticCurveTo(-18,8+tailWag,-14,-2+tailWag);
    ctx.stroke();
    // Arms
    const armSwing = P.grounded ? Math.sin(P.anim*3)*8 : -15;
    ctx.strokeStyle='#a16207'; ctx.lineWidth=3;
    ctx.beginPath(); ctx.moveTo(-8,0); ctx.lineTo(-14,armSwing>0?8:-2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(8,0); ctx.lineTo(14,armSwing<0?8:-2); ctx.stroke();
    // Legs
    if(P.grounded){
        const legL = Math.sin(P.anim*3)*6, legR = -legL;
        ctx.beginPath(); ctx.moveTo(-5,12); ctx.lineTo(-7+legL,18); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(5,12); ctx.lineTo(7+legR,18); ctx.stroke();
    } else {
        ctx.beginPath(); ctx.moveTo(-5,12); ctx.lineTo(-8,18); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(5,12); ctx.lineTo(8,18); ctx.stroke();
    }
    ctx.restore();
}

// ── Stars (dark theme HTML) ──
if(GAME==='space'){
    const el=document.getElementById('bgLayer');
    for(let i=0;i<70;i++){
        const d=document.createElement('div'); d.className='sl '+(i%2?'sl-1':'sl-2');
        d.style.cssText='left:'+(Math.random()*150)+'%;top:'+(Math.random()*100)+'%;width:'+(1+Math.random()*1.5)+'px;height:'+(1+Math.random()*1.5)+'px;opacity:'+(0.2+Math.random()*0.5);
        el.appendChild(d);
    }
}

// ── Loop ──
(function loop(){ update(); draw(); requestAnimationFrame(loop); })();
})();
</script>
</body>
</html>
