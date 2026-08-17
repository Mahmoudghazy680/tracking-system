<template>
    <div class="stats-cards">
        <div class="stats-card">
            <div class="stats-card__label">TOTAL ACTIVITY TIME</div>
            <div class="stats-card__value">
                <span class="stats-card__unit-val">{{ days }}</span>
                <span class="stats-card__unit">DAYS</span>
                <span class="stats-card__sep">:</span>
                <span class="stats-card__unit-val">{{ hours }}</span>
                <span class="stats-card__unit">HOURS</span>
                <span class="stats-card__sep">:</span>
                <span class="stats-card__unit-val">{{ minutes }}</span>
                <span class="stats-card__unit">MINUTES</span>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card__label">TOTAL INTERNET BROWSING TIME</div>
            <div class="stats-card__value">
                <span class="stats-card__unit-val">{{ browseDays }}</span>
                <span class="stats-card__unit">DAYS</span>
                <span class="stats-card__sep">:</span>
                <span class="stats-card__unit-val">{{ browseHours }}</span>
                <span class="stats-card__unit">HOURS</span>
                <span class="stats-card__sep">:</span>
                <span class="stats-card__unit-val">{{ browseMinutes }}</span>
                <span class="stats-card__unit">MINUTES</span>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        name: 'StatsCards',
        props: {
            totalActivitySeconds: {
                type: Number,
                default: 0,
            },
            totalBrowsingSeconds: {
                type: Number,
                default: 0,
            },
        },
        computed: {
            days() {
                return String(Math.floor(this.totalActivitySeconds / 86400)).padStart(2, '0');
            },
            hours() {
                return String(Math.floor((this.totalActivitySeconds % 86400) / 3600)).padStart(2, '0');
            },
            minutes() {
                return String(Math.floor((this.totalActivitySeconds % 3600) / 60)).padStart(2, '0');
            },
            browseDays() {
                return String(Math.floor(this.totalBrowsingSeconds / 86400)).padStart(2, '0');
            },
            browseHours() {
                return String(Math.floor((this.totalBrowsingSeconds % 86400) / 3600)).padStart(2, '0');
            },
            browseMinutes() {
                return String(Math.floor((this.totalBrowsingSeconds % 3600) / 60)).padStart(2, '0');
            },
        },
    };
</script>

<style lang="scss" scoped>
    .stats-cards {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .stats-card {
        flex: 1;
        min-width: 280px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 24px 32px;
        text-align: center;

        &__label {
            font-size: 0.85rem;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }

        &__value {
            display: flex;
            justify-content: center;
            align-items: baseline;
            gap: 4px;
            flex-wrap: wrap;
        }

        &__unit-val {
            font-size: 2.4rem;
            font-weight: 700;
            color: #4fc3f7;
            font-family: monospace;
        }

        &__unit {
            font-size: 0.65rem;
            color: #aaa;
            letter-spacing: 0.05em;
            align-self: flex-end;
            padding-bottom: 4px;
        }

        &__sep {
            font-size: 2rem;
            font-weight: 700;
            color: #4fc3f7;
            margin: 0 2px;
        }
    }
</style>
