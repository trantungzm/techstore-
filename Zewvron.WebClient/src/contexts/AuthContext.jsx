import React, { createContext, useContext, useState, useEffect, useRef } from 'react';
import { ADMIN_PANEL_ROLES } from '../constants/roles';
import { authApi } from '../services/api';

const AuthContext = createContext(null);

// Refresh this long before the access token actually expires, so a slow request never races
// an expiry mid-flight.
const REFRESH_BUFFER_MS = 5 * 60 * 1000;

const decodeTokenExpiryMs = (token) => {
    try {
        const base64Url = String(token).split('.')[1] || '';
        const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
        const payload = JSON.parse(atob(padded));
        return payload.exp ? payload.exp * 1000 : null;
    } catch {
        return null;
    }
};

const isTokenExpired = (token) => {
    const expiryMs = decodeTokenExpiryMs(token);
    return expiryMs ? expiryMs <= Date.now() : false;
};

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const refreshTimerRef = useRef(null);

    const clearRefreshTimer = () => {
        if (refreshTimerRef.current) {
            clearTimeout(refreshTimerRef.current);
            refreshTimerRef.current = null;
        }
    };

    // Schedule a proactive refresh ~5 minutes before the access token expires, instead of
    // waiting for a request to fail with 401.
    const scheduleRefresh = (token) => {
        clearRefreshTimer();
        const expiryMs = decodeTokenExpiryMs(token);
        if (!expiryMs) return;
        const delay = Math.max(expiryMs - Date.now() - REFRESH_BUFFER_MS, 5000);
        refreshTimerRef.current = setTimeout(() => {
            refreshAccessToken();
        }, delay);
    };

    const clearSession = () => {
        clearRefreshTimer();
        localStorage.removeItem('token');
        localStorage.removeItem('refreshToken');
        localStorage.removeItem('user');
        setUser(null);
    };

    const applySession = (userData) => {
        localStorage.setItem('token', userData.token);
        if (userData.refreshToken) {
            localStorage.setItem('refreshToken', userData.refreshToken);
        }
        localStorage.setItem('user', JSON.stringify(userData));
        setUser(userData);
        scheduleRefresh(userData.token);
    };

    const refreshAccessToken = async () => {
        const storedRefreshToken = localStorage.getItem('refreshToken');
        if (!storedRefreshToken) {
            clearSession();
            return;
        }
        try {
            const response = await authApi.refresh(storedRefreshToken);
            const userData = response.data;
            if (!userData.token || isTokenExpired(userData.token)) {
                clearSession();
                return;
            }
            applySession(userData);
        } catch {
            // Refresh token invalid/expired/revoked — user must log in again.
            clearSession();
        }
    };

    useEffect(() => {
        try {
            const storedUser = localStorage.getItem('user');
            const token = localStorage.getItem('token');
            if (storedUser && token) {
                if (isTokenExpired(token)) {
                    clearSession();
                } else {
                    setUser(JSON.parse(storedUser));
                    scheduleRefresh(token);
                }
            }
        } catch {
            clearSession();
        }
        setLoading(false);
        return clearRefreshTimer;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const login = async (username, password) => {
        try {
            const response = await authApi.login(username, password);
            const userData = response.data;
            if (!userData.token || isTokenExpired(userData.token)) {
                return { success: false, message: 'Login failed' };
            }

            applySession(userData);

            return { success: true, user: userData };
        } catch (error) {
            const data = error.response?.data;
            const message = data?.message || data?.detail || data?.title || 'Login failed';
            return { success: false, message };
        }
    };

    const register = async (data) => {
        try {
            const response = await authApi.register(data);
            return { success: true, data: response.data };
        } catch (error) {
            const data = error.response?.data;
            const message = data?.message || data?.detail || data?.title || 'Registration failed';
            return { success: false, message };
        }
    };

    const logout = () => {
        const storedRefreshToken = localStorage.getItem('refreshToken');
        if (storedRefreshToken) {
            // Best-effort server-side revoke; don't block logout on the network call.
            authApi.logout(storedRefreshToken).catch(() => {});
        }
        clearSession();
    };

    const isAdmin = () => {
        return user?.role === 'Admin';
    };

    const hasRole = (roles = []) => {
        if (!user?.role) return false;
        if (!Array.isArray(roles) || roles.length === 0) return true;
        return roles.includes(user.role);
    };

    // Roles allowed into the admin panel (must match the /admin ProtectedRoute guards).
    const canAccessAdminPanel = () => hasRole(ADMIN_PANEL_ROLES);

    const value = {
        user,
        login,
        register,
        logout,
        isAdmin,
        hasRole,
        canAccessAdminPanel,
        isAuthenticated: !!user,
        loading,
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
};

export default AuthContext;