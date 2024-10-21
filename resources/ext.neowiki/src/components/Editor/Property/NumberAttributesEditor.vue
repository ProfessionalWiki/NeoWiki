<template>
	<div class="number-attributes">
		<CdxField
			:label="$i18n( 'neowiki-property-editor-minimum' ).text()"
		>
			<CdxTextInput
				:model-value="property.minimum?.toString()"
				type="number"
				@update:model-value="updateMinimum"
			/>
		</CdxField>
		<CdxField
			:label="$i18n( 'neowiki-property-editor-maximum' ).text()"
		>
			<CdxTextInput
				:model-value="property.maximum?.toString()"
				type="number"
				@update:model-value="updateMaximum"
			/>
		</CdxField>
		<CdxField
			:label="$i18n( 'neowiki-property-editor-precision' ).text()"
		>
			<CdxTextInput
				:model-value="property.precision?.toString()"
				type="number"
				@update:model-value="updatePrecision"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { NumberProperty } from '@neo/domain/valueFormats/Number.ts';

const props = defineProps( {
	property: {
		type: Object as PropType<NumberProperty>,
		required: true
	}
} );

const emit = defineEmits( [ 'update:property' ] );

const updateMinimum = ( value: string ): void => {
	emit( 'update:property', { ...props.property, minimum: value ? Number( value ) : null } );
};

const updateMaximum = ( value: string ): void => {
	emit( 'update:property', { ...props.property, maximum: value ? Number( value ) : null } );
};

const updatePrecision = ( value: string ): void => {
	emit( 'update:property', { ...props.property, precision: value ? Number( value ) : null } );
};
</script>
