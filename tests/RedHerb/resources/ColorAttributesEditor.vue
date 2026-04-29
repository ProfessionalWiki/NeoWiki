<template>
	<div class="ext-redherb-color-attributes cdx-field">
		<neo-nested-field :optional="true">
			<template #label>
				{{ paletteLabel }}
			</template>

			<ul
				ref="paletteList"
				class="ext-redherb-color-attributes__list"
			>
				<li
					v-for="( entry, index ) in entries"
					:key="entry.id"
					class="ext-redherb-color-attributes__item"
				>
					<span
						class="ext-redherb-color-attributes__drag-handle"
						:title="dragTooltip"
					>
						<cdx-icon
							:icon="dragIcon"
							size="small"
						/>
					</span>
					<span
						class="ext-redherb-color-attributes__swatch"
						:style="hexIsValid( entry.value ) ? { backgroundColor: entry.value } : {}"
						:class="{ 'ext-redherb-color-attributes__swatch--invalid': !hexIsValid( entry.value ) }"
						aria-hidden="true"
					></span>
					<cdx-text-input
						class="ext-redherb-color-attributes__text"
						placeholder="#ff5733"
						:model-value="entry.value"
						@update:model-value="updateEntry( index, $event )"
					></cdx-text-input>
					<cdx-button
						class="ext-redherb-color-attributes__remove"
						weight="quiet"
						:aria-label="removeLabel"
						@click="removeEntry( index )"
					>
						<cdx-icon
							:icon="removeIcon"
							size="small"
						/>
					</cdx-button>
				</li>
			</ul>

			<cdx-button
				action="progressive"
				weight="normal"
				@click="addEntry"
			>
				<cdx-icon
					:icon="addIcon"
					size="small"
				/>
				{{ addLabel }}
			</cdx-button>
		</neo-nested-field>
	</div>
</template>

<script>
var vue = require( 'vue' );
var codex = require( './codex.js' );
var icons = require( './icons.json' );
var nw = require( 'ext.neowiki' );

var HEX_REGEX = require( './hexRegex.js' );
var nextId = 0;

function wrapEntries( colors ) {
	return ( colors || [] ).map( function ( value ) {
		nextId += 1;
		return { id: 'entry-' + nextId, value: value };
	} );
}

module.exports = exports = {
	components: {
		CdxButton: codex.CdxButton,
		CdxIcon: codex.CdxIcon,
		CdxTextInput: codex.CdxTextInput,
		NeoNestedField: nw.NeoNestedField
	},
	props: {
		property: { type: Object, required: true }
	},
	emits: [ 'update:property' ],
	setup: function ( props, ctx ) {
		var entries = vue.ref( wrapEntries( props.property.allowedColors ) );
		var paletteList = vue.ref( null );

		vue.watch(
			function () { return props.property.allowedColors; },
			function ( newColors ) {
				if ( sameColors( colorsOf( entries.value ), newColors || [] ) ) {
					return;
				}
				entries.value = wrapEntries( newColors );
			}
		);

		function emitColors() {
			ctx.emit( 'update:property', {
				allowedColors: colorsOf( entries.value )
			} );
		}

		function updateEntry( index, nextValue ) {
			entries.value[ index ] = {
				id: entries.value[ index ].id,
				value: nextValue
			};
			emitColors();
		}

		function addEntry() {
			nextId += 1;
			entries.value.push( { id: 'entry-' + nextId, value: '' } );
			emitColors();
		}

		function removeEntry( index ) {
			entries.value.splice( index, 1 );
			emitColors();
		}

		nw.useSortable( paletteList, {
			handle: '.ext-redherb-color-attributes__drag-handle',
			onReorder: function ( oldIndex, newIndex ) {
				var moved = entries.value.splice( oldIndex, 1 )[ 0 ];
				entries.value.splice( newIndex, 0, moved );
				emitColors();
			}
		} );

		return {
			entries: entries,
			paletteList: paletteList,
			paletteLabel: mw.message( 'redherb-color-allowed-palette-label' ).text(),
			addLabel: mw.message( 'redherb-color-add-color' ).text(),
			removeLabel: mw.message( 'redherb-color-remove-color' ).text(),
			dragTooltip: mw.message( 'redherb-color-reorder-color' ).text(),
			addIcon: icons.cdxIconAdd,
			removeIcon: icons.cdxIconClose,
			dragIcon: icons.cdxIconDraggable,
			hexIsValid: function ( value ) { return HEX_REGEX.test( value ); },
			addEntry: addEntry,
			removeEntry: removeEntry,
			updateEntry: updateEntry
		};
	}
};

function colorsOf( entries ) {
	return entries.map( function ( entry ) { return entry.value; } );
}

function sameColors( a, b ) {
	if ( a.length !== b.length ) {
		return false;
	}
	for ( var i = 0; i < a.length; i += 1 ) {
		if ( a[ i ] !== b[ i ] ) {
			return false;
		}
	}
	return true;
}
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-color-attributes {
	&__list {
		list-style: none;
		margin: 0 0 @spacing-50 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: @spacing-25;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: @spacing-50;
	}

	&__drag-handle {
		cursor: grab;
		color: @color-subtle;
		display: inline-flex;
	}

	&__swatch {
		display: inline-block;
		width: @size-125;
		height: @size-125;
		border: @border-base;
		border-radius: @border-radius-base;
		flex-shrink: 0;

		&--invalid {
			background-color: @background-color-error-subtle;
		}
	}

	&__text {
		flex: 1;
	}
}
</style>
