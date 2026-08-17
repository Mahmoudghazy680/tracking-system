export const ModuleConfig = {
    routerPrefix: 'daily-program-summary-report',
    loadOrder: 48,
    moduleName: 'DailyProgramSummaryReport',
};

export function init(context) {
    context.addRoute({
        path: '/report/daily-program-summary',
        name: 'report.daily-program-summary',
        component: () =>
            import(/* webpackChunkName: "report.dailyprogramsummary" */ './views/DailyProgramSummaryReport.vue'),
        meta: {
            auth: true,
        },
    });

    context.addNavbarEntryDropDown({
        label: 'navigation.daily-program-summary-report',
        section: 'navigation.dropdown.reports',
        to: {
            name: 'report.daily-program-summary',
        },
    });

    context.addLocalizationData({
        en: require('./locales/en'),
    });

    return context;
}
