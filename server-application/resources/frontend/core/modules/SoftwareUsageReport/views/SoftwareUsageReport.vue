<template>
    <div class="software-usage-report">
        <h1 class="page-title">{{ $t('navigation.software-usage-report') }}</h1>

        <!-- Filter row -->
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
                    :placeholder="$t('software-usage-report.filter_apps')"
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

        <!-- Summary cards -->
        <div v-if="reportData.length && !isDataLoading" class="summary-cards">
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('field.total_time') }}</span>
                <span class="summary-card__value">{{ formatDurationString(totalSeconds) }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('software-usage-report.unique_apps') }}</span>
                <span class="summary-card__value">{{ uniqueAppsCount }}</span>
            </div>
            <div class="summary-card">
                <span class="summary-card__label">{{ $t('software-usage-report.users_count') }}</span>
                <span class="summary-card__value">{{ reportData.length }}</span>
            </div>
        </div>

        <!-- Table -->
        <div class="at-container">
            <div v-if="reportData.length && !isDataLoading">
                <SoftwareUsageList :report-data="reportData" />
            </div>
            <div v-else class="at-container__inner no-data">
                <preloader v-if="isDataLoading" />
                <span v-else>{{ $t('message.no_data') }}</span>
            </div>
        </div>
    </div>
</template>

<script>
    import SoftwareUsageReportService from '_internal/SoftwareUsageReport/service/software-usage-report.service';
    import SoftwareUsageList from './SoftwareUsageReport/List';
    import { formatDurationString, getStartOfDayInTimezone, getEndOfDayInTimezone } from '@/utils/time';
    import Preloader from '@/components/Preloader';
    import UserSelect from '@/components/UserSelect';
    import ProjectSelect from '@/components/ProjectSelect';
    import Calendar from '@/components/Calendar';
    import ExportDropdown from '@/components/ExportDropdown';
    import { mapGetters } from 'vuex';
    import debounce from 'lodash.debounce';

    const reportService = new SoftwareUsageReportService();

    export default {
        name: 'SoftwareUsageReport',
        components: {
            SoftwareUsageList,
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
                sessionStorageKey: 'amazingcat.session.storage.software_usage_report',
            };
        },
        computed: {
            ...mapGetters('user', ['companyData']),
            totalSeconds() {
                return this.reportData.reduce((sum, u) => {
                    const seconds = Number(u.total_seconds);
                    if (!Number.isFinite(seconds) || seconds <= 0 || seconds > Number.MAX_SAFE_INTEGER) {
                        return sum;
                    }

                    return sum + Math.floor(seconds);
                }, 0);
            },
            uniqueAppsCount() {
                const names = new Set();
                this.reportData.forEach(u => {
                    (u.apps || []).forEach(a => names.add(a.app_name));
                });
                return names.size;
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
                    this.reportData = data.data;
                } catch ({ response }) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn(response || 'software-usage report request failed');
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
                        console.warn(response || 'software-usage export failed');
                    }
                }
            },
        },
        async mounted() {
            // Initial load deferred until calendar emits a range
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
