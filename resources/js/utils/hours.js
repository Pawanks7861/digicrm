export function formatHours(minutes) {
    const value = Math.max(0, Math.round(Number(minutes) || 0));
    const hours = Math.floor(value / 60);
    const remainder = value % 60;
    if (hours && remainder) {
        return `${hours}h ${remainder}m`;
    }
    if (hours) {
        return `${hours}h`;
    }

    return `${remainder}m`;
}
