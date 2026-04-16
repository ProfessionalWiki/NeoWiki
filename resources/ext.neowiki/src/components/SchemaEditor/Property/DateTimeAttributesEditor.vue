<template>
	<div class="datetime-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField
				class="datetime-attributes__minimum"
				:status="minimumError === null ? 'default' : 'error'"
				:messages="minimumError === null ? {} : { error: minimumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<!-- eslint-disable-next-line vue/html-self-closing -->
				<input
					type="datetime-local"
					class="cdx-text-input__input"
					:value="minimumInput"
					@input="updateMinimum"
				>
			</CdxField>

			<CdxField
				class="datetime-attributes__maximum"
				:status="maximumError === null ? 'default' : 'error'"
				:messages="maximumError === null ? {} : { error: maximumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<!-- eslint-disable-next-line vue/html-self-closing -->
				<input
					type="datetime-local"
					class="cdx-text-input__input"
					:value="maximumInput"
					@input="updateMaximum"
				>
			</CdxField>
		</NeoNestedField>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { DateTimeProperty } from '@/domain/propertyTypes/DateTime.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

const props = defineProps<AttributesEditorProps<DateTimeProperty>>();
const emit = defineEmits<AttributesEditorEmits<DateTimeProperty>>();

const minimumInput = ref( toLocalInputValue( props.property.minimum ) );
const maximumInput = ref( toLocalInputValue( props.property.maximum ) );
const minimumError = ref<string | null>( null );
const maximumError = ref<string | null>( null );

watch( () => props.property.minimum, ( newVal ) => {
	minimumInput.value = toLocalInputValue( newVal );
} );

watch( () => props.property.maximum, ( newVal ) => {
	maximumInput.value = toLocalInputValue( newVal );
} );

function toLocalInputValue( isoString: string | undefined ): string {
	if ( !isoString ) {
		return '';
	}
	return isoString.replace( /Z$/, '' ).slice( 0, 16 );
}

function fromLocalInputValue( localValue: string ): string | undefined {
	return localValue ? localValue + ':00Z' : undefined;
}

function minExceedsMax( min: string, max: string ): boolean {
	if ( min === '' || max === '' ) {
		return false;
	}
	const minTime = Date.parse( min );
	const maxTime = Date.parse( max );
	return !Number.isNaN( minTime ) && !Number.isNaN( maxTime ) && minTime > maxTime;
}

const updateMinimum = ( event: Event ): void => {
	const value = ( event.target as HTMLInputElement ).value;
	minimumInput.value = value;

	if ( minExceedsMax( value, maximumInput.value ) ) {
		minimumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	minimumError.value = null;
	maximumError.value = null;
	emit( 'update:property', { minimum: fromLocalInputValue( value ) } );
};

const updateMaximum = ( event: Event ): void => {
	const value = ( event.target as HTMLInputElement ).value;
	maximumInput.value = value;

	if ( minExceedsMax( minimumInput.value, value ) ) {
		maximumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	maximumError.value = null;
	minimumError.value = null;
	emit( 'update:property', { maximum: fromLocalInputValue( value ) } );
};
</script>
