const root = document.getElementById('room-app');
const { code, roomId, userId } = root.dataset;
const devUser = new URLSearchParams(location.search).get('as');
const devHeaders = devUser ? { 'X-Dev-User': devUser } : {};
const csrf = document.querySelector('meta[name="csrf-token"]').content;

let state = null;
const SAMPLE_MS = 200; // 音量サンプリング間隔
const BASELINE_MS = 2000; // 環境ノイズ測定時間
let mic = { ctx: null, timer: null, sum: 0, active: false, baseline: null, calibrating: false, answerId: null };
let typing = false; // 入力中は再描画で内容が消えないようにする

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const btn = (id, label, cls = '') => `<button id="${id}" class="btn ${cls}">${label}</button>`;
const card = (inner) => `<section class="stage-panel mb-4 p-5">${inner}</section>`;

async function api(path, body) {
    const res = await fetch(`/rooms/${code}/${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...devHeaders },
        body: JSON.stringify(body ?? {}),
    });
    if (!res.ok) {
        const j = await res.json().catch(() => ({}));
        alert(j.message || 'エラーが発生しました');
    }
    return res.ok;
}

let lastKey = null;
async function refresh() {
    const res = await fetch(`/rooms/${code}/state`, { headers: { Accept: 'application/json', ...devHeaders } });
    if (!res.ok) return;
    state = await res.json();
    await syncVolume();
    // 入力中は、ラウンドの局面が変わらない限り再描画しない(入力内容を守る)
    const r = state.round;
    const key = JSON.stringify([state.room.status, r?.id, r?.status, r?.revealed.length, r?.current_answer_id, !!r?.results]);
    if (typing && key === lastKey) return;
    lastKey = key;
    render();
}

const ROLE = { host: 'ホスト', performer: '演者', judge: '審査員' };
const MODE = { offline_laugh: 'オフライン(笑い声)', online_vote: 'オンライン(投票)' };

// お題の下書き(候補選択・手入力)。再描画で消えないようモジュール変数に持つ
let odaiDraft = { title: '', source: null };
let candidates = null; // null=未取得 / []=取得失敗 / [...]
let candidatesError = '';
let flippedId = null; // めくりアニメーションは、新しく公開された回答に1度だけ付ける

function render() {
    const { room, me, members, round } = state;
    const isHost = me.role === 'host';
    let html = '';

    html += `<section class="stage-panel mb-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="text-xs text-stone-400">招待コード</div>
                <div class="text-4xl font-bold leading-none tracking-[0.25em] text-white">${esc(room.code)}</div>
            </div>
            <div class="text-right text-sm text-stone-300">
                <div>あなたは <b class="text-white">${ROLE[me.role]}</b>${me.is_eliminated ? '(脱落)' : ''}</div>
                <div class="text-xs text-stone-400">${MODE[room.judging_mode]} ・ 演者定員${room.max_performers}人 ・ ${room.max_rounds ? `全${room.max_rounds}ラウンド` : 'ラウンド無制限'}</div>
            </div>
        </div>
        <ul class="mt-3 flex flex-wrap gap-2">
            ${members.map((m) => `<li class="nameplate" data-role="${m.role}" data-out="${m.is_eliminated}">${esc(m.name)}<span class="ml-1 text-xs font-normal opacity-60">${ROLE[m.role]}</span></li>`).join('')}
        </ul>
    </section>`;

    if (room.status === 'finished') {
        const alive = members.filter((m) => m.role === 'performer' && !m.is_eliminated);
        html += `<section class="mekuri mb-4 flex flex-wrap items-center justify-center gap-4 px-6 py-6 text-center">
            <span class="seal">${alive.length === 1 ? '優勝' : '大会終了'}</span>
            <span class="text-3xl">${alive.length === 1 ? esc(alive[0].name) : alive.map((m) => esc(m.name)).join('、')}</span>
        </section>`;
    }

    if (!round) {
        html += card('<p class="text-stone-300">ホストがお題を出すのを待っています。</p>');
    } else {
        html += renderRound(round, me, room);
    }

    if (isHost) html += renderHost(round, room);
    if (round?.results) html += renderResults(round);

    if (room.status === 'finished') {
        // 開発用の ?as= を引き継いで、同じユーザーのままトップへ戻る
        const home = devUser ? `/?as=${encodeURIComponent(devUser)}` : '/';
        html += `<div class="mt-8 text-center"><a href="${home}" class="btn">トップに戻る</a></div>`;
    }

    const draft = document.getElementById('answer-body')?.value;
    root.innerHTML = html;
    const box = document.getElementById('answer-body');
    if (box && draft) box.value = draft;
    bind(round, room, me);
    tick();
}

function renderRound(round, me, room) {
    let h = `<div class="mekuri mb-4 px-6 py-6 sm:px-8">
        <div class="flex items-start justify-between gap-4">
            <div class="font-sans text-sm text-stone-500">第${round.number}ラウンド</div>
            ${round.status === 'collecting' ? `<div class="text-right font-sans"><div id="timer" class="text-4xl font-bold tabular-nums leading-none" data-end="${round.ends_at}" data-total="${round.time_limit}">--</div><div class="text-xs text-stone-500">残り秒 ・ 提出 ${round.answer_count}件</div></div>` : ''}
        </div>
        <div class="mt-2 text-3xl leading-snug sm:text-4xl">${esc(round.odai.title)}</div>
        ${round.odai.source_url ? `<a href="${esc(round.odai.source_url)}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block font-sans text-xs text-stone-500 underline">出典: 大喜利掲示板</a>` : ''}
        ${round.status === 'collecting' ? '<div class="timebar mt-4" id="timebar"><i style="width:100%"></i></div>' : ''}
    </div>`;

    if (round.status === 'collecting') {
        if (me.role === 'performer' && !me.is_eliminated) {
            h += `<div class="stage-panel mb-4 p-5">
                <textarea id="answer-body" maxlength="200" rows="2" placeholder="一言回答を入力" class="field text-lg"></textarea>
                <div class="mt-3 flex flex-wrap items-center gap-3">${btn('answer-submit', '回答を送る')}
                <span class="text-sm text-stone-400">${round.my_answer ? `送信済み: 「${esc(round.my_answer)}」(送り直すと上書きされます)` : '締切まで、他の人の回答は見えません。'}</span></div></div>`;
        } else if (me.role !== 'host') {
            h += '<p class="mb-4 text-sm text-stone-400">演者が回答を考えています。回答は締切後に1件ずつ公開されます。</p>';
        }
    } else if (round.status !== 'finished') {
        h += `<p class="mb-3 text-sm text-stone-400">回答を公開中(${round.revealed.length}/${round.reveal_total}件)</p>`;
        if (round.next_reveal_is_mine) {
            h += `<div class="stage-panel mb-4 p-5 text-center">
                <p class="mb-3 text-lg font-bold">次はあなたの回答です</p>
                <button id="reveal-mine" class="btn !px-8 !py-4 text-xl">自分の回答を公開する</button>
                <p class="mt-2 text-xs text-stone-400">前の回答の反応が落ち着いてから押してください。</p></div>`;
        } else if (me.role === 'performer' && !me.is_eliminated && round.my_answer) {
            h += '<p class="mb-3 text-sm text-stone-400">順番が来たら、公開ボタンが出ます。</p>';
        }
    }

    if (round.revealed.length) {
        const current = round.revealed.find((a) => a.id === round.current_answer_id);
        const flip = current && current.id !== flippedId;
        if (current) flippedId = current.id;
        const past = round.revealed.filter((a) => a.id !== round.current_answer_id).reverse();

        if (current) {
            h += `<div class="mekuri ${flip ? 'mekuri-flip' : ''} mb-4 px-6 py-8 text-center text-3xl leading-snug sm:text-5xl">${esc(current.body)}</div>`;
        }
        if (past.length) {
            h += `<ol class="mb-4 space-y-2">${past.map((a) => `<li class="flex items-baseline justify-between gap-3 border-l-4 border-stage-3 bg-stage-2 px-4 py-3">
                <span class="text-lg font-bold leading-snug ${current ? 'text-stone-300' : 'text-white'}">${esc(a.body)}</span>
                <span class="shrink-0 text-right text-xs text-stone-400">${a.name ? esc(a.name) : ''}${a.votes ? ` 面白い${a.votes.funny}/微妙${a.votes.meh}` : ''}</span></li>`).join('')}</ol>`;
        }
    }

    if (round.status === 'voting' && me.role === 'host' && round.current_answer_id) {
        h += `<p class="mb-4 text-sm text-stone-300">この回答への投票: ${round.current_vote_count}件</p>`;
    }
    if (round.status === 'voting' && me.role === 'judge' && round.current_answer_id) {
        const on = (c) => (round.my_vote === c ? 'ring-4 ring-white' : 'opacity-80');
        h += `<div class="mb-4 grid grid-cols-2 gap-3">
            <button data-vote="funny" class="btn !py-5 text-xl ${on('funny')}">面白い</button>
            <button data-vote="meh" class="btn btn-quiet !py-5 text-xl ${on('meh')}">微妙</button></div>
            <p class="mb-4 text-center text-xs text-stone-400">${round.my_vote ? '投票済み。押し直すと変更できます。' : 'この回答に投票してください。'}</p>`;
    }
    return h;
}

function renderHost(round, room) {
    let h = '<h3 class="mb-3 font-bold text-kaki">ホスト操作</h3>';
    const idle = !round || round.status === 'finished';
    if (idle && room.status !== 'finished') {
        h += `<div class="space-y-3">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-3">
                    ${btn('odai-fetch', candidates ? '別の3つを出す' : '大喜利掲示板からお題を3つ出す', 'btn-quiet')}
                    <span class="text-xs text-stone-400">出典: 大喜利掲示板(chinsukoustudy.com)</span>
                </div>
                ${candidatesError ? `<p class="mb-2 text-sm text-kaki">${esc(candidatesError)}</p>` : ''}
                ${candidates ? `<ul class="grid gap-2">${candidates.map((c, i) => `
                    <li><button data-candidate="${i}" class="w-full border px-4 py-3 text-left text-lg leading-snug ${odaiDraft.source === c.url ? 'border-moegi bg-moegi/20' : 'border-stage-3 bg-stage hover:bg-stage-2'}">${esc(c.title)}</button></li>`).join('')}</ul>` : ''}
            </div>
            <input id="odai-title" value="${esc(odaiDraft.title)}" placeholder="候補を選ぶか、お題を直接入力" class="field text-lg">
            <div class="flex flex-wrap items-center gap-3"><label class="text-sm">制限時間(秒)
                <input id="odai-time" type="number" value="60" min="10" max="600" class="field ml-1 !inline-block !w-24"></label>
                ${btn('host-start', 'このお題で始める')}</div></div>`;
    } else if (round?.status === 'collecting') {
        h += btn('host-close', '回答を締め切って公開を始める');
    } else if (round && round.status !== 'finished') {
        const remaining = round.reveal_total - round.revealed.length;
        if (room.judging_mode === 'offline_laugh') {
            h += `<div class="mb-3 flex flex-wrap items-center gap-3 text-sm">
                ${mic.calibrating ? '<span class="text-kaki">環境音を測定中です。静かにしてください。</span>'
                    : mic.ctx ? '<span class="text-moegi">マイクで笑い声を計測中</span>'
                    : btn('host-mic', 'マイクを有効にする', 'btn-quiet') + '<span class="text-kaki">最初の回答を公開する前に有効にしてください。</span>'}
                <span id="mic-level" class="font-mono text-stone-400"></span></div>`;
        }
        h += remaining > 0
            ? `<p class="mb-3 text-sm text-stone-300">回答は演者が自分で公開します(残り${remaining}件)。</p>${btn('host-next', '演者に代わって次を公開する', 'btn-quiet')}`
            : btn('host-finish', '結果を集計する', 'btn-danger');
    } else if (room.status === 'finished') {
        h += '<p class="text-sm text-stone-400">大会は終了しました。</p>';
    }
    return card(h);
}

function renderResults(round) {
    const nobodyOut = round.results.every((r) => !r.is_eliminated);
    return `<section class="mekuri mb-4 px-6 py-6">
        <h3 class="mb-1 text-2xl">結果発表</h3>
        ${nobodyOut ? '<p class="mb-2 font-sans text-sm text-stone-600">脱落者はいません(全員同点、または回答なし)。次のラウンドに進みます。</p>' : ''}
        <ol class="divide-y divide-stone-300">${round.results.map((r) => `
            <li class="flex items-center gap-4 py-3 ${r.is_eliminated ? 'text-enji' : ''}">
                <span class="w-10 text-center text-4xl leading-none">${r.rank}</span>
                <span class="flex-1 text-xl">${esc(r.name)}</span>
                <span class="font-sans text-sm tabular-nums">${r.score}点</span>
                <span class="w-12 text-right font-sans text-sm font-bold">${r.is_eliminated ? '脱落' : ''}</span>
            </li>`).join('')}</ol></section>`;
}

function bind(round, room, me) {
    const on = (id, fn) => document.getElementById(id)?.addEventListener('click', fn);

    const body = document.getElementById('answer-body');
    body?.addEventListener('focus', () => (typing = true));
    body?.addEventListener('blur', () => (typing = false));
    on('answer-submit', async () => {
        if (await api('answer', { body: body.value })) { typing = false; refresh(); }
    });

    document.querySelectorAll('[data-vote]').forEach((b) =>
        b.addEventListener('click', async () => { await api('vote', { choice: b.dataset.vote }); refresh(); }));

    for (const id of ['odai-title', 'odai-time']) {
        const el = document.getElementById(id);
        el?.addEventListener('focus', () => (typing = true));
        el?.addEventListener('blur', () => (typing = false));
    }
    document.getElementById('odai-title')?.addEventListener('input', (e) => {
        odaiDraft.title = e.target.value;
        // 候補から選んだあとに書き換えたら、出典は付けない
        if (candidates && odaiDraft.source && !candidates.some((c) => c.url === odaiDraft.source && c.title === odaiDraft.title)) odaiDraft.source = null;
    });
    on('odai-fetch', async () => {
        candidatesError = '';
        const res = await fetch(`/rooms/${code}/odai-candidates`, { headers: { Accept: 'application/json', ...devHeaders } });
        const j = await res.json().catch(() => ({}));
        if (res.ok) candidates = j.candidates;
        else candidatesError = j.message || 'お題を取得できませんでした。直接入力してください。';
        render();
    });
    document.querySelectorAll('[data-candidate]').forEach((b) => b.addEventListener('click', () => {
        const c = candidates[+b.dataset.candidate];
        odaiDraft = { title: c.title, source: c.url };
        render();
    }));
    on('host-start', async () => {
        typing = false;
        const ok = await api('rounds', {
            title: document.getElementById('odai-title').value,
            source_url: odaiDraft.source,
            time_limit_sec: +document.getElementById('odai-time').value,
        });
        if (ok) { odaiDraft = { title: '', source: null }; candidates = null; refresh(); }
    });
    on('host-close', async () => { if (await api('close-answers')) refresh(); });
    on('reveal-mine', async () => { if (await api('reveal-next')) refresh(); });
    on('host-next', async () => { if (await api('reveal-next')) refresh(); });
    on('host-finish', async () => {
        await flushVolume(); // 最後の回答の音量を保存してから集計する
        if (await api('finish-round')) { mic.answerId = null; mic.active = false; refresh(); }
    });
    on('host-mic', startMic);
}

// ---- 笑い声検出(オフラインモード・ホストのみ) ----
// 公開中の回答ごとに RMS を 200ms 間隔でサンプリングして合計する
// 演者が回答を公開して現在の回答が切り替わるたびに、直前の回答の音量合計をホストPCから送る
async function flushVolume() {
    if (mic.answerId && mic.ctx) await api('volume', { answer_id: mic.answerId, volume_sum: mic.sum });
    mic.sum = 0;
}
async function syncVolume() {
    if (state.me.role !== 'host' || state.room.judging_mode !== 'offline_laugh') return;
    const cur = state.round?.status === 'finished' ? null : state.round?.current_answer_id ?? null;
    if (cur === mic.answerId) return;
    const prev = mic.answerId;
    mic.answerId = cur; // 先に切り替えて、並行するrefreshでの二重送信を防ぐ
    const sum = mic.sum;
    mic.sum = 0;
    mic.active = !!mic.ctx && !!cur;
    if (prev && mic.ctx) await api('volume', { answer_id: prev, volume_sum: sum });
}

async function startMic() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mic.ctx = new AudioContext();
        const analyser = mic.ctx.createAnalyser();
        analyser.fftSize = 2048;
        mic.ctx.createMediaStreamSource(stream).connect(analyser);
        const buf = new Float32Array(analyser.fftSize);

        // 最初の BASELINE_MS は環境ノイズ(会場の暗騒音)を測り、以降はその平均を差し引く
        const ambient = [];
        mic.calibrating = true;
        mic.baseline = 0;
        setTimeout(() => {
            mic.baseline = ambient.length ? ambient.reduce((a, b) => a + b, 0) / ambient.length : 0;
            mic.calibrating = false;
            render();
        }, BASELINE_MS);

        mic.timer = setInterval(() => {
            analyser.getFloatTimeDomainData(buf);
            const rms = Math.sqrt(buf.reduce((s, v) => s + v * v, 0) / buf.length);
            if (mic.calibrating) ambient.push(rms);
            else if (mic.active) mic.sum += Math.max(0, rms - mic.baseline);
            const el = document.getElementById('mic-level');
            if (el) el.textContent = `音量 ${'▮'.repeat(Math.min(20, Math.round(Math.max(0, rms - mic.baseline) * 100)))}`;
        }, SAMPLE_MS);
        mic.answerId = state.round?.status === 'finished' ? null : state.round?.current_answer_id ?? null;
        mic.active = !!mic.answerId;
        render();
    } catch (e) {
        alert('マイクを使用できません: ' + e.message);
    }
}

let expiredFor = null;
function tick() {
    const el = document.getElementById('timer');
    if (!el) return;
    const remaining = Math.max(0, Math.ceil((new Date(el.dataset.end) - Date.now()) / 1000));
    el.textContent = remaining;
    const bar = document.getElementById('timebar');
    if (bar) {
        const total = +el.dataset.total || 60;
        bar.firstElementChild.style.width = `${Math.min(100, (remaining / total) * 100)}%`;
        bar.dataset.low = remaining <= 10;
    }
    // 締切を過ぎたらサーバー側の自動締切(猶予2秒)後に状態を取り直す
    if (remaining === 0 && expiredFor !== state?.round?.id) {
        expiredFor = state.round.id;
        setTimeout(refresh, 2500);
    }
}
setInterval(tick, 500);

// ---- リアルタイム(Reverb + Echo) ----
const channel = window.Echo.join(`room.${roomId}`)
    .here(refresh).joining(refresh).leaving(refresh);
for (const ev of ['RoundStarted', 'RoundUpdated', 'AnswerRevealed', 'VoteCast', 'RoundFinished']) {
    channel.listen(ev, refresh);
}
window.Echo.private(`room.${roomId}.performer.${userId}`).listen('AnswerSubmitted', refresh);

refresh();
setInterval(refresh, 10000); // WebSocket切断時のフォールバック
