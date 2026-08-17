import axios from 'axios';
import ReportService from '@/services/report.service';

export default class DailyProgramSummaryReportService extends ReportService {
    getReport(startAt, endAt, users = [], projects = [], tasks = [], apps = '') {
        return axios.post('report/daily-program-summary', {
            start_at: startAt,
            end_at: endAt,
            users: users.length ? users : undefined,
            projects: projects.length ? projects : undefined,
            tasks: tasks.length ? tasks : undefined,
            apps: apps || undefined,
        });
    }

    downloadReport(startAt, endAt, users = [], projects = [], tasks = [], apps = '', format) {
        return axios.post(
            'report/daily-program-summary/download',
            {
                start_at: startAt,
                end_at: endAt,
                users: users.length ? users : undefined,
                projects: projects.length ? projects : undefined,
                tasks: tasks.length ? tasks : undefined,
                apps: apps || undefined,
            },
            { headers: { Accept: format } },
        );
    }
}
