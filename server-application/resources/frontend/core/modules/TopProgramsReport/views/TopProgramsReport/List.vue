<template>
    <div class="top-programs-list">
        <div class="app-table-wrapper">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="sortable" @click="sortBy('app_name')">
                            {{ $t('top-programs-report.col_program') }}
                            <span class="sort-icon">{{ sortIcon('app_name') }}</span>
                        </th>
                        <th class="sortable" @click="sortBy('executable')">
                            {{ $t('top-programs-report.col_executable') }}
                            <span class="sort-icon">{{ sortIcon('executable') }}</span>
                        </th>
                        <th class="sortable" @click="sortBy('total_seconds')">
                            {{ $t('top-programs-report.col_duration') }}
                            <span class="sort-icon">{{ sortIcon('total_seconds') }}</span>
                        </th>
                        <th>{{ $t('top-programs-report.col_intervals') }}</th>
                        <th>{{ $t('top-programs-report.col_users') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in sorted"
                        :key="`${row.app_name}::${row.executable}`"
                        class="app-row"
                    >
                        <td class="app-name">{{ row.app_name }}</td>
                        <td class="muted">{{ row.executable || '—' }}</td>
                        <td class="duration">{{ formatDurationString(row.total_seconds) }}</td>
                        <td>{{ row.interval_count }}</td>
                        <td>{{ row.user_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
    import { formatDurationString } from '@/utils/time';

    export default {
        name: 'TopProgramsList',
        props: {
            reportData: {
                type: Array,
                required: true,
            },
        },
        data() {
            return {
                sortKey: 'total_seconds',
                sortDir: -1,
            };
        },
        computed: {
            sorted() {
                const key = this.sortKey;
                const dir = this.sortDir;
                return [...this.reportData].sort((a, b) => {
                    const av = typeof a[key] === 'string' ? a[key].toLowerCase() : (a[key] ?? 0);
                    const bv = typeof b[key] === 'string' ? b[key].toLowerCase() : (b[key] ?? 0);
                    if (av < bv) return -1 * dir;
                    if (av > bv) return 1 * dir;
                    return 0;
                });
            },
        },
        methods: {
            formatDurationString,
            sortBy(key) {
                if (this.sortKey === key) {
                    this.sortDir *= -1;
                } else {
                    this.sortKey = key;
                    this.sortDir = key === 'total_seconds' ? -1 : 1;
                }
            },
            sortIcon(key) {
                if (this.sortKey !== key) return '⇅';
                return this.sortDir === 1 ? '↑' : '↓';
            },
        },
    };
</script>

<style lang="scss" scoped>
    .app-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }

    .app-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;
        font-size: 0.9rem;

        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        th {
            background: #f7f7f7;
            font-weight: 600;

            &.sortable {
                cursor: pointer;
                user-select: none;

                &:hover {
                    background: #efefef;
                }
            }
        }

        .app-row:hover {
            background: #fafafa;
        }

        .app-name {
            font-weight: 500;
        }

        .muted {
            color: #999;
        }

        .duration {
            font-family: monospace;
            font-size: 0.95rem;
        }

        .sort-icon {
            margin-left: 4px;
            color: #aaa;
        }
    }
</style>
