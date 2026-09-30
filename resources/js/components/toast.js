let nextId = 1

export default function toasts() {
    return {
        items: [],

        push(message, tone = 'neutral', timeout = 3000) {
            const id = nextId++
            this.items.push({ id, message, tone })
            if (timeout > 0) {
                setTimeout(() => this.dismiss(id), timeout)
            }
        },

        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id)
        },
    }
}
