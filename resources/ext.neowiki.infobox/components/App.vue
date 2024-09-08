<template>
	<cdx-card>
		<template #title>
			Infobox
		</template>
		<template #description>
			<neo-message :message="$i18n( 'neowiki-infobox' ).text()"></neo-message>
		</template>
	</cdx-card>
</template>

<script>
const { CdxCard } = require( '../../codex.js' );
const NeoMessage = require( '../../ext.neowiki.core/components/NeoMessage.vue' );

// @vue/component
module.exports = exports = {
	components: {
		CdxCard,
		NeoMessage
	}
};
</script>
