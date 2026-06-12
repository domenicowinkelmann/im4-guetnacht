/**
 * api.js — lightweight fetch wrapper for GuetNacht backend.
 * All API communication goes through here.
 */

const API_BASE = '/backend/api';

// ── Token storage ─────────────────────────────────────────────────────────────
const Auth = {
    getToken: ()          => localStorage.getItem('sw_token'),
    setToken: (token)     => localStorage.setItem('sw_token', token),
    getUser:  ()          => JSON.parse(localStorage.getItem('sw_user') || 'null'),
    setUser:  (user)      => localStorage.setItem('sw_user', JSON.stringify(user)),
    clear:    ()          => { localStorage.removeItem('sw_token'); localStorage.removeItem('sw_user'); },
    isLoggedIn: ()        => !!localStorage.getItem('sw_token'),
};

// ── Core fetch helper ─────────────────────────────────────────────────────────
async function apiFetch(path, { method = 'GET', body = null, auth = true } = {}) {
    const headers = { 'Content-Type': 'application/json' };
    if (auth) {
        const token = Auth.getToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;
    }

    const options = { method, headers };
    if (body !== null) options.body = JSON.stringify(body);

    const res  = await fetch(`${API_BASE}/${path}`, options);
    const data = await res.json();

    if (!data.success) {
        throw new ApiError(data.error || 'Unbekannter Fehler', res.status);
    }

    return data;
}

class ApiError extends Error {
    constructor(message, status) {
        super(message);
        this.status = status;
    }
}

// ── Auth endpoints ────────────────────────────────────────────────────────────
const AuthApi = {
    async register(name, email, password) {
        const data = await apiFetch('auth/register.php', {
            method: 'POST',
            body: { name, email, password },
            auth: false,
        });
        Auth.setToken(data.token);
        Auth.setUser(data.user);
        return data;
    },

    async login(email, password) {
        const data = await apiFetch('auth/login.php', {
            method: 'POST',
            body: { email, password },
            auth: false,
        });
        Auth.setToken(data.token);
        Auth.setUser(data.user);
        return data;
    },

    async logout() {
        try {
            await apiFetch('auth/logout.php', { method: 'POST' });
        } finally {
            Auth.clear();
        }
    },
};

// ── Data endpoints ────────────────────────────────────────────────────────────
const DataApi = {
    dashboard:     () => apiFetch('dashboard.php'),
    live:          () => apiFetch('live.php'),
    notifications: () => apiFetch('notifications.php'),
    markAllRead:   () => apiFetch('notifications.php', { method: 'POST' }),
    getBaby:       () => apiFetch('baby.php'),
    updateBaby:    (name, birth_date) => apiFetch('baby.php', { method: 'POST', body: { name, birth_date } }),
};