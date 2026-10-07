<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Classifier lab</title>
<style>
:root{--bg:#f6f7f9;--card:#fff;--ink:#14181f;--muted:#5b6574;--line:#d9dee6;--accent:#2f5bd3;--ok:#1f7a4d;--bad:#b3261e;--bar:#dbe4fb}
@media (prefers-color-scheme:dark){:root{--bg:#101318;--card:#181c23;--ink:#e7eaf0;--muted:#97a1b0;--line:#2a313c;--accent:#7f9cff;--ok:#5cc58f;--bad:#f08a80;--bar:#25304d}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,sans-serif;padding:24px 16px}
main{max-width:960px;margin:0 auto;display:grid;gap:16px}h1{font-size:20px;margin:0}p{margin:0;color:var(--muted)}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;display:grid;gap:12px}
textarea{width:100%;min-height:70px;font:inherit;padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--ink)}
.row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}label.chk{display:flex;gap:6px;align-items:center;border:1px solid var(--line);border-radius:999px;padding:4px 10px}
button{font:inherit;border:0;border-radius:8px;padding:8px 14px;background:var(--accent);color:#fff;cursor:pointer}button.ghost{background:transparent;color:var(--accent);border:1px solid var(--line)}
.chip{font-size:13px;border:1px solid var(--line);border-radius:999px;padding:3px 10px;background:var(--bg);color:var(--ink);cursor:pointer}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.res h3{margin:0;font-size:14px;color:var(--muted);text-transform:lowercase}.kind{font-size:20px;font-weight:600}.err{color:var(--bad);font-size:13px;overflow-wrap:anywhere}
.bar{display:grid;grid-template-columns:110px 1fr 44px;gap:6px;align-items:center;font-size:12px}.bar div{height:8px;background:var(--bar);border-radius:4px;overflow:hidden}.bar span{display:block;height:100%;background:var(--accent)}
table{width:100%;border-collapse:collapse;font-size:13px}td,th{text-align:left;padding:6px;border-bottom:1px solid var(--line)}.ok{color:var(--ok)}.no{color:var(--bad)}
</style>
</head>
<body>
<main>
  <h1>Classifier lab</h1>
  <p>The classify step alone, per model. Nothing is saved. App currently uses: <strong>{{ $active }}</strong>.</p>

  <section class="card">
    <textarea id="text" placeholder="squat 90 90 95 85 85">My squat just now was 90 90 95 85 85</textarea>
    <div class="row">
      @foreach ($drivers as $d)
        <label class="chk"><input type="checkbox" name="driver" value="{{ $d }}" {{ in_array($d, ['rules','llm','laya']) ? 'checked' : '' }}> {{ $d }}</label>
      @endforeach
      <button id="run">Classify</button>
      <button id="eval" class="ghost">Score the eval set</button>
    </div>
    <div class="row">
      @foreach (array_slice($examples, 0, 14) as $ex)
        <button class="chip" type="button" data-ex="{{ $ex }}">{{ $ex }}</button>
      @endforeach
    </div>
  </section>

  <section id="out" class="grid"></section>
  <section id="evalout"></section>
</main>
<script>
const token = document.querySelector('meta[name=csrf-token]').content;
const drivers = () => [...document.querySelectorAll('input[name=driver]:checked')].map(i => i.value);
const post = (url, body) => fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify(body)}).then(r => r.json());
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

function card(r) {
  const bars = Object.entries(r.probabilities || {}).map(([k, p]) =>
    `<div class="bar"><small>${esc(k)}</small><div><span style="width:${Math.round(p * 100)}%"></span></div><small>${p.toFixed(2)}</small></div>`).join('');
  return `<article class="card res"><h3>${esc(r.driver)} · ${r.ms} ms</h3>` +
    (r.error ? `<div class="err">${esc(r.error)}</div>` :
      `<div class="kind">${esc(r.kind ?? 'no opinion')}</div><small>confidence ${r.confidence ?? '-'}</small>${bars}`) + `</article>`;
}

document.getElementById('run').onclick = async () => {
  const out = document.getElementById('out');
  out.innerHTML = '<p>Asking...</p>';
  const data = await post('/lab/classify', {text: document.getElementById('text').value, drivers: drivers()});
  out.innerHTML = (data.results || []).map(card).join('') || `<p class="err">${esc(data.message)}</p>`;
};

document.getElementById('eval').onclick = async () => {
  const box = document.getElementById('evalout');
  box.innerHTML = '<p>Scoring every labelled message...</p>';
  const data = await post('/lab/classify/eval', {drivers: drivers()});
  const ds = Object.keys(data.summary || {});
  const sum = ds.map(d => { const s = data.summary[d]; return `<tr><td>${d}</td><td>${s.correct}/${s.total}</td><td>${s.total ? Math.round(100 * s.correct / s.total) : 0}%</td><td>${s.errors}</td><td>${s.avg_ms}</td></tr>`; }).join('');
  const rows = (data.cases || []).map(c => `<tr><td>${esc(c.expected)}</td><td>${esc(c.text)}</td>` +
    c.results.map(r => r.error ? '<td class="no">error</td>' : `<td class="${r.kind === c.expected ? 'ok' : 'no'}">${esc(r.kind ?? '-')}</td>`).join('') + '</tr>').join('');
  box.innerHTML = `<div class="card"><table><tr><th>driver</th><th>correct</th><th>accuracy</th><th>errors</th><th>avg ms</th></tr>${sum}</table></div>` +
    `<div class="card"><table><tr><th>expected</th><th>message</th>${ds.map(d => `<th>${d}</th>`).join('')}</tr>${rows}</table></div>`;
};

document.querySelectorAll('[data-ex]').forEach(b => b.onclick = () => { document.getElementById('text').value = b.dataset.ex; document.getElementById('run').click(); });
</script>
</body>
</html>
