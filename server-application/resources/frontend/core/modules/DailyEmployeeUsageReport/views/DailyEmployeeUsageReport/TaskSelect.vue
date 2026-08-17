<template>
    <multi-select
        placeholder="control.task_selected"
        :inputHandler="selectedTasks"
        :selected="selectedTaskIds"
        :service="taskService"
        name="tasks"
        :size="size"
        @onOptionsLoad="onLoad"
    />
</template>

<script>
    import MultiSelect from '@/components/MultiSelect';
    import ReportTaskSelectService from '../../service/report-task-select.service';

    const localStorageKey = 'amazingcat.local.storage.daily_employee_usage.task_select';

    export default {
        name: 'DailyEmployeeUsageTaskSelect',
        components: {
            MultiSelect,
        },
        props: {
            size: {
                type: String,
                default: 'normal',
            },
            value: {
                type: Array,
                default: null,
            },
        },
        data() {
            const selectedTaskIds =
                this.value !== null ? this.value : JSON.parse(localStorage.getItem(localStorageKey));

            return {
                taskService: new ReportTaskSelectService(),
                selectedTaskIds,
            };
        },
        methods: {
            onLoad(allSelectOptions) {
                const allTaskIds = allSelectOptions.map(option => option.id);

                if (!localStorage.getItem(localStorageKey)) {
                    this.selectedTaskIds = allTaskIds;
                    localStorage.setItem(localStorageKey, JSON.stringify(this.selectedTaskIds));
                    this.$emit('change', this.selectedTaskIds);
                    this.$nextTick(() => this.$emit('loaded'));
                    return;
                }

                const existingTaskIds = (this.selectedTaskIds || []).filter(taskId => allTaskIds.includes(taskId));

                if ((this.selectedTaskIds || []).length > existingTaskIds.length) {
                    this.selectedTaskIds = existingTaskIds;
                    localStorage.setItem(localStorageKey, JSON.stringify(this.selectedTaskIds));
                }

                this.$emit('change', this.selectedTaskIds || []);
                this.$nextTick(() => this.$emit('loaded'));
            },
            selectedTasks(values) {
                this.selectedTaskIds = values;
                localStorage.setItem(localStorageKey, JSON.stringify(this.selectedTaskIds));
                this.$emit('change', values);
            },
        },
    };
</script>
