import axios from 'axios';
import ReportService from '@/services/report.service';

export default class TopProgramsReportService extends ReportService {
    getReport(startAt, endAt, users = [], projects = [], apps = '') {
        return axios.post('report/top-programs', {
            start_at: startAt,
            end_at: endAt,
            users: users.length ? users : undefined,
            projects: projects.length ? projects : undefined,
            apps: apps || undefined,
        });
    }

    downloadReport(startAt, endAt, users = [], projects = [], apps = '', format) {
        return axios.post(
            'report/top-programs/download',
            {
                start_at: startAt,
                end_at: endAt,
                users: users.length ? users : undefined,
                projects: projects.length ? projects : undefined,
                apps: apps || undefined,
            },
            { headers: { Accept: format } },
        );
    }
}
