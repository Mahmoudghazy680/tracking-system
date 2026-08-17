export const ModuleConfig = {
    routerPrefix: 'software-usage-report',
    loadOrder: 45,
    moduleName: 'SoftwareUsageReport',
};

export function init(context) {
    context.addRoute({
        path: '/report/software-usage',
        name: 'report.software-usage',
        component: () =>
            import(/* webpackChunkName: "report.softwareusage" */ './views/SoftwareUsageReport.vue'),
        meta: {
            auth: true,
        },
    });

    context.addNavbarEntryDropDown({
        label: 'navigation.software-usage-report',
        section: 'navigation.dropdown.reports',
        to: {
            name: 'report.software-usage',
        },
    });

    context.addLocalizationData({
        en: require('./locales/en'),
    });

    return context;
}
