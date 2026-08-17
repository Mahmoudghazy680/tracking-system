<template>
    <div class="daily-program-summary-list">
        <div v-for="userGroup in groupedUsers" :key="userGroup.user_key" class="user-block">
            <div class="user-header" @click="toggleUser(userGroup.user_key)">
                <span class="user-header__name">{{ userGroup.user_name }}</span>
                <span class="user-header__email">{{ userGroup.user_email }}</span>
                <span class="user-header__total">{{ formatDurationString(userGroup.total_seconds) }}</span>
                <span class="user-header__toggle">{{ isExpanded(userGroup.user_key) ? '▲' : '▼' }}</span>
            </div>

            <transition name="collapse">
                <div v-if="isExpanded(userGroup.user_key)" class="app-table-wrapper">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>{{ $t('daily-program-summary-report.col_date') }}</th>
                                <th>{{ $t('daily-program-summary-report.col_program') }}</th>
                                <th>{{ $t('daily-program-summary-report.col_executable') }}</th>
                                <th>{{ $t('daily-program-summary-report.col_duration') }}</th>
                                <th>{{ $t('daily-program-summary-report.col_intervals') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in userGroup.rows" :key="row.key" class="app-row">
                                <td>{{ row.date }}</td>
                                <td>{{ row.program_name }}</td>
                                <td class="muted">{{ row.executable || '—' }}</td>
                                <td class="duration">{{ formatDurationString(row.duration_seconds) }}</td>
                                <td>{{ row.interval_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </transition>
        </div>
    </div>
</template>

<script>
    import { formatDurationString } from '@/utils/time';

    export default {
        name: 'DailyProgramSummaryList',
        props: {
            rows: {
                type: Array,
                required: true,
            },
        },
        data() {
            return {
                expandedUsers: {},
            };
        },
        watch: {
            groupedUsers: {
                immediate: true,
                handler(groups) {
                    const expanded = {};
                    groups.forEach(group => {
                        expanded[group.user_key] = true;
                    });
                    this.expandedUsers = expanded;
                },
            },
        },
        computed: {
            groupedUsers() {
                const map = new Map();

                this.rows.forEach(row => {
                    const userKey = `${row.user_name || ''}::${row.user_email || ''}`;
                    if (!map.has(userKey)) {
                        map.set(userKey, {
                            user_key: userKey,
                            user_name: row.user_name,
                            user_email: row.user_email,
                            total_seconds: 0,
                            rows: [],
                        });
                    }

                    const group = map.get(userKey);
                    group.total_seconds += Number(row.duration_seconds) || 0;
                    group.rows.push(row);
                });

                return [...map.values()]
                    .map(group => ({
                        ...group,
                        rows: [...group.rows].sort((a, b) => {
                            if (a.date !== b.date) {
                                return a.date < b.date ? 1 : -1;
                            }

                            return b.duration_seconds - a.duration_seconds;
                        }),
                    }))
                    .sort((a, b) => a.user_name.localeCompare(b.user_name));
            },
        },
        methods: {
            formatDurationString,
            isExpanded(userKey) {
                return !!this.expandedUsers[userKey];
            },
            toggleUser(userKey) {
                this.$set(this.expandedUsers, userKey, !this.expandedUsers[userKey]);
            },
        },
    };
</script>

<style lang="scss" scoped>
    .user-block {
        margin-bottom: 12px;
        border: 1px solid #ececec;
        border-radius: 8px;
        overflow: hidden;
    }

    .user-header {
        display: grid;
        grid-template-columns: minmax(160px, 1fr) minmax(180px, 1fr) auto auto;
        gap: 10px;
        align-items: center;
        padding: 10px 12px;
        background: #fafafa;
        border-bottom: 1px solid #efefef;
        cursor: pointer;

        &__name {
            font-weight: 700;
            color: #333;
        }

        &__email {
            color: #7d7d7d;
            font-size: 0.82rem;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        &__total {
            font-family: monospace;
            font-weight: 700;
            color: #2f2f2f;
            white-space: nowrap;
            text-align: right;
        }

        &__toggle {
            color: #7d7d7d;
            font-size: 0.78rem;
            text-align: right;
            width: 18px;
        }
    }

    .app-table-wrapper {
        overflow-x: auto;
    }

    .app-table {
        width: 100%;
        border-collapse: collapse;

        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 0.86rem;
        }

        th {
            font-weight: 600;
            color: #666;
            background: #fafafa;
            white-space: nowrap;
        }

        .app-row:hover {
            background: #fcfcfc;
        }

        .muted {
            color: #7d7d7d;
            font-size: 0.82rem;
        }

        .duration {
            font-family: monospace;
            font-weight: 600;
            color: #2f2f2f;
        }
    }

    .collapse-enter-active,
    .collapse-leave-active {
        transition: all 0.2s ease;
    }

    .collapse-enter,
    .collapse-leave-to {
        opacity: 0;
        transform: translateY(-4px);
    }

    @media (max-width: 900px) {
        .user-header {
            grid-template-columns: 1fr auto auto;

            &__email {
                grid-column: 1 / -1;
                white-space: normal;
            }
        }
    }
</style>
