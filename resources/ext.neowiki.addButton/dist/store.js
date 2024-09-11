"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.useNeoWikiStore = void 0;
var _pinia = require("pinia");
var useNeoWikiStore = exports.useNeoWikiStore = (0, _pinia.defineStore)('neoWiki', {
  state: function state() {
    return {
      extensionName: 'NeoWiki Schema Selector',
      selectedSchemaType: '',
      schemaTypes: ['Person', 'Company', 'Product', 'Location', 'Object Event'],
      schemaProperties: {
        Person: [{
          name: 'Name',
          type: 'String'
        }, {
          name: 'Birth Date',
          type: 'Date'
        }, {
          name: 'Occupation',
          type: 'String'
        }],
        Company: [{
          name: 'Company Name',
          type: 'String'
        }, {
          name: 'Founded Date',
          type: 'Date'
        }, {
          name: 'Industry',
          type: 'String'
        }],
        Product: [{
          name: 'Product Name',
          type: 'String'
        }, {
          name: 'Price',
          type: 'Number'
        }, {
          name: 'Manufacturer',
          type: 'String'
        }],
        Location: [{
          name: 'Name',
          type: 'String'
        }, {
          name: 'Coordinates',
          type: 'GeoCoordinates'
        }, {
          name: 'Country',
          type: 'String'
        }],
        'Object Event': [{
          name: 'Event Name',
          type: 'String'
        }, {
          name: 'Date',
          type: 'Date'
        }, {
          name: 'Object',
          type: 'String'
        }]
      }
    };
  },
  actions: {
    updateSchemaType: function updateSchemaType(newType) {
      this.selectedSchemaType = newType;
      console.log("Schema type updated to: ".concat(newType));
    }
  }
});