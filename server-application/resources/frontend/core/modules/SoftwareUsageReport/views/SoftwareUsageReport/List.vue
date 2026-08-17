<template>
    <div class="software-usage-list">
        <div v-for="userEntry in reportData" :key="userEntry.user.id" class="user-block">
            <!-- User header -->
            <div class="user-header" @click="toggleUser(userEntry.user.id)">
                <span class="user-header__name">{{ userEntry.user.full_name }}</span>
                <span class="user-header__email">{{ userEntry.user.email }}</span>
                <span class="user-header__total">{{ formatDurationString(userEntry.total_seconds) }}</span>
                <span class="user-header__toggle">{{ isExpanded(userEntry.user.id) ? '▲' : '▼' }}</span>
            </div>

            <div v-if="softwareList(userEntry).length" class="user-software-summary">
                <span
                    v-for="software in topSoftware(userEntry)"
                    :key="`${userEntry.user.id}::${software.name}::${software.executable || ''}::${software.url || ''}`"
                    class="software-pill"
                >
                    {{ software.name }} ({{ formatDurationString(software.duration_seconds) }})
                </span>
                <span v-if="remainingSoftwareCount(userEntry) > 0" class="software-pill software-pill--muted">
                    +{{ remainingSoftwareCount(userEntry) }} more
                </span>
            </div>

            <!-- App rows (collapsible) -->
            <transition name="collapse">
                <div v-if="isExpanded(userEntry.user.id)" class="app-table-wrapper">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>{{ $t('software-usage-report.col_app') }}</th>
                                <th>{{ $t('software-usage-report.col_executable') }}</th>
                                <th>{{ $t('software-usage-report.col_url') }}</th>
                                <th>{{ $t('software-usage-report.col_date') }}</th>
                                <th>{{ $t('software-usage-report.col_duration') }}</th>
                                <th>{{ $t('software-usage-report.col_intervals') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="app in userEntry.apps">
                                <!-- App summary row -->
                                <tr
                                    :key="`${app.app_name}::${app.executable}::${app.url || ''}`"
                                    class="app-row app-row--summary"
                                    @click="toggleApp(userEntry.user.id, app)"
                                >
                                    <td class="app-name">
                                        <span class="toggle-icon">
                                            {{ isAppExpanded(userEntry.user.id, app) ? '▲' : '▼' }}
                                        </span>
                                        {{ app.app_name }}
                                    </td>
                                    <td class="muted">{{ app.executable }}</td>
                                    <td>
                                        <a
                                            v-if="app.url"
                                            :href="app.url"
                                            class="app-link"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            {{ app.url }}
                                        </a>
                                        <span v-else class="muted">—</span>
                                    </td>
                                    <td>—</td>
                                    <td class="duration">{{ formatDurationString(app.total_seconds) }}</td>
                                    <td>{{ app.days.reduce((s, d) => s + d.interval_count, 0) }}</td>
                                </tr>
                                <!-- Per-day detail rows -->
                                <template v-if="isAppExpanded(userEntry.user.id, app)">
                                    <tr
                                        v-for="day in app.days"
                                        :key="`${app.app_name}::${app.executable}::${app.url || ''}::${day.date}`"
                                        class="day-row"
                                    >
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td>{{ day.date }}</td>
                                        <td>{{ formatDurationString(day.duration_seconds) }}</td>
                                        <td>{{ day.interval_count }}</td>
                                    </tr>
                                </template>
                            </template>
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
        name: 'SoftwareUsageList',
        props: {
            reportData: {
                type: Array,
                required: true,
            },
        },
        data() {
            return {
                expandedUsers: {},
                expandedApps: {},
            };
        },
        watch: {
            reportData() {
                // Auto-expand all users on first load
                const expanded = {};
                this.reportData.forEach(u => { expanded[u.user.id] = true; });
                this.expandedUsers = expanded;
                this.expandedApps = {};
            },
        },
        methods: {
            formatDurationString,
            softwareList(userEntry) {
                if (Array.isArray(userEntry.software) && userEntry.software.length) {
                    return userEntry.software;
                }

                return (userEntry.apps || []).map(app => ({
                    name: app.app_name,
                    executable: app.executable,
                    url: app.url,
                    duration_seconds: app.total_seconds,
                }));
            },
            topSoftware(userEntry) {
                return this.softwareList(userEntry).slice(0, 5);
            },
            remainingSoftwareCount(userEntry) {
                const total = this.softwareList(userEntry).length;
                return Math.max(0, total - 5);
            },
            isExpanded(userId) {
                return !!this.expandedUsers[userId];
            },
            toggleUser(userId) {
                this.$set(this.expandedUsers, userId, !this.expandedUsers[userId]);
            },
            appKey(userId, app) {
                return `${userId}::${app.app_name}::${app.executable || ''}::${app.url || ''}`;
            },
            isAppExpanded(userId, app) {
                return !!this.expandedApps[this.appKey(userId, app)];
            },
            toggleApp(userId, app) {
                const key = this.appKey(userId, app);
                this.$set(this.expandedApps, key, !this.expandedApps[key]);
            },
        },
    };
</script>

<style lang="scss" scoped>
    .user-block {
        margin-bottom: 8px;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        overflow: hidden;
    }

    .user-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: #f8f8f8;
        cursor: pointer;
        user-select: none;

        &:hover {
            background: #f0f0f0;
        }

        &__name {
            font-weight: bold;
            flex: 0 0 auto;
        }

        &__email {
            color: #888;
            font-size: 0.85rem;
            flex: 1 1 auto;
        }

        &__total {
            font-weight: bold;
            color: #333;
        }

        &__toggle {
            font-size: 0.7rem;
            color: #aaa;
        }
    }

    .app-table-wrapper {
        overflow-x: auto;
    }

    .user-software-summary {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding: 8px 16px;
        border-top: 1px solid #efefef;
        border-bottom: 1px solid #efefef;
        background: #fcfcfc;
    }

    .software-pill {
        display: inline-flex;
        align-items: center;
        border: 1px solid #dcdcdc;
        border-radius: 999px;
        padding: 2px 10px;
        font-size: 0.75rem;
        color: #444;
        background: #fff;
    }

    .software-pill--muted {
        color: #777;
    }

    .app-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;

        th {
            text-align: left;
            padding: 8px 12px;
            background: #fafafa;
            border-bottom: 1px solid #e5e5e5;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #666;
        }

        td {
            padding: 7px 12px;
            border-bottom: 1px solid #f0f0f0;
        }
    }

    .app-row--summary {
        cursor: pointer;
        background: #fff;

        &:hover {
            background: #f9f9ff;
        }
    }

    .app-name {
        font-weight: 600;
    }

    .day-row td {
        background: #fafafa;
        padding-left: 36px;
        font-size: 0.85rem;
        color: #555;
    }

    .muted {
        color: #999;
        font-size: 0.82rem;
    }

    .app-link {
        color: #2b6cb0;
        font-size: 0.82rem;
        word-break: break-all;
    }

    .duration {
        font-variant-numeric: tabular-nums;
    }

    .toggle-icon {
        font-size: 0.6rem;
        color: #bbb;
        margin-right: 6px;
    }

    .collapse-enter-active,
    .collapse-leave-active {
        transition: opacity 0.2s ease;
    }

    .collapse-enter,
    .collapse-leave-to {
        opacity: 0;
    }
</style>
