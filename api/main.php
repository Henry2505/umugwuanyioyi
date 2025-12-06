;(function(){
  'use strict';
  const id = v => document.getElementById(v),
        q = s => document.querySelector(s),
        qAll = s => Array.from(document.querySelectorAll(s || ""));

  if (id("year")) id("year").textContent = (new Date()).getFullYear();

  const toast = (t, ms = 3e3) => {
    try {
      let c = id("toastContainer");
      if (!c) return;
      const n = document.createElement("div");
      n.className = "toast";
      n.textContent = t;
      c.appendChild(n);
      setTimeout(() => { try { n.remove(); } catch (e) {} }, ms);
    } catch (e) {}
  };

  const getToken = () => {
    try {
      const keys = ['sb-jwt-token','sb-access-token','access_token','token','umu_token','umuy_token'];
      for (const key of keys) {
        try { const v = localStorage.getItem(key); if (v) return v; } catch (e) {}
      }
      const m = document.cookie.match(/(?:^|; )(?:sb-jwt-token|sb-access-token|access_token|token|umu_token|umuy_token)=([^;]+)/);
      return m ? decodeURIComponent(m[1]) : "";
    } catch (e) { return ""; }
  };

  const authHeader = () => {
    const t = getToken();
    return t ? { Authorization: "Bearer " + t } : {};
  };

  const parseJwt = t => {
    try {
      if (!t) return null;
      const p = t.split(".");
      if (p.length < 2) return null;
      const pl = p[1].replace(/-/g,"+").replace(/_/g,"/");
      const json = decodeURIComponent(atob(pl).split("").map(c => "%" + ("00" + c.charCodeAt(0).toString(16)).slice(-2)).join(""));
      return JSON.parse(json);
    } catch (e) { return null; }
  };

  const getCurrentUser = async (force = false) => {
    try {
      if (!force) {
        const raw = localStorage.getItem("umuy_user");
        if (raw) try { const o = JSON.parse(raw); if (o && o.id) return o; } catch (e) {}
      }
      const t = getToken();
      if (t) {
        const p = parseJwt(t);
        if (p && (p.sub || p.user_id || p.user || p.id)) {
          const idVal = p.sub || p.user_id || p.user || p.id;
          const out = { id: String(idVal) };
          localStorage.setItem("umuy_user", JSON.stringify(out));
          return out;
        }
      }
    } catch (e) {}
    return null;
  };

  const getSyncUserId = () => {
    try {
      const raw = localStorage.getItem("umuy_user");
      if (raw) {
        const o = JSON.parse(raw);
        if (o && o.id) return String(o.id);
      }
      const t = getToken();
      if (t) {
        const p = parseJwt(t);
        if (p && (p.sub || p.user_id || p.user || p.id)) return String(p.sub || p.user_id || p.user || p.id);
      }
    } catch (e) {}
    return "";
  };

  // ---- DOM refs ----
  const membersList = id("membersList"),
        membersSearch = id("membersSearch"),
        membersSearchBtn = id("membersSearchBtn"),
        mobileSearchToggle = id("mobileSearchToggle"),
        menuToggle = id("menuToggle"),
        chatViewport = id("chatViewport"),
        profilePanel = id("profilePanel"),
        profileToggle = id("profileToggle"),
        hideProfile = id("hideProfile"),
        profileToggleAvatar = id("profileToggleAvatar"),
        peerAvatar = id("peerAvatar"),
        peerName = id("peerName"),
        peerPresence = id("peerPresence"),
        peerMeta = id("peerMeta"),
        sendBtn = id("sendBtn"),
        messageInput = id("messageInput"),
        attachBtn = id("attachBtn");

  let attachInput = id("attachInput");
  const attachmentsList = id("attachmentsList"),
        emojiBtn = id("emojiBtn"),
        recordBtn = id("voiceNoteBtn"),
        composerMeta = id("composerMeta"),
        typingIndicator = id("typingIndicator"),
        langToggle = id("langToggle"),
        themeToggle = id("themeToggle"),
        notificationMount = id("notification-widget"),
        callBtn = id("voiceCallBtn"),
        videoBtn = id("videoCallBtn"),
        hideConvBtn = id("hideConvBtn"),
        membersCloseBtn = id("membersCloseBtn"),
        membersPanel = id("membersPanel"),
        chatViewportWrap = id("chatViewportWrap"),
        siteFooter = q(".site-footer"),
        landingScreen = id("landingScreen"),
        landingNewChat = id("landingNewChat"),
        chatTop = q(".chat-top"),
        chatInput = q(".chat-input"),
        backToLanding = id("backToLanding"),
        cameraBtn = id("cameraBtn"),
        voiceCallIcon = id("voiceCallIcon"),
        videoCallIcon = id("videoCallIcon"),
        newChatBtn = id("newChatBtn"),
        umiEmojiPanel = id("umiEmojiPanel"),
        emojiGrid = id("emojiGrid"),
        emojiSearch = id("emojiSearch"),
        emojiCats = id("emojiCats");

  // ---- state ----
  let selectedMember = null,
      mediaRecorder = null,
      recordedChunks = [],
      recordStart = 0,
      recordInterval = null,
      typingPoll = null,
      pollingTyping = null,
      typingSentTimer = null,
      typingIdleTimer = null,
      typingPollFailures = 0,
      slideshowInterval = null,
      currentSlide = 0,
      activeStream = null,
      mediaRecorderFinalized = false,
      watchId = null,
      locationShareId = null,
      peerLocationPoll = null,
      elapsedBeforePause = 0;

  // ---- helpers ----
  function escapeHtml(s){
    return String(s||"").replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch] || ch));
  }

  function normalize(r){
    if (!r) return { id: null, full_name: "", avatar_url: "default-avatar.png", email: null, phone: null, online: false, last_seen: null, bio: null, last_message: "", username: "" };
    return {
      id: r.id || r.uuid || r.user_id || r.username || null,
      full_name: r.full_name || r.fullName || r.name || r.username || "",
      avatar_url: r.avatar_url || r.photo || r.photo_url || r.avatar || "default-avatar.png",
      email: r.email || null,
      phone: r.phone || null,
      online: !!r.online,
      last_seen: r.last_seen || r.lastSeen || r.last_active || null,
      bio: r.bio || r.description || r.short_bio || null,
      last_message: r.last_message || r.lastMessage || "",
      username: r.username || ""
    };
  }

  // ensure attach input exists
  if (!attachInput) {
    attachInput = document.createElement("input");
    attachInput.type = "file";
    attachInput.multiple = true;
    attachInput.id = "attachInput";
    attachInput.style.display = "none";
    document.body.appendChild(attachInput);
  }
  if (attachBtn) attachBtn.addEventListener("click", ()=> { try { attachInput && attachInput.click(); } catch (e) {} });

  function blurIfInside(el){
    try {
      if (!el) return;
      if (el.contains(document.activeElement)) document.activeElement.blur();
    } catch (e) {}
  }

  function ensureProfileScrollable(){
    try {
      const content = profilePanel ? profilePanel.querySelector(".profile-content") : null;
      const header = document.querySelector(".site-header");
      const headerH = header ? header.getBoundingClientRect().height : 72;
      if (!content) return;
      content.style.maxHeight = (window.innerHeight - headerH - 80) + "px";
      content.style.overflow = "auto";
      content.style.webkitOverflowScrolling = "touch";
    } catch (e) {}
  }

  // ---------- improved message cache ----------
  // caches per-conversation in localStorage with dedupe, ordering and easy sync
  const messageCache = (function(){
    const mem = {};
    const LS_PREFIX = "umi:chatcache:";

    function toSafeId(v){
      try { return v === null || typeof v === "undefined" ? "" : String(v); } catch (e) { return ""; }
    }

    function toMs(ts){
      // normalize timestamp to milliseconds number
      try {
        if (!ts) return Date.now();
        if (typeof ts === "number") {
          // if looks like seconds (10 digits) convert to ms
          if (String(ts).length <= 10) return Number(ts) * 1000;
          return Number(ts);
        }
        // try parse ISO string
        const n = Date.parse(ts);
        if (!isNaN(n)) return n;
        // fallback
        const n2 = parseInt(ts, 10);
        if (!isNaN(n2)) {
          if (String(n2).length <= 10) return n2 * 1000;
          return n2;
        }
      } catch (e) {}
      return Date.now();
    }

    function sortAndUniq(arr){
      try {
        const seen = new Set();
        const out = arr.filter(Boolean).map(m => {
          const id = m.id || m.temp_id || ('tmp_' + Math.random().toString(36).slice(2,8) + Date.now());
          const ts = toMs(m.timestamp || m.created_at || m.time || Date.now());
          return Object.assign({}, m, { id: id, timestamp: ts });
        }).sort((a,b) => (a.timestamp || 0) - (b.timestamp || 0)).reduce((acc, cur) => {
          if (seen.has(cur.id)) return acc;
          seen.add(cur.id);
          acc.push(cur);
          return acc;
        }, []);
        return out;
      } catch (e) { return arr || []; }
    }

    const load = (convId) => {
      try {
        if (!convId) return { msgs: [], ids: new Set() };
        convId = toSafeId(convId);
        if (mem[convId]) return mem[convId];
        const raw = localStorage.getItem(LS_PREFIX + convId);
        if (raw) {
          const data = JSON.parse(raw);
          const arr = Array.isArray(data.msgs) ? sortAndUniq(data.msgs) : [];
          const s = new Set((data.ids||[]));
          mem[convId] = { msgs: arr, ids: s };
          return mem[convId];
        }
        mem[convId] = { msgs: [], ids: new Set() };
        return mem[convId];
      } catch (e) {
        return { msgs: [], ids: new Set() };
      }
    };
    const save = (convId) => {
      try {
        if (!convId || !mem[convId]) return;
        const msgs = sortAndUniq(mem[convId].msgs).slice(-1000);
        const d = { msgs: msgs, ids: Array.from(new Set(msgs.filter(Boolean).map(m => m.id).filter(Boolean))) };
        localStorage.setItem(LS_PREFIX + convId, JSON.stringify(d));
        // notify other tabs that cache was updated: small custom event via localStorage
        try { localStorage.setItem(LS_PREFIX + convId + ":updated", String(Date.now())); } catch (e) {}
      } catch (e) {}
    };

    return {
      get(convId){ return load(convId); },
      set(convId, msgs){ try { const c = load(convId); c.msgs = Array.isArray(msgs) ? sortAndUniq(msgs) : []; c.ids = new Set((c.msgs||[]).filter(Boolean).map(m => m.id).filter(Boolean)); mem[convId] = c; save(convId); } catch (e) {} },
      add(convId, msg){ try { const c = load(convId); if (!msg) return; // normalize msg shape
          const id = msg.id || msg.temp_id || ('tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2,7));
          const timestamp = msg.timestamp || msg.created_at || Date.now();
          const normalized = Object.assign({}, msg, { id: id, timestamp: timestamp });
          if (c.ids.has(normalized.id)) return;
          c.msgs.push(normalized);
          if (normalized.id) c.ids.add(normalized.id);
          if (c.msgs.length > 1000) c.msgs = c.msgs.slice(-1000);
          c.msgs = sortAndUniq(c.msgs);
          mem[convId] = c; save(convId); } catch (e) {} },
      replaceTemp(convId, tempId, serverMsg){ try { if (!convId || !tempId || !serverMsg) return; const c = load(convId); let replaced = false; for (let i = 0; i < c.msgs.length; i++) { const m = c.msgs[i]; if ((m.temp_id && m.temp_id === tempId) || (m.id && m.id === tempId)) { // replace
                const nm = Object.assign({}, serverMsg);
                if (!nm.timestamp) nm.timestamp = m.timestamp || Date.now();
                c.msgs[i] = nm; replaced = true; break; } } if (!replaced) { const nm = Object.assign({}, serverMsg); if (!nm.timestamp) nm.timestamp = Date.now(); c.msgs.push(nm); } // rebuild ids & sort
            c.msgs = sortAndUniq(c.msgs);
            c.ids = new Set(c.msgs.filter(Boolean).map(m => m.id).filter(Boolean));
            mem[convId] = c; save(convId); } catch (e) {} },
      exists(convId, id){ try { const c = load(convId); return c.ids.has(id); } catch (e) { return false; } }
    };
  })();

  // ---- rendering messages ----
  function renderMessages(msgs, ctx){
    try {
      if (!chatViewport) return;
      chatViewport.innerHTML = "";
      const myId = getSyncUserId();
      (msgs || []).forEach(m => {
        try {
          const tpl = id("messageTpl");
          if (!tpl) return;
          const node = tpl.content.firstElementChild.cloneNode(true);
          const senderId = m.sender_id || m.sender || m.from || m.user_id || m.user;
          const isSent = senderId && String(senderId) === String(myId);
          if (isSent) node.classList.add("sent");
          const body = node.querySelector(".msg-body"),
                time = node.querySelector(".msg-time"),
                status = node.querySelector(".msg-status");
          const mediaUrl = m.media || m.media_url || m.mediaUrl || m.file_url || m.file;
          if (mediaUrl) {
            if (/\.(mp4|webm|mov)(\?.*)?$/i.test(mediaUrl)) body.innerHTML = '<video controls playsinline src="'+escapeHtml(mediaUrl)+'"></video>';
            else if (/\.(mp3|wav|m4a|ogg|webm)(\?.*)?$/i.test(mediaUrl)) body.innerHTML = '<audio controls src="'+escapeHtml(mediaUrl)+'"></audio>';
            else body.innerHTML = '<a href="'+escapeHtml(mediaUrl)+'" target="_blank" rel="noopener noreferrer">Download attachment</a>';
          } else {
            const text = m.content || m.message || m.body || "";
            if (body) body.textContent = text;
          }
          if (time) time.textContent = new Date(m.timestamp || m.created_at || m.createdAt || Date.now()).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
          if (status && m.status) status.textContent = m.status;
          chatViewport.appendChild(node);
          node.classList.add("enter");
          setTimeout(()=>node.classList.remove("enter"), 400);
        } catch (err) { console.error('renderMessages inner', err); }
      });
      requestAnimationFrame(()=>chatViewport.scrollTo({ top: chatViewport.scrollHeight, behavior: "smooth" }));
    } catch (e) { console.error('renderMessages', e); }
  }

  // ---- open / close chat ----
  async function openChat(member){
    try {
      stopAllBackgroundTasks();
      selectedMember = member;
      if (landingScreen) landingScreen.style.display = "none";
      if (chatTop) chatTop.style.display = "flex";
      if (chatInput) chatInput.style.display = "flex";
      if (chatViewportWrap) chatViewportWrap.style.display = "block";
      if (peerName) peerName.textContent = (member.full_name || "Conversation");
      if (peerAvatar) peerAvatar.src = member.avatar_url || "default-avatar.png";
      if (profileToggleAvatar) profileToggleAvatar.src = member.avatar_url || "default-avatar.png";
      if (peerPresence) peerPresence.className = "presence-dot " + (member.online ? "online" : "offline");
      if (peerMeta) peerMeta.textContent = member.online ? "Online" : (member.last_seen ? ("Last seen " + new Date(member.last_seen).toLocaleString()) : "Last seen —");
      const profileNameEl = id("profileName");
      if (profileNameEl) profileNameEl.textContent = member.full_name || "Profile";
      if (chatViewport) chatViewport.innerHTML = '<div class="loading">Loading…</div>';
      populateProfile(member);
      await fetchChatMessages(member.id);
      startTypingPoll();
      ensureProfileScrollable();
      startPeerLocationPoll();
      // ensure realtime polling is active for this user
      try { startRealtimePolling(); } catch (e) {}
      setTimeout(()=>updateChatHeights(),60);
      const evt = new CustomEvent('umi:conversation-opened', { detail: normalize(member) });
      window.dispatchEvent(evt);
    } catch (e) { console.error('openChat', e); }
  }

  function closeChat(){
    try {
      selectedMember = null;
      if (landingScreen) landingScreen.style.display = "block";
      if (chatTop) chatTop.style.display = "none";
      if (chatInput) chatInput.style.display = "none";
      if (chatViewportWrap) chatViewportWrap.style.display = "none";
      if (chatViewport) chatViewport.innerHTML = "";
      stopTypingPoll();
      if (profilePanel) profilePanel.classList.remove("open");
      qAll(".member-item.selected").forEach(x => x.classList.remove("selected"));
      stopPeerLocationPoll();
      // do not stop realtime polling globally; it's tied to user session
      setTimeout(()=>updateChatHeights(),60);
    } catch (e) { console.error('closeChat', e); }
  }

  // ---- fetch chat messages from server and set cache ----
  async function fetchChatMessages(peerId){
    try {
      const currentUser = await getCurrentUser();
      const userId = currentUser && currentUser.id ? currentUser.id : "";
      if (!peerId){
        if (chatViewport) chatViewport.innerHTML = "";
        return;
      }
      const cache = messageCache.get(peerId);
      const url = "/.netlify/functions/all-chatapi/get-chat?id=" + encodeURIComponent(peerId);
      try {
        const res = await fetch(url, { headers: Object.assign({}, authHeader()), credentials: 'same-origin' });
        if (!res || !res.ok) {
          if (cache && cache.msgs && cache.msgs.length) renderMessages(cache.msgs);
          else if (chatViewport) chatViewport.innerHTML = "";
          return;
        }
        const data = await res.json().catch(()=>null);
        let msgs = [];
        let participant = null;
        if (Array.isArray(data)) {
          msgs = data;
        } else if (data && data.messages) {
          msgs = data.messages;
          participant = data.user || data.participant || data.with || data.profile || data.user_info;
        } else if (data && Array.isArray(data.rows)) {
          msgs = data.rows;
          participant = data.user || data.profile || null;
        } else {
          msgs = data || [];
        }
        const normalizedMsgs = (msgs || []).map(m => {
          return {
            id: m.id || m.msg_id || m.message_id || m._id || null,
            sender_id: m.sender_id || m.sender || m.from || m.user_id || null,
            content: m.content || m.message || m.body || "",
            media: m.media || m.media_url || m.mediaUrl || m.file_url || null,
            timestamp: m.timestamp || m.created_at || m.createdAt || Date.now(),
            status: m.status || null
          };
        });
        // merge with cache deduped
        messageCache.set(peerId, normalizedMsgs);
        renderMessages(messageCache.get(peerId).msgs, { avatar_url: selectedMember && selectedMember.avatar_url });
        if (!participant && data && data.user) participant = data.user;
        if (!participant && data && data.participant) participant = data.participant;
        if (participant) {
          const enriched = normalize(participant);
          selectedMember = Object.assign({}, selectedMember, enriched);
          populateProfile(selectedMember);
          const evt = new CustomEvent('umi:profile-loaded', { detail: enriched });
          window.dispatchEvent(evt);
        }
      } catch (e) {
        console.error('fetchChatMessages fetch', e);
        const cache = messageCache.get(peerId);
        if (cache && cache.msgs && cache.msgs.length) renderMessages(cache.msgs);
        else if (chatViewport) chatViewport.innerHTML = "";
      }
    } catch (e) {
      console.error('fetchChatMessages top', e);
      if (chatViewport) chatViewport.innerHTML = "";
    }
  }

  // ---- local UI append for optimistic send ----
  function appendLocalMessage(text, files = [], tempId = null){
    try {
      const tpl = id("messageTpl");
      if (!tpl || !chatViewport) return null;
      const node = tpl.content.firstElementChild.cloneNode(true);
      node.classList.add("sent");
      const body = node.querySelector(".msg-body"), time = node.querySelector(".msg-time"), status = node.querySelector(".msg-status");
      if (body) body.textContent = text || "";
      if (time) time.textContent = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
      if (status) status.textContent = "Sending…";
      chatViewport.appendChild(node);
      node.classList.add("enter");
      setTimeout(()=>node.classList.remove("enter"), 400);
      requestAnimationFrame(()=>chatViewport.scrollTo({ top: chatViewport.scrollHeight, behavior: "smooth" }));
      const temp_msg = { temp_id: tempId || ('tmp_' + Date.now() + '_' + Math.random().toString(36).slice(2,7)), sender_id: getSyncUserId(), content: text || "", timestamp: Date.now(), status: "sending", attachments: files || [] };
      if (selectedMember && selectedMember.id) messageCache.add(String(selectedMember.id), temp_msg);
      return temp_msg.temp_id;
    } catch (e) { console.error('appendLocalMessage', e); return null; }
  }

  function replaceLastLocalStatus(txt){
    try {
      if (!chatViewport) return;
      const els = chatViewport.querySelectorAll(".message.sent .msg-status");
      if (els.length) els[els.length - 1].textContent = txt;
    } catch (e) {}
  }

  // ---- send message with optimistic UI ----
  async function sendMessage(){
    try {
      const text = (messageInput && messageInput.value || "").trim();
      const currentUser = await getCurrentUser();
      const senderId = currentUser && currentUser.id ? currentUser.id : "";
      const peerId = selectedMember && selectedMember.id ? selectedMember.id : "";

      if (!peerId && !text && !(attachmentsList && attachmentsList.children.length)) { toast("Nothing to send"); return; }

      if (!senderId && String(peerId).toLowerCase() !== 'my-ai') { toast("You are not signed in"); return; }

      if (peerId === 'my-ai') {
        if (!text && !(attachmentsList && attachmentsList.children.length)) { toast("Nothing to send to AI"); return; }
        const tempId = appendLocalMessage(text);
        try {
          if (window.UmuMyAI && typeof window.UmuMyAI.processToAI === 'function') {
            window.UmuMyAI.processToAI(text);
          } else toast("AI engine unavailable");
        } catch (e) {
          console.error('local AI send', e); toast("AI failed");
        }
        if (messageInput) messageInput.value = "";
        if (attachInput) attachInput.value = "";
        if (attachmentsList) attachmentsList.innerHTML = "";
        if (composerMeta) composerMeta.style.display = "none";
        if (profilePanel && profilePanel.classList.contains("open")) profilePanel.classList.remove("open");
        return;
      }

      const form = new FormData();
      if (senderId) form.append("sender", senderId);
      form.append("recipient", peerId);
      form.append("content", text);
      try { const files = attachInput ? Array.from(attachInput.files || []) : []; files.forEach((f,i) => form.append("file"+i, f, f.name)); } catch (e) {}

      Array.from(attachmentsList ? attachmentsList.children : []).forEach((pill, idx) => {
        if (pill._fileBlob) form.append("file_rec_" + idx, pill._fileBlob, "recording-" + Date.now() + ".webm");
        else if (pill._file) form.append("file_att_" + idx, pill._file, pill._file.name || ("file-" + idx));
      });

      const tempId = appendLocalMessage(text);
      if (messageInput) messageInput.value = "";
      if (attachInput) attachInput.value = "";
      if (attachmentsList) attachmentsList.innerHTML = "";
      if (composerMeta) composerMeta.style.display = "none";
      if (profilePanel && profilePanel.classList.contains("open")) profilePanel.classList.remove("open");

      const spinner = document.createElement("span");
      spinner.className = "umi-send-spinner";
      if (sendBtn) sendBtn.appendChild(spinner);
      if (sendBtn) sendBtn.disabled = true;

      let res = null;
      try {
        if (peerId === "team-snap") { form.append("team", "Team Umugwuanyioyi"); res = await fetch("/.netlify/functions/all-chatapi/send-message", { method: "POST", body: form, credentials: "same-origin", headers: authHeader() }); }
        else res = await fetch("/.netlify/functions/all-chatapi/send-message", { method: "POST", body: form, credentials: "same-origin", headers: authHeader() });
        if (sendBtn) { sendBtn.disabled = false; try { spinner.remove(); } catch (e) {} }
        if (!res || !res.ok) { toast("Send failed"); messageCache.replaceTemp(String(peerId), tempId, { id: null, sender_id: senderId, content: text, timestamp: Date.now(), status: "failed" }); replaceLastLocalStatus("Failed"); return; }
        const json = await res.json().catch(()=>null);
        if (json && json.id) {
          const serverMsg = { id: json.id, sender_id: json.sender || senderId, content: json.content || text, media: json.media || json.media_url || null, timestamp: json.timestamp || Date.now(), status: "sent" };
          messageCache.replaceTemp(String(peerId), tempId, serverMsg);
          if (selectedMember && selectedMember.id && String(selectedMember.id) === String(peerId)) {
            const cache = messageCache.get(String(peerId));
            if (cache && cache.msgs) renderMessages(cache.msgs);
          }
        } else {
          // server returned no id — treat as optimistic sent
          messageCache.replaceTemp(String(peerId), tempId, { id: null, sender_id: senderId, content: text, timestamp: Date.now(), status: "sent" });
          replaceLastLocalStatus("Sent");
        }

        // immediate poll after send to pick up server-side state across devices
        try { pollNow(); } catch (e) {}
      } catch (e) {
        if (sendBtn) { sendBtn.disabled = false; try { spinner.remove(); } catch(err){} }
        messageCache.replaceTemp(String(peerId), tempId, { id: null, sender_id: senderId, content: text, timestamp: Date.now(), status: "failed" });
        replaceLastLocalStatus("Failed");
        toast("Send error");
        console.error('sendMessage', e);
      }
    } catch (e) { console.error('sendMessage top', e); }
  }

  if (sendBtn) sendBtn.addEventListener("click", sendMessage);
  if (messageInput) messageInput.addEventListener("keydown", function(e){ if (e.key === "Enter" && !e.shiftKey){ e.preventDefault(); sendMessage(); } });

  // -------- file attach UI (unchanged) --------
  if (attachInput) {
    attachInput.addEventListener("change", (e) => {
      try {
        const files = Array.from(e.target.files || []);
        files.forEach(f => {
          const pill = document.createElement("div");
          pill.className = "attachment-pill";
          const name = document.createElement("span");
          name.textContent = f.name;
          const play = document.createElement("button");
          play.type = "button";
          play.className = "ghost preview";
          play.textContent = "Preview";
          play.addEventListener("click", ()=> {
            try {
              if (f.type.startsWith("image/")) {
                const img = new Image();
                img.src = URL.createObjectURL(f);
                const w = window.open("");
                if (w) { w.document.body.style.background = "#111"; w.document.body.style.margin = "0"; w.document.body.appendChild(img); }
                else toast("Popup blocked, download to preview");
              } else if (f.type.startsWith("audio/") || f.type.startsWith("video/")) {
                const url = URL.createObjectURL(f);
                const w = window.open("");
                if (w) {
                  const tag = f.type.startsWith("video/") ? "video" : "audio";
                  const media = w.document.createElement(tag);
                  media.controls = true; media.src = url; w.document.body.style.background = "#111"; w.document.body.style.margin = "0"; w.document.body.appendChild(media);
                } else toast("Popup blocked, download to preview");
              } else alert("Preview not available for this file type.");
            } catch (e) { toast("Preview failed"); }
          });
          const del = document.createElement("button");
          del.type = "button"; del.className = "ghost remove"; del.textContent = "Remove";
          del.addEventListener("click", ()=> { pill.remove(); if (attachmentsList && !attachmentsList.children.length) composerMeta.style.display = "none"; });
          pill.appendChild(name); pill.appendChild(play); pill.appendChild(del);
          pill._file = f;
          attachmentsList && attachmentsList.appendChild(pill);
        });
        if (attachmentsList) composerMeta.style.display = attachmentsList.children.length ? "flex" : "none";
        setTimeout(()=>updateChatHeights(), 60);
      } catch (e) { console.error('attach change', e); }
    });
  }

  // ---- recording utilities (unchanged) ----
  function supportedMediaRecorder(){ return typeof MediaRecorder !== "undefined"; }
  function chooseMime(){
    if (typeof MediaRecorder === "undefined") return "";
    if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported("audio/webm;codecs=opus")) return "audio/webm;codecs=opus";
    if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported("audio/webm")) return "audio/webm";
    if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported("audio/mp4")) return "audio/mp4";
    return "";
  }

  function startTimer(elTimer, elBar){
    try {
      recordStart = Date.now() - (elapsedBeforePause || 0);
      if (recordInterval) clearInterval(recordInterval);
      recordInterval = setInterval(()=>{
        const s = Math.floor((Date.now() - recordStart) / 1000);
        const mm = String(Math.floor(s/60)).padStart(2, "0");
        const ss = String(s % 60).padStart(2, "0");
        if (elTimer) elTimer.textContent = mm + ":" + ss;
        const p = Math.min(100, (s / 180) * 100);
        if (elBar) elBar.style.width = p + "%";
      }, 200);
    } catch (e) {}
  }
  function pauseTimer(){ if (recordInterval) { elapsedBeforePause = Date.now() - recordStart; clearInterval(recordInterval); recordInterval = null; } }
  function resumeTimer(elTimer, elBar){ startTimer(elTimer, elBar); }
  function stopTimer(){ if (recordInterval) { clearInterval(recordInterval); recordInterval = null; } elapsedBeforePause = 0; }

  async function startRecording(){
    try {
      if (!supportedMediaRecorder()) { alert("Recording not supported"); return; }
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      activeStream = stream;
      const mime = chooseMime();
      mediaRecorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
      recordedChunks = [];
      mediaRecorderFinalized = false;
      mediaRecorder.ondataavailable = e => { if (e.data && e.data.size) recordedChunks.push(e.data); };
      mediaRecorder.onstop = () => { if (mediaRecorderFinalized) return; mediaRecorderFinalized = true; finalizeRecording(); };
      const recorder = id("voiceNoteRecorder");
      const timer = recorder ? recorder.querySelector(".recording-timer") : null;
      const progress = recorder ? recorder.querySelector(".recording-progress i") : null;
      mediaRecorder.onpause = () => { const rp = id("recPause"), rr = id("recResume"); if (rp) rp.style.display = "none"; if (rr) rr.style.display = "inline-flex"; pauseTimer(); };
      mediaRecorder.onresume = () => { const rp = id("recPause"), rr = id("recResume"); if (rp) rp.style.display = "inline-flex"; if (rr) rr.style.display = "none"; resumeTimer(timer, progress); };
      mediaRecorder.start(250);
      if (recorder) recorder.style.display = "flex";
      const cancel = recorder ? recorder.querySelector(".recording-cancel") : null;
      if (cancel) { cancel.removeEventListener("click", recorderCancelHandler); cancel.addEventListener("click", recorderCancelHandler); }
      startTimer(timer, progress);
      if (recordBtn) recordBtn.classList.add("recording");
      setTimeout(()=>updateChatHeights(), 60);
    } catch (e) { toast("Unable to start recording"); console.error('startRecording', e); }
  }
  function recorderCancelHandler(){ stopRecording(true); const recorder = id("voiceNoteRecorder"); if (recorder) recorder.style.display = "none"; }
  function stopRecording(cancel){
    try {
      if (!mediaRecorder && recordedChunks.length === 0) return;
      if (mediaRecorder && (mediaRecorder.state === "recording" || mediaRecorder.state === "paused")) mediaRecorder.stop();
      if (activeStream) { activeStream.getTracks().forEach(t => t.stop()); activeStream = null; }
      stopTimer();
      const recorder = id("voiceNoteRecorder");
      if (recorder) recorder.style.display = "none";
      if (recorder){
        const timerEl = recorder.querySelector(".recording-timer"), p = recorder.querySelector(".recording-progress i");
        if (timerEl) timerEl.textContent = "00:00";
        if (p) p.style.width = "0%";
      }
      if (recordBtn) recordBtn.classList.remove("recording");
      if (cancel) recordedChunks = [];
      setTimeout(()=>updateChatHeights(), 60);
    } catch (e) { console.error('stopRecording', e); }
  }
  function finalizeRecording(){
    try {
      if (!recordedChunks || !recordedChunks.length) return;
      const mime = chooseMime() || "audio/webm";
      const blob = new Blob(recordedChunks, { type: mime });
      const pill = document.createElement("div");
      pill.className = "attachment-pill";
      const audio = document.createElement("audio");
      audio.controls = true; audio.preload = "metadata"; audio.src = URL.createObjectURL(blob);
      audio.addEventListener("error", () => { toast("Playback error"); });
      pill.appendChild(audio);
      const del = document.createElement("button");
      del.type = "button";
      del.className = "ghost remove";
      del.textContent = "Remove";
      del.addEventListener("click", ()=> { pill.remove(); if (attachmentsList && !attachmentsList.children.length) composerMeta.style.display = "none"; });
      pill.appendChild(del);
      pill._fileBlob = blob;
      attachmentsList && attachmentsList.appendChild(pill);
      if (composerMeta) composerMeta.style.display = "flex";
      recordedChunks = [];
      setTimeout(()=>updateChatHeights(), 60);
    } catch (e) { toast("Recording error"); console.error('finalizeRecording', e); }
  }

  const recPause = id("recPause"), recResume = id("recResume"), recStop = id("recStop"), recSend = id("recSend");
  if (recPause) recPause.addEventListener("click", ()=> { try{ if (mediaRecorder && mediaRecorder.state === "recording") { mediaRecorder.pause(); pauseTimer(); } else if (mediaRecorder && mediaRecorder.state === "paused") { mediaRecorder.resume(); const rec = id("voiceNoteRecorder"); const timer = rec ? rec.querySelector(".recording-timer") : null; const prog = rec ? rec.querySelector(".recording-progress i") : null; resumeTimer(timer, prog); } }catch(e){} });
  if (recResume) recResume.addEventListener("click", ()=> { try{ if (mediaRecorder && mediaRecorder.state === "paused") { mediaRecorder.resume(); const rec = id("voiceNoteRecorder"); const timer = rec ? rec.querySelector(".recording-timer") : null; const prog = rec ? rec.querySelector(".recording-progress i") : null; resumeTimer(timer, prog); } } catch (e) {} });
  if (recStop) recStop.addEventListener("click", ()=> { stopRecording(false); });
  if (recSend) recSend.addEventListener("click", async ()=> {
    try {
      if (mediaRecorder && mediaRecorder.state === "recording") stopRecording(false);
      await new Promise(r => setTimeout(r, 120));
      if (attachmentsList && attachmentsList.children.length) { sendMessage(); } else toast("No recording to send");
    } catch (e) { console.error('recSend', e); }
  });
  if (recordBtn) recordBtn.addEventListener("click", ()=> { try { if (recordBtn.classList.contains("recording")) stopRecording(false); else startRecording(); } catch (e) { console.error('recordBtn click', e); } });

  // ---- typing indicator send ----
  function startTyping(){
    try {
      if (!selectedMember || !selectedMember.id) return;
      if (typingSentTimer) clearTimeout(typingSentTimer);
      if (typingIdleTimer) clearTimeout(typingIdleTimer);
      typingSentTimer = setTimeout(async ()=> { try { if (selectedMember && selectedMember.id && String(selectedMember.id).toLowerCase() !== 'my-ai') { await fetch("/.netlify/functions/all-chatapi/send-typing", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ recipientId: selectedMember.id, typing: true }) }); } } catch (e) {} }, 180);
      typingIdleTimer = setTimeout(async ()=> { try { if (selectedMember && selectedMember.id && String(selectedMember.id).toLowerCase() !== 'my-ai') { await fetch("/.netlify/functions/all-chatapi/send-typing", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ recipientId: selectedMember.id, typing: false }) }); } } catch (e) {} }, 2500);
    } catch (e) {}
  }

  if (messageInput) messageInput.addEventListener("input", debounce(()=> { startTyping(); updateChatHeights(); }, 120));

  // ---- typing polling (existing) ----
  function startTypingPoll(){
    try {
      if (pollingTyping) clearInterval(pollingTyping);
      typingPollFailures = 0;
      pollingTyping = setInterval(async ()=> {
        try {
          if (!selectedMember || !selectedMember.id) { if (typingIndicator) typingIndicator.style.display = "none"; return; }
          if (String(selectedMember.id).toLowerCase() === 'my-ai') { if (typingIndicator) typingIndicator.style.display = "none"; return; }
          const currentUser = await getCurrentUser();
          const myId = currentUser && currentUser.id ? currentUser.id : "";
          const userQuery = encodeURIComponent(myId || "");
          const peerQuery = encodeURIComponent(selectedMember.id);
          const res = await fetch("/.netlify/functions/all-chatapi/get-typing?userId="+userQuery+"&peerId="+peerQuery, { headers: Object.assign({}, authHeader()) }).catch(()=>null);
          if (res && res.ok) {
            const j = await res.json().catch(()=>null);
            if (j && (j.typing === true || (Array.isArray(j) && j.length && j.some(x => x.typing)))) { if (typingIndicator) typingIndicator.style.display = "flex"; }
            else { if (typingIndicator) typingIndicator.style.display = "none"; }
            typingPollFailures = 0;
          } else {
            typingPollFailures++;
            if (typingPollFailures > 8) { clearInterval(pollingTyping); pollingTyping = null; if (typingIndicator) typingIndicator.style.display = "none"; }
          }
        } catch (e) {
          typingPollFailures++;
          if (typingPollFailures > 8) { clearInterval(pollingTyping); pollingTyping = null; if (typingIndicator) typingIndicator.style.display = "none"; }
        }
      }, 2500);
    } catch (e) { console.error('startTypingPoll', e); }
  }

  function stopTypingPoll(){ try { if (pollingTyping) clearInterval(pollingTyping); pollingTyping = null; if (typingIndicator) typingIndicator.style.display = "none"; } catch (e) {} }

  // ---- misc UI helpers ----
  function loadNotificationWidget(){
    try { const s = document.createElement("script"); s.src = "/notification.js"; s.defer = true; document.head.appendChild(s); } catch (e) {}
  }

  function ensureThemeLink(){
    let link = document.getElementById("umiThemeLink");
    if (!link) { link = document.createElement("link"); link.rel = "stylesheet"; link.id = "umiThemeLink"; document.head.appendChild(link); }
    return link;
  }
  function applyTheme(name){
    const link = ensureThemeLink();
    if (name === "dark") link.href = "chats-dark.css";
    else link.href = "chats-light.css";
    try { localStorage.setItem("umi:theme", name); } catch (e) {}
  }

  if (themeToggle) themeToggle.addEventListener("click", ()=> {
    const cur = localStorage.getItem("umi:theme") || "dark";
    const next = cur === "dark" ? "light" : "dark";
    applyTheme(next);
    themeToggle.textContent = next === "dark" ? "Dark" : "Light";
  });

  // (translations & UI setup unchanged to save space)
  const translations = {
    "Umu-Oyi":"Umu-Oyi",
    "Eternal Heritage of Umugwuanyi-Oyi · Amama Village · Ede-Oballa · Nsukka · Enugu":"Ebe Ncheta Umu-Oyi · Umugwuanyi-Oyi · Amama Village · Ede-Oballa · Nsukka · Enugu",
    "🔍":"🔍","☰":"☰","Feed":"Nri","Family Tree":"Ọkụkọ Ụmụnna","Igbo Calendar":"Kalenda Igbo",
    "Watch Later":"Soro Lee Mgbe E mesịrị","Settings":"Nhazi","Contact":"Kpọtụrụ","Igbo":"Igbo","Dark":"Ojii",
    "Sign Out":"Pụọ","Umu-Oyi Chat":"Mkparịta ụka Umu-Oyi","Select a conversation or search for someone to start chatting":"Họrọ mkparịta ụka ma ọ bụ chọọ onye ị ga-amalite ịkparịta ụka",
    " + New Chat":"+ Mkparịta ụka Ọhụrụ","+ New Chat":"+ Mkparịta ụka Ọhụrụ","Hide conversation":"Zoo mkparịta ụka",
    "Set a password to hide this conversation. To unhide you'll need this password.":"Tinye okwuntughe iji zoo mkparịta ụka a. Iji mepee ya, ị ga-achọ okwuntughe a.",
    "Cancel":"Kwụsị","Snap Score":"Akụkụ Snap","—":"—","Username":"Aha njirimara","Phone":"Ekwentị",
    "Location":"Ebe","Live location not available":"Ebe ndụ adịghị","About":"Banyere","Voice Call":"Akpọ oku olu",
    "Video Call":"Akpọ oku vidiyo","Mute Notifications":"Kwụsị nkwupụta","Block":"Kwụsị onye a","Report":"Kọọ akụkọ",
    "My AI Trainer":" Onye nkuzi AI m"
  };

  async function translatePageToIgbo(){
    try {
      Object.keys(translations).forEach(k => {
        const nodes = Array.from(document.querySelectorAll("body *"));
        nodes.forEach(el => {
          try {
            if (!el || el.children.length) return;
            const t = (el.textContent || "").trim();
            if (!t) return;
            if (t === k) el.textContent = translations[k];
            else if (t.indexOf(k) !== -1) el.textContent = el.textContent.replace(k, translations[k]);
          } catch (e) {}
        });
      });
      const idsToMap = {
        "brandTitle":"Umu-Oyi",
        "brandSub": translations["Eternal Heritage of Umugwuanyi-Oyi · Amama Village · Ede-Oballa · Nsukka · Enugu"],
        "landingTitle":"Mkparịta ụka Umugwu-Oyi",
        "landingSubtitle": translations["Select a conversation or search for someone to start chatting"],
        "membersTitle":"Ụmụ Ndị"
      };
      Object.keys(idsToMap).forEach(idk => { const el = id(idk); if (el) el.textContent = idsToMap[idk]; });
      const navMap = [["Feed","Nri"],["Family Tree","Ọkụkọ Ụmụnna"],["Igbo Calendar","Kalenda Igbo"],["Watch Later","Soro Lee Mgbe E mesịrị"],["Settings","Nhazi"],["Contact","Kpọtụrụ"],["Sign Out","Pụọ"]];
      navMap.forEach(pair => { document.querySelectorAll(".nav-link").forEach(a => { if (a.textContent.trim() === pair[0]) a.textContent = pair[1]; }); });
      document.querySelectorAll("[placeholder]").forEach(inp => { const p = inp.getAttribute("placeholder"); if (translations[p]) inp.setAttribute("placeholder", translations[p]); });
    } catch (e) {}
  }
  if (langToggle) langToggle.addEventListener("click", async ()=> {
    const lang = langToggle.dataset.lang === "igbo" ? "en" : "igbo";
    if (lang === "igbo") { await translatePageToIgbo(); langToggle.dataset.lang = "igbo"; langToggle.textContent = "English"; }
    else window.location.reload();
  });

  function initFromStorage(){ const theme = localStorage.getItem("umi:theme") || "dark"; applyTheme(theme); if (themeToggle) themeToggle.textContent = theme === "dark" ? "Dark" : "Light"; }

  if (membersList) membersList.addEventListener("click", (e) => { const li = e.target.closest(".member-item"); if (li) { qAll(".member-item.selected").forEach(x => x.classList.remove("selected")); li.classList.add("selected"); } });

  async function startCall(mode){
    try {
      if (!selectedMember || !selectedMember.id) { toast("Select a user first"); return; }
      const currentUser = await getCurrentUser();
      const userId = currentUser && currentUser.id ? currentUser.id : "";
      const peerId = selectedMember.id || "";
      if (!userId) { toast("You must be signed in to call"); return; }
      if (!peerId) { toast("Call failed: Invalid target"); return; }
      const ids = [userId, peerId].sort();
      const roomId = `call_${ids.join("_")}`;
      window.location.href = `/call.html?room=${encodeURIComponent(roomId)}&mode=${encodeURIComponent(mode)}`;
    } catch (e) { console.error('startCall', e); }
  }
  if (callBtn) callBtn.addEventListener("click", ()=> startCall("voice"));
  if (videoBtn) videoBtn.addEventListener("click", ()=> startCall("video"));
  if (voiceCallIcon) voiceCallIcon.addEventListener("click", ()=> startCall("voice"));
  if (videoCallIcon) { videoCallIcon.textContent = "📹"; videoCallIcon.addEventListener("click", ()=> startCall("video")); }

  // ---- hide conversation modal (unchanged) ----
  function ensureHideModal(){
    if (id("hideConvModal")) return id("hideConvModal");
    const div = document.createElement("div");
    div.id = "hideConvModal"; div.className = "modal hide-conv-modal";
    div.innerHTML = `<div class="modal-backdrop"></div><div class="modal-body" role="dialog" aria-modal="true" aria-label="Hide conversation"><h3 id="hideConvTitle">Hide conversation</h3><p id="hideConvText">Set a password to hide this conversation. To unhide you'll need this password.</p><form id="hideConvForm"><input id="hideConvPassword" type="password" placeholder="Enter password" /><div class="modal-actions"><button id="hideConvCancel" class="ghost" type="button">Cancel</button><button id="hideConvSubmit" class="action-primary" type="submit">Hide</button></div><div id="hideConvMessage" class="modal-msg" aria-live="polite"></div></form></div>`;
    div.style.position = "fixed"; div.style.left = "0"; div.style.top = "0"; div.style.width = "100%"; div.style.height = "100%"; div.style.display = "flex"; div.style.alignItems = "center"; div.style.justifyContent = "center"; div.style.zIndex = "9999";
    const bd = div.querySelector(".modal-backdrop"), mb = div.querySelector(".modal-body");
    if (bd) { bd.style.position = "absolute"; bd.style.left = "0"; bd.style.top = "0"; bd.style.right = "0"; bd.style.bottom = "0"; bd.style.background = "rgba(0,0,0,0.6)"; bd.style.zIndex = "1"; bd.addEventListener("click", ()=>{ div.remove(); }); }
    if (mb) { mb.style.position = "relative"; mb.style.zIndex = "2"; mb.style.width = "min(92vw,520px)"; mb.style.maxHeight = "86vh"; mb.style.overflow = "auto"; mb.style.borderRadius = "12px"; mb.style.padding = "16px"; mb.style.background = "var(--bg, #1b1b1b)"; }
    document.body.appendChild(div);
    const origRemove = div.remove.bind(div);
    div.remove = () => { document.documentElement.classList.remove("no-scroll"); origRemove(); };
    div.querySelector("#hideConvCancel").addEventListener("click", ()=> div.remove());
    div.querySelector("#hideConvForm").addEventListener("submit", async (e) => {
      try {
        e.preventDefault();
        const pwd = div.querySelector("#hideConvPassword").value || "";
        const msg = div.querySelector("#hideConvMessage");
        msg.textContent = "";
        if (!pwd) { msg.textContent = "Enter a password"; return; }
        const currentUser = await getCurrentUser();
        const userId = currentUser && currentUser.id ? currentUser.id : "";
        const peerId = selectedMember && selectedMember.id ? selectedMember.id : "";
        if (!userId || !peerId) { msg.textContent = "Invalid user or conversation"; return; }
        const res = await fetch("/.netlify/functions/all-chatapi/hide-chat", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ chatWith: peerId, password: pwd, user: userId })});
        if (!res) { msg.textContent = "Server error"; return; }
        if (res.status === 200) {
          toast("Conversation hidden");
          const li = membersList ? membersList.querySelector(`[data-id=\"${peerId}\"]`) : null;
          if (li) li.remove();
          div.remove();
          if (chatViewport) chatViewport.innerHTML = "";
          selectedMember = null;
        } else if (res.status === 401) msg.textContent = "Invalid password or not authorized";
        else {
          const j = await res.json().catch(()=>null);
          msg.textContent = (j && j.error) ? j.error : "Failed to hide";
        }
      } catch (err) {
        console.error('hideConv submit', err);
        div.querySelector("#hideConvMessage").textContent = "Request failed";
      }
    });
    return div;
  }

  if (hideConvBtn) hideConvBtn.addEventListener("click", ()=> {
    try {
      if (!selectedMember || !selectedMember.id) { toast("No conversation selected"); return; }
      const modal = ensureHideModal();
      modal.style.display = "flex";
      document.documentElement.classList.add("no-scroll");
      const pwdField = modal.querySelector("#hideConvPassword");
      if (pwdField) pwdField.focus();
    } catch (e) {}
  });

  window.addEventListener("beforeunload", ()=> {
    try { stopTypingPoll(); stopSlideshow(); stopLocationSharing(); stopPeerLocationPoll(); stopRealtimePolling(); } catch (e) {}
  });

  // quick search binding
  if (membersSearch) membersSearch.addEventListener("input", debounce(()=> { const v = membersSearch.value.trim(); searchMembers(v); }, 220));
  if (membersSearchBtn) membersSearchBtn.addEventListener("click", ()=> searchMembers(membersSearch ? membersSearch.value.trim() : ""));

  // mobile search toggle
  if (mobileSearchToggle){
    mobileSearchToggle.addEventListener("click", ()=> {
      try {
        const mp = membersPanel;
        if (!mp) return;
        const open = mp.classList.toggle("open");
        if (!open) { blurIfInside(mp); }
        mp.setAttribute("aria-hidden", open ? "false" : "true");
        if (mobileSearchToggle) mobileSearchToggle.setAttribute("aria-expanded", open ? "true" : "false");
        if (membersCloseBtn) membersCloseBtn.style.display = open ? "inline-flex" : "none";
        if (membersSearch && open) membersSearch.focus();
        if (open) document.documentElement.classList.add("no-scroll"); else document.documentElement.classList.remove("no-scroll");
        setTimeout(()=>updateChatHeights(), 60);
      } catch (e) {}
    });
  }

  if (membersCloseBtn) {
    membersCloseBtn.addEventListener("click", ()=> {
      if (!membersPanel) return;
      membersPanel.classList.remove("open");
      blurIfInside(membersPanel);
      membersPanel.setAttribute("aria-hidden", "true");
      membersCloseBtn.style.display = "none";
      if (mobileSearchToggle) mobileSearchToggle.setAttribute("aria-expanded", "false");
      document.documentElement.classList.remove("no-scroll");
      setTimeout(()=>updateChatHeights(), 60);
    });
  }

  if (menuToggle) menuToggle.addEventListener("click", ()=> { const nav = q(".nav"); if (nav) nav.classList.toggle("open"); });

  loadNotificationWidget();
  initFromStorage();
  ensureProfileScrollable();

  if (membersCloseBtn) membersCloseBtn.textContent = "Close";

  if (messageInput) messageInput.addEventListener("focus", ()=> {
    setTimeout(()=> { requestAnimationFrame(()=> chatViewport && chatViewport.scrollTo({ top: chatViewport.scrollHeight, behavior: "smooth" })); updateChatHeights(); }, 260);
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape"){
      const evtClose = new CustomEvent('umi:close-profile', { detail: null });
      window.dispatchEvent(evtClose);
      if (membersPanel && membersPanel.classList.contains("open")) {
        blurIfInside(membersPanel); membersPanel.classList.remove("open"); membersPanel.setAttribute("aria-hidden", "true");
        if (mobileSearchToggle) mobileSearchToggle.setAttribute("aria-expanded", "false");
        if (membersCloseBtn) membersCloseBtn.style.display = "none";
        document.documentElement.classList.remove("no-scroll");
      }
      if (umiEmojiPanel && umiEmojiPanel.style.display === "block") umiEmojiPanel.style.display = "none";
    }
  });

  function trapFocus(el){
    try {
      if (!el) return;
      const focusable = 'a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
      const nodes = Array.from(el.querySelectorAll(focusable));
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      function handler(e){
        if (e.key !== "Tab") return;
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
      el._focusTrap = handler;
      el.addEventListener("keydown", handler);
      first.focus();
    } catch (e) {}
  }

  function releaseFocusTrap(){
    try {
      const el = profilePanel;
      if (el && el._focusTrap){ el.removeEventListener("keydown", el._focusTrap); el._focusTrap = null; }
    } catch (e) {}
  }

  if (membersCloseBtn) {
    membersCloseBtn.addEventListener("click", ()=> {
      try { blurIfInside(membersPanel); if (membersPanel) membersPanel.classList.remove("open"); if (membersPanel) membersPanel.setAttribute("aria-hidden","true"); document.documentElement.classList.remove("no-scroll"); setTimeout(()=>updateChatHeights(),60); } catch (e) {}
    });
  }

  if (hideProfile) hideProfile.addEventListener("click", ()=> { try { blurIfInside(profilePanel); const evtClose = new CustomEvent('umi:close-profile', { detail: null }); window.dispatchEvent(evtClose); } catch (e) {} });

  function startSlideshow(){
    try {
      const slides = qAll(".slideshow .slide");
      if (slides.length === 0) return;
      slides.forEach(s => s.classList.remove("active"));
      currentSlide = 0;
      slides[currentSlide].classList.add("active");
      if (slideshowInterval) clearInterval(slideshowInterval);
      slideshowInterval = setInterval(()=> {
        slides[currentSlide].classList.remove("active");
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add("active");
      }, 10000);
    } catch (e) {}
  }

  function stopSlideshow(){ if (slideshowInterval) clearInterval(slideshowInterval); slideshowInterval = null; }

  if (backToLanding) backToLanding.addEventListener("click", closeChat);
  if (newChatBtn) newChatBtn.addEventListener("click", ()=> { if (window.innerWidth < 768) { if (mobileSearchToggle) mobileSearchToggle.click(); } else searchMembers(""); });
  if (landingNewChat) landingNewChat.addEventListener("click", ()=> { if (window.innerWidth < 768) { if (mobileSearchToggle) mobileSearchToggle.click(); } else searchMembers(""); });
  if (cameraBtn) cameraBtn.addEventListener("click", ()=> { openCameraModal(); });

  function openCameraModal(){
    try {
      const modal = document.createElement("div");
      modal.className = "camera-modal";
      modal.innerHTML = `<div class="camera-backdrop"></div><div class="camera-body"><video id="cameraPreview" autoplay playsinline muted style="max-width:100%;border-radius:12px;"></video><div style="display:flex;gap:8px;margin-top:8px;"><button id="takePhotoBtn" class="btn small">Snap</button><button id="startVideoBtn" class="btn small">Record</button><button id="stopVideoBtn" class="btn small" style="display:none">Stop</button><button id="sendCaptureBtn" class="btn small royal-color" style="display:none">Send</button><button id="closeCameraBtn" class="ghost">Close</button></div></div>`;
      document.body.appendChild(modal);
      let preview = modal.querySelector("#cameraPreview"),
          takePhotoBtn = modal.querySelector("#takePhotoBtn"),
          startVideoBtn = modal.querySelector("#startVideoBtn"),
          stopVideoBtn = modal.querySelector("#stopVideoBtn"),
          sendCaptureBtn = modal.querySelector("#sendCaptureBtn"),
          closeCameraBtn = modal.querySelector("#closeCameraBtn");

      navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then(stream => {
        activeStream = stream;
        preview.srcObject = stream;
        preview.play();
      }).catch(e => { toast("Camera access denied"); modal.remove(); });

      let mediaRecorderLocal = null, videoChunksLocal = [];

      takePhotoBtn.addEventListener("click", ()=> {
        try {
          const canvas = document.createElement("canvas");
          canvas.width = preview.videoWidth || 1280;
          canvas.height = preview.videoHeight || 720;
          const ctx = canvas.getContext("2d");
          ctx.drawImage(preview, 0, 0, canvas.width, canvas.height);
          canvas.toBlob(blob => {
            const pill = document.createElement("div"); pill.className = "attachment-pill";
            const img = document.createElement("img"); img.src = URL.createObjectURL(blob); img.style.maxWidth = "220px";
            pill.appendChild(img);
            const del = document.createElement("button"); del.type = "button"; del.className = "ghost remove"; del.textContent = "Remove";
            del.addEventListener("click", ()=> { pill.remove(); if (attachmentsList && !attachmentsList.children.length) composerMeta.style.display = "none"; });
            pill.appendChild(del);
            pill._fileBlob = blob;
            attachmentsList && attachmentsList.appendChild(pill);
            if (composerMeta) composerMeta.style.display = "flex";
          }, "image/jpeg", 0.9);
        } catch (e) { toast("Capture failed"); }
      });

      startVideoBtn.addEventListener("click", ()=> {
        try {
          if (!activeStream) return;
          videoChunksLocal = [];
          mediaRecorderLocal = new MediaRecorder(activeStream, { mimeType: "video/webm;codecs=vp8,opus" });
          mediaRecorderLocal.ondataavailable = e => { if (e.data && e.data.size) videoChunksLocal.push(e.data); };
          mediaRecorderLocal.onstop = ()=> {
            const blob = new Blob(videoChunksLocal, { type: "video/webm" });
            const pill = document.createElement("div"); pill.className = "attachment-pill";
            const v = document.createElement("video"); v.controls = true; v.src = URL.createObjectURL(blob); v.style.maxWidth = "240px";
            pill.appendChild(v);
            const del = document.createElement("button"); del.type = "button"; del.className = "ghost remove"; del.textContent = "Remove";
            del.addEventListener("click", ()=> { pill.remove(); if (attachmentsList && !attachmentsList.children.length) composerMeta.style.display = "none"; });
            pill.appendChild(del);
            pill._fileBlob = blob;
            attachmentsList && attachmentsList.appendChild(pill);
            if (composerMeta) composerMeta.style.display = "flex";
          };
          mediaRecorderLocal.start();
          startVideoBtn.style.display = "none";
          stopVideoBtn.style.display = "inline-flex";
          sendCaptureBtn.style.display = "none";
        } catch (e) { toast("Recording failed"); }
      });

      stopVideoBtn.addEventListener("click", ()=> {
        try { if (!mediaRecorderLocal) return; mediaRecorderLocal.stop(); } catch (e) {}
        startVideoBtn.style.display = "inline-flex";
        stopVideoBtn.style.display = "none";
        sendCaptureBtn.style.display = "inline-flex";
      });

      sendCaptureBtn.addEventListener("click", ()=> {
        if (attachmentsList && attachmentsList.children.length) { sendMessage(); modal.remove(); } else toast("Nothing captured");
      });

      closeCameraBtn.addEventListener("click", ()=> {
        try { if (activeStream) activeStream.getTracks().forEach(t => t.stop()); activeStream = null; } catch (e) {}
        modal.remove();
      });

    } catch (e) { console.error('openCameraModal', e); }
  }

  window.addEventListener("load", ()=> { startSlideshow(); if (window.innerWidth >= 768) searchMembers(""); populateEmojiPanel(); });

  // ---- location sharing (unchanged) ----
  function stopLocationSharing(){ try { if (watchId !== null && navigator.geolocation && navigator.geolocation.clearWatch) { navigator.geolocation.clearWatch(watchId); } watchId = null; locationShareId = null; } catch (e) {} }
  function startLocationSharing(){
    try {
      if (!navigator.geolocation) { toast("Geolocation not supported"); return; }
      if (locationShareId !== null) { toast("Already sharing"); return; }
      const startSharing = async pos => {
        try {
          const coords = pos.coords;
          const currentUser = await getCurrentUser();
          const userId = currentUser && currentUser.id ? currentUser.id : "";
          if (!userId) return;
          await fetch("/.netlify/functions/all-chatapi/update-location", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ user: userId, lat: coords.latitude, lon: coords.longitude, accuracy: coords.accuracy, timestamp: pos.timestamp }) });
        } catch (e) {}
      };
      watchId = navigator.geolocation.watchPosition(startSharing, err => { toast("Location error: " + (err && err.message ? err.message : "denied")); }, { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 });
      locationShareId = Date.now();
      toast("Sharing location");
    } catch (e) { console.error('startLocationSharing', e); }
  }

  function stopPeerLocationPoll(){
    try {
      if (peerLocationPoll) { clearInterval(peerLocationPoll); peerLocationPoll = null; }
      const profileLocationEl = id("profileLocation");
      if (profileLocationEl) profileLocationEl.textContent = selectedMember && selectedMember.last_seen ? ("Last seen: " + new Date(selectedMember.last_seen).toLocaleTimeString()) : "Live location not available";
      if (peerMeta) peerMeta.textContent = selectedMember && selectedMember.online ? "Online" : (selectedMember && selectedMember.last_seen ? ("Last seen " + new Date(selectedMember.last_seen).toLocaleTimeString()) : "Last seen —");
    } catch (e) {}
  }

  function startPeerLocationPoll(){
    try {
      stopPeerLocationPoll();
      if (!selectedMember || !selectedMember.id) return;
      if (String(selectedMember.id).toLowerCase() === 'my-ai') {
        const profileLocationEl = id("profileLocation");
        if (profileLocationEl) profileLocationEl.textContent = "Live location not available";
        if (peerMeta) peerMeta.textContent = "My AI (local)";
        return;
      }
      async function poll(){
        try {
          const res = await fetch("/.netlify/functions/all-chatapi/get-location?userId=" + encodeURIComponent(selectedMember.id), { headers: Object.assign({}, authHeader()) }).catch(()=>null);
          if (res && res.ok) {
            const j = await res.json().catch(()=>null);
            if (j && j.lat && j.lon){
              const t = new Date(j.timestamp || Date.now()).toLocaleTimeString();
              const profileLocationEl = id("profileLocation");
              if (profileLocationEl) profileLocationEl.innerHTML = `<a href="https://www.google.com/maps?q=${encodeURIComponent(j.lat+','+j.lon)}" target="_blank" rel="noopener noreferrer">Live: ${j.lat.toFixed(4)}, ${j.lon.toFixed(4)}</a><div style="font-size:12px;color:var(--muted)">Updated: ${t}</div>`;
              if (peerMeta) peerMeta.textContent = `Live location • ${t}`;
              return;
            }
          }
        } catch (e) {}
        const profileLocationEl = id("profileLocation");
        if (profileLocationEl) profileLocationEl.textContent = selectedMember.last_seen ? ("Last seen: " + new Date(selectedMember.last_seen).toLocaleTimeString()) : "Live location not available";
        if (peerMeta) peerMeta.textContent = selectedMember && selectedMember.online ? "Online" : (selectedMember && selectedMember.last_seen ? ("Last seen " + new Date(selectedMember.last_seen).toLocaleTimeString()) : "Last seen —");
      }
      poll();
      peerLocationPoll = setInterval(poll, 5000);
    } catch (e) { console.error('startPeerLocationPoll', e); }
  }

  // ---- mute / block / report handlers unchanged ----
  const muteBtn = id("muteBtn"), blockBtn = id("blockBtn"), reportBtn = id("reportBtn");
  if (muteBtn) muteBtn.addEventListener("click", async ()=> {
    try {
      if (!selectedMember || !selectedMember.id) { toast("No user selected"); return; }
      const currentUser = await getCurrentUser();
      const userId = currentUser && currentUser.id ? currentUser.id : "";
      if (!userId) { toast("Sign in required"); return; }
      const key = `mute_${userId}_${selectedMember.id}`;
      const muted = (localStorage.getItem(key) === "1");
      if (!muted){
        await fetch("/.netlify/functions/all-chatapi/mute-notifications", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ user: userId, target: selectedMember.id, action: "mute" }) });
        localStorage.setItem(key, "1");
        toast("Notifications muted for this chat");
      } else {
        await fetch("/.netlify/functions/all-chatapi/mute-notifications", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ user: userId, target: selectedMember.id, action: "unmute" }) });
        localStorage.removeItem(key);
        toast("Notifications unmuted for this chat");
      }
    } catch (e) { toast("Mute failed"); console.error('mute', e); }
  });

  if (blockBtn) blockBtn.addEventListener("click", async ()=> {
    try {
      if (!selectedMember || !selectedMember.id) { toast("No user selected"); return; }
      const currentUser = await getCurrentUser();
      const userId = currentUser && currentUser.id ? currentUser.id : "";
      if (!userId) { toast("Sign in required"); return; }
      const res = await fetch("/.netlify/functions/all-chatapi/block-user", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ user: userId, target: selectedMember.id }) });
      if (res && res.ok) { toast("User blocked"); const li = membersList ? membersList.querySelector(`[data-id=\"${selectedMember.id}\"]`) : null; if (li) li.remove(); closeChat(); } else toast("Block failed");
    } catch (e) { toast("Block error"); console.error('block', e); }
  });

  if (reportBtn) reportBtn.addEventListener("click", async ()=> {
    try {
      if (!selectedMember || !selectedMember.id) { toast("No user selected"); return; }
      const reason = prompt("Please describe the issue (optional)", "");
      const currentUser = await getCurrentUser();
      const userId = currentUser && currentUser.id ? currentUser.id : "";
      if (!userId) { toast("Sign in required"); return; }
      const res = await fetch("/.netlify/functions/all-chatapi/report-user", { method: "POST", headers: Object.assign({"Content-Type":"application/json"}, authHeader()), body: JSON.stringify({ user: userId, target: selectedMember.id, reason: reason || "" }) });
      if (res && res.ok) toast("Report submitted"); else toast("Report failed");
    } catch (e) { toast("Report error"); console.error('report', e); }
  });

  if (q(".profile-toggle .icon img")) { q(".profile-toggle .icon img").style.width = "40px"; q(".profile-toggle .icon img").style.height = "40px"; q(".profile-toggle .icon img").style.borderRadius = "50%"; }

  function stopAllBackgroundTasks(){
    try { stopTypingPoll(); stopPeerLocationPoll(); stopSlideshow(); stopLocationSharing(); stopRealtimePolling(); } catch (e) {}
  }

  window.openChat = openChat;
  window.closeChat = closeChat;

  function ensureProfileElementsReady(){
    try {
      const allAvatars = document.querySelectorAll("img.member-avatar, img.peer-avatar, .open-profile");
      allAvatars.forEach(el => {
        el.addEventListener("click", (ev) => {
          try {
            ev.stopPropagation();
            const pid = el.dataset.profileId || el.dataset.id || (el.closest && el.closest(".member-item") && el.closest(".member-item").dataset && el.closest(".member-item").dataset.id);
            const info = { id: pid || el.dataset.id || getSyncUserId(), full_name: el.dataset.name || el.alt || "", avatar_url: el.src || "" };
            const evt = new CustomEvent('umi:open-profile', { detail: info, bubbles: true, cancelable: true });
            window.dispatchEvent(evt);
            populateProfile(info);
          } catch (e) {}
        });
      });
    } catch (e) {}
  }
  setTimeout(ensureProfileElementsReady, 700);

  if (profileToggle) profileToggle.addEventListener("click", async (e) => {
    try {
      e.stopPropagation();
      let payload = null;
      if (selectedMember && selectedMember.id) payload = normalize(selectedMember);
      else {
        const user = await getCurrentUser();
        if (user && user.id) payload = { id: String(user.id), full_name: "", avatar_url: profileToggleAvatar && profileToggleAvatar.src || "default-avatar.png" };
        else payload = { id: getSyncUserId() || "", full_name: "", avatar_url: profileToggleAvatar && profileToggleAvatar.src || "default-avatar.png" };
      }
      const evt = new CustomEvent('umi:open-profile', { detail: payload, bubbles: true, cancelable: true });
      window.dispatchEvent(evt);
      populateProfile(payload);
    } catch (e) {}
  });

  function debounce(fn, ms = 200){ let t; return (...a)=>{ clearTimeout(t); t = setTimeout(()=> fn.apply(this, a), ms); }; }

  // Emoji code unchanged (skipped here for brevity, it is present above in file)
  const EMO_MAP = {
    Recent: [],
    Smileys: ["😀","😃","😄","😁","😆","😅","😂","🤣","🙂","🙃","😉","😊","😇","😍","🤩","😘","😗","😚","😙","😋","😛","😜","🤪","🤨","🤯","🥳"],
    Faces: ["🙂","🙃","😉","😊","😇","😳","🥺","😬","🤯","🥴"],
    Laugh: ["😂","🤣","😆","😅","😄","😃"],
    Sad: ["😔","😢","😭","😥","😓"],
    Love: ["😍","😘","😗","💖","💕","💓","❤️"],
    Food: ["🍏","🍎","🍐","🍊","🍋","🍌","🍉","🍇","🍓","🍒","🍑","🥭","🍍","🥥","🥝","🍗","🍖","🍤","🍣","🍱","🍜","🍲","🍛","🍙","🍚"],
    Activities: ["🏆","🎮","🎧","🎤","🎵","🎶","🎬","🎨","🎲","🎯"],
    Travel: ["✈️","🛩️","🚀","🚗","🚕","🚙","🚌","🚎","🚲","🚤","⛵️","🚁","🚂"],
    Objects: ["💡","🔔","📷","📱","💻","🎧","🧭"],
    Animals: ["🐶","🐱","🐭","🐹","🐰","🦊","🐻","🐼","🦁","🐯","🐨","🐸","🐵"],
    Flags: ["🇺🇸","🇬🇧","🇳🇬","🇨🇦","🇮🇳","🇩🇪","🇫🇷"]
  };
  const EMOJIS = Object.values(EMO_MAP).flat().filter(Boolean);

  function populateEmojiPanel(){
    try {
      if (!emojiGrid) return;
      emojiGrid.innerHTML = "";
      EMOJIS.forEach(e => {
        const b = document.createElement("button");
        b.className = "emoji-btn";
        b.type = "button";
        b.textContent = e;
        b.addEventListener("click", ()=>{ insertAtCursor(messageInput, e); hideEmojiPanel(); saveEmojiFreq(e); messageInput && messageInput.focus(); });
        emojiGrid.appendChild(b);
      });
      if (emojiCats) {
        emojiCats.innerHTML = "";
        Object.keys(EMO_MAP).forEach(cat => {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.textContent = cat;
          btn.addEventListener("click", ()=> { if (emojiSearch) emojiSearch.value = ""; filterEmoji(cat); });
          emojiCats.appendChild(btn);
        });
      }
    } catch (e) {}
  }

  function filterEmoji(category){
    try {
      if (!emojiGrid) return;
      const arr = EMO_MAP[category] || [];
      emojiGrid.innerHTML = "";
      arr.forEach(e => {
        const b = document.createElement("button");
        b.className = "emoji-btn";
        b.type = "button";
        b.textContent = e;
        b.addEventListener("click", ()=>{ insertAtCursor(messageInput, e); hideEmojiPanel(); saveEmojiFreq(e); messageInput && messageInput.focus(); });
        emojiGrid.appendChild(b);
      });
    } catch (e) {}
  }

  function showEmojiPanel(){
    try {
      if (!umiEmojiPanel || !emojiBtn) return;
      const rect = emojiBtn.getBoundingClientRect();
      const right = Math.max(8, window.innerWidth - rect.right);
      umiEmojiPanel.style.right = (right + 8) + "px";
      umiEmojiPanel.style.bottom = (window.innerHeight - rect.top + 10) + "px";
      umiEmojiPanel.style.display = 'block';
      if (emojiSearch) emojiSearch.focus();
    } catch (e) {}
  }
  function hideEmojiPanel(){ try { if (umiEmojiPanel) umiEmojiPanel.style.display = 'none'; } catch (e) {} }

  function insertAtCursor(el, text){
    try {
      if (!el) return;
      const start = el.selectionStart || 0, end = el.selectionEnd || 0, v = el.value || "";
      el.value = v.slice(0, start) + text + v.slice(end);
      el.selectionStart = el.selectionEnd = start + text.length;
      el.focus(); updateChatHeights();
    } catch (e) {}
  }

  function saveEmojiFreq(sym){
    try {
      const raw = localStorage.getItem("umu:emoji:freq") || "{}";
      const freqs = JSON.parse(raw);
      freqs[sym] = (freqs[sym] || 0) + 1;
      const entries = Object.entries(freqs).sort((a,b)=>b[1]-a[1]).slice(0,80);
      localStorage.setItem("umu:emoji:freq", JSON.stringify(Object.fromEntries(entries)));
    } catch (e) {}
  }

  function renderRecentEmojis(container){
    try {
      const raw = localStorage.getItem("umu:emoji:freq") || "{}";
      const freqs = JSON.parse(raw);
      const entries = Object.keys(freqs).slice(0,24);
      container.innerHTML = "";
      entries.forEach(e => {
        const b = document.createElement("button");
        b.type = "button"; b.textContent = e;
        b.addEventListener("click", ()=> { insertAtCursor(messageInput, e); container.parentElement.style.display = "none"; saveEmojiFreq(e); messageInput && messageInput.focus(); });
        container.appendChild(b);
      });
    } catch (e) {}
  }

  if (emojiBtn) emojiBtn.addEventListener("click", function(e){ try { e.stopPropagation(); if (umiEmojiPanel && umiEmojiPanel.style.display === "block") hideEmojiPanel(); else { populateEmojiPanel(); showEmojiPanel(); } } catch (err) {} });
  if (emojiSearch) emojiSearch.addEventListener("input", debounce(()=> {
    try {
      const v = (emojiSearch.value || "").trim().toLowerCase();
      if (!v) { populateEmojiPanel(); return; }
      const results = EMOJIS.filter(e => e.toLowerCase().includes(v));
      emojiGrid.innerHTML = "";
      results.forEach(e => {
        const b = document.createElement("button");
        b.className = "emoji-btn";
        b.type = "button";
        b.textContent = e;
        b.addEventListener("click", ()=> { insertAtCursor(messageInput, e); hideEmojiPanel(); saveEmojiFreq(e); messageInput && messageInput.focus(); });
        emojiGrid.appendChild(b);
      });
    } catch (e) {}
  }, 120));

  document.addEventListener("click", function(e){ try { if (umiEmojiPanel && umiEmojiPanel.style.display === "block" && !umiEmojiPanel.contains(e.target) && e.target !== emojiBtn) umiEmojiPanel.style.display = "none"; } catch (err) {} }, { passive: true });

  //
  // -------------------- Polling-based Realtime Client (Netlify friendly) --------------------
  //
  // Replaces the websocket approach. Polls /.netlify/functions/all-chatapi/get-events?userId=...&since=...
  // Persists last event timestamp in localStorage per user: umi:lastEvent:<userId>
  // Exponential backoff on failures. Lower frequency when tab hidden.
  //

  let realtimePollingInterval = null;
  let realtimePollingFailures = 0;
  let realtimePollMs = 2000; // base polling interval (2s)
  let realtimeMaxBackoff = 30000; // 30s
  let lastEventKeyBase = "umi:lastEvent:"; // store per user
  let realtimeUserId = getSyncUserId() || "";
  let isTabHidden = (document.visibilityState === "hidden");

  document.addEventListener('visibilitychange', ()=>{
    isTabHidden = (document.visibilityState === "hidden");
    // if hidden, poll more slowly
    if (isTabHidden) {
      if (realtimePollingInterval) {
        clearInterval(realtimePollingInterval);
        realtimePollingInterval = setInterval(pollNow, Math.min(15000, realtimeMaxBackoff)); // 15s when hidden
      }
    } else {
      if (realtimePollingInterval) {
        clearInterval(realtimePollingInterval);
        realtimePollingInterval = setInterval(pollNow, realtimePollMs); // resume normal
      }
      // when returning, trigger immediate poll to catch up
      pollNow();
    }
  });

  function getLastEventTsForUser(uid){
    try {
      if (!uid) return 0;
      const raw = localStorage.getItem(lastEventKeyBase + uid);
      if (!raw) return 0;
      const v = parseInt(raw, 10);
      return isNaN(v) ? 0 : v;
    } catch (e) { return 0; }
  }
  function setLastEventTsForUser(uid, ts){
    try {
      if (!uid) return;
      localStorage.setItem(lastEventKeyBase + uid, String(ts));
    } catch (e) {}
  }

  function toMs(ts){
    // reuse earlier logic: convert various ts shapes to ms
    try {
      if (!ts) return 0;
      if (typeof ts === 'number') {
        if (String(ts).length <= 10) return ts * 1000;
        return ts;
      }
      const n = Date.parse(ts);
      if (!isNaN(n)) return n;
      const n2 = parseInt(ts,10);
      if (!isNaN(n2)) {
        if (String(n2).length <= 10) return n2 * 1000;
        return n2;
      }
    } catch (e) {}
    return 0;
  }

  async function processEvent(ev){
    try {
      if (!ev || !ev.type) return;
      const type = ev.type;
      if (type === "message_created" || type === "message:new" || type === "message") {
        const msg = ev.message || ev.data || ev;
        const convId = String(msg.conversation_id || msg.chat_with || msg.recipient || msg.recipient_id || msg.with || msg.to || msg.from || msg.sender || msg.user || (selectedMember && selectedMember.id || ""));
        const normalized = {
          id: msg.id || msg._id || msg.msg_id || msg.message_id || null,
          sender_id: msg.sender_id || msg.sender || msg.from || msg.user_id || msg.user || null,
          content: msg.content || msg.message || msg.body || "",
          media: msg.media || msg.media_url || msg.mediaUrl || msg.file_url || null,
          timestamp: msg.timestamp || msg.created_at || msg.createdAt || Date.now(),
          status: msg.status || "sent"
        };
        if (!normalized.id) {
          normalized.id = 'evt_' + (ev.id || Date.now() + Math.random().toString(36).slice(2,8));
        }
        if (!messageCache.exists(convId, normalized.id)) {
          messageCache.add(convId, normalized);
          if (selectedMember && selectedMember.id && String(selectedMember.id) === String(convId)) {
            const cache = messageCache.get(convId);
            if (cache && cache.msgs) renderMessages(cache.msgs);
          } else {
            try {
              const li = membersList ? membersList.querySelector(`[data-id="${convId}"]`) : null;
              if (li) li.classList.add("has-unread");
            } catch (e) {}
          }
        }
        window.dispatchEvent(new CustomEvent('umi:realtime:message', { detail: normalized }));
        return;
      }

      if (type === "message_updated" || type === "message_edit") {
        const msg = ev.message || ev.data || ev;
        const convId = String(msg.conversation_id || msg.chat_with || msg.recipient || msg.to || msg.from || msg.sender || msg.user || (selectedMember && selectedMember.id || ""));
        const normalized = {
          id: msg.id || msg._id || msg.msg_id || msg.message_id || null,
          sender_id: msg.sender_id || msg.sender || msg.from || msg.user_id || msg.user || null,
          content: msg.content || msg.message || msg.body || "",
          media: msg.media || msg.media_url || null,
          timestamp: msg.timestamp || msg.updated_at || Date.now(),
          status: msg.status || "sent"
        };
        if (normalized.id) {
          messageCache.replaceTemp(convId, normalized.id, normalized);
          if (selectedMember && selectedMember.id && String(selectedMember.id) === String(convId)) {
            const cache = messageCache.get(convId);
            if (cache && cache.msgs) renderMessages(cache.msgs);
          }
        }
        window.dispatchEvent(new CustomEvent('umi:realtime:message:update', { detail: normalized }));
        return;
      }

      if (type === "presence_update" || type === "user_presence") {
        const u = normalize(ev.user || ev.data || ev);
        try {
          const li = membersList ? membersList.querySelector(`[data-id="${u.id}"]`) : null;
          if (li) {
            const pd = li.querySelector(".presence-dot");
            if (pd) pd.className = "presence-dot " + (u.online ? "online" : "offline");
            const sub = li.querySelector(".member-sub");
            if (sub && u.last_message) sub.textContent = u.last_message;
          }
        } catch (e) {}
        window.dispatchEvent(new CustomEvent('umi:realtime:presence', { detail: u }));
        return;
      }

      if (type === "typing" || type === "typing_indicator") {
        const d = ev.data || ev;
        const from = d.from || d.sender || d.user || d.user_id;
        const isTyping = !!d.typing;
        if (selectedMember && String(selectedMember.id) === String(from)) {
          if (typingIndicator) typingIndicator.style.display = isTyping ? "flex" : "none";
        } else {
          try {
            const li = membersList ? membersList.querySelector(`[data-id="${from}"]`) : null;
            if (li) {
              if (isTyping) li.classList.add("typing"); else li.classList.remove("typing");
            }
          } catch (e) {}
        }
        window.dispatchEvent(new CustomEvent('umi:realtime:typing', { detail: { from, typing: isTyping } }));
        return;
      }

      // generic events
      window.dispatchEvent(new CustomEvent('umi:realtime:event', { detail: ev }));
    } catch (e) {
      console.error('processEvent', e);
    }
  }

  async function fetchEventsSince(uid, sinceTs){
    try {
      if (!uid) return null;
      const url = "/.netlify/functions/all-chatapi/get-events?userId=" + encodeURIComponent(uid) + (sinceTs ? ("&since=" + encodeURIComponent(sinceTs)) : "");
      const res = await fetch(url, { headers: Object.assign({}, authHeader()), credentials: "same-origin" });
      if (!res || !res.ok) return null;
      const j = await res.json().catch(()=>null);
      return j;
    } catch (e) {
      return null;
    }
  }

  // Single-run poll function (can be called on interval or manually)
  async function pollNow(){
    try {
      realtimeUserId = realtimeUserId || getSyncUserId() || "";
      if (!realtimeUserId) {
        const cu = await getCurrentUser();
        if (cu && cu.id) realtimeUserId = String(cu.id);
      }
      if (!realtimeUserId) return;

      const lastTs = getLastEventTsForUser(realtimeUserId) || 0;
      const eventsRaw = await fetchEventsSince(realtimeUserId, lastTs);
      if (!eventsRaw) {
        realtimePollingFailures++;
        const backoff = Math.min(realtimePollMs * Math.pow(1.9, realtimePollingFailures), realtimeMaxBackoff);
        if (realtimePollingInterval) { clearInterval(realtimePollingInterval); realtimePollingInterval = setInterval(pollNow, isTabHidden ? Math.min(15000, backoff) : backoff); }
        return;
      }

      realtimePollingFailures = 0;
      let events = [];
      let newLast = lastTs;
      if (Array.isArray(eventsRaw)) {
        events = eventsRaw;
        events.forEach(ev => {
          if (ev && (ev.ts || ev.timestamp || ev.time)) {
            const t = toMs(ev.ts || ev.timestamp || ev.time);
            if (t && t > newLast) newLast = t;
          }
        });
      } else if (eventsRaw.events && Array.isArray(eventsRaw.events)) {
        events = eventsRaw.events;
        if (eventsRaw.lastEvent) newLast = Math.max(newLast, toMs(eventsRaw.lastEvent) || 0);
      } else if (eventsRaw.rows && Array.isArray(eventsRaw.rows)) {
        events = eventsRaw.rows.map(r => ({ type: "message_created", message: r }));
        if (eventsRaw.lastEvent) newLast = Math.max(newLast, toMs(eventsRaw.lastEvent) || 0);
      } else if (eventsRaw.event) {
        events = [eventsRaw.event];
        newLast = Math.max(newLast, toMs(eventsRaw.lastEvent || Date.now()) || 0);
      } else {
        if (eventsRaw.id || eventsRaw.message_id || eventsRaw.msg_id) {
          events = [{ type: "message_created", message: eventsRaw }];
          newLast = Math.max(newLast, toMs(eventsRaw.timestamp || eventsRaw.created_at || Date.now()) || 0);
        } else {
          // nothing usable
        }
      }

      if (events && events.length) {
        for (let i=0;i<events.length;i++){
          const ev = events[i];
          try {
            if (ev && ev.type && ev.type === "message_created") { await processEvent(ev); }
            else if (ev && (ev.message || ev.data) && (ev.type || ev.message.type)) { await processEvent(ev); }
            else if (ev && (ev.id || ev.msg_id || ev.message_id)) { await processEvent({ type: "message_created", message: ev }); }
            else await processEvent(ev);
            if (ev && (ev.ts || ev.timestamp || ev.time)) {
              const t = toMs(ev.ts || ev.timestamp || ev.time);
              if (t && t > newLast) newLast = t;
            } else if (ev && ev.message && (ev.message.timestamp || ev.message.created_at)) {
              const t = toMs(ev.message.timestamp || ev.message.created_at);
              if (t && t > newLast) newLast = t;
            }
          } catch (err) { console.error('event process error', err); }
        }
      }

      if (eventsRaw.lastEvent) newLast = Math.max(newLast, toMs(eventsRaw.lastEvent) || 0);
      if (newLast && newLast > lastTs) setLastEventTsForUser(realtimeUserId, newLast);

      if (!realtimePollingInterval) {
        realtimePollingInterval = setInterval(pollNow, isTabHidden ? 15000 : realtimePollMs);
      } else {
        clearInterval(realtimePollingInterval);
        realtimePollingInterval = setInterval(pollNow, isTabHidden ? 15000 : realtimePollMs);
      }
    } catch (e) {
      realtimePollingFailures++;
      console.error('pollNow error', e);
    }
  }

  function startRealtimePolling(){
    try {
      realtimeUserId = getSyncUserId() || "";
      if (!realtimeUserId) {
        const tuser = getCurrentUser();
        if (tuser && tuser.id) realtimeUserId = String(tuser.id);
      }
      if (realtimePollingInterval) clearInterval(realtimePollingInterval);
      realtimePollingInterval = setInterval(pollNow, isTabHidden ? 15000 : realtimePollMs);
      // do one immediate poll
      pollNow();
      window.addEventListener('umi:force-poll', pollNow);
    } catch (e) { console.error('startRealtimePolling', e); }
  }

  function stopRealtimePolling(){
    try {
      if (realtimePollingInterval) { clearInterval(realtimePollingInterval); realtimePollingInterval = null; }
      window.removeEventListener('umi:force-poll', pollNow);
    } catch (e) {}
  }

  // expose API
  window.startRealtimePolling = startRealtimePolling;
  window.stopRealtimePolling = stopRealtimePolling;
  window.pollNow = pollNow;

  // start
  try { startRealtimePolling(); } catch (e) { console.error('init realtime polling', e); }

  //
  // ---------------------------------------------------------------------------------------
  //

  function updateChatHeights(){
    try{
      const headerH = document.querySelector(".site-header") ? document.querySelector(".site-header").offsetHeight : 72;
      const footerH = siteFooter ? siteFooter.offsetHeight : 0;
      const composerEl = document.querySelector(".chat-input");
      const composerH = composerEl ? composerEl.offsetHeight : parseInt(getComputedStyle(document.documentElement).getPropertyValue("--composer-h")) || 68;
      const wrap = document.querySelector(".chat-viewport-wrap");
      if (wrap) {
        const max = Math.max(120, window.innerHeight - headerH - composerH - footerH - 32);
        wrap.style.maxHeight = max + "px";
      }
      if (chatViewport) {
        const padBottom = composerH + 18;
        chatViewport.style.paddingBottom = padBottom + "px";
      }
    } catch (e) {}
  }

  window.addEventListener("resize", ()=>{ updateChatHeights(); ensureProfileScrollable(); }, { passive:true });
  window.addEventListener("orientationchange", ()=>{ setTimeout(()=>{ updateChatHeights(); },120); });
  window.addEventListener("load", ()=>{ setTimeout(()=>{ updateChatHeights(); },40); });

  const EMO_MAP_SHORT = EMO_MAP;

  function startSlideshowOnLoad(){ startSlideshow(); }

  startSlideshowOnLoad();

  //
  // ---- storage event sync for multi-tab updates ----
  //
  window.addEventListener('storage', (e)=>{
    try {
      if (!e.key) return;
      // watch for chatcache updates and updated markers
      if (e.key.indexOf("umi:chatcache:") === 0) {
        const convId = e.key.replace("umi:chatcache:","").replace(":updated","");
        if (!convId) return;
        // if user currently viewing this conversation, reload from cache and render
        if (selectedMember && String(selectedMember.id) === String(convId)) {
          const cache = messageCache.get(convId);
          if (cache && cache.msgs) renderMessages(cache.msgs);
        } else {
          // add unread marker
          try {
            const li = membersList ? membersList.querySelector(`[data-id="${convId}"]`) : null;
            if (li) li.classList.add("has-unread");
          } catch (e) {}
        }
      }
      // also listen for lastEvent changes
      if (e.key && e.key.indexOf("umi:lastEvent:") === 0) {
        // do nothing now - it's normal
      }
    } catch (err) {}
  });

  //
  // small improvement: allow other parts of app to push an event to update UI
  //
  window.addEventListener('umi:reload-conversation', async (ev) => {
    try {
      const convId = ev && ev.detail ? String(ev.detail || "") : "";
      if (!convId) return;
      if (selectedMember && String(selectedMember.id) === String(convId)) {
        await fetchChatMessages(convId);
      }
    } catch (e) {}
  });

  //
  // quick members search & render (unchanged)
  //
  async function searchMembers(qs){
    try {
      if (!qs || !qs.trim()){
        if (window.innerWidth >= 768) {
          try {
            const url = "/.netlify/functions/all-chatapi/search-members";
            const res = await fetch(url, { headers: Object.assign({"Content-Type":"application/json"}, authHeader()) });
            if (!res.ok) { toast("Load failed"); return; }
            const rows = await res.json().catch(()=>[]);
            renderMembers(Array.isArray(rows) ? rows : []);
          } catch (e) {
            console.error('searchMembers fetch', e);
          }
        } else renderMembers([]);
        return;
      }
      const url = "/.netlify/functions/all-chatapi/search-members?q=" + encodeURIComponent(qs.trim());
      const res = await fetch(url, { headers: Object.assign({"Content-Type":"application/json"}, authHeader()) });
      if (!res.ok){ toast("Search failed"); return; }
      const rows = await res.json().catch(()=>[]);
      renderMembers(Array.isArray(rows) ? rows : []);
    } catch (e) {
      console.error('searchMembers', e);
      renderMembers([]);
    }
  }

  function renderMembers(rows){
    try {
      if (!membersList) return;
      membersList.innerHTML = "";
      const myAI = { id: "my-ai", full_name: "My AI", avatar_url: "my-ai-avatar.png", online: true, last_message: "Ask me anything!" };
      renderFriendItem(myAI, true);
      const teamSnap = { id: "team-snap", full_name: "Team Umugwuanyioyi", avatar_url: "team-snap-avatar.png", online: true, last_message: "Contact the team — admin will reply" };
      renderFriendItem(teamSnap);
      (rows || []).forEach(r => renderFriendItem(normalize(r)));
    } catch (e) { console.error('renderMembers', e); }
  }

  function renderFriendItem(m, pinned = false){
    try {
      const tpl = id("friendTpl");
      if (!tpl || !membersList) return;
      const li = tpl.content.firstElementChild.cloneNode(true);
      li.dataset.id = m.id;
      if (m.full_name) li.dataset.fullName = m.full_name;
      if (m.phone) li.dataset.phone = m.phone;
      if (m.email) li.dataset.email = m.email;
      if (m.bio) li.dataset.bio = m.bio;
      if (m.avatar_url) li.dataset.avatar = m.avatar_url;
      const avatar = li.querySelector(".member-avatar");
      if (avatar) {
        avatar.src = m.avatar_url;
        avatar.dataset.profileId = m.id;
        avatar.addEventListener("click", (ev) => {
          ev.stopPropagation();
          const payload = Object.assign({}, m);
          const evt = new CustomEvent('umi:open-profile', { detail: payload, bubbles: true, cancelable: true });
          window.dispatchEvent(evt);
          populateProfile(payload);
        });
      }
      const nameEl = li.querySelector(".member-name");
      if (nameEl) nameEl.textContent = m.full_name;
      const subEl = li.querySelector(".member-sub");
      if (subEl) subEl.textContent = m.last_message;
      const presenceDot = li.querySelector(".presence-dot");
      if (presenceDot) presenceDot.className = "presence-dot " + (m.online ? "online" : "offline");
      if (pinned) li.classList.add("pinned");
      li.addEventListener("click", () => {
        try {
          qAll(".member-item.selected").forEach(x => x.classList.remove("selected"));
          li.classList.add("selected");
          const memberObj = Object.assign({}, m);
          try {
            if (!memberObj.phone && li.dataset.phone) memberObj.phone = li.dataset.phone;
            if (!memberObj.email && li.dataset.email) memberObj.email = li.dataset.email;
            if (!memberObj.bio && li.dataset.bio) memberObj.bio = li.dataset.bio;
            if (!memberObj.avatar_url && li.dataset.avatar) memberObj.avatar_url = li.dataset.avatar;
          } catch(e){}
          // remove unread indicator upon open
          try { li.classList.remove("has-unread"); } catch(e){}
          openChat(memberObj);
          if (window.innerWidth < 768 && membersPanel){
            membersPanel.classList.remove("open");
            blurIfInside(membersPanel);
            membersPanel.setAttribute("aria-hidden", "true");
            if (mobileSearchToggle) mobileSearchToggle.setAttribute("aria-expanded", "false");
            document.documentElement.classList.remove("no-scroll");
          }
        } catch (e) { console.error('friend click', e); }
      });
      membersList.appendChild(li);
    } catch (e) { console.error('renderFriendItem', e); }
  }

  function populateProfile(member){
    try {
      if (!member) return;
      const profileNameEl = id("profileName");
      const profilePhoneEl = id("profilePhone");
      const profileLocationEl = id("profileLocation");
      const profileAboutEl = id("profileAbout");
      const profileEmailEl = id("profileEmail");
      const profileAvatarEl = id("profileAvatar");

      if (profileNameEl) profileNameEl.textContent = member.full_name || member.name || member.username || "Profile";
      if (profilePhoneEl) profilePhoneEl.textContent = member.phone || (member.contact || "") || "";
      if (profileEmailEl) profileEmailEl.textContent = member.email || "";
      if (profileAboutEl) profileAboutEl.textContent = member.bio || member.description || "";
      if (profileAvatarEl) profileAvatarEl.src = member.avatar_url || member.photo_url || "default-avatar.png";

      if (peerName) peerName.textContent = member.full_name || member.name || "Conversation";
      if (peerAvatar) peerAvatar.src = member.avatar_url || "default-avatar.png";
      if (profileToggleAvatar) profileToggleAvatar.src = member.avatar_url || "default-avatar.png";
      if (peerPresence) peerPresence.className = "presence-dot " + (member.online ? "online" : "offline");
      if (peerMeta) peerMeta.textContent = member.online ? "Online" : (member.last_seen ? ("Last seen " + new Date(member.last_seen).toLocaleString()) : "Last seen —");
      if (profileLocationEl) {
        if (member.location_lat && member.location_lon) {
          profileLocationEl.innerHTML = `<a href="https://www.google.com/maps?q=${encodeURIComponent(member.location_lat+','+member.location_lon)}" target="_blank" rel="noopener noreferrer">Live: ${parseFloat(member.location_lat).toFixed(4)}, ${parseFloat(member.location_lon).toFixed(4)}</a>`;
        } else if (member.last_seen) {
          profileLocationEl.textContent = "Last seen: " + new Date(member.last_seen).toLocaleTimeString();
        } else {
          profileLocationEl.textContent = "Live location not available";
        }
      }
    } catch (e) { console.error('populateProfile', e); }
  }

  // end of file
})();
