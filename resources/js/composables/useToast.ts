import { reactive } from 'vue';

export interface ToastMessage {
    id: string;
    title: string;
    description?: string;
    type?: 'success' | 'error' | 'warning' | 'info';
    duration?: number;
}

const state = reactive<{
    toasts: ToastMessage[];
}>({
    toasts: [],
});

export function useToast() {
    const toast = (options: Omit<ToastMessage, 'id'>) => {
        const id = Math.random().toString(36).substring(2, 9);
        const newToast: ToastMessage = {
            id,
            duration: 4000,
            type: 'info',
            ...options,
        };

        state.toasts.push(newToast);

        if (newToast.duration && newToast.duration > 0) {
            setTimeout(() => {
                removeToast(id);
            }, newToast.duration);
        }

        return id;
    };

    const success = (title: string, description?: string) => {
        return toast({ title, description, type: 'success' });
    };

    const error = (title: string, description?: string) => {
        return toast({ title, description, type: 'error' });
    };

    const warning = (title: string, description?: string) => {
        return toast({ title, description, type: 'warning' });
    };

    const info = (title: string, description?: string) => {
        return toast({ title, description, type: 'info' });
    };

    const removeToast = (id: string) => {
        const index = state.toasts.findIndex((t) => t.id === id);
        if (index !== -1) {
            state.toasts.splice(index, 1);
        }
    };

    return {
        toasts: state.toasts,
        toast,
        success,
        error,
        warning,
        info,
        removeToast,
    };
}
