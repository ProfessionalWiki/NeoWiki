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

var _vue = require("vue");
var _PropertyList = _interopRequireDefault(require("./PropertyList.vue"));
var _store = require("../store.js");
function _interopRequireDefault(e) { return e && e.__esModule ? e : { default: e }; }
// @vue/component
module.exports = exports = (0, _vue.defineComponent)({
  name: 'App',
  components: {
    PropertyList: _PropertyList.default
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