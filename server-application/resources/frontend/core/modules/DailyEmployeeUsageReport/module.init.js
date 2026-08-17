export const ModuleConfig = {
    routerPrefix: 'daily-employee-usage-report',
    loadOrder: 49,
    moduleName: 'DailyEmployeeUsageReport',
};

export function init(context) {
    context.addRoute({
        path: '/report/daily-employee-usage',
        name: 'report.daily-employee-usage',
        component: () =>
            import(/* webpackChunkName: "report.dailyemployeeusage" */ './views/DailyEmployeeUsageReport.vue'),
        meta: {
            auth: true,
        },
    });

    context.addNavbarEntryDropDown({
        label: 'navigation.daily-employee-usage-report',
        section: 'navigation.dropdown.reports',
        to: {
            name: 'report.daily-employee-usage',
        },
    });

    context.addLocalizationData({
        en: require('./locales/en'),
    });

    return context;
}
