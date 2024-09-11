<template>
<div class="user-greeting">
		<p>Current Schema: {{ store.selectedSchemaType || 'None selected' }}</p>
	</div>
</template>

<script>
"use strict";

// @vue/component
module.exports = {
  inject: ['store'],
  created: function created() {
    // Type assertion for the injected store
    this.store = this.store;
  }
};
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