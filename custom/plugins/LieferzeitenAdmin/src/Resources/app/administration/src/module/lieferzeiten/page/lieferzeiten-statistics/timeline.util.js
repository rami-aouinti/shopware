const toNumber = (value) => {
    const parsed = Number(value);

    if (Number.isFinite(parsed)) {
        return parsed;
    }

    return 0;
};

const toTimelineDate = (point) => {
    return point.date || point.day || point.eventAt || point.createdAt || null;
};

const toTimelineValue = (point) => {
    return toNumber(point.count ?? point.value ?? point.total ?? point.orders ?? 0);
};

const toIsoDate = (dateString) => {
    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toISOString().slice(0, 10);
};

const matchesFilter = (point, selectedChannel, selectedDomain) => {
    if (selectedChannel && selectedChannel !== 'all' && point.channel && point.channel !== selectedChannel) {
        return false;
    }

    if (selectedDomain && point.domain && point.domain !== selectedDomain) {
        return false;
    }

    return true;
};

export function normalizeTimelinePoints(timeline, { selectedChannel = 'all', selectedDomain = null } = {}) {
    if (!Array.isArray(timeline)) {
        return [];
    }

    const groupedPoints = timeline.reduce((accumulator, point) => {
        if (!point || !matchesFilter(point, selectedChannel, selectedDomain)) {
            return accumulator;
        }

        const timelineDate = toTimelineDate(point);
        const isoDate = toIsoDate(timelineDate);

        if (!isoDate) {
            return accumulator;
        }

        accumulator[isoDate] = (accumulator[isoDate] || 0) + toTimelineValue(point);

        return accumulator;
    }, {});

    return Object.entries(groupedPoints)
        .sort(([left], [right]) => left.localeCompare(right))
        .map(([date, value]) => ({ date, value }));
}
