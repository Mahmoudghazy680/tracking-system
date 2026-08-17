export const ModuleConfig = {
    routerPrefix: 'top-websites-report',
    loadOrder: 47,
    moduleName: 'TopWebsitesReport',
};

export function init(context) {
    context.addRoute({
        path: '/report/top-websites',
        name: 'report.top-websites',
        component: () =>
            import(/* webpackChunkName: "report.topwebsites" */ './views/TopWebsitesReport.vue'),
        meta: {
            auth: true,
        },
    });

    context.addNavbarEntryDropDown({
        label: 'navigation.top-websites-report',
        section: 'navigation.dropdown.reports',
        to: {
            name: 'report.top-websites',
        },
    });

    context.addLocalizationData({
        en: require('./locales/en'),
    });

    return context;
}
