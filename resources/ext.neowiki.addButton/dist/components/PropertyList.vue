<template>
<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script>
"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _vue = require("vue");
var component = (0, _vue.defineComponent)({
  setup: function setup() {
    return {
      store: (0, _vue.inject)('store')
    };
  }
});
var _default = exports.default = component; // this can be replaced with module.exports = exports = component in build.js
module.exports = exports = component; // we have to add this for MW to work, that's the only redundancy
</script>

<style>
.user-greeting {
	margin-top: 15px;
	padding: 10px;
	background-color: #f0f0f0;
	border-radius: 4px;
}

p {
	font-weight: bold;
	color: #2c3e50;
}
</style>