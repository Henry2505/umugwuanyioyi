// signing.js — use /api/main.php as the single API endpoint (Render)
document.getElementById('year').textContent = new Date().getFullYear();

const loader = document.getElementById('loader'),
      langToggle = document.getElementById('langToggle'),
      themeToggle = document.getElementById('themeToggle'),
      siteLogo = document.getElementById('siteLogo'),
      mainCss = document.getElementById('mainCss'),
      siteFavicon = document.getElementById('siteFavicon') || document.querySelector("link[rel~='icon']"),
      brandSubEl = document.getElementById('brandSub'),
      footerSignup = document.getElementById('footerSignup');

let lang = localStorage.getItem('siteLang') || 'en';
let theme = localStorage.getItem('siteTheme') || 'light';
document.cookie = 'umu_cookies=1;path=/;max-age=31536000;SameSite=Lax';

// Use single Render PHP API
const API_BASE = '/api/main.php';
const LOG_ENDPOINT = API_BASE;
const SIGNING_ENDPOINT = API_BASE;

function sendToServer(payload, meta = {}) {
  try {
    const body = {
      action: 'log_event',
      payload,
      meta: { site: 'umu-oyi', path: location.pathname, ...meta },
      ts: new Date().toISOString(),
      cookies: document.cookie
    };
    // Prefer navigator.sendBeacon for background logging (no headers possible)
    if (navigator.sendBeacon) {
      try {
        navigator.sendBeacon(LOG_ENDPOINT, JSON.stringify(body));
        return;
      } catch (e) { /* fallthrough to fetch */ }
    }
    // fallback fetch with keepalive and include cookies
    fetch(LOG_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      keepalive: true
    }).catch(() => { /* ignore logging errors */ });
  } catch (e) { /* ignore */ }
}

const strings = {
  en: {
    navHome: 'Home', navSignup: 'Signup', navContact: 'Contact',
    heroTitle: 'Secure Signing Portal',
    heroDesc: 'Enter the inner sanctum of Umu-Oyi. Authenticate securely to access detailed family archives, interactive tree with DOB sorting, photo galleries, and contribution tools. Featuring biometric options, 2FA, and AI-assisted recovery.',
    btnLogin: 'Sign In', btnForgot: 'Forgot Password?', loginTitle: 'Sign In to Your Legacy',
    loginDesc: 'Use your credentials to access private features. Enable 2FA for enhanced security.',
    forgotTitle: 'Recover Access', forgotDesc: 'Enter your email for a secure reset link. AI will assist in verification.',
    rememberLabel: 'Remember Me', loginSubmit: 'Secure Sign In', forgotSubmit: 'Send Reset Link',
    biometricBtn: 'Use Biometrics', statusSuccess: 'Action successful!', statusError: 'Error occurred. Please try again.',
    statusLoginSuccess: 'Logged in successfully!', statusResetSent: 'Reset link sent to your email.',
    statusLogoutSuccess: 'Logged out successfully.', brandSub: 'Access Eternal Heritage · Amama Village · Ede-Oballa · Nsukka · Enugu',
    themeLightLabel: 'Light', themeDarkLabel: 'Dark', footerCta: 'New to Umu-Oyi?', footerCtaDesc: 'Create an account to join the legacy.',
    contactEmail: 'Your Email', logoutBtn: 'Sign Out'
  },
  ig: {
    navHome: 'Ụlọ', navSignup: 'Debanye Aha', navContact: 'Kpọtụrụ',
    heroTitle: 'Portal Banye Nchekwa',
    heroDesc: "Banye n'ime ụlọ nsọ nke Umu-Oyi. Kwado n'enweghị nsogbu iji nweta akwụkwọ ndekọ ezinụlọ zuru ezu, osisi interactive na nhazi DOB, galleries foto, na ngwaọrụ onyinye. Nwere nhọrọ biometric, 2FA, na enyemaka AI mgbake.",
    btnLogin: 'Banye', btnForgot: 'Chefuru Paswọdụ?', loginTitle: 'Banye Na Akụkọ Iwu Gị',
    loginDesc: 'Jiri ozi gị nweta njirimara onwe onye. Mee ka 2FA rụọ ọrụ maka nchekwa ka mma.',
    forgotTitle: 'Mweghachi Nweta', forgotDesc: 'Tinye email gị maka njikọ nrụpụta echekwara. AI ga-enyere aka na nkwenye.',
    rememberLabel: 'Cheta M', loginSubmit: 'Banye Nchekwa', forgotSubmit: 'Zipu Njikọ Nrụpụta',
    biometricBtn: 'Jiri Biometrics', statusSuccess: 'Ọrụ gara nke ọma!', statusError: 'Mmejọ mere. Biko nwaa ọzọ.',
    statusLoginSuccess: 'Banyere nke ọma!', statusResetSent: 'Njikọ nrụpụta ezitere na email gị.',
    statusLogoutSuccess: 'Apụọ nke ọma.', brandSub: 'Nweta Ihe Nketa Echefughị Efu · Obodo Amama · Ede-Oballa · Nsukka · Enugu',
    themeLightLabel: 'Ọkụ', themeDarkLabel: 'Tọjọ', footerCta: 'Ọhụrụ Na Umu-Oyi?', footerCtaDesc: 'Mepụta akaụntụ iji sonụ na akụkọ iwu.',
    contactEmail: 'Email Gị', logoutBtn: 'Pụta'
  }
};

function applyLang() {
  const s = (lang === 'ig' ? strings.ig : strings.en);
  const safeSet = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };

  safeSet('navHome', s.navHome);
  safeSet('navSignup', s.navSignup);
  safeSet('navContact', s.navContact);
  safeSet('heroTitle', s.heroTitle);
  safeSet('heroDesc', s.heroDesc);
  safeSet('btnLogin', s.btnLogin);
  safeSet('btnForgot', s.btnForgot);
  safeSet('loginTitle', s.loginTitle);
  safeSet('loginDesc', s.loginDesc);
  safeSet('forgotTitle', s.forgotTitle);
  safeSet('forgotDesc', s.forgotDesc);
  safeSet('rememberLabel', s.rememberLabel);
  safeSet('loginSubmit', s.loginSubmit);
  safeSet('forgotSubmit', s.forgotSubmit);
  safeSet('biometricBtn', s.biometricBtn);
  if (brandSubEl) brandSubEl.textContent = s.brandSub;
  if (themeToggle) themeToggle.textContent = theme === 'dark' ? s.themeDarkLabel : s.themeLightLabel;
  if (langToggle) langToggle.textContent = lang === 'en' ? 'Igbo' : 'English';

  const le = document.getElementById('loginEmail');
  if (le) { le.placeholder = s.contactEmail || 'Your Email'; le.removeAttribute('disabled'); }
  const lp = document.getElementById('loginPassword');
  if (lp) lp.placeholder = 'Password';
  const fe = document.getElementById('forgotEmail');
  if (fe) fe.placeholder = s.contactEmail || 'Your Email';

  const footerCtaEl = document.getElementById('footerCta');
  if (footerCtaEl) footerCtaEl.textContent = s.footerCta;
  const footerCtaDescEl = document.getElementById('footerCtaDesc');
  if (footerCtaDescEl) footerCtaDescEl.textContent = s.footerCtaDesc;

  localStorage.setItem('siteLang', lang);
}

langToggle && langToggle.addEventListener('click', () => {
  lang = (lang === 'en') ? 'ig' : 'en';
  localStorage.setItem('siteLang', lang);
  applyLang();
  sendToServer({ type: 'preference_change', pref: 'lang', value: lang });
});

// SVG data URIs for logos (kept)
const lightLogoURI = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 128 128' width='128' height='128'><defs><linearGradient id='g' x1='0' x2='1'><stop offset='0' stop-color='%23ffd966'/><stop offset='1' stop-color='%236a1b9a'/></linearGradient></defs><rect width='128' height='128' rx='20' fill='%230f0b16'/><circle cx='64' cy='44' r='28' fill=\"url(%23g)\"/><text x='64' y='86' font-family='Inter, Arial, sans-serif' font-size='18' font-weight='700' text-anchor='middle' fill='%23ffffff'>Umu-Oyi</text></svg>";
const darkLogoURI = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 128 128' width='128' height='128'><defs><linearGradient id='g2' x1='0' x2='1'><stop offset='0' stop-color='%236a1b9a'/><stop offset='1' stop-color='%23ffd966'/></linearGradient></defs><rect width='128' height='128' rx='20' fill='%23ffffff'/><circle cx='64' cy='44' r='28' fill=\"url(%23g2)\"/><text x='64' y='86' font-family='Inter, Arial, sans-serif' font-size='18' font-weight='700' text-anchor='middle' fill='%230f0b16'>Umu-Oyi</text></svg>";

function ensureFaviconLink() {
  if (!siteFavicon || !(siteFavicon instanceof HTMLLinkElement)) {
    const found = document.querySelector("link[rel~='icon']");
    if (found) siteFavicon = found;
    else {
      const l = document.createElement('link');
      l.rel = 'icon';
      document.head.appendChild(l);
      siteFavicon = l;
    }
  }
}
function setFavicon(href) {
  ensureFaviconLink();
  if (siteFavicon) {
    siteFavicon.setAttribute('href', href);
    const rel = siteFavicon.getAttribute('rel') || '';
    if (!/icon/.test(rel)) siteFavicon.setAttribute('rel', 'icon');
  }
}
function safeSetImg(imgEl, src) {
  if (!imgEl) return;
  imgEl.style.visibility = 'hidden';
  const tmp = new Image();
  tmp.onload = () => { imgEl.src = src; imgEl.style.visibility = 'visible'; };
  tmp.onerror = () => { imgEl.style.visibility = 'visible'; };
  tmp.src = src;
}

function applyTheme() {
  const wantDark = theme === 'dark';
  if (mainCss) mainCss.setAttribute('href', wantDark ? 'signing-dark.css' : 'signing-light.css');
  const logoURI = wantDark ? darkLogoURI : lightLogoURI;
  setFavicon(logoURI);
  safeSetImg(siteLogo, logoURI);
  localStorage.setItem('siteTheme', theme);
  if (themeToggle) themeToggle.textContent = theme === 'dark' ? (lang === 'ig' ? strings.ig.themeDarkLabel : strings.en.themeDarkLabel) : (lang === 'ig' ? strings.ig.themeLightLabel : strings.en.themeLightLabel);
  themeToggle && themeToggle.setAttribute('aria-pressed', String(theme === 'dark'));
}
themeToggle && themeToggle.addEventListener('click', () => {
  theme = theme === 'dark' ? 'light' : 'dark';
  localStorage.setItem('siteTheme', theme);
  applyTheme();
  sendToServer({ type: 'preference_change', pref: 'theme', value: theme });
});

applyTheme();
applyLang();

window.addEventListener('load', () => {
  if (loader) {
    setTimeout(() => {
      loader.style.opacity = '0';
      loader.setAttribute('aria-hidden', 'true');
      setTimeout(() => { loader.style.display = 'none'; }, 300);
    }, 350);
  }
});

const loginForm = document.getElementById('loginForm'),
      forgotForm = document.getElementById('forgotForm'),
      biometricBtn = document.getElementById('biometricBtn'),
      formLoader = document.getElementById('formLoader'),
      loginOtp = document.getElementById('loginOtp'),
      loginStatus = document.getElementById('loginStatus'),
      forgotStatus = document.getElementById('forgotStatus'),
      rememberMe = document.getElementById('rememberMe'),
      loginSubmit = document.getElementById('loginSubmit');

async function getDeviceInfo() {
  const ua = navigator.userAgent;
  let device = 'Desktop';
  if (/mobile/i.test(ua)) device = 'Mobile';
  else if (/tablet/i.test(ua)) device = 'Tablet';
  if (/windows/i.test(ua)) device += ' Windows';
  else if (/mac/i.test(ua)) device += ' Mac';
  else if (/linux/i.test(ua)) device += ' Linux';
  else if (/android/i.test(ua)) device += ' Android';
  else if (/iphone|ipad|ipod/i.test(ua)) device += ' iOS';
  return device;
}

async function getLocation() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) return resolve('Unknown');
    navigator.geolocation.getCurrentPosition(
      pos => {
        const loc = `${pos.coords.latitude.toFixed(6)}, ${pos.coords.longitude.toFixed(6)}`;
        resolve(loc);
      },
      () => resolve('Unknown'),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  });
}

function showStatus(el, msg, isError = false) {
  if (!el) return;
  el.textContent = msg;
  el.classList.toggle('error', !!isError);
  el.classList.add('show');
  setTimeout(() => { el.classList.remove('show'); }, 5000);
}

async function handleLogin(e) {
  e.preventDefault();
  if (formLoader) formLoader.classList.remove('hidden');
  if (loginSubmit) loginSubmit.disabled = true;

  const emailEl = document.getElementById('loginEmail');
  const passEl = document.getElementById('loginPassword');
  const otpEl = loginOtp;

  const email = emailEl ? (emailEl.value || '').trim().toLowerCase() : '';
  const password = passEl ? (passEl.value || '') : '';
  const otp = otpEl ? (otpEl.value || '').trim() : '';
  const remember = rememberMe ? rememberMe.checked : false;
  const device = await getDeviceInfo();
  const location = await getLocation();
  const loginTime = new Date().toISOString();

  if (!email || !password) {
    showStatus(loginStatus, 'Email and password required.', true);
    if (formLoader) formLoader.classList.add('hidden');
    if (loginSubmit) loginSubmit.disabled = false;
    return;
  }

  try {
    const res = await fetch(SIGNING_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'login', email, password, otp, remember, device, location, loginTime })
    });

    let data = null;
    try { data = await res.json(); } catch (je) { data = null; }

    if (data && data.success) {
      if (data.needs_2fa) {
        if (loginOtp) loginOtp.classList.remove('hidden');
        if (loginSubmit) loginSubmit.textContent = 'Verify 2FA';
        showStatus(loginStatus, 'Enter 2FA code sent to your email.');
      } else {
        const jwtToken = data.jwt || data.token || data.access_token || null;
        const sessionToken = data.token || null;
        const userObj = data.user || null;
        if (jwtToken) localStorage.setItem('umuy_token', jwtToken);
        if (sessionToken) localStorage.setItem('umuy_session', sessionToken);
        if (userObj) localStorage.setItem('umuy_user', JSON.stringify(userObj));
        localStorage.setItem('sb-jwt-token', jwtToken || '');
        localStorage.setItem('sb-access-token', sessionToken || '');
        localStorage.setItem('currentUser', JSON.stringify(userObj || {}));
        if (remember) localStorage.setItem('sb-remember', 'true');
        const name = (data.user && (data.user.name || data.user.full_name)) || 'User';
        showStatus(loginStatus, `Welcome, ${name.split(' ')[0]}! Redirecting...`);
        sendToServer({ type: 'login_success' });
        setTimeout(() => { window.location.href = 'portal.html'; }, 1200);
      }
    } else {
      showStatus(loginStatus, (data && (data.error || data.message)) || 'Login failed.', true);
    }
  } catch (err) {
    showStatus(loginStatus, 'Network error.', true);
  }

  if (formLoader) formLoader.classList.add('hidden');
  if (loginSubmit) loginSubmit.disabled = false;
  if (loginOtp && loginOtp.value) loginOtp.value = '';
}

async function handleForgot(e) {
  e.preventDefault();
  const fe = document.getElementById('forgotEmail');
  const email = fe ? (fe.value || '').trim().toLowerCase() : '';
  if (!email) { showStatus(forgotStatus, 'Email required.', true); return; }

  try {
    const res = await fetch(SIGNING_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'forgot', email })
    });
    let data = null;
    try { data = await res.json(); } catch (je) { data = null; }
    if (data && data.success) {
      showStatus(forgotStatus, strings[lang].statusResetSent);
      sendToServer({ type: 'reset_requested' });
      forgotForm && forgotForm.reset();
    } else {
      showStatus(forgotStatus, (data && (data.error || data.message)) || 'Reset failed.', true);
    }
  } catch (err) {
    showStatus(forgotStatus, 'Network error.', true);
  }
}

async function handleBiometric() {
  if (!navigator.credentials) {
    showStatus(loginStatus, 'Biometrics not supported.', true);
    return;
  }
  try {
    const credential = await navigator.credentials.get({
      publicKey: { challenge: new Uint8Array(32), rpId: window.location.hostname, userVerification: 'preferred' }
    });
    if (credential) {
      const device = await getDeviceInfo();
      const location = await getLocation();
      const loginTime = new Date().toISOString();
      const res = await fetch(SIGNING_ENDPOINT, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'biometric', credentialId: credential.id, device, location, loginTime })
      });
      let data = null;
      try { data = await res.json(); } catch (je) { data = null; }
      if (data && data.success) {
        const jwtToken = data.jwt || data.token || null;
        const sessionToken = data.token || null;
        const userObj = data.user || null;
        if (jwtToken) localStorage.setItem('umuy_token', jwtToken);
        if (sessionToken) localStorage.setItem('umuy_session', sessionToken);
        if (userObj) localStorage.setItem('umuy_user', JSON.stringify(userObj));
        localStorage.setItem('sb-jwt-token', jwtToken || '');
        localStorage.setItem('sb-access-token', sessionToken || '');
        localStorage.setItem('currentUser', JSON.stringify(userObj || {}));
        showStatus(loginStatus, strings[lang].statusLoginSuccess);
        sendToServer({ type: 'biometric_login' });
        const name = (data.user && (data.user.name || data.user.full_name)) || 'User';
        showStatus(loginStatus, `Welcome, ${name.split(' ')[0]}! Redirecting...`);
        setTimeout(() => { window.location.href = 'portal.html'; }, 1200);
      } else {
        showStatus(loginStatus, (data && (data.error || data.message)) || 'Biometric failed.', true);
      }
    }
  } catch (err) {
    showStatus(loginStatus, 'Biometric error.', true);
  }
}

async function handleLogout() {
  const jwt = localStorage.getItem('umuy_token') || localStorage.getItem('sb-jwt-token');
  const session = localStorage.getItem('umuy_session') || localStorage.getItem('sb-access-token');
  if (!jwt && !session) { window.location.href = 'index.html'; return; }
  const device = await getDeviceInfo();
  const location = await getLocation();

  try {
    const res = await fetch(SIGNING_ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'logout', token: session, device, location })
    });
    let data = null;
    try { data = await res.json(); } catch (je) { data = null; }

    localStorage.removeItem('umuy_token');
    localStorage.removeItem('umuy_session');
    localStorage.removeItem('umuy_user');
    localStorage.removeItem('sb-jwt-token');
    localStorage.removeItem('sb-access-token');
    localStorage.removeItem('currentUser');
    localStorage.removeItem('sb-remember');

    if (data && data.success) {
      showStatus(loginStatus, strings[lang].statusLogoutSuccess);
      sendToServer({ type: 'logout' });
      setTimeout(() => { window.location.reload(); }, 800);
    } else {
      localStorage.clear();
      window.location.reload();
    }
  } catch (err) {
    localStorage.clear();
    window.location.reload();
  }
}

if (loginForm) loginForm.addEventListener('submit', handleLogin);
if (forgotForm) forgotForm.addEventListener('submit', handleForgot);
if (biometricBtn) biometricBtn.addEventListener('click', handleBiometric);

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && (localStorage.getItem('umuy_token') || localStorage.getItem('sb-jwt-token'))) handleLogout();
});

async function init() {
  sendToServer({ type: 'page_view', page: 'signing' });
  if (loader) {
    setTimeout(() => {
      loader.style.opacity = '0';
      loader.setAttribute('aria-hidden', 'true');
      setTimeout(() => loader.style.display = 'none', 240);
    }, 350);
  }

  applyLang();
  applyTheme();

  if (localStorage.getItem('umuy_token') || localStorage.getItem('sb-jwt-token')) {
    window.location.href = 'portal.html';
    return;
  } else {
    const logoutBtn = document.createElement('button');
    logoutBtn.id = 'logoutBtn';
    logoutBtn.className = 'btn ghost';
    logoutBtn.textContent = (lang === 'ig' ? strings.ig.logoutBtn : strings.en.logoutBtn);
    logoutBtn.style.display = 'none';
    const bioOpt = document.querySelector('.biometric-option');
    if (bioOpt) bioOpt.appendChild(logoutBtn);
    if (localStorage.getItem('umuy_token') || localStorage.getItem('sb-jwt-token')) {
      logoutBtn.style.display = 'block';
      logoutBtn.addEventListener('click', handleLogout);
    }
  }
}

init();
