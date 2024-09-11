<template>
<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script>
"use strict";

var _vue = require("vue");
// @vue/component
module.exports = (0, _vue.defineComponent)({
  inject: ['store'],
  created: function created() {
    this.store = this.store;
  }
});
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