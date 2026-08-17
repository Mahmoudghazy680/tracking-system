import axios from 'axios';
import httpInterceptor from '@/helpers/httpInterceptor';

axios.defaults.baseURL = `${window.location.origin}/api/`;
axios.defaults.headers.common['X-REQUESTED-WITH'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-Tracker-CLIENT'] = window.location.host;
axios.defaults.headers.common['X-Tracker-VERSION'] = process.env.MIX_APP_VERSION;

httpInterceptor.setup();

export default axios;
