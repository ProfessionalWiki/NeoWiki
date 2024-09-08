<template>
	<neo-message :message="$i18n( 'neowiki-add-button' ).text()"></neo-message>
</template>

<script>
const NeoMessage = require( '../../ext.neowiki.core/components/NeoMessage.vue' );

// @vue/component
module.exports = exports = {
	components: {
		NeoMessage
	}
};
</script>
