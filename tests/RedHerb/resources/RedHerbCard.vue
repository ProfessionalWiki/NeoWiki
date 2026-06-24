<template>
	<div
		v-if="subject"
		class="ext-redherb-card"
	>
		<div class="ext-redherb-card__caption">
			{{ $i18n( 'redherb-card-caption' ).text() }}
		</div>
		<div class="ext-redherb-card__label">
			{{ subject.getLabel() }}
		</div>
		<div class="ext-redherb-card__schema">
			{{ subject.getSchemaName() }}
		</div>
		<ul
			v-if="propertyNames.length > 0"
			class="ext-redherb-card__properties"
		>
			<li
				v-for="name in propertyNames"
				:key="name"
			>
				{{ name }}
			</li>
		</ul>
	</div>
</template>

<script>
var vue = require( 'vue' );
var nw = require( 'ext.neowiki' );

// Example View Type: renders a Subject as a compact card. The component receives
// the ViewTypeProps contract ( subjectId, canEditSubject, layoutName ) and reads
// the Subject from NeoWiki's store, which NeoWiki has already populated before it
// mounts the view. A richer View Type would reuse NeoWiki's value-display
// components ( see Infobox ); this stays to labels and property names to keep the
// example minimal.
module.exports = exports = {
	props: {
		subjectId: { type: Object, required: true },
		canEditSubject: { type: Boolean, required: true },
		layoutName: { type: String, default: undefined }
	},
	setup: function ( props ) {
		var subjectStore = nw.useSubjectStore();

		var subject = vue.computed( function () {
			return subjectStore.getSubject( props.subjectId );
		} );

		var propertyNames = vue.computed( function () {
			return subject.value.getNamesOfNonEmptyProperties().map( function ( name ) {
				return name.toString();
			} );
		} );

		return {
			subject: subject,
			propertyNames: propertyNames
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-card {
	max-width: 20rem;
	padding: @spacing-100;
	border: @border-base;
	border-radius: @border-radius-base;
	background-color: @background-color-base;

	&__caption {
		color: @color-subtle;
		font-size: @font-size-small;
		text-transform: uppercase;
	}

	&__label {
		font-size: @font-size-x-large;
		font-weight: @font-weight-bold;
	}

	&__schema {
		color: @color-subtle;
		font-size: @font-size-small;
	}

	&__properties {
		margin-block-start: @spacing-75;
		padding-inline-start: @spacing-150;
	}
}
</style>
