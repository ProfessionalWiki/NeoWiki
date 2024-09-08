<template>
	<strong>{{ message }}</strong>
</template>

<script>
// @vue/component
module.exports = exports = {
	props: {
		message: {
			type: String,
			required: true
		}
	}
};
</script>
