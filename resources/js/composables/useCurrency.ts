export const formatCurrency = (amount: number, currency: string = 'KES'): string => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

export const formatNumber = (num: number): string => {
    return new Intl.NumberFormat('en-KE').format(num);
};

export const formatDateTime = (dateStr: string | Date): string => {
    const d = typeof dateStr === 'string' ? new Date(dateStr) : dateStr;
    return new Intl.DateTimeFormat('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(d);
};

export const formatDateOnly = (dateStr: string | Date): string => {
    const d = typeof dateStr === 'string' ? new Date(dateStr) : dateStr;
    return new Intl.DateTimeFormat('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(d);
};

export const generateMpesaCode = (): string => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
    let code = 'QK';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return code;
};
