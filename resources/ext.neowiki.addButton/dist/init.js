"use strict";

var _vue = _interopRequireDefault(require("vue"));
var _pinia = require("pinia");
var _App = _interopRequireDefault(require("./components/App.vue"));
var _store = require("./store.js");
function _interopRequireDefault(e) { return e && e.__esModule ? e : { default: e }; }
// init.ts

(function () {
  // Create the Pinia instance
  var pinia = (0, _pinia.createPinia)();

  // Set the active Pinia instance
  (0, _pinia.setActivePinia)(pinia);

  // Initialize the store
  var store = (0, _store.useNeoWikiStore)();
  var app = _vue.default.createMwApp(_App.default);

  // Attach the store to the app
  app.provide('store', store);

  // Mount the app
  app.mount('#neowiki-add-button');
})();