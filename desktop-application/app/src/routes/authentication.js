const { shell } = require('electron');
const Logger = require('../utils/log');
const auth = require('../base/authentication');
const config = require('../base/config');
const { UIError } = require('../utils/errors');
const keychain = require('../utils/keychain');
const trackingFeatures = require('../controller/tracking-features');

const log = new Logger('Router:Authentication');
log.debug('Loaded');

module.exports = router => {

  // Returns login form bootstrap state
  router.serve('auth/get-login-state', async request => {

    try {

      const savedCredentials = await keychain.getSavedCredentials();
      return request.send(200, {
        hostname: savedCredentials && typeof savedCredentials.hostname === 'string'
          ? savedCredentials.hostname
          : config.authentication.defaultHostname,
        login: !config.authentication.domainOnlyEnabled && savedCredentials && typeof savedCredentials.email === 'string'
          ? savedCredentials.email
          : null,
        rememberLogin: !config.authentication.domainOnlyEnabled && Boolean(
          savedCredentials
          && typeof savedCredentials.email === 'string'
          && typeof savedCredentials.password === 'string'
        ),
        domainOnly: config.authentication.domainOnlyEnabled,
        domainUser: auth.getCurrentWindowsPrincipal(),
      });

    } catch (error) {

      log.error('Operating error occured in login state route', error);
      return request.send(200, {
        hostname: config.authentication.defaultHostname,
        login: null,
        rememberLogin: false,
        domainOnly: config.authentication.domainOnlyEnabled,
        domainUser: auth.getCurrentWindowsPrincipal(),
      });

    }

  });

  // Checks and sets the API hostname
  router.serve('auth/check-hostname', async request => {

    try {

      // Trying the exact URL provided first
      return request.send(200, await auth.setHostname(request.packet.body.hostname));

    } catch (error) {

      return request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });

    }

  });

  // Is authentication required
  router.serve('auth/is-authentication-required', async req => {

    req.send(200, { required: (await auth.isAuthenticationRequired()) });

  });

  // Login
  router.serve('auth/authenticate', async request => {

    if (config.authentication.domainOnlyEnabled) {

      request.send(403, {
        message: 'Desktop client is configured for domain authentication only',
        id: 'EAUTHDOMAINONLY',
      });
      return;

    }

    // Getting request properties
    const { username, password, rememberLogin } = request.packet.body;

    // Checking input parameters prescense
    if (typeof username !== 'string' || typeof password !== 'string') {

      request.send(400, { error: 'Incorrect credentials' });
      return;

    }

    // Calling authentication function
    try {

      // Making authentication request
      const authResponseUser = await auth.userAuthentication(username, password, rememberLogin === true);

      // Fetch tracking features with immediate acknowledge
      const features = await trackingFeatures.updateFromUser(authResponseUser, true);

      // Returning authenticated user object to renderer
      request.send(200, { user: authResponseUser, features });

    } catch (error) {

      // Pass UIErrors directly to renderer
      if (error instanceof UIError) {
        // {error: error.error} means we are passing error that initially triggered UIError
        request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });
        return;

      }

      // It'll be extremely weird if real errors will occur there. We should log them.
      log.error('Operating error occured in authentication route', error);
      request.send(500, { message: 'Internal error occured', id: 'EISR000' });

    }

  });

  // Login using currently logged-in Windows user
  router.serve('auth/authenticate-domain-user', async request => {

    try {

      const success = await auth.tryWindowsAutoAuthentication();
      if (!success) {

        return request.send(401, {
          message: 'Unable to authenticate using current domain user',
          id: 'EAUTHDOMAINUSER',
        });

      }

      const user = await auth.getCurrentUser(true);
      const features = await trackingFeatures.updateFromUser(user, true);
      return request.send(200, { user, features });

    } catch (error) {

      if (error instanceof UIError)
        return request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });

      log.error('Operating error occured in domain authentication route', error);
      return request.send(500, { message: 'Internal error occured', id: 'EISR000' });

    }

  });

  // Logout
  router.serve('auth/logout', async request => {

    try {

      await auth.logout();

    } catch (error) {

      // Return UIErrors
      if (error instanceof UIError) {
        // {error: error.error} means we are passing error that initially triggered UIError
        request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });
        return;

      }

      // Wrap and log all other kinds of errors
      log.error('Operating error occured in logout route', error);
      request.send(500, { message: 'Internal error occured', id: 'EISR000' });

    }

  });

  // Request for a single-click redirection URL
  router.serve('auth/request-single-click-redirection', async request => {

    try {

      // Get URL
      const url = await auth.getSingleClickRedirection();

      // Open this URL in system's browser
      shell.openExternal(url);

      // Return successfull status
      request.send(200, {});
      return;

    } catch (error) {

      // Return UIErrors
      if (error instanceof UIError) {
        // {error: error.error} means we are passing error that initially triggered UIError
        request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });
        return;

      }

      // Wrap and log all other kinds of errors
      log.error('Operating error occured in the single click redirection route', error);
      request.send(500, { message: 'Internal error occured', id: 'EISR000' });

    }

  });

  // Request for a Web-Desktop SSO check
  router.serve('auth/check-sso-presence', async request => {

    if (config.authentication.domainOnlyEnabled)
      return request.send(404, {});

    try {

      // Get properties
      const ssoParams = auth.getSSOFromProtocol();
      if (ssoParams)
        return request.send(200, ssoParams);

      return request.send(404, {});

    } catch (error) {

      // Return UIErrors
      if (error instanceof UIError)
        // {error: error.error} means we are passing error that initially triggered UIError
        return request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });


      // Wrap and log all other kinds of errors
      log.error('Operating error occured in the single click redirection route', error);
      return request.send(500, { message: 'Internal error occured', id: 'EISR000' });

    }

  });

  // Performs a Web-Desktop SSO login
  router.serve('auth/perform-sso', async request => {

    if (config.authentication.domainOnlyEnabled)
      return request.send(403, { message: 'Single Sign-On is disabled for this client', id: 'ESSO_DISABLED' });

    try {

      // Get properties
      const ssoParams = request.packet.body;

      // Set base url
      if (!(await auth.setHostname(ssoParams.baseUrl)))
        return request.send(400, { message: 'Single-Sign On URL is not correct', id: 'ESSO001' });

      // Try to authenticate
      const authenticationResponse = await auth.authenticateSSO(ssoParams);

      // Fetch tracking features with immediate acknowledge
      const features = await trackingFeatures.updateFromUser(authenticationResponse.user, true);

      // Returning authenticated user object to renderer
      return request.send(200, { features });

    } catch (error) {

      // Return UIErrors
      if (error instanceof UIError)
        // {error: error.error} means we are passing error that initially triggered UIError
        return request.send(error.code, { message: error.message, id: error.errorId, error: error.error == null ? error.error : JSON.parse(JSON.stringify(error.error)) });


      // Wrap and log all other kinds of errors
      log.error('Operating error occured in the single click redirection route', error);
      return request.send(500, { message: 'Internal error occured', id: 'EISR000' });

    }

  });

  // Pass company identifier to Sentry on frontend
  auth.events.once('company-instance-fetched', cid => router.emit('auth/company-instance-fetched', { cid }));

  // Pass detected SSO URLs from duplicating instances
  auth.events.on('sso-detected', ssoParams => router.emit('auth/sso-detected', ssoParams));

};
