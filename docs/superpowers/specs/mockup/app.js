/* Inkwell mockup — shared data + behaviour (all screens) */
(function(){
  /* ---------- workspace data ---------- */
  const USERS = {
    mira:   {name:'mira.ink', handle:'mira_ink', avatar:47, bio:'Comics + character studies. Mostly ink, sometimes colour. NSFW sometimes.', followers:'12.4k', following:'318', posts:'204'},
    rin:    {name:'Rin Senpai', handle:'rin_senpai', avatar:12, bio:'Making manga about cities at 2am. Explicit chapters are marked. 18+.', followers:'31.2k', following:'112', posts:'412'},
    val:    {name:'Val', handle:'valdraws', avatar:32, bio:'Digital painter. GIFs and loops. Commissions closed.', followers:'8.9k', following:'240', posts:'167'},
    kuro:   {name:'Kuro', handle:'kurostudies', avatar:5, bio:'Studies, sketch dumps, process videos. SFW only.', followers:'5.1k', following:'88', posts:'301'},
    aya:    {name:'Aya', handle:'aya_lines', avatar:26, bio:'Lineart gremlin. OCs. 18+.', followers:'3.8k', following:'54', posts:'142'}
  };

  /* one work = one post. type: image | gif | video | album | comic */
  const WORKS = [
    {id:'w01', type:'image', ar:1.45, user:'rin',  rate:'sfw',  title:'Mountain quiet', likes:'2.1k', views:'18k', img:'https://picsum.photos/id/1015/600/870', body:'Did a plein-air study up near the lake. Never doing 6am again.'},
    {id:'w02', type:'image', ar:1.66, user:'mira', rate:'sug',  title:'Late study',      likes:'840',  views:'6.4k', img:'https://picsum.photos/id/1027/600/1000', body:'Lighting study, oil filter, too many layers.'},
    {id:'w03', type:'gif',   ar:1.00, user:'val',  rate:'explicit', title:'loop study', likes:'1.4k', views:'22k', img:'https://picsum.photos/id/1005/600/600', body:'Adjusted the loop timing — flagging explicit because of the second half.'},
    {id:'w04', type:'video', ar:1.28, user:'kuro', rate:'sfw',  title:'timelapse — 4h in 40s', likes:'920', views:'11k', img:'https://picsum.photos/id/1018/600/770', body:'Full process video, with the embarrassing parts left in.'},
    {id:'w05', type:'album', ar:1.40, user:'rin',  rate:'sug',  title:'Autumn set · 7 pieces', likes:'630', views:'5.2k', img:'https://picsum.photos/id/1035/600/840', body:'Seven pieces from this month. Swipe through in the post.'},
    {id:'w06', type:'comic', ar:1.60, user:'rin',  rate:'explicit', title:'Neon After Dark · Ch. 12', likes:'3.2k', views:'40k', img:'https://picsum.photos/id/1043/600/960', body:'New chapter is up. Ch. 12 is where it gets loud.', series:{slug:'neon-after-dark', title:'Neon After Dark', chapter:12, pages:24}},
    {id:'w07', type:'image', ar:1.26, user:'val',  rate:'sfw',  title:'Morning market', likes:'1.1k', views:'9k', img:'https://picsum.photos/id/1059/600/760', body:'Trying to draw fewer details, actually failing.'},
    {id:'w08', type:'image', ar:1.85, user:'mira', rate:'explicit', title:'After hours', likes:'2.8k', views:'31k', img:'https://picsum.photos/id/1062/600/1110', body:'Wanted it moodier than the last one. Explicit.'},
    {id:'w09', type:'gif',   ar:1.12, user:'kuro', rate:'sug',  title:'blink test', likes:'410', views:'3.1k', img:'https://picsum.photos/id/1067/600/672', body:'12 frames, blink cycle. Took 3 hours to shade.'},
    {id:'w10', type:'image', ar:1.55, user:'rin',  rate:'sfw',  title:'Canopy', likes:'1.9k', views:'14k', img:'https://picsum.photos/id/1074/600/930', body:'Ceiling of a temple garden.'},
    {id:'w11', type:'video', ar:1.62, user:'val',  rate:'explicit', title:'process · 60s', likes:'880', views:'7.7k', img:'https://picsum.photos/id/1080/600/970', body:'Process for the piece from last week. Explicit edit.'},
    {id:'w12', type:'image', ar:1.72, user:'kuro', rate:'sfw',  title:'Quiet room', likes:'1.3k', views:'10k', img:'https://picsum.photos/id/1084/600/1030', body:'Reference from my own bedroom. Yes it looks like that.'},
    {id:'w13', type:'comic', ar:1.55, user:'mira', rate:'sug',  title:'Paper Lanterns · Ch. 4', likes:'760', views:'6.9k', img:'https://picsum.photos/id/1036/600/930', body:'Ch. 4 — lantern festival, mostly dialogue.', series:{slug:'paper-lanterns', title:'Paper Lanterns', chapter:4, pages:16}},
    {id:'w14', type:'image', ar:1.40, user:'val',  rate:'sug',  title:'Rooftop', likes:'1.0k', views:'8.2k', img:'https://picsum.photos/id/1040/600/840', body:'Warm light, late summer.'},
    {id:'w15', type:'album', ar:1.28, user:'kuro', rate:'sfw',  title:'Sketch dump · 12', likes:'540', views:'4.4k', img:'https://picsum.photos/id/1047/600/770', body:'Twelve pages of junk, some of it good.'},
    {id:'w16', type:'image', ar:1.55, user:'rin',  rate:'explicit', title:'Study 14', likes:'2.4k', views:'27k', img:'https://picsum.photos/id/1052/600/930', body:'Study 14 in the series. Explicit.'},
    {id:'w17', type:'image', ar:1.35, user:'aya',  rate:'sfw',  title:'Line weight tests', likes:'610', views:'4.8k', img:'https://picsum.photos/id/1076/600/810', body:'Testing brush textures. Probably keep two.'},
    {id:'w18', type:'gif',   ar:1.25, user:'aya',  rate:'sug',  title:'hair wind loop', likes:'720', views:'5.5k', img:'https://picsum.photos/id/1069/600/750', body:'Wind loop, second pass.'},
    {id:'w19', type:'comic', ar:1.60, user:'aya',  rate:'sfw',  title:'Wired · Ch. 1', likes:'1.5k', views:'12k', img:'https://picsum.photos/id/1041/600/960', body:'First chapter of the new series. SFW start.', series:{slug:'wired', title:'Wired', chapter:1, pages:20}},
    {id:'w20', type:'image', ar:1.48, user:'mira', rate:'sfw',  title:'Tea house', likes:'980', views:'7.1k', img:'https://picsum.photos/id/1039/600/890', body:'Watercolour, scanned at 600dpi.'}
  ];

  const KIND = {image:'Image', gif:'GIF', video:'Video', album:'Album', comic:'Comic'};
  const KIND_ICON = {image:'🖼', gif:'🎞', video:'▶', album:'🗂', comic:'📖'};
  const RATE_CLASS = {sfw:'sfw', sug:'sug', explicit:'expl'};
  const RATE_LABEL = {sfw:'SFW', sug:'Suggestive', explicit:'Explicit'};

  /* ---------- persistent prefs (shared per-origin) ---------- */
  const LS = {
    get(k, d){ try { const v = localStorage.getItem('inkwell.' + k); return v === null ? d : JSON.parse(v); } catch(e){ return d; } },
    set(k, v){ try { localStorage.setItem('inkwell.' + k, JSON.stringify(v)); } catch(e){} }
  };
  const prefs = {
    showExplicit: LS.get('showExplicit', true),
    maturePreview: LS.get('maturePreview', false),
    gifHover: LS.get('gifHover', true),
    motion: LS.get('actionMotion', true),
    readerMode: LS.get('readerMode', 'page'),
    emailNotif: LS.get('emailNotif', false),
    dmRequests: LS.get('dmRequests', true),
    readReceipts: LS.get('readReceipts', true)
  };
  function savePrefs(){ LS.set('showExplicit', prefs.showExplicit); LS.set('maturePreview', prefs.maturePreview);
    LS.set('gifHover', prefs.gifHover); LS.set('actionMotion', prefs.motion); LS.set('readerMode', prefs.readerMode);
    LS.set('emailNotif', prefs.emailNotif); LS.set('dmRequests', prefs.dmRequests); LS.set('readReceipts', prefs.readReceipts); }

  /* ---------- helpers ---------- */
  const el = (h) => { const t = document.createElement('template'); t.innerHTML = h.trim(); return t.content.firstElementChild; };
  const byId = (id) => WORKS.find(w => w.id === id);
  const userOf = (w) => USERS[w.user];
  function thumbSrc(w, wpx){ return w.img; }

  function statsHTML(w){
    return `<div class="stats">
      <span data-icon="heart">♥ ${w.likes}</span>
      <span data-icon="eye">👁 ${w.views}</span>
    </div>`;
  }

  /* ---------- gallery cell ---------- */
  function cellHTML(w, i){
    if (w.id === 'promo') return promoCell();
    const u = userOf(w);
    const blur = w.rate === 'explicit' && !prefs.showExplicit;
    return `<div class="cell" data-id="${w.id}" data-i="${i}" tabindex="0" role="link" aria-label="${w.title} by ${u.name}">
      <div class="cbox">✓</div>
      <div class="thumb" style="aspect-ratio:${w.ar}">
        <img src="${thumbSrc(w)}" alt="" loading="lazy" onerror="this.style.display='none'" class="${blur ? 'blurred' : ''}">
        ${blur ? `<div class="blurgate"><span class="b1">Explicit</span><span class="b2">Hidden by your feed settings</span></div>` : ''}
        <div class="chipbadge"><span class="rate ${RATE_CLASS[w.rate]}">${RATE_LABEL[w.rate]}</span></div>
        ${w.type === 'video' || w.type === 'gif' ? `<div class="play"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div>` : ''}
        ${w.type === 'comic' ? `<span class="kind">${KIND_ICON.comic} Comic series</span>` : ''}
        <span class="kind" style="left:auto;right:9px;bottom:9px">${KIND_ICON[w.type]} ${KIND[w.type]}</span>
        ${w.type === 'album' ? `<span class="floorpill">7</span>` : ''}
      </div>
      <div class="cellfoot">
        <div class="byline"><img src="https://i.pravatar.cc/40?img=${u.avatar}" alt=""><span class="name">${u.name} <em>@${u.handle}</em></span></div>
        <div class="title">${w.title}</div>
        ${statsHTML(w)}
      </div>
    </div>`;
  }
  function promoCell(){
    return `<div class="cell promoted" data-promo="1">
      <div class="thumb" style="aspect-ratio:1.15">
        <div class="plabel" style="position:absolute;top:10px;left:12px">Promoted</div>
        <div>Ad network native tile<br><span style="opacity:.6">collapses when empty</span></div>
      </div>
      <div class="cellfoot">
        <div class="byline"><span class="name">Adobe of the North <em>· sponsor</em></span></div>
        <div class="stats"><span>Learn more →</span></div>
      </div>
    </div>`;
  }

  /* ---------- Twitter-style post card ---------- */
  function postHTML(w, opts){
    opts = opts || {};
    const u = userOf(w);
    const blur = w.rate === 'explicit' && !prefs.showExplicit;
    let media = '';
    if (w.type === 'comic'){
      media = `<div class="media comic">
        <img src="${w.img}" alt="" onerror="this.style.background='#2a2330'">
        <div class="ci"><div class="t">${w.series.title} · Ch. ${w.series.chapter}</div>
          <div class="d">${w.series.pages} pages · Explicit · Continue where you left off</div>
          <a class="go" href="screen-reader.html?series=${w.series.slug}&chapter=${w.series.chapter}">Read chapter</a></div>
      </div>`;
    } else {
      media = `<div class="media"><img src="${w.img}" alt="" onerror="this.style.display='none'" class="${blur ? 'blurred' : ''}">
        ${blur ? `<div class="blurgate"><span class="b1">${RATE_LABEL[w.rate]} content</span><span class="b2">Tap to reveal</span><button data-reveal>Show</button></div>` : ''}
        ${w.type === 'album' ? `<span class="albumpill">7 pieces</span>` : ''}
      </div>`;
    }
    return `<article class="post" data-id="${w.id}">
      <img class="avatar" src="https://i.pravatar.cc/96?img=${u.avatar}" alt="">
      <div style="min-width:0">
        <div class="head"><b>${u.name}</b><span class="h">@${u.handle}</span><span class="dot">·</span><span class="when">${opts.when || '2h'}</span>
          <span class="right"><span class="rate ${RATE_CLASS[w.rate]}">${RATE_LABEL[w.rate]}</span><span style="padding:0 6px">⋯</span></span></div>
        <div class="body"><p>${w.body}</p>${media}</div>
        <div class="reacts">
          <button data-act="like" aria-label="Like"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/></svg><span>${w.likes}</span></button>
          <button data-act="comment"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v11H5.5L4 18.5V5z"/></svg><span>128</span></button>
          <button data-act="save"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h12v17l-6-4-6 4V4z"/></svg><span>Save</span></button>
          <button data-act="share"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v7h16v-7"/><path d="M12 15V4M8 8l4-4 4 4"/></svg><span></span></button>
        </div>
      </div>
    </article>`;
  }

  /* ---------- shared markup builders ---------- */
  function navHTML(active){
    const items = [
      ['Gallery','screens-gallery.html','grid'],
      ['Following','screen-following.html','list'],
      ['Messages','screen-messages.html','chat', 3],
      ['Notifications','screen-notifications.html','bell', 12],
      ['Comics','screen-comic-series.html','book'],
      ['Profile','screen-profile.html','user']
    ];
    const icons = {
      grid:`<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>`,
      list:`<path d="M4 6h16M4 12h16M4 18h10"/>`,
      chat:`<path d="M4 4h16v12H5.2L4 17.5V4z"/>`,
      bell:`<path d="M12 4a6 6 0 0 0-6 6v3l-1.5 4h15L18 13v-3a6 6 0 0 0-6-6z"/><path d="M9.5 20a2.5 2.5 0 0 0 5 0"/>`,
      book:`<path d="M4 5h10a4 4 0 0 1 0 8H6l-2 3V5z"/>`,
      user:`<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>`
    };
    const linkHTML = ([label, href, icon, badge]) => {
      const on = label === active ? ' active' : '';
      return `<a class="navlink${on}" href="${href}" aria-label="${label}"><span class="ico">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">${icons[icon]}</svg></span>
        <span class="lbl">${label}</span>${badge ? `<span class="badge">${badge}</span>` : ''}</a>`;
    };
    const left = `<aside class="rail">
      <a class="brand" href="screens-gallery.html"><span class="mark">I</span>Inkwell</a>
      ${items.map(linkHTML).join('')}
      <a class="postbtn" href="screen-upload.html" aria-label="Upload work">＋<span class="plbl"> Upload work</span></a>
      <a class="me" href="screen-profile.html"><img src="https://i.pravatar.cc/96?img=47" alt=""><div><div class="n">mira.ink</div><div class="h">@mira_ink</div></div></a>
      <div class="foot">Mockup v1 · gallery-first</div>
    </aside>`;
    const bicons = {grid:icons.grid, list:icons.list, chat:icons.chat, bell:icons.bell, user:icons.user};
    const bnav = `<nav class="bottomnav" aria-label="Main">
      ${['Gallery','Following','Messages','Notifications','Profile'].map((label,idx) => {
        const it = items.find(x=>x[0]===label); const on = label===active ? ' on' : '';
        return `<a href="${it[1]}" class="${on.trim()}" aria-label="${label}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${bicons[it[2]] || icons.user}</svg>
          <span>${label}</span>${it[3] ? `<span class="badge">${it[3]}</span>` : ''}</a>`;
      }).join('')}
    </nav>`;
    return {left, bnav};
  }

  function railHTML(){
    return `<aside class="rightrail">
      <div class="card"><div class="adlabel">Ad — sidebar 300×250</div>
        <div class="adbox" style="height:190px"><div>ExoClick / JuicyAds tag<br><span style="opacity:.65">injected when in view</span></div></div></div>
      <div class="card"><h4>Artists to follow</h4>
        ${[['Rin Senpai','rin_senpai',12],['Val','valdraws',32],['Kuro','kurostudies',5]].map(([n,h,a]) =>
          `<div class="who"><img src="https://i.pravatar.cc/70?img=${a}" alt=""><div><div class="n">${n}</div><div class="h">@${h}</div></div><button class="follow" data-follow>Follow</button></div>`).join('')}
      </div>
      <div class="card"><h4>Trending tags</h4><div class="tags">
        ${['#neon','#sketch','#webtoon','#yuri','#romance','#oc','#comic','#lineart'].map(t=>`<a class="tag" href="#">${t}</a>`).join('')}
      </div></div>
      <div style="font-size:11.5px;color:#6d6275;line-height:1.7;padding:0 4px">No ads inside the comic reader. Promoted tiles every 12 cells.</div>
    </aside>`;
  }

  function topbarHTML(opts){
    opts = opts || {};
    const back = opts.back ? `<a class="back" href="${opts.back}" aria-label="Back">‹</a>` : '';
    return `<div class="topbar">
      <div class="row1">${back}<h1>${opts.title || 'Gallery'}${opts.sub ? `<span>${opts.sub}</span>` : ''}</h1>
        <div class="search" role="search">🔍<input placeholder="Search art, artists, comics…"></div>
        <button class="searchbtn" aria-label="Search">🔍</button>
        <a class="mobilepost" href="screen-upload.html" aria-label="Upload work">＋</a>
      </div>
      ${opts.tabs || ''}
    </div>`;
  }

  /* ---------- renderers used by pages ---------- */
  function mount(opts){
    const n = navHTML(opts.active || 'Gallery');
    const app = document.querySelector('.app');
    if (!app) return;
    app.insertAdjacentHTML('afterbegin', n.left);
    app.insertAdjacentHTML('beforeend', opts.right === false ? '' : railHTML());
    document.body.insertAdjacentHTML('beforeend', n.bnav);
  }

  function renderGallery(mountSel, filterType, sortMode){
    const wrap = document.querySelector(mountSel);
    if (!wrap) return;
    let list = WORKS.slice();
    if (filterType && filterType !== 'all') list = list.filter(w => w.type === filterType);
    if (sortMode === 'newest') list = list.reverse();
    if (sortMode === 'views') list.sort((a,b) => parseFloat(b.views) - parseFloat(a.views));
    if (sortMode === 'top') list.sort((a,b) => parseFloat(b.likes) - parseFloat(a.likes));
    const out = [];
    list.forEach((w, idx) => { if (idx > 0 && idx % 12 === 0) out.push(promoCell()); out.push(cellHTML(w, idx)); });
    if (out.length === 0) out.push('<div class="empty" style="column-span:all">Nothing in this filter yet.</div>');
    wrap.innerHTML = out.join('');
  }

  /* icons not defined in CSS get inline svg swap */
  function swapEmojiIcons(root){
    (root || document).querySelectorAll('[data-icon="heart"]').forEach(s => s.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z"/></svg>' + s.textContent.replace('♥',''));
    (root || document).querySelectorAll('[data-icon="eye"]').forEach(s => s.innerHTML = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg>' + s.textContent.replace('👁',''));
  }

  /* ---------- toast ---------- */
  function toast(msg){
    let t = document.querySelector('.toast');
    if (!t){ t = el('<div class="toast"></div>'); document.body.appendChild(t); }
    t.textContent = msg; requestAnimationFrame(() => t.classList.add('show'));
    clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 2200);
  }

  /* ---------- gallery page: format / sort / toggles ---------- */
  if (document.getElementById('masonry')) {
    const urlp = new URLSearchParams(location.search);
    const state = { format: urlp.get('format') || 'all', sort: urlp.get('sort') || 'top' };
    const FORMATS = [['all','All',''],['image','Images','🖼'],['gif','GIFs','🎞'],['video','Videos','▶'],['album','Albums','🗂'],['comic','Comics','📖']];
    const SORTS = [['top','Top rated'],['views','Most viewed'],['newest','Newest']];
    const WINDOWS = ['Today','This week','This month','All time'];
    let winIdx = 0;

    const tabsHTML = () => `<div class="tabs" role="tablist">` + FORMATS.map(([k,l,i]) =>
      `<button class="tab${state.format===k?' on':''}" data-format="${k}" role="tab" aria-selected="${state.format===k}">${i?`<span aria-hidden="true">${i}</span>`:''}${l}</button>`).join('') + `</div>`;

    const controlsHTML = () => `<div class="controls">
      <div class="sortgroup" role="group" aria-label="Sort">${SORTS.map(([k,l]) =>
        `<button data-sort="${k}" class="${state.sort===k?'on':''}">${l}</button>`).join('')}</div>
      <button class="select" data-window aria-label="Time window"><span data-winlabel>${WINDOWS[winIdx]}</span> <span style="opacity:.6">▾</span></button>
      <div class="toggle"><div class="sw${prefs.showExplicit?' on':''}" data-pref="showExplicit" role="switch" tabindex="0" aria-checked="${prefs.showExplicit}"></div><span class="flake">Show explicit</span></div>
      <div class="toggle"><div class="sw${prefs.maturePreview?' on':''}" data-pref="maturePreview" role="switch" tabindex="0" aria-checked="${prefs.maturePreview}"></div><span class="flake">Mature preview</span></div>
    </div>`;

    function renderGrid(){
      renderGallery('#masonry', state.format, state.sort);
      swapEmojiIcons();
    }
    function renderTop(){
      document.getElementById('topbar').innerHTML = topbarHTML({ title:'Gallery', sub:'18,402 works · 1,284 artists', tabs: tabsHTML() + controlsHTML() });
      renderGrid();
    }

    function handle(e){
      if (!e.target.closest('#topbar')) return false;
      const f = e.target.closest('[data-format]');
      if (f){ state.format = f.dataset.format; renderTop(); return true; }
      const s = e.target.closest('[data-sort]');
      if (s){ state.sort = s.dataset.sort; renderTop(); return true; }
      const w = e.target.closest('[data-window]');
      if (w){ winIdx = (winIdx + 1) % WINDOWS.length; const lab = w.querySelector('[data-winlabel]'); if (lab) lab.textContent = WINDOWS[winIdx]; return true; }
      const p = e.target.closest('[data-pref]');
      if (p){ const k = p.dataset.pref; prefs[k] = !prefs[k]; savePrefs(); p.classList.toggle('on', prefs[k]); p.setAttribute('aria-checked', prefs[k]); if (k === 'showExplicit') renderGrid(); return true; }
      return false;
    }

    mount({active:'Gallery'});
    renderTop();
    document.addEventListener('click', (e) => { if (handle(e)) { e.preventDefault(); e.stopPropagation(); } }, true);
  }

  /* ---------- global interactions ---------- */
  document.addEventListener('click', (e) => {
    const c = e.target.closest('.cell');
    if (c && !c.dataset.promo) {
      if (e.metaKey || e.ctrlKey){ e.preventDefault(); c.classList.toggle('sel'); updateSelBar(); return; }
      const w = byId(c.dataset.id);
      location.href = 'screen-post.html?id=' + c.dataset.id;
      return;
    }
    const f = e.target.closest('[data-follow]');
    if (f){ f.classList.toggle('on'); f.textContent = f.classList.contains('on') ? 'Following' : 'Follow'; toast(f.textContent); return; }
    const reveal = e.target.closest('[data-reveal]');
    if (reveal){ const m = reveal.closest('.media').querySelector('img.blurred'); if (m) m.classList.remove('blurred'); reveal.closest('.blurgate').remove(); return; }
    const act = e.target.closest('[data-act]');
    if (act){
      e.preventDefault();
      if (act.dataset.act === 'like'){ act.classList.toggle('liked'); toast(act.classList.contains('liked') ? 'Liked' : 'Unliked'); }
      if (act.dataset.act === 'save'){ toast('Saved to library'); }
      if (act.dataset.act === 'share'){ toast('Link copied'); }
      if (act.dataset.act === 'comment'){ if (window.__openComments) window.__openComments(); else toast('Comments'); }
      return;
    }
    const sw = e.target.closest('.sw');
    if (sw){ sw.classList.toggle('on'); }
  });

  function updateSelBar(){
    const bar = document.getElementById('floatingbar');
    const n = document.querySelectorAll('.cell.sel').length;
    if (!bar) return;
    bar.classList.toggle('show', n > 0);
    const c = document.getElementById('selcount'); if (c) c.textContent = `${n} work${n===1?'':'s'} selected`;
  }

  /* public api */
  window.Ink = { USERS, WORKS, prefs, savePrefs, KIND, KIND_ICON, RATE_CLASS, RATE_LABEL, byId, userOf,
    cellHTML, postHTML, mount, renderGallery, topbarHTML, promoCell, swapEmojiIcons, toast, statsHTML, updateSelBar };
})();
