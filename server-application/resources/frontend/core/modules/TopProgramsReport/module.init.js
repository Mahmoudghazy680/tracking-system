export const ModuleConfig = {
    routerPrefix: 'top-programs-report',
    loadOrder: 46,
    moduleName: 'TopProgramsReport',
};

export function init(context) {
    context.addRoute({
        path: '/report/top-programs',
        name: 'report.top-programs',
        component: () =>
            import(/* webpackChunkName: "report.topprograms" */ './views/TopProgramsReport.vue'),
        meta: {
            auth: true,
        },
    });

    context.addNavbarEntryDropDown({
        label: 'navigation.top-programs-report',
        section: 'navigation.dropdown.reports',
        to: {
            name: 'report.top-programs',
        },
    });

    context.addLocalizationData({
        en: require('./locales/en'),
    });

    return context;
}
