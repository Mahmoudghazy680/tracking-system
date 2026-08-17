<template>
    <div class="daily-summary-widget">
        <div class="daily-summary-widget__header">
            <span class="daily-summary-widget__title">DAILY PROGRAM SUMMARY</span>
            <router-link
                class="daily-summary-widget__link"
                :to="{ name: 'report.daily-program-summary' }"
            >
                View full report →
            </router-link>
        </div>

        <div v-if="!rows.length" class="daily-summary-widget__empty">No data for selected period.</div>

        <table v-else class="daily-summary-widget__table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Date</th>
                    <th>Program</th>
                    <th class="align-right">Duration</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in topRows" :key="row.key" class="daily-summary-widget__row">
                    <td class="user-cell">{{ row.user_name }}</td>
                    <td class="muted">{{ row.date }}</td>
                    <td class="program-cell">
                        <span class="program-name">{{ row.program_name }}</span>
                        <span v-if="row.executable" class="executable">({{ row.executable }})</span>
                    </td>
                    <td class="duration align-right">{{ formatDuration(row.duration_seconds) }}</td>
                </tr>
            </tbody>
        </table>

        <div v-if="remainingRows > 0" class="daily-summary-widget__more">
            +{{ remainingRows }} more rows in
            <router-link :to="{ name: 'report.daily-program-summary' }">full report</router-link>
        </div>
    </div>
</template>

<script>
    import axios from 'axios';
    import { formatDurationString } from '@/utils/time';

    const MAX_ROWS = 8;

    export default {
        name: 'DailyProgramSummaryWidget',
        props: {
            startAt: { type: String, required: true },
            endAt:   { type: String, required: true },
            users:   { type: Array,  default: () => [] },
        },
        data() {
            return {
                reportData: [],
                loading: false,
            };
        },
        computed: {
            rows() {
                return this.reportData.flatMap(userEntry =>
                    (userEntry.days || []).flatMap(day =>
                        (day.programs || []).map(program => ({
                            key: `${userEntry.user?.id || '?'}::${day.date}::${program.program_name || ''}::${program.executable || ''}`,
                            user_name: userEntry.user?.full_name || '',
                            date: day.date,
                            program_name: program.program_name || '',
                            executable: program.executable || '',
                            duration_seconds: Number(program.duration_seconds) || 0,
                        })),
                    ),
                ).sort((a, b) => b.duration_seconds - a.duration_seconds);
            },
            topRows() {
                return this.rows.slice(0, MAX_ROWS);
            },
            remainingRows() {
                return Math.max(0, this.rows.length - MAX_ROWS);
            },
        },
        watch: {
            startAt() { this.fetch(); },
            endAt()   { this.fetch(); },
            users()   { this.fetch(); },
        },
        mounted() {
            this.fetch();
        },
        methods: {
            formatDuration(seconds) {
                return formatDurationString(seconds);
            },
            async fetch() {
                if (!this.startAt || !this.endAt) return;
                this.loading = true;
                try {
                    const { data } = await axios.post('report/daily-program-summary', {
                        start_at: this.startAt,
                        end_at:   this.endAt,
                        users: this.users.length ? this.users : undefined,
                    });
                    this.reportData = data.data || [];
                } catch (e) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn('daily-program-summary widget failed', e);
                    }
                }
                this.loading = false;
            },
        },
    };
</script>

<style lang="scss" scoped>
    .daily-summary-widget {
        background: #fff;
        border: 1px solid #ececec;
        border-radius: 8px;
        padding: 14px 16px;
        flex: 1 1 100%;

        &__header {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        &__title {
            font-size: 0.72rem;
            font-weight: 700;
            color: #888;
            letter-spacing: 0.06em;
        }

        &__link {
            font-size: 0.78rem;
            color: #4e79a7;
            text-decoration: none;

            &:hover { text-decoration: underline; }
        }

        &__empty {
            padding: 18px 0;
            text-align: center;
            color: #aaa;
            font-size: 0.85rem;
        }

        &__table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;

            th {
                text-align: left;
                font-size: 0.7rem;
                font-weight: 600;
                color: #999;
                letter-spacing: 0.04em;
                padding: 0 6px 6px;
                border-bottom: 1px solid #f0f0f0;
            }

            td {
                padding: 6px;
                border-bottom: 1px solid #f9f9f9;
            }
        }

        &__row:last-child td {
            border-bottom: none;
        }

        &__more {
            margin-top: 8px;
            font-size: 0.78rem;
            color: #999;
            text-align: right;

            a { color: #4e79a7; text-decoration: none; }
            a:hover { text-decoration: underline; }
        }
    }

    .user-cell  { font-weight: 600; color: #333; white-space: nowrap; }
    .muted      { color: #aaa; white-space: nowrap; }
    .program-cell { max-width: 220px; }

    .program-name {
        font-weight: 500;
        color: #333;
    }

    .executable {
        margin-left: 4px;
        font-size: 0.75rem;
        color: #aaa;
    }

    .duration {
        font-family: monospace;
        color: #2f2f2f;
        white-space: nowrap;
    }

    .align-right { text-align: right; }
</style>
