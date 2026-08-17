const Tracker = require('@Tracker/node');
const keychain = require('../utils/keychain');

const api = new Tracker();

api.tokenProvider = {

  get: keychain.getSavedToken,
  set: keychain.saveToken,

};

api.credentialsProvider = {

  get: keychain.getSavedCredentials,
  set: keychain.saveCredentials,

};

module.exports = api;
