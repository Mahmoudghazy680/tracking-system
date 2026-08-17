import axios from 'axios';
import ReportService from '@/services/report.service';

export default class DailyEmployeeUsageReportService extends ReportService {
    getReport(startAt, endAt, users = [], projects = [], tasks = [], search = '') {
        return axios.post('report/daily-employee-usage', {
            start_at: startAt,
            end_at: endAt,
            users: users.length ? users : undefined,
            projects: projects.length ? projects : undefined,
            tasks: tasks.length ? tasks : undefined,
            search: search || undefined,
        });
    }

    downloadReport(startAt, endAt, users = [], projects = [], tasks = [], search = '', format) {
        return axios.post(
            'report/daily-employee-usage/download',
            {
                start_at: startAt,
                end_at: endAt,
                users: users.length ? users : undefined,
                projects: projects.length ? projects : undefined,
                tasks: tasks.length ? tasks : undefined,
                search: search || undefined,
            },
            { headers: { Accept: format } },
        );
    }
}
