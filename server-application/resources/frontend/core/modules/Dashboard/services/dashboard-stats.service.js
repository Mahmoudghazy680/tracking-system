import axios from 'axios';

export default class DashboardStatsService {
    getStats(startAt, endAt, users = [], limit = 10) {
        return axios.post('report/dashboard-stats', {
            start_at: startAt,
            end_at: endAt,
            users: users.length ? users : undefined,
            limit,
        });
    }
}
