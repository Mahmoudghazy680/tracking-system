<template>
    <div class="email-monitoring">
        <h1 class="page-title">{{ $t('navigation.email-monitoring') }}</h1>

        <!-- Filter row -->
        <div class="controls-row">
            <div class="calendar controls-row__item">
                <Calendar :sessionStorageKey="sessionStorageKey" @change="onCalendarChange" />
            </div>
            <div class="select controls-row__item">
                <UserSelect @change="onUsersChange" />
            </div>
            <div class="controls-row__item">
                <at-select v-model="direction" class="direction-select" @change="fetchReport">
                    <at-option value="">{{ $t('email-monitoring.direction_all') }}</at-option>
                    <at-option value="sent">{{ $t('email-monitoring.direction_sent') }}</at-option>
                    <at-option value="received">{{ $t('email-monitoring.direction_received') }}</at-option>
                </at-select>
            </div>
            <div class="controls-row__item">
                <input
                    v-model="search"
                    class="at-input__original"
                    :placeholder="$t('email-monitoring.filter_search')"
                    @input="onSearchInput"
                />
            </div>
            <div class="controls-row__item controls-row__item--left-auto">
                <small v-if="companyData.timezone">
                    {{ $t('project-report.report_timezone', [companyData.timezone]) }}
                </small>
            </div>
        </div>

        <!-- Table -->
        <div class="at-container">
            <div v-if="emails.length && !isDataLoading" class="email-table">
                <table class="at-table at-table--large">
                    <thead>
                        <tr>
                            <th>{{ $t('email-monitoring.col_user') }}</th>
                            <th>{{ $t('email-monitoring.col_from') }}</th>
                            <th>{{ $t('email-monitoring.col_to') }}</th>
                            <th>{{ $t('email-monitoring.col_subject') }}</th>
                            <th>{{ $t('email-monitoring.col_client') }}</th>
                            <th>{{ $t('email-monitoring.col_attachment') }}</th>
                            <th>{{ $t('email-monitoring.col_datetime') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in emails">
                            <tr
                                :key="'row-' + idx"
                                class="email-row"
                                :class="{ 'email-row--expanded': expandedRows.includes(idx) }"
                                @click="toggleRow(idx)"
                            >
                                <td>{{ row.user ? row.user.full_name : '—' }}</td>
                                <td>{{ row.from_address || '—' }}</td>
                                <td class="to-cell">{{ formatToAddresses(row.to_addresses) }}</td>
                                <td>{{ row.subject || $t('email-monitoring.no_subject') }}</td>
                                <td>{{ row.email_client || '—' }}</td>
                                <td>
                                    <span v-if="row.has_attachment" class="attachment-icon" title="Has attachment">📎</span>
                                    <span v-else>—</span>
                                </td>
                                <td>{{ formatDatetime(row.email_datetime) }}</td>
                            </tr>
                            <tr
                                v-if="expandedRows.includes(idx)"
                                :key="'detail-' + idx"
                                class="email-detail-row"
                            >
                                <td colspan="7">
                                    <div class="email-detail">
                                        <div v-if="row.to_addresses && row.to_addresses.length > 1">
                                            <strong>{{ $t('email-monitoring.col_to') }}:</strong>
                                            {{ row.to_addresses.join(', ') }}
                                        </div>
                                        <div v-if="row.body_excerpt">
                                            <strong>{{ $t('email-monitoring.body_preview') }}:</strong>
                                            <p class="body-excerpt">{{ row.body_excerpt }}</p>
                                        </div>
                                        <div v-if="!row.body_excerpt && (!row.to_addresses || row.to_addresses.length <= 1)">
                                            <em>No additional details.</em>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div v-else class="at-container__inner no-data">
                <preloader v-if="isDataLoading" />
                <span v-else>{{ $t('email-monitoring.no_data') }}</span>
            </div>
        </div>

        <at-pagination
            v-if="totalItems > perPage"
            :total="totalItems"
            :current="currentPage"
            :page-size="perPage"
            class="email-pagination"
            @page-change="onPageChange"
        />
    </div>
</template>

<script>
    import EmailMonitoringService from '_internal/EmailMonitoring/service/email-monitoring.service';
    import { getStartOfDayInTimezone, getEndOfDayInTimezone } from '@/utils/time';
    import Preloader from '@/components/Preloader';
    import UserSelect from '@/components/UserSelect';
    import Calendar from '@/components/Calendar';
    import { mapGetters } from 'vuex';
    import debounce from 'lodash.debounce';
    import moment from 'moment';

    const service = new EmailMonitoringService();

    export default {
        name: 'EmailMonitoring',
        components: {
            Preloader,
            UserSelect,
            Calendar,
        },
        data() {
            return {
                isDataLoading: false,
                emails: [],
                totalItems: 0,
                currentPage: 1,
                perPage: 15,
                datepickerDateStart: '',
                datepickerDateEnd: '',
                userIDs: [],
                direction: '',
                search: '',
                expandedRows: [],
                sessionStorageKey: 'amazingcat.session.storage.email_monitoring',
            };
        },
        computed: {
            ...mapGetters('user', ['companyData']),
        },
        methods: {
            onCalendarChange({ start, end }) {
                this.datepickerDateStart = start;
                this.datepickerDateEnd = end;
                this.currentPage = 1;
                this.fetchReport();
            },
            onUsersChange(ids) {
                this.userIDs = ids;
                this.currentPage = 1;
                this.fetchReport();
            },
            onSearchInput: debounce(function () {
                this.currentPage = 1;
                this.fetchReport();
            }, 400),
            onPageChange(page) {
                this.currentPage = page;
                this.expandedRows = [];
                this.fetchReport();
            },
            toggleRow(idx) {
                const pos = this.expandedRows.indexOf(idx);
                if (pos === -1) {
                    this.expandedRows.push(idx);
                } else {
                    this.expandedRows.splice(pos, 1);
                }
            },
            formatToAddresses(addresses) {
                if (!addresses || !addresses.length) return '—';
                if (addresses.length === 1) return addresses[0];
                return `${addresses[0]} (+${addresses.length - 1})`;
            },
            formatDatetime(dt) {
                if (!dt) return '—';
                return moment(dt).format('YYYY-MM-DD HH:mm');
            },
            fetchReport: debounce(async function () {
                if (!this.datepickerDateStart) {
                    return;
                }
                this.isDataLoading = true;
                this.expandedRows = [];
                try {
                    const response = await service.getReport({
                        startAt: getStartOfDayInTimezone(this.datepickerDateStart, this.companyData.timezone),
                        endAt: getEndOfDayInTimezone(this.datepickerDateEnd, this.companyData.timezone),
                        users: this.userIDs,
                        search: this.search,
                        direction: this.direction,
                        perPage: this.perPage,
                        page: this.currentPage,
                    });
                    const { data, meta } = response;
                    this.emails = data.data || [];
                    this.totalItems = meta ? meta.total : (data.total || 0);
                    if (meta) {
                        this.currentPage = meta.current_page;
                        this.perPage = meta.per_page;
                    }
                } catch (err) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn('email-monitoring request failed', err);
                    }
                }
                this.isDataLoading = false;
            }, 350),
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

    .direction-select {
        min-width: 130px;
    }

    .email-table {
        width: 100%;
        overflow-x: auto;

        table {
            width: 100%;
        }
    }

    .email-row {
        cursor: pointer;

        &:hover {
            background: #f5f7fa;
        }

        &--expanded {
            background: #ebf3ff;
        }
    }

    .to-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .email-detail-row td {
        background: #f9fbff;
    }

    .email-detail {
        padding: 8px 16px;
        font-size: 13px;
        line-height: 1.6;

        .body-excerpt {
            margin: 4px 0 0;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 120px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #e0e6ed;
            border-radius: 4px;
            padding: 8px;
        }
    }

    .attachment-icon {
        font-size: 16px;
    }

    .email-pagination {
        margin-top: 16px;
        display: flex;
        justify-content: flex-end;
    }
</style>
