import axios from 'axios';

export default class EmailMonitoringService {
    async getReport(params) {
        const { data } = await axios.post('report/email-monitoring', {
            start_at: params.startAt,
            end_at: params.endAt,
            users: params.users || [],
            search: params.search || '',
            direction: params.direction || '',
            per_page: params.perPage || 15,
            page: params.page || 1,
        });
        return data;
    }
}
