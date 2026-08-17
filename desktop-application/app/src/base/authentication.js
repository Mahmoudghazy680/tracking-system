const EventEmitter = require('events');
const axios = require('axios');
const os = require('os');
const { app } = require('electron');
const api = require('./api');
const config = require('./config');
const Log = require('../utils/log');
const keychain = require('../utils/keychain');
const { UIError } = require('../utils/errors');
const db = require('../models');
const OfflineUser = require('../controller/offline-user');
const OfflineMode = require('./offline-mode');
const Sentry = require('../utils/sentry');
const trackingFeatures = require('../controller/tracking-features');

const log = new Log('AuthenticationProvider');

/**
 * @typedef   {Object}  Token
 * @property  {String}  token  Token string
 * @property  {String}  type   Token type (i.e., bearer)
 */

/**
 * @typedef {Object} WebToDesktopSSOParameters
 * @property {String} baseUrl Base URL to the selected API instance
 * @property {String} token   Intermediate authentication token
 */

/**
 * Variable, contains current user properties
 * @type {Object|null}
 */
let _currentUser = null;

/**
 * Authentication events
 * @type {EventEmitter}
 */
module.exports.events = new EventEmitter();

// Save company identifier to Sentry
module.exports.events.once('company-instance-fetched', cid => Sentry.configureScope(s => s.setTag('companyIdentifier', cid)));

/**
 * Fetches company identifier
 * @async
 * @returns {String|null} Company identifier
 */
const fetchCompanyIdentifier = async () => {

  // Fetch company identifier submittable to Sentry
  try {

    const companyDetails = await api.company.about();
    const companyIdentifier = companyDetails.app ? companyDetails.app.instance_id : null;
    if (companyIdentifier)
      module.exports.events.emit('company-instance-fetched', companyIdentifier);

    return companyIdentifier || null;

  } catch (_) {

    return null;

  }

};

/**
 * Resolves current Windows principal in DOMAIN\\username format when possible
 * @returns {String}
 */
const resolveWindowsPrincipal = () => {

  if (process.platform !== 'win32')
    return '';

  let username = '';
  try {

    username = (os.userInfo().username || '').trim();

  } catch (_) {

    return '';

  }

  if (!username)
    return '';

  if (username.includes('\\') || username.includes('@'))
    return username;

  const domain = (process.env.USERDOMAIN || '').trim();
  if (!domain)
    return username;

  return `${domain}\\${username}`;

};

/**
 * Builds API URL for Windows username authentication endpoint
 * @returns {String}
 */
const buildWindowsAuthUrl = () => {

  const baseUrl = typeof api.baseUrl === 'string' && api.baseUrl.length > 0
    ? api.baseUrl
    : config.authentication.defaultHostname;

  const url = new URL(baseUrl);
  const normalizedPath = (url.pathname || '').replace(/\/+$/, '');
  const basePath = /\/api$/i.test(normalizedPath)
    ? normalizedPath
    : `${normalizedPath}/api`;

  url.pathname = `${basePath}/auth/login-by-windows-user`;
  url.search = '';
  url.hash = '';

  return url.toString();

};

/**
 * Returns SSO parameters from application protocol call if they are presented
 * @param {Object} [args] Arguments (process.argv will be used, if args not defined)
 * @returns {WebToDesktopSSOParameters|null}
 */
module.exports.getSSOFromProtocol = args => {

  // Trying to extract SSO URL by protocol+action preamble
  let ssoUrl = Array.from(args || process.argv).filter(arg => arg.indexOf('Tracker://authenticate') === 0);
  if (!ssoUrl || ssoUrl.length === 0)
    return null;

  // Always select the first occurence, even if more than one are presented
  [ssoUrl] = ssoUrl;

  try {

    // Parse provided URL
    ssoUrl = new URL(ssoUrl);

    // "url" & "token" fields are expected as query params
    const baseUrl = ssoUrl.searchParams.get('url');
    const token = ssoUrl.searchParams.get('token');

    if (!baseUrl || !token)
      return null;

    return { token, baseUrl };

  } catch (err) {

    return null;

  }

};

/**
 * Checks is this is Tracker API instance
 * @return {Promise<Boolean>} True, if it's working Tracker instance, False otherwise
 */
module.exports.isTrackerInstance = async () => {

  try {

    return await api.isTrackerInstance();

  } catch (err) {

    return false;

  }

};

/**
 * Makes authentication request
 * @param  {String}                    email     Email
 * @param  {String}                    password  Password
 * @return {Promise<Object|UIError>}             User object if succeed, UIError otherwise
 */
module.exports.authenticate = async (email, password) => {

  // Checking input parameters
  if (typeof email !== 'string' || typeof password !== 'string')
    throw new UIError(400, 'Incorrect credentials given', 'EAUTH000');

  // Authenticating using library function
  let authenticationResponse = {};
  try {

    authenticationResponse = await api.authentication.login(email, password);

  } catch (error) {

    // Checking is it a system error
    if (!error.isApiError) {

      // Log it
      log.error('Request error occured during authentication', error);
      throw new UIError(500, 'Request to server was failed', 'EAUTH500', error);

    }

      log.error('Internal Server Error', error);
      Log.captureApiError('Unknown status code received during authentication request', error);
      throw new UIError(500, error.message, 'EAUTH500', error);

  }

  // Persist user and tracking features before emitting user-fetched so
  // auto-start tracking reads the latest monitoring flags.
  _currentUser = authenticationResponse.user;
  await OfflineUser.setProperties(authenticationResponse.user);
  await trackingFeatures.updateFromUser(authenticationResponse.user);
  await OfflineUser.commit();
  module.exports.events.emit('user-fetched', _currentUser);

  // Saving token into system keychain
  try {

    await keychain.saveToken(
      authenticationResponse.token.token,
      authenticationResponse.token.tokenType,
      authenticationResponse.token.tokenExpire,
    );

  } catch (error) {

    log.error('Error occured during saving token into system keychain', error);
    throw new UIError(500, 'Internal error occured', 'EAUTH500', error);

  }

  // Fetch company identifier
  await fetchCompanyIdentifier();

  // Fire authenticated event
  module.exports.events.emit('authenticated');

  log.debug(`Account ${email} successfully authenticated`);
  return authenticationResponse;

};

/**
 * Token getter
 * @return {Promise<Token|UIError>} Returns Token object if success, null otherwise
 */
module.exports.getToken = async () => {

  // Trying to get token
  const token = await keychain.getSavedToken();

  try {

    // Checking if token exists in system keychain
    if (token) {

      // Obtaininig hostname
      const savedCredentials = await keychain.getSavedCredentials();
      if (!savedCredentials || typeof savedCredentials.hostname !== 'string') {

        log.warning('Saved token detected without a persisted hostname');
        throw new UIError(802, 'There is no available ways to fetch token', 'EAUTH001');

      }

      const { hostname } = savedCredentials;
      await this.setHostname(hostname, true);
      return token;

    }

    if (config.authentication.domainOnlyEnabled) {

      log.warning('Domain-only authentication mode enabled, saved credentials fallback is disabled');
      throw new UIError(802, 'There is no available ways to fetch token', 'EAUTH001');

    }

    // Trying to get saved credentials
    const credentials = await keychain.getSavedCredentials();

    // Checking if saved credentials exists
    if (
      !credentials
      || typeof credentials.email !== 'string'
      || typeof credentials.password !== 'string'
    ) {

      log.warning('Cannot find the way to fetch token automatically');
      throw new UIError(802, 'There is no available ways to fetch token', 'EAUTH001');

    }

    // Set API hostname based on the saved credentials
    await api.credentialsProvider.set();

    // Make authentication request
    const authRequest = await this.authenticate(credentials.email, credentials.password);

    // If it's succeed, token is already saved in keychain and we can simply return it
    log.debug('Successfully obtained new token using credentials');
    return authRequest;

  } catch (error) {

    // Transparently pass all UIErrors
    if (error instanceof UIError)
      throw error;

    // Catch other errors
    log.error('Error occurred during token getting', error);
    throw new UIError(500, 'Unhandled system error occured', 'EAUTH502', error);

  }

};

/**
 * Returns current user parameters
 * @return {Object|null} Returns user object if success, null otherwise
 */
module.exports.getCurrentUser = async (force = false) => {

  try {

    // Checking if current user exists in buffer
    if (_currentUser && _currentUser.id !== 'undefined' && force === false)
      return _currentUser;

    let user = null;

    // Trying to fetch user from API
    try {

      user = await api.authentication.me();

      await fetchCompanyIdentifier();

    } catch (err) {

      // Perform logout operation if user is disabled or removed
      if (err.isApiError && err.code === 'authorization.user_disabled') {

        log.warning('Current user is disabled or removed from server, logging out...');
        await module.exports.logout();
        app.quit();

      }

      console.log(err);

      log.warning('Failed to fetch user from API, seems like that we\'re offline');

    }

    // Saving user if it is successfully retrieved from API
    if (user) {

      _currentUser = user;
      OfflineUser.setProperties(user);
      await trackingFeatures.updateFromUser(user);
      await OfflineUser.commit();
      module.exports.events.emit('user-fetched', _currentUser);
      return user;

    }

    // Fetching user properties from local storage
    await OfflineUser.fetch();
    if (!OfflineUser.user.id)
      throw new UIError(803, 'There is no available ways to fetch user properties', 'EAUTH0803');

    log.debug('Fetched user from local storage');
    _currentUser = OfflineUser.user;
    module.exports.events.emit('user-fetched', _currentUser);
    OfflineMode.trigger();
    return _currentUser;

  } catch (error) {

    // Transparently pass all UIErrors
    if (error instanceof UIError)
      throw error;

    // Handle other errors
    log.error('Error occured during current user getting', error);
    throw new UIError(500, 'Unhandled system error occured', 'EAUTH504', error);

  }

};

/**
 * Checking is authentication required
 * @return {Promise<Boolean>} Returns true / false accordingly to the auth status
 */
module.exports.isAuthenticationRequired = async () => {

  if (config.authentication.domainOnlyEnabled) {

    try {

      await this.getToken();
      _currentUser = await this.getCurrentUser();
      module.exports.events.emit('user-fetched', _currentUser);
      return false;

    } catch (_) {

      const autoLoginSucceeded = await module.exports.tryWindowsAutoAuthentication();
      return !autoLoginSucceeded;

    }

  }

  try {

    // Token getting routine should do all the job by itself
    await this.getToken();

    // Check token via real API request
    _currentUser = await this.getCurrentUser();
    module.exports.events.emit('user-fetched', _currentUser);

    // .. if it's done, we're good to go w/out authentication
    return false;

  } catch (error) {

    const shouldAttemptWindowsAutoLogin = (
      (error instanceof UIError && error.code === 802)
      || (error instanceof UIError && error.code === 803)
      || (error.isApiError && error.statusCode === 403)
    );

    if (shouldAttemptWindowsAutoLogin) {

      const autoLoginSucceeded = await module.exports.tryWindowsAutoAuthentication();
      if (autoLoginSucceeded)
        return false;

      return true;

    }

    // Filter expected errors
    // Log all other
    log.error('Error occured during authentication requirement check', error);

    // Require reauthentication
    return true;

  }

};

/**
 * Performs user-friendly authentication
 * @param  {String}                  email         User's email
 * @param  {String}                  password      User's password
 * @param  {Boolean}                 [save=true]   Should we save this credentials into the system keychain?
 * @return {Promise<Object|UIError>}               Returns User object if succeed, UIError otherwise
 */
module.exports.userAuthentication = async (email, password, save = true) => {

  if (config.authentication.domainOnlyEnabled)
    throw new UIError(403, 'Desktop client is configured for domain authentication only', 'EAUTHDOMAINONLY');

  // Running authentication routine
  try {

    // Performing authentication
    const authRequest = await this.authenticate(email, password);

    // Save credentials if neccessary
    if (save)
      await keychain.saveCredentials({ hostname: api.baseUrl, email, password });
    else
      await keychain.saveCredentials({ hostname: api.baseUrl, email: null, password: null });

    // Return user object from authentication request
    return authRequest.user;

  } catch (error) {

    // If it's an UIError - simply pass to next handler
    if (error instanceof UIError)
      throw error;

    // Otherwise, log this issue and return internal error
    log.error('Error occured during user authentication', error);
    throw new UIError(500, 'Internal error occured during user authentication', 'EAUTH501', error);

  }

};

/**
 * Sets remote API hostname
 * @param   {String} hostname  Hostname
 * @param   {Boolean} [force=false]
 * @returns {Boolean|UIError} Boolean(true) if success, UIError otherwise
 */
module.exports.setHostname = async (hostname, force = false) => {

  if (typeof hostname !== 'string')
    throw new UIError(400, 'Incorrect hostname given', 'EAUTH001');


  try {

    return await api.setBaseUrl(hostname, force);

  } catch (error) {

    if (error.isNetworkError) {

      // Log it
      log.error('Incorrect hostname, please, check your input', error);
      throw new UIError(500, 'Incorrect hostname, please, check your input', 'EAUTH500', error);

    }

    if (error.isApiError && error.statusCode === 500) {

      // Log it
      log.error('Server error occured', error);
      throw new UIError(500, error.message, 'EAUTH500', error);

    }

    if (error.isApiError && error.statusCode === 404) {

      // Log it
      log.error('PIN-Tracker is not found on this hostname', error);
      throw new UIError(404, 'PIN-Tracker is not found on this hostname', 'EAUTH404', error);

    }

    // Catch other errors
    log.error('Unknown error occured', error);
    throw new UIError(500, 'Unknown error occured', 'EAUTH502', error);
  }


};

/**
 * Poke the server with a stick to check, if it is alive
 */
module.exports.ping = async () => {

  try {

    return await api.ping();

  } catch (error) {

    log.error('Unexpected error with server checkout', error);
    return false;

  }

};

/**
 * Logout
 * @return {Promise<Boolean|Error>} Returns True if succeed, error otherwise
 */
module.exports.logout = async () => {

  try {

    // Logout on API side
    try {

      await api.authentication.logout();
      log.debug('Successfully logged out from server');

    } catch (error) {

      // Ignore errors during logout
      // Kinda of temporary fix
      log.warning(`Error occured during logout request (ignoring): ${error}`);

    }

    // Removing system keychain entries
    await keychain.removeToken();
    await keychain.removeSavedCredentials();
    _currentUser = null;

    // Flushing database
    const toDestroy = Object.values(db.db.sequelize.models).map(model => {

      if (model.tableName === 'SequelizeMeta')
        return false;

      return model.destroy({ truncate: true, force: true });

    });

    await Promise.all(toDestroy);

    // Fire logout event
    module.exports.events.emit('logged-out');

    return true;

  } catch (error) {

    // Transparently pass all UIErrors
    if (error instanceof UIError)
      throw error;

    // Handle real errors
    const crypto = require("crypto");
    error.context = {};
    error.context.client_trace_id = crypto.randomUUID();
    log.error('Error occured during logout', error);
    throw new UIError(500, 'Unhandled system error occured', 'EAUTH503', error);

  }

};

/**
 * Returns a single-click redirection URL
 * @async
 * @returns {Promise.<Error|String>} Redirection URL or Error
 */
module.exports.getSingleClickRedirection = async () => api.authentication.getSingleClickRedirection();

/**
 * Attempts silent authentication using current Windows username
 * @async
 * @returns {Promise<Boolean>} True if authenticated successfully, false otherwise
 */
module.exports.tryWindowsAutoAuthentication = async () => {

  if (process.platform !== 'win32')
    return false;

  if (
    typeof config.authentication.windowsAuthSecret !== 'string'
    || config.authentication.windowsAuthSecret.trim().length === 0
  ) {

    log.debug('Windows auto-login skipped: AT_WINDOWS_AUTH_SECRET is not configured');
    return false;

  }

  try {

    const savedCredentials = await keychain.getSavedCredentials();
    const hostname = savedCredentials && typeof savedCredentials.hostname === 'string'
      ? savedCredentials.hostname
      : config.authentication.defaultHostname;

    await module.exports.setHostname(hostname, true);

  } catch (error) {

    log.warning('Windows auto-login skipped: unable to resolve API hostname', error);
    return false;

  }

  const windowsUsername = resolveWindowsPrincipal();

  if (typeof windowsUsername !== 'string' || windowsUsername.trim().length === 0)
    return false;

  try {

    const authenticationResponse = await axios.post(buildWindowsAuthUrl(), {
      windows_username: windowsUsername.trim(),
      domain_user: windowsUsername.trim(),
      device_secret: config.authentication.windowsAuthSecret,
    });

    const payload = authenticationResponse
      && authenticationResponse.data
      && authenticationResponse.data.data
      ? authenticationResponse.data.data
      : null;

    if (
      !payload
      || typeof payload.access_token !== 'string'
      || typeof payload.user !== 'object'
      || payload.user === null
    ) {

      log.warning('Windows auto-login skipped: malformed authentication payload');
      return false;

    }

    _currentUser = payload.user;
    await OfflineUser.setProperties(payload.user);
    await trackingFeatures.updateFromUser(payload.user);
    await OfflineUser.commit();
    module.exports.events.emit('user-fetched', _currentUser);

    await keychain.saveToken(
      payload.access_token,
      payload.token_type || 'bearer',
      payload.expires_in || null,
    );
    await keychain.saveCredentials({ hostname: api.baseUrl, email: null, password: null });

    await fetchCompanyIdentifier();

    module.exports.events.emit('authenticated');

    log.debug(`Successfully authenticated via Windows username: ${windowsUsername}`);
    return true;

  } catch (error) {

    // Invalid mapping/secret should fall back to normal login quietly.
    if (error && error.response && [400, 401, 403, 422].includes(error.response.status))
      return false;

    log.error('Unexpected error during Windows auto-login', error);
    return false;

  }

};

/**
 * Returns the current Windows principal for UI diagnostics
 * @returns {String}
 */
module.exports.getCurrentWindowsPrincipal = () => resolveWindowsPrincipal();

/**
 * Makes authentication request
 * @async
 * @param  {WebToDesktopSSOParameters} params SSO parameters
 * @return {Promise.<Object|UIError>}
 */
module.exports.authenticateSSO = async params => {

  // Authenticating using library function
  let authenticationResponse = {};
  try {

    authenticationResponse = await api.authentication.authenticateViaSSO(params.token);

  } catch (error) {

    // Checking is it a system error
    if (!error.isApiError) {

      // Log it
      log.error('Request error occured during authentication', error);
      throw new UIError(500, 'Request to server was failed', 'EAUTH500');

    }

    // Throw different errors according to the status codes in response
    switch (error.statusCode) {

      case 401:
        throw new UIError(400, 'Incorrect credentials given', 'EAUTH000');
      case 403:
        throw new UIError(403, 'Invalid credentials given', 'EAUTH001');
      default:
        log.error(`EAUTH506-${error.statusCode}`, 'Unspecified status code received from server during authentication', true);
        Log.captureApiError('Unknown status code received during authentication request', error);
        throw new UIError(500, 'Request to server was failed', 'EAUTH500');

    }

  }

  // Saving hostname
  await keychain.saveCredentials({ hostname: api.baseUrl, email: null, password: null });

  // Persist user and tracking features before emitting user-fetched so
  // auto-start tracking reads the latest monitoring flags.
  _currentUser = authenticationResponse.user;
  await OfflineUser.setProperties(authenticationResponse.user);
  await trackingFeatures.updateFromUser(authenticationResponse.user);
  await OfflineUser.commit();
  module.exports.events.emit('user-fetched', _currentUser);

  // Saving token into system keychain
  try {

    await keychain.saveToken(
      authenticationResponse.token.token,
      authenticationResponse.token.tokenType,
      authenticationResponse.token.tokenExpire,
    );

  } catch (error) {

    log.error('Error occured during saving token into system keychain', error);
    throw new UIError(500, 'Internal error occured', 'EAUTH500');

  }

  // Fire authenticated event
  module.exports.events.emit('authenticated');

  log.debug('Successfully authenticated via SSO');
  return authenticationResponse;

};

// Handle SSO links from second instances
app.on('second-instance', (event, args) => {

  const ssoParams = module.exports.getSSOFromProtocol(args);
  if (ssoParams)
    module.exports.events.emit('sso-detected', ssoParams);

});
