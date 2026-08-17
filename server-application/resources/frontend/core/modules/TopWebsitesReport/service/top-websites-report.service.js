import axios from 'axios';
import ReportService from '@/services/report.service';

export default class TopWebsitesReportService extends ReportService {
    getReport(startAt, endAt, users = [], projects = [], search = '') {
        return axios.post('report/top-websites', {
            start_at: startAt,
            end_at: endAt,
            users: users.length ? users : undefined,
            projects: projects.length ? projects : undefined,
            search: search || undefined,
        });
    }

    downloadReport(startAt, endAt, users = [], projects = [], search = '', format) {
        return axios.post(
            'report/top-websites/download',
            {
                start_at: startAt,
                end_at: endAt,
                users: users.length ? users : undefined,
                projects: projects.length ? projects : undefined,
                search: search || undefined,
            },
            { headers: { Accept: format } },
        );
    }
}
