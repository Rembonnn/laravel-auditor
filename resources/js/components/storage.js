// localStorage can throw (private mode, blocked storage): never let it break the UI.
export const storage = {
    get(key, fallback = null) {
        try {
            const value = window.localStorage.getItem('auditor.' + key)
            return value === null ? fallback : value
        } catch (e) {
            return fallback
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem('auditor.' + key, value)
        } catch (e) {
            // ignore
        }
    },
    getJson(key, fallback) {
        try {
            return JSON.parse(this.get(key, 'null')) ?? fallback
        } catch (e) {
            return fallback
        }
    },
    setJson(key, value) {
        this.set(key, JSON.stringify(value))
    },
}
