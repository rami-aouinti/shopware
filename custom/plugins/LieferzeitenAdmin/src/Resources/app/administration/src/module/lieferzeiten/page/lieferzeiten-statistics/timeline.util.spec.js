import { normalizeTimelinePoints } from './timeline.util';

describe('lieferzeiten/page/lieferzeiten-statistics/timeline util', () => {
    it('groups points by day and sorts them chronologically', () => {
        const timeline = [
            { date: '2025-01-03T09:00:00+00:00', count: 2 },
            { date: '2025-01-01T11:00:00+00:00', count: 1 },
            { date: '2025-01-03T13:00:00+00:00', count: 4 },
        ];

        const result = normalizeTimelinePoints(timeline);

        expect(result).toEqual([
            { date: '2025-01-01', value: 1 },
            { date: '2025-01-03', value: 6 },
        ]);
    });

    it('keeps table/chart filter consistency for channel and domain', () => {
        const timeline = [
            { date: '2025-01-01', count: 2, channel: 'shop', domain: 'de' },
            { date: '2025-01-01', count: 3, channel: 'shop', domain: 'com' },
            { date: '2025-01-01', count: 5, channel: 'market', domain: 'de' },
        ];

        const result = normalizeTimelinePoints(timeline, {
            selectedChannel: 'shop',
            selectedDomain: 'de',
        });

        expect(result).toEqual([
            { date: '2025-01-01', value: 2 },
        ]);
    });

    it('returns empty array for invalid points', () => {
        const timeline = [
            { date: 'not-a-date', count: 3 },
            { foo: 'bar' },
            null,
        ];

        expect(normalizeTimelinePoints(timeline)).toEqual([]);
    });
});
