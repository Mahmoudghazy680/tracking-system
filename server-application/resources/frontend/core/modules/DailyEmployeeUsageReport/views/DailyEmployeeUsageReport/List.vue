<template>
    <div class="daily-employee-usage-list">
        <div v-for="userGroup in groupedUsers" :key="userGroup.user_key" class="user-block">
            <div class="user-header" @click="toggleUser(userGroup.user_key)">
                <span class="user-header__name">{{ userGroup.user_name }}</span>
                <span class="user-header__email">{{ userGroup.user_email }}</span>
                <span class="user-header__total">{{ formatDurationString(userGroup.total_seconds) }}</span>
                <span class="user-header__toggle">{{ isExpanded(userGroup.user_key) ? '▲' : '▼' }}</span>
            </div>

            <transition name="collapse">
                <div v-if="isExpanded(userGroup.user_key)" class="activity-table-wrapper">
                    <table class="activity-table">
                        <thead>
                            <tr>
                                <th>{{ $t('daily-employee-usage-report.col_date') }}</th>
                                <th>{{ $t('daily-employee-usage-report.col_type') }}</th>
                                <th>{{ $t('daily-employee-usage-report.col_activity') }}</th>
                                <th>{{ $t('daily-employee-usage-report.col_duration') }}</th>
                                <th>{{ $t('daily-employee-usage-report.col_intervals') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in userGroup.rows" :key="row.key" class="activity-row">
                                <td>{{ row.date }}</td>
                                <td>
                                    <span class="type-badge" :class="`type-badge--${row.activity_type}`">
                                        {{ row.type_label }}
                                    </span>
                                </td>
                                <td>{{ row.activity_name }}</td>
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
        name: 'DailyEmployeeUsageList',
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
                const groupedMap = new Map();

                this.rows.forEach(row => {
                    const userKey = `${row.user_name || ''}::${row.user_email || ''}`;
                    if (!groupedMap.has(userKey)) {
                        groupedMap.set(userKey, {
                            user_key: userKey,
                            user_name: row.user_name,
                            user_email: row.user_email,
                            total_seconds: 0,
                            rows: [],
                        });
                    }

                    const group = groupedMap.get(userKey);
                    group.total_seconds += Number(row.duration_seconds) || 0;
                    group.rows.push(row);
                });

                return [...groupedMap.values()]
                    .map(group => ({
                        ...group,
                        rows: [...group.rows].sort((left, right) => {
                            if (left.date !== right.date) {
                                return left.date < right.date ? 1 : -1;
                            }

                            return right.duration_seconds - left.duration_seconds;
                        }),
                    }))
                    .sort((left, right) => left.user_name.localeCompare(right.user_name));
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

    .activity-table-wrapper {
        overflow-x: auto;
    }

    .activity-table {
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

        .activity-row:hover {
            background: #fcfcfc;
        }

        .duration {
            font-family: monospace;
            font-weight: 600;
            color: #2f2f2f;
        }
    }

    .type-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 0.72rem;
        font-weight: 600;

        &--program {
            background: #edf4ff;
            color: #2d5f9a;
        }

        &--website {
            background: #eef9f1;
            color: #2f7d4a;
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
