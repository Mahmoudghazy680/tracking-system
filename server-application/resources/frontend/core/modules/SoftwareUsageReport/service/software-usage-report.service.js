import axios from 'axios';
import ReportService from '@/services/report.service';

export default class SoftwareUsageReportService extends ReportService {
    /**
     * Fetch grouped software usage data.
     *
     * @param {string}   startAt
     * @param {string}   endAt
     * @param {number[]} users
     * @param {number[]} projects
     * @param {number[]} tasks
     * @param {string}   apps
     * @returns {Promise<AxiosResponse>}
     */
    getReport(startAt, endAt, users = [], projects = [], tasks = [], apps = '') {
        return axios.post('report/software-usage', {
            start_at: startAt,
            end_at: endAt,
            users: users.length ? users : undefined,
            projects: projects.length ? projects : undefined,
            tasks: tasks.length ? tasks : undefined,
            apps: apps || undefined,
        });
    }

    /**
     * Download the report in the requested format.
     *
     * @param {string}   startAt
     * @param {string}   endAt
     * @param {number[]} users
     * @param {number[]} projects
     * @param {number[]} tasks
     * @param {string}   apps
     * @param {string}   format   Accept header MIME type
     * @returns {Promise<AxiosResponse>}
     */
    downloadReport(startAt, endAt, users = [], projects = [], tasks = [], apps = '', format) {
        return axios.post(
            'report/software-usage/download',
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
