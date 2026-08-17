<template>
    <div class="top-users-tables">
        <div class="users-table-card">
            <div class="users-table-card__title">TOP USERS BY ACTIVITY TIME (hours)</div>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Duration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(user, i) in topActivity" :key="user.user_id">
                        <td class="rank">{{ i + 1 }}</td>
                        <td>{{ user.full_name }}</td>
                        <td class="duration">{{ toHours(user.total_seconds) }}</td>
                        <td class="bar-col">
                            <div
                                class="bar"
                                :style="{ width: barWidth(user.total_seconds, maxActivity) + '%' }"
                            ></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="users-table-card">
            <div class="users-table-card__title">TOP USERS BY INTERNET BROWSING TIME (hours)</div>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Duration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(user, i) in topBrowsing" :key="user.user_id">
                        <td class="rank">{{ i + 1 }}</td>
                        <td>{{ user.full_name }}</td>
                        <td class="duration">{{ toHours(user.total_seconds) }}</td>
                        <td class="bar-col">
                            <div
                                class="bar"
                                :style="{ width: barWidth(user.total_seconds, maxBrowsing) + '%' }"
                            ></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'TopUsersTable',
        props: {
            topActivity: {
                type: Array,
                default: () => [],
            },
            topBrowsing: {
                type: Array,
                default: () => [],
            },
        },
        computed: {
            maxActivity() {
                return this.topActivity.reduce((m, u) => Math.max(m, u.total_seconds), 0);
            },
            maxBrowsing() {
                return this.topBrowsing.reduce((m, u) => Math.max(m, u.total_seconds), 0);
            },
        },
        methods: {
            toHours(seconds) {
                return (seconds / 3600).toFixed(2);
            },
            barWidth(seconds, max) {
                if (!max) return 0;
                return Math.round((seconds / max) * 100);
            },
        },
    };
</script>

<style lang="scss" scoped>
    .top-users-tables {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
    }

    .users-table-card {
        flex: 1;
        min-width: 300px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 16px 20px;

        &__title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #444;
            margin-bottom: 12px;
        }
    }

    .users-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;

        th, td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            color: #888;
            font-weight: 500;
        }

        .rank {
            color: #aaa;
            width: 28px;
        }

        .duration {
            font-variant-numeric: tabular-nums;
            color: #333;
            white-space: nowrap;
        }

        .bar-col {
            width: 120px;
        }

        .bar {
            height: 8px;
            background: #4e79a7;
            border-radius: 4px;
            min-width: 2px;
        }
    }
</style>
