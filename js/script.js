/**
 * script.js — GuetNacht main app logic.
 * Depends on api.js being loaded first.
 */

// ── App State ─────────────────────────────────────────────────────────────────
const state = {
    currentPage: 'login',
    onboardingStep: 0,
    settings: {
        notifications: true,
        sounds: false,
        nightMode: JSON.parse(localStorage.getItem('sw_nightmode') ?? 'false'),
    },
};

// ── Night Mode ────────────────────────────────────────────────────────────────
function applyNightMode(enabled) {
    document.documentElement.classList.toggle('night', enabled);
    localStorage.setItem('sw_nightmode', JSON.stringify(enabled));
    state.settings.nightMode = enabled;

    // Recolor the inline SVG chart gradient stops
    const color = enabled ? '#6ac1b9' : '#0D9488';
    document.querySelectorAll('#activity-gradient stop').forEach(stop => {
        stop.setAttribute('stop-color', color);
    });
    const chartLine = document.getElementById('chart-line');
    if (chartLine) chartLine.setAttribute('stroke', color);
}

// ── Navigation ────────────────────────────────────────────────────────────────
function navigateTo(pageName) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const target = document.getElementById(`${pageName}-page`);
    if (!target) return;

    target.classList.add('active');
    state.currentPage = pageName;

    // Re-trigger animations
    target.querySelectorAll('.fade-in, .slide-up, .slide-left, .slide-right').forEach(el => {
        el.style.animation = 'none';
        requestAnimationFrame(() => { el.style.animation = ''; });
    });

    updateBottomNav(pageName);

    if (pageName === 'dashboard')     loadDashboard();
    if (pageName === 'live')          loadLive();
    if (pageName === 'notifications') loadNotifications();
    if (pageName === 'baby')          loadBabyProfile();
    if (pageName === 'baby-setup')    {} // no preload needed, form is empty intentionally
}

function updateBottomNav(pageName) {
    document.querySelectorAll('.nav-item').forEach(btn => {
        const onclick = btn.getAttribute('onclick') || '';
        btn.classList.toggle('active', onclick.includes(`'${pageName}'`));
        btn.classList.toggle('text-foreground/40', !onclick.includes(`'${pageName}'`));
    });
}

// ── Error UI ──────────────────────────────────────────────────────────────────
function showFormError(el, message) {
    el.textContent = message;
    el.classList.remove('hidden');
}

function hideFormError(el) {
    el.textContent = '';
    el.classList.add('hidden');
}

function setButtonLoading(btn, loading, originalText) {
    btn.disabled = loading;
    btn.textContent = loading ? 'Bitte warten…' : originalText;
}

// ── Auth: Register ────────────────────────────────────────────────────────────
document.getElementById('register-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form     = e.target;
    const errorEl  = document.getElementById('register-error');
    const btn      = form.querySelector('button[type="submit"]');
    const name     = form.querySelector('input[name="name"]').value.trim();
    const email    = form.querySelector('input[name="email"]').value.trim();
    const password = form.querySelector('input[name="password"]').value;
    const confirm  = form.querySelector('input[name="confirm"]').value;

    hideFormError(errorEl);

    if (password.length < 8) {
        showFormError(errorEl, 'Passwort muss mindestens 8 Zeichen lang sein.');
        return;
    }
    if (!/[a-z]/.test(password)) {
        showFormError(errorEl, 'Passwort muss mindestens einen Kleinbuchstaben enthalten.');
        return;
    }
    if (!/[A-Z]/.test(password)) {
        showFormError(errorEl, 'Passwort muss mindestens einen Grossbuchstaben enthalten.');
        return;
    }
    if (!/[0-9]/.test(password)) {
        showFormError(errorEl, 'Passwort muss mindestens eine Zahl enthalten.');
        return;
    }
    if (!/[^a-zA-Z0-9]/.test(password)) {
        showFormError(errorEl, 'Passwort muss mindestens ein Sonderzeichen enthalten (z.B. !@#$).');
        return;
    }
    if (password !== confirm) {
        showFormError(errorEl, 'Passwörter stimmen nicht überein.');
        return;
    }

    setButtonLoading(btn, true, 'Registrieren');
    try {
        await AuthApi.register(name, email, password);
        navigateTo('onboarding');
    } catch (err) {
        showFormError(errorEl, err.message);
    } finally {
        setButtonLoading(btn, false, 'Registrieren');
    }
});

// ── Auth: Login ───────────────────────────────────────────────────────────────
document.getElementById('login-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form    = e.target;
    const errorEl = document.getElementById('login-error');
    const btn     = form.querySelector('button[type="submit"]');
    const email   = form.querySelector('input[type="email"]').value.trim();
    const pass    = form.querySelector('input[type="password"]').value;

    hideFormError(errorEl);
    setButtonLoading(btn, true, 'Anmelden');
    try {
        await AuthApi.login(email, pass);
        navigateTo('dashboard');
    } catch (err) {
        showFormError(errorEl, err.message);
    } finally {
        setButtonLoading(btn, false, 'Anmelden');
    }
});

// ── Onboarding ────────────────────────────────────────────────────────────────
const onboardingSteps = document.querySelectorAll('.onboarding-step');
const progressDots    = document.querySelectorAll('.progress-dot');
const nextBtn         = document.getElementById('next-onboarding');
const skipBtn         = document.getElementById('skip-onboarding');

function updateOnboardingStep() {
    onboardingSteps.forEach((s, i) => s.classList.toggle('active', i === state.onboardingStep));
    progressDots.forEach((d, i)    => d.classList.toggle('active', i === state.onboardingStep));
    nextBtn.textContent = state.onboardingStep === onboardingSteps.length - 1 ? "Los geht's" : 'Weiter';
}

nextBtn?.addEventListener('click', () => {
    if (state.onboardingStep < onboardingSteps.length - 1) {
        state.onboardingStep++;
        updateOnboardingStep();
    } else {
        state.onboardingStep = 0;
        navigateAfterOnboarding();
    }
});

skipBtn?.addEventListener('click', () => {
    state.onboardingStep = 0;
    navigateAfterOnboarding();
});

async function navigateAfterOnboarding() {
    try {
        const data = await DataApi.getBaby();
        if (data.baby && data.baby.name) {
            navigateTo('dashboard');
        } else {
            navigateTo('baby-setup');
        }
    } catch (err) {
        // If check fails, still go to baby setup to be safe
        navigateTo('baby-setup');
    }
}

// ── Dashboard ─────────────────────────────────────────────────────────────────
async function loadDashboard() {
    try {
        const data = await DataApi.dashboard();

        if (data.baby) {
            document.querySelectorAll('.js-baby-name').forEach(el => {
                el.textContent = data.baby.name;
            });
        }

        const statusText   = document.getElementById('sleep-status-text');
        const sleepTime    = document.getElementById('sleep-time');
        const sleepQuality = document.getElementById('sleep-quality');

        if (statusText) statusText.textContent = data.is_sleeping
            ? `${data.baby?.name ?? 'Dein Baby'} ruht friedlich`
            : `${data.baby?.name ?? 'Dein Baby'} ist wach`;

        if (sleepTime) {
            const h = data.current_sleep.hours;
            const m = data.current_sleep.minutes;
            sleepTime.textContent = data.is_sleeping ? `${h}h ${String(m).padStart(2, '0')}m` : '—';
        }

        if (sleepQuality) sleepQuality.textContent = data.is_sleeping
            ? `Schlafqualität: ${data.sleep_quality} ✓`
            : '';

        document.getElementById('stat-movements')   && (document.getElementById('stat-movements').textContent   = data.stats.movement_count);
        document.getElementById('stat-total-sleep') && (document.getElementById('stat-total-sleep').textContent = data.stats.total_sleep);
        document.getElementById('stat-wakeups')     && (document.getElementById('stat-wakeups').textContent     = data.stats.wake_up_count);

        const badge = document.getElementById('notif-badge');
        if (badge) badge.classList.toggle('hidden', data.unread_notifications === 0);

        drawActivityChart(data.chart_data);
    } catch (err) {
        console.error('Dashboard error:', err);
        if (err.status === 401) { Auth.clear(); navigateTo('login'); }
    }
}

// ── Activity Chart ────────────────────────────────────────────────────────────
function drawActivityChart(dataArray) {
    const max = Math.max(...dataArray, 1);
    const n   = dataArray.length;

    const points = dataArray.map((val, i) => ({
        x: (i / (n - 1)) * 100,
        y: 100 - (val / max) * 80,
    }));

    let pathD = `M ${points[0].x} ${points[0].y}`;
    for (let i = 1; i < points.length; i++) {
        const prev = points[i - 1];
        const curr = points[i];
        const cpX  = (prev.x + curr.x) / 2;
        pathD += ` C ${cpX} ${prev.y}, ${cpX} ${curr.y}, ${curr.x} ${curr.y}`;
    }

    const last  = points[points.length - 1];
    const areaD = `${pathD} L ${last.x} 100 L 0 100 Z`;

    document.getElementById('chart-line')?.setAttribute('d', pathD);
    document.getElementById('chart-area')?.setAttribute('d', areaD);
}

// ── Live ──────────────────────────────────────────────────────────────────────
async function loadLive() {
    try {
        const data = await DataApi.live();

        // Baby name in header
        document.querySelectorAll('.js-baby-name').forEach(el => {
            el.textContent = `${data.baby_name ?? 'Baby'} wird überwacht`;
        });

        document.getElementById('live-status-label')  && (document.getElementById('live-status-label').textContent  = data.status_label);
        document.getElementById('live-status-detail') && (document.getElementById('live-status-detail').textContent = data.status_detail);

        const battEl   = document.getElementById('live-battery');
        const signalEl = document.getElementById('live-signal');
        if (battEl)   battEl.textContent   = data.device ? `${data.device.battery_percent}%` : '—';
        if (signalEl) signalEl.textContent = data.device ? data.device.signal_strength : '—';

        const eventsList = document.getElementById('live-events');
        if (eventsList) {
            if (data.events.length > 0) {
                eventsList.innerHTML = data.events.map(ev => `
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-foreground opacity-70">${ev.time}</span>
                        <span class="text-sm text-foreground">${ev.label}</span>
                    </div>
                `).join('');
            } else {
                eventsList.innerHTML = '<p class="text-sm text-foreground opacity-50">Noch keine Aktivitäten</p>';
            }
        }
    } catch (err) {
        console.error('Live error:', err);
        if (err.status === 401) { Auth.clear(); navigateTo('login'); }
    }
}

// ── Notifications ─────────────────────────────────────────────────────────────
const ICON_SVG = {
    sleep: `<svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 3a6 6 0 0 0 9 5.2M12 21v-8.5"/><path d="M12 12.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/></svg>`,
    wake:  `<svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>`,
    sun:   `<svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>`,
    alert: `<svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>`,
};

async function loadNotifications() {
    try {
        const data = await DataApi.notifications();
        const list = document.getElementById('notifications-list');
        if (!list) return;

        if (data.notifications.length === 0) {
            list.innerHTML = '<p class="text-center text-sm text-foreground opacity-50 py-8">Keine Benachrichtigungen</p>';
            return;
        }

        list.innerHTML = data.notifications.map((n, i) => `
            <div class="bg-white rounded-2xl p-5 flex gap-4 shadow-sm cursor-pointer hover:shadow-md transition-shadow slide-up" style="animation-delay: ${i * 0.07}s">
                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    ${ICON_SVG[n.icon_type] ?? ICON_SVG.sleep}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-1 gap-2">
                        <h4 class="text-foreground font-medium ${n.is_read ? 'opacity-70' : ''}">${n.title}</h4>
                        <span class="text-xs text-foreground opacity-50 flex-shrink-0">${n.time_ago}</span>
                    </div>
                    <p class="text-sm text-foreground opacity-70">${n.body}</p>
                </div>
            </div>
        `).join('');

        setTimeout(() => DataApi.markAllRead().catch(() => {}), 2000);
    } catch (err) {
        console.error('Notifications error:', err);
    }
}

// ── Baby Setup (forced on first login) ───────────────────────────────────────
document.getElementById('baby-setup-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl  = document.getElementById('baby-setup-error');
    const btn      = e.target.querySelector('button[type="submit"]');
    const name     = document.getElementById('baby-setup-name').value.trim();
    const birthDate = document.getElementById('baby-setup-birthdate').value;

    hideFormError(errorEl);

    if (!name) {
        showFormError(errorEl, 'Bitte gib einen Namen ein.');
        return;
    }
    if (!birthDate) {
        showFormError(errorEl, 'Bitte gib das Geburtsdatum ein.');
        return;
    }

    setButtonLoading(btn, true, 'Weiter');

    try {
        await DataApi.updateBaby(name, birthDate);
        navigateTo('dashboard');
    } catch (err) {
        showFormError(errorEl, err.message);
    } finally {
        setButtonLoading(btn, false, 'Weiter');
    }
});

// ── Baby Profile ──────────────────────────────────────────────────────────────
async function loadBabyProfile() {
    try {
        const data = await DataApi.getBaby();
        if (data.baby) {
            document.getElementById('baby-name-input').value      = data.baby.name ?? '';
            document.getElementById('baby-birthdate-input').value = data.baby.birth_date ?? '';
        }
    } catch (err) {
        console.error('Baby profile load error:', err);
    }
}

document.getElementById('baby-profile-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl   = document.getElementById('baby-profile-error');
    const successEl = document.getElementById('baby-profile-success');
    const btn       = e.target.querySelector('button[type="submit"]');
    const name      = document.getElementById('baby-name-input').value.trim();
    const birthDate = document.getElementById('baby-birthdate-input').value;

    hideFormError(errorEl);
    successEl.classList.add('hidden');
    setButtonLoading(btn, true, 'Speichern');

    try {
        await DataApi.updateBaby(name, birthDate);
        successEl.classList.remove('hidden');
        document.querySelectorAll('.js-baby-name').forEach(el => el.textContent = name);
    } catch (err) {
        showFormError(errorEl, err.message);
    } finally {
        setButtonLoading(btn, false, 'Speichern');
    }
});

// ── Settings ──────────────────────────────────────────────────────────────────
document.getElementById('notifications-toggle')?.addEventListener('change', e => {
    state.settings.notifications = e.target.checked;
});

document.getElementById('sounds-toggle')?.addEventListener('change', e => {
    state.settings.sounds = e.target.checked;
});

document.getElementById('nightmode-toggle')?.addEventListener('change', e => {
    applyNightMode(e.target.checked);
});

document.getElementById('logout-btn')?.addEventListener('click', async () => {
    await AuthApi.logout();
    navigateTo('login');
});

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Apply persisted night mode immediately on load
    applyNightMode(state.settings.nightMode);

    // Sync toggles
    const notifToggle = document.getElementById('notifications-toggle');
    const soundToggle = document.getElementById('sounds-toggle');
    const nightToggle = document.getElementById('nightmode-toggle');
    if (notifToggle) notifToggle.checked = state.settings.notifications;
    if (soundToggle) soundToggle.checked = state.settings.sounds;
    if (nightToggle) nightToggle.checked = state.settings.nightMode;

    // Route based on auth state
    if (Auth.isLoggedIn()) {
        navigateTo('dashboard');
    } else {
        navigateTo('login');
    }
});
