import ResourceService from '@/services/resource.service';
import TasksService from '@/services/resource/task.service';

const tasksService = new TasksService();

export default class ReportTaskSelectService extends ResourceService {
    async getAll() {
        const { data } = await tasksService.getWithFilters({}, { headers: { 'X-Paginate': 'false' } });

        return (data.data || []).map(task => ({
            id: task.id,
            name: task.task_name,
        }));
    }
}
