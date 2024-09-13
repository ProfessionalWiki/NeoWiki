<template>
<div class="neowiki-component">
		<h2>{{ store.extensionName }}</h2>
		<p>Select a Schema Type:</p>
		<select v-model="store.selectedSchemaType" @change="onSchemaTypeChange">
			<option value="">Select a Schema Type</option>
			<option v-for="type in store.schemaTypes" :key="type" :value="type">
				{{ type }}
			</option>
		</select>
		<p v-if="store.selectedSchemaType" class="selected-schema">
			Selected Schema: "{{ store.selectedSchemaType }}"
		</p>
		<PropertyList/>
	</div>
</template>

<script>
"use strict";

function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _vue = require("vue");
var PropertyList = _interopRequireWildcard(require("./PropertyList.vue"));
var _store = require("../store.js");
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function _getRequireWildcardCache(e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != _typeof(e) && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && {}.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
var component = (0, _vue.defineComponent)({
  name: 'App',
  components: {
    PropertyList: PropertyList
  },
  inject: ['store'],
  setup: function setup() {
    var store = (0, _store.useNeoWikiStore)();
    return {
      store: store
    };
  },
  methods: {
    onSchemaTypeChange: function onSchemaTypeChange(event) {
      var target = event.target;
      this.store.updateSchemaType(target.value);
    }
  },
  mounted: function mounted() {
    console.log('NeoWiki component mounted');
  }
});
var _default = exports.default = component; // this can also be replaced with module.exports = exports = component in build.js
module.exports = exports = component; // we have to add this for MW to work, that's the only redundancy
</script>

<style>
.neowiki-component {
	font-family: Arial, sans-serif;
	max-width: 600px;
	margin: 0 auto;
	padding: 20px;
	border: 1px solid #cccccc;
	border-radius: 5px;
}

h1 {
	color: #333333;
}

button {
	background-color: #4caf50;
	color: white;
	padding: 10px 15px;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	margin-bottom: 10px;
}

button:hover {
	background-color: #45a049;
}

input {
	width: 100%;
	padding: 10px;
	margin-bottom: 10px;
	border: 1px solid #dddddd;
	border-radius: 4px;
}

ul {
	list-style-type: none;
	padding: 0;
}

li {
	background-color: #f1f1f1;
	margin-bottom: 5px;
	padding: 10px;
	border-radius: 4px;
}
</style>