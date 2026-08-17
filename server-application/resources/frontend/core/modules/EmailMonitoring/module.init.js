export const ModuleConfig = {
    routerPrefix: 'email-monitoring',
    loadOrder: 48,
    moduleName: 'EmailMonitoring',
};

export function init(context) {
    context.addRoute({
        path: '/report/email-monitoring',
        name: 'report.email-monitoring',
        component: () =>
            import(/* webpackChunkName: "report.emailmonitoring" */ './views/EmailMonitoring.vue'),
        meta: {
            auth: true,
        },
    });

    context.addNavbarEntryDropDown({
        label: 'navigation.email-monitoring',
        section: 'navigation.dropdown.reports',
        to: {
            name: 'report.email-monitoring',
        },
    });

    context.addLocalizationData({
        en: require('./locales/en'),
    });

    return context;
}
