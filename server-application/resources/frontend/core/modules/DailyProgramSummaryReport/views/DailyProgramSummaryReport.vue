<template>
    <div class="daily-program-summary-report">
        <h1 class="page-title">{{ $t('daily-program-summary-report.title') }}</h1>

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
                    v-model="appsFilter"
                    class="at-input__original"
                    :placeholder="$t('daily-program-summary-report.filter_apps')"
                    @input="onAppsInput"
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
                <span class="summary-card__label">{{ $t('daily-program-summary-report.users_count') }}</span>
                <span class="summary-card__value">{{ reportData.length }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('daily-program-summary-report.days_count') }}</span>
                <span class="summary-card__value">{{ uniqueDaysCount }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('daily-program-summary-report.program_rows') }}</span>
                <span class="summary-card__value">{{ rows.length }}</span>
            </div>
        </div>

        <div class="at-container">
            <div v-if="rows.length && !isDataLoading">
                <DailyProgramSummaryList :rows="rows" />
            </div>
            <div v-else class="at-container__inner no-data">
                <preloader v-if="isDataLoading" />
                <span v-else>{{ $t('message.no_data') }}</span>
            </div>
        </div>
    </div>
</template>

<script>
    import DailyProgramSummaryReportService from '../service/daily-program-summary-report.service';
    import DailyProgramSummaryList from './DailyProgramSummaryReport/List';
    import { formatDurationString, getStartOfDayInTimezone, getEndOfDayInTimezone } from '@/utils/time';
    import Preloader from '@/components/Preloader';
    import UserSelect from '@/components/UserSelect';
    import ProjectSelect from '@/components/ProjectSelect';
    import Calendar from '@/components/Calendar';
    import ExportDropdown from '@/components/ExportDropdown';
    import { mapGetters } from 'vuex';
    import debounce from 'lodash.debounce';

    const reportService = new DailyProgramSummaryReportService();

    export default {
        name: 'DailyProgramSummaryReport',
        components: {
            DailyProgramSummaryList,
            Preloader,
            UserSelect,
            ProjectSelect,
            Calendar,
            ExportDropdown,
        },
        data() {
            return {
                isDataLoading: false,
                reportData: [],
                datepickerDateStart: '',
                datepickerDateEnd: '',
                userIDs: [],
                projectIDs: [],
                appsFilter: '',
                sessionStorageKey: 'amazingcat.session.storage.daily_program_summary_report',
            };
        },
        computed: {
            ...mapGetters('user', ['companyData']),
            rows() {
                return this.reportData.flatMap(userEntry =>
                    (userEntry.days || []).flatMap(day =>
                        (day.programs || []).map(program => ({
                            key: `${userEntry.user?.id || 'na'}::${day.date}::${program.program_name || ''}::${program.executable || ''}`,
                            user_name: userEntry.user?.full_name || '',
                            user_email: userEntry.user?.email || '',
                            date: day.date,
                            program_name: program.program_name || '',
                            executable: program.executable || '',
                            duration_seconds: this.toSafeInt(program.duration_seconds),
                            interval_count: this.toSafeInt(program.interval_count),
                        })),
                    ),
                );
            },
            totalSeconds() {
                return this.rows.reduce((sum, row) => sum + this.toSafeInt(row.duration_seconds), 0);
            },
            uniqueDaysCount() {
                return new Set(this.rows.map(row => row.date)).size;
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
            onAppsInput: debounce(function () {
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
                        [],
                        this.appsFilter,
                    );
                    this.reportData = data.data || [];
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'daily program summary report request failed');
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
                        [],
                        this.appsFilter,
                        format,
                    );
                    window.open(data.data.url, '_blank');
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'daily program summary export failed');
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
</style>
