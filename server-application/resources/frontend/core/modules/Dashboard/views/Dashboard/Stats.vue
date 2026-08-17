<template>
    <div class="dashboard-stats">
        <!-- Date filter -->
        <div class="controls-row">
            <div class="controls-row__item">
                <label class="controls-filter-label">FILTER BY DATES</label>
                <select v-model="preset" class="at-select" @change="onPresetChange">
                    <option value="last14">Last 14 days</option>
                    <option value="last30">Last 30 days</option>
                    <option value="thisMonth">This month</option>
                    <option value="custom">Custom…</option>
                </select>
            </div>
            <div class="controls-row__item">
                <label class="controls-filter-label">DATE/TIME FROM</label>
                <input v-model="startAt" type="date" class="at-input__original" @change="fetchStats" />
            </div>
            <div class="controls-row__item">
                <label class="controls-filter-label">DATE/TIME TO</label>
                <input v-model="endAt" type="date" class="at-input__original" @change="fetchStats" />
            </div>
            <div class="controls-row__item">
                <label class="controls-filter-label">TOP RECORDS</label>
                <select v-model="limit" class="at-select" @change="fetchStats">
                    <option :value="5">5</option>
                    <option :value="10">10</option>
                    <option :value="15">15</option>
                </select>
            </div>
        </div>

        <preloader v-if="loading" />

        <template v-else-if="stats">
            <!-- Stats cards row -->
            <StatsCards
                :total-activity-seconds="stats.total_activity_seconds"
                :total-browsing-seconds="stats.total_browsing_seconds"
            />

            <!-- Donut charts row -->
            <div class="charts-row">
                <TopProgramsChart :programs="stats.top_programs" />
                <TopWebsitesChart :websites="stats.top_websites" />
            </div>

            <!-- Top users tables -->
            <TopUsersTable
                :top-activity="stats.top_users_by_activity"
                :top-browsing="stats.top_users_by_browsing"
            />

            <!-- Daily program summary widget -->
            <DailyProgramSummaryWidget
                :start-at="startAt + 'T00:00:00'"
                :end-at="endAt + 'T23:59:59'"
            />
        </template>

        <div v-else class="no-data">{{ $t('message.no_data') }}</div>
    </div>
</template>

<script>
    import DashboardStatsService from '_internal/Dashboard/services/dashboard-stats.service';
    import StatsCards from '_internal/Dashboard/components/StatsCards';
    import TopProgramsChart from '_internal/Dashboard/components/TopProgramsChart';
    import TopWebsitesChart from '_internal/Dashboard/components/TopWebsitesChart';
    import TopUsersTable from '_internal/Dashboard/components/TopUsersTable';
    import DailyProgramSummaryWidget from '_internal/Dashboard/components/DailyProgramSummaryWidget';
    import Preloader from '@/components/Preloader';
    import { mapGetters } from 'vuex';

    const statsService = new DashboardStatsService();

    function toDateString(d) {
        return d.toISOString().slice(0, 10);
    }

    export default {
        name: 'DashboardStats',
        components: {
            StatsCards,
            TopProgramsChart,
            TopWebsitesChart,
            TopUsersTable,
            DailyProgramSummaryWidget,
            Preloader,
        },
        data() {
            const now = new Date();
            const twoWeeksAgo = new Date(now);
            twoWeeksAgo.setDate(now.getDate() - 13);
            return {
                loading: false,
                stats: null,
                preset: 'last14',
                startAt: toDateString(twoWeeksAgo),
                endAt: toDateString(now),
                limit: 10,
            };
        },
        computed: {
            ...mapGetters('user', ['companyData']),
        },
        mounted() {
            this.fetchStats();
        },
        methods: {
            onPresetChange() {
                const now = new Date();
                if (this.preset === 'last14') {
                    const from = new Date(now);
                    from.setDate(now.getDate() - 13);
                    this.startAt = toDateString(from);
                    this.endAt = toDateString(now);
                } else if (this.preset === 'last30') {
                    const from = new Date(now);
                    from.setDate(now.getDate() - 29);
                    this.startAt = toDateString(from);
                    this.endAt = toDateString(now);
                } else if (this.preset === 'thisMonth') {
                    this.startAt = toDateString(new Date(now.getFullYear(), now.getMonth(), 1));
                    this.endAt = toDateString(now);
                }
                this.fetchStats();
            },
            async fetchStats() {
                if (!this.startAt || !this.endAt) return;
                this.loading = true;
                try {
                    const { data } = await statsService.getStats(
                        this.startAt + 'T00:00:00',
                        this.endAt + 'T23:59:59',
                        [],
                        this.limit,
                    );
                    this.stats = data.data;
                } catch (e) {
                    if (process.env.NODE_ENV === 'development') {
                        console.warn('dashboard-stats failed', e);
                    }
                }
                this.loading = false;
            },
        },
    };
</script>

<style lang="scss" scoped>
    .dashboard-stats {
        padding: 8px 0;
    }

    .controls-row {
        display: flex;
        gap: 16px;
        align-items: flex-end;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .controls-filter-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        color: #888;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }

    .at-select {
        padding: 6px 10px;
        border: 1px solid #d9d9d9;
        border-radius: 4px;
        background: #fff;
        font-size: 0.9rem;
    }

    .charts-row {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #888;
    }
</style>
