<template>
    <div class="daily-employee-usage-report">
        <h1 class="page-title">{{ $t('daily-employee-usage-report.title') }}</h1>

        <div class="controls-row">
            <div class="calendar controls-row__item">
                <Calendar :sessionStorageKey="sessionStorageKey" @change="onCalendarChange" />
            </div>
            <div class="select controls-row__item">
                <UserSelect @change="onUsersChange" />
            </div>
            <div class="select controls-row__item">
                <ProjectSelect @change="onProjectsChange" />
            </div>
            <div class="select controls-row__item">
                <TaskSelect @change="onTasksChange" />
            </div>
            <div class="controls-row__item">
                <input
                    v-model="searchFilter"
                    class="at-input__original"
                    :placeholder="$t('daily-employee-usage-report.filter_search')"
                    @input="onSearchInput"
                />
            </div>

            <div class="controls-row__item controls-row__item--left-auto">
                <small v-if="companyData.timezone">
                    {{ $t('project-report.report_timezone', [companyData.timezone]) }}
                </small>
            </div>

            <ExportDropdown
                class="export-btn dropdown controls-row__btn controls-row__item"
                position="left-top"
                trigger="hover"
                @export="onExport"
            />
        </div>

        <div v-if="rows.length && !isDataLoading" class="summary-cards">
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('field.total_time') }}</span>
                <span class="summary-card__value">{{ formatDurationString(totalSeconds) }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('daily-employee-usage-report.users_count') }}</span>
                <span class="summary-card__value">{{ reportData.length }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('daily-employee-usage-report.days_count') }}</span>
                <span class="summary-card__value">{{ uniqueDaysCount }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('daily-employee-usage-report.activity_rows') }}</span>
                <span class="summary-card__value">{{ rows.length }}</span>
            </div>
        </div>

        <div v-if="chartData && !isDataLoading" class="chart-wrapper at-container">
            <div class="chart-title">{{ $t('daily-employee-usage-report.top_employees') }}</div>
            <Bar :data="chartData" :options="chartOptions" />
        </div>

        <div class="at-container">
            <div v-if="rows.length && !isDataLoading">
                <DailyEmployeeUsageList :rows="rows" />
            </div>
            <div v-else class="at-container__inner no-data">
                <preloader v-if="isDataLoading" />
                <span v-else>{{ $t('message.no_data') }}</span>
            </div>
        </div>
    </div>
</template>

<script>
    import { Bar } from 'vue-chartjs';
    import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';

    ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

    import DailyEmployeeUsageReportService from '../service/daily-employee-usage-report.service';
    import DailyEmployeeUsageList from './DailyEmployeeUsageReport/List';
    import TaskSelect from './DailyEmployeeUsageReport/TaskSelect';
    import { formatDurationString, getStartOfDayInTimezone, getEndOfDayInTimezone } from '@/utils/time';
    import Preloader from '@/components/Preloader';
    import UserSelect from '@/components/UserSelect';
    import ProjectSelect from '@/components/ProjectSelect';
    import Calendar from '@/components/Calendar';
    import ExportDropdown from '@/components/ExportDropdown';
    import { mapGetters } from 'vuex';
    import debounce from 'lodash.debounce';

    const CHART_COLORS = [
        '#4e79a7',
        '#f28e2b',
        '#e15759',
        '#76b7b2',
        '#59a14f',
        '#edc948',
        '#b07aa1',
        '#ff9da7',
        '#9c755f',
        '#bab0ac',
    ];

    const reportService = new DailyEmployeeUsageReportService();

    export default {
        name: 'DailyEmployeeUsageReport',
        components: {
            DailyEmployeeUsageList,
            TaskSelect,
            Preloader,
            UserSelect,
            ProjectSelect,
            Calendar,
            ExportDropdown,
            Bar,
        },
        data() {
            return {
                isDataLoading: false,
                reportData: [],
                datepickerDateStart: '',
                datepickerDateEnd: '',
                userIDs: [],
                projectIDs: [],
                taskIDs: [],
                searchFilter: '',
                sessionStorageKey: 'amazingcat.session.storage.daily_employee_usage_report',
            };
        },
        computed: {
            ...mapGetters('user', ['companyData']),
            rows() {
                return this.reportData.flatMap(userEntry =>
                    (userEntry.days || []).flatMap(day =>
                        (day.activities || []).map(activity => ({
                            key: `${userEntry.user?.id || 'na'}::${day.date}::${activity.activity_type || ''}::${activity.activity_name || ''}`,
                            user_name: userEntry.user?.full_name || '',
                            user_email: userEntry.user?.email || '',
                            date: day.date,
                            activity_type: activity.activity_type || 'program',
                            type_label: this.activityTypeLabel(activity.activity_type),
                            activity_name: activity.activity_name || '',
                            duration_seconds: this.toSafeInt(activity.duration_seconds),
                            interval_count: this.toSafeInt(activity.interval_count),
                        })),
                    ),
                );
            },
            totalSeconds() {
                return this.reportData.reduce((sum, userEntry) => sum + this.toSafeInt(userEntry.total_seconds), 0);
            },
            uniqueDaysCount() {
                return new Set(this.rows.map(row => row.date)).size;
            },
            chartData() {
                if (!this.reportData.length) {
                    return null;
                }

                const topEmployees = [...this.reportData]
                    .map(userEntry => ({
                        label: userEntry.user?.full_name || 'Unknown',
                        total_seconds: this.toSafeInt(userEntry.total_seconds),
                    }))
                    .sort((left, right) => right.total_seconds - left.total_seconds)
                    .slice(0, 10);

                return {
                    labels: topEmployees.map(entry => entry.label),
                    datasets: [
                        {
                            label: this.$t('daily-employee-usage-report.chart_hours'),
                            data: topEmployees.map(entry => +(entry.total_seconds / 3600).toFixed(2)),
                            backgroundColor: topEmployees.map((_, index) => CHART_COLORS[index % CHART_COLORS.length]),
                        },
                    ],
                };
            },
            chartOptions() {
                return {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: context => {
                                    const hours = Number(context.raw || 0);
                                    return ` ${hours}h`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: this.$t('daily-employee-usage-report.chart_hours'),
                            },
                        },
                    },
                };
            },
        },
        methods: {
            formatDurationString,
            toSafeInt(value) {
                const parsed = Number(value);
                if (!Number.isFinite(parsed) || parsed <= 0 || parsed > Number.MAX_SAFE_INTEGER) {
                    return 0;
                }

                return Math.floor(parsed);
            },
            activityTypeLabel(activityType) {
                return this.$t(`daily-employee-usage-report.type_${activityType || 'program'}`);
            },
            onCalendarChange({ start, end }) {
                this.datepickerDateStart = start;
                this.datepickerDateEnd = end;
                this.fetchReport();
            },
            onUsersChange(ids) {
                this.userIDs = ids;
                this.fetchReport();
            },
            onProjectsChange(ids) {
                this.projectIDs = ids;
                this.fetchReport();
            },
            onTasksChange(ids) {
                this.taskIDs = ids;
                this.fetchReport();
            },
            onSearchInput: debounce(function () {
                this.fetchReport();
            }, 400),
            fetchReport: debounce(async function () {
                if (!this.datepickerDateStart) {
                    return;
                }

                this.isDataLoading = true;
                try {
                    const { data } = await reportService.getReport(
                        getStartOfDayInTimezone(this.datepickerDateStart, this.companyData.timezone),
                        getEndOfDayInTimezone(this.datepickerDateEnd, this.companyData.timezone),
                        this.userIDs,
                        this.projectIDs,
                        this.taskIDs,
                        this.searchFilter,
                    );
                    this.reportData = data.data || [];
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'daily employee usage report request failed');
                    }
                }
                this.isDataLoading = false;
            }, 350),
            async onExport(format) {
                if (!this.datepickerDateStart) {
                    return;
                }

                try {
                    const { data } = await reportService.downloadReport(
                        getStartOfDayInTimezone(this.datepickerDateStart, this.companyData.timezone),
                        getEndOfDayInTimezone(this.datepickerDateEnd, this.companyData.timezone),
                        this.userIDs,
                        this.projectIDs,
                        this.taskIDs,
                        this.searchFilter,
                        format,
                    );
                    window.open(data.data.url, '_blank');
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'daily employee usage export failed');
                    }
                }
            },
        },
    };
</script>

<style lang="scss" scoped>
    .controls-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;

        &__item {
            min-width: 180px;

            &--left-auto {
                margin-left: auto;
                display: flex;
                align-items: center;
            }
        }
    }

    .at-container {
        overflow: hidden;
        min-height: 100px;
    }

    .no-data {
        text-align: center;
        font-weight: bold;
        position: relative;
        padding: 40px 0;
    }

    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }

    .summary-card {
        border: 1px solid #ececec;
        background: #fafafa;
        border-radius: 8px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;

        &__label {
            font-size: 0.75rem;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        &__value {
            margin-top: 4px;
            font-size: 1rem;
            font-weight: 700;
            color: #2f2f2f;
        }
    }

    .chart-wrapper {
        height: 360px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .chart-title {
        margin-bottom: 12px;
        font-size: 0.95rem;
        font-weight: 700;
        color: #2f2f2f;
    }
</style>
