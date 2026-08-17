<template>
    <div class="top-websites-report">
        <h1 class="page-title">{{ $t('top-websites-report.title') }}</h1>

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
            <div class="controls-row__item">
                <input
                    v-model="searchFilter"
                    class="at-input__original"
                    :placeholder="$t('top-websites-report.filter_search')"
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

        <div v-if="reportData.length && !isDataLoading" class="summary-cards">
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('field.total_time') }}</span>
                <span class="summary-card__value">{{ formatDurationString(totalSeconds) }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('top-websites-report.unique_domains') }}</span>
                <span class="summary-card__value">{{ reportData.length }}</span>
            </div>
        </div>

        <!-- Bar chart -->
        <div v-if="chartData && !isDataLoading" class="chart-wrapper at-container">
            <Bar :data="chartData" :options="chartOptions" />
        </div>

        <div class="at-container">
            <div v-if="reportData.length && !isDataLoading">
                <TopWebsitesList :report-data="reportData" />
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
    import {
        Chart as ChartJS,
        Title,
        Tooltip,
        Legend,
        BarElement,
        CategoryScale,
        LinearScale,
    } from 'chart.js';

    ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

    import TopWebsitesReportService from '_internal/TopWebsitesReport/service/top-websites-report.service';
    import TopWebsitesList from './TopWebsitesReport/List';
    import { formatDurationString, getStartOfDayInTimezone, getEndOfDayInTimezone } from '@/utils/time';
    import Preloader from '@/components/Preloader';
    import UserSelect from '@/components/UserSelect';
    import ProjectSelect from '@/components/ProjectSelect';
    import Calendar from '@/components/Calendar';
    import ExportDropdown from '@/components/ExportDropdown';
    import { mapGetters } from 'vuex';
    import debounce from 'lodash.debounce';

    const CHART_COLORS = [
        '#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f',
        '#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ac',
    ];

    const reportService = new TopWebsitesReportService();

    export default {
        name: 'TopWebsitesReport',
        components: {
            TopWebsitesList,
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
                searchFilter: '',
                sessionStorageKey: 'amazingcat.session.storage.top_websites_report',
            };
        },
        computed: {
            ...mapGetters('user', ['companyData']),
            totalSeconds() {
                return this.reportData.reduce((sum, r) => {
                    const seconds = Number(r.total_seconds);
                    if (!Number.isFinite(seconds) || seconds <= 0 || seconds > Number.MAX_SAFE_INTEGER) {
                        return sum;
                    }

                    return sum + Math.floor(seconds);
                }, 0);
            },
            chartData() {
                if (!this.reportData.length) return null;
                const top = this.reportData.slice(0, 15);
                return {
                    labels: top.map(r => r.domain),
                    datasets: [
                        {
                            label: 'Hours',
                            data: top.map(r => +(r.total_seconds / 3600).toFixed(2)),
                            backgroundColor: top.map((_, i) => CHART_COLORS[i % CHART_COLORS.length]),
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
                                label: ctx => {
                                    const secs = this.reportData[ctx.dataIndex]?.total_seconds || 0;
                                    return ' ' + formatDurationString(secs);
                                },
                            },
                        },
                    },
                    scales: {
                        x: { title: { display: true, text: 'Hours' } },
                    },
                };
            },
        },
        methods: {
            formatDurationString,
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
            onSearchInput: debounce(function () {
                this.fetchReport();
            }, 400),
            fetchReport: debounce(async function () {
                if (!this.datepickerDateStart) return;
                this.isDataLoading = true;
                try {
                    const { data } = await reportService.getReport(
                        getStartOfDayInTimezone(this.datepickerDateStart, this.companyData.timezone),
                        getEndOfDayInTimezone(this.datepickerDateEnd, this.companyData.timezone),
                        this.userIDs,
                        this.projectIDs,
                        this.searchFilter,
                    );
                    this.reportData = data.data;
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'top-websites report request failed');
                    }
                }
                this.isDataLoading = false;
            }, 350),
            async onExport(format) {
                if (!this.datepickerDateStart) return;
                try {
                    const { data } = await reportService.downloadReport(
                        getStartOfDayInTimezone(this.datepickerDateStart, this.companyData.timezone),
                        getEndOfDayInTimezone(this.datepickerDateEnd, this.companyData.timezone),
                        this.userIDs,
                        this.projectIDs,
                        this.searchFilter,
                        format,
                    );
                    window.open(data.data.url, '_blank');
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'top-websites export failed');
                    }
                }
            },
        },
    };
</script>

<style lang="scss" scoped>
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

    .chart-wrapper {
        height: 360px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .summary-cards {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .summary-card {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        padding: 12px 24px;
        display: flex;
        flex-direction: column;
        min-width: 150px;

        &__label {
            font-size: 0.8rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        &__value {
            font-size: 1.6rem;
            font-weight: bold;
            color: #333;
        }
    }
</style>
