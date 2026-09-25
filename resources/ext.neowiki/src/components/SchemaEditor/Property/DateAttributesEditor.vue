<template>
	<div class="date-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField
				class="date-attributes__minimum"
				:is-fieldset="true"
				:status="minimumError === null ? 'default' : 'error'"
				:messages="minimumError === null ? {} : { error: minimumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<div class="date-attributes__row">
					<DateTextInput
						ref="minimumField"
						:model-value="minimumInput"
						@update:model-value="updateMinimum"
					/>
					<SeverityInput
						v-if="property.minimum !== undefined"
						:constraint="$i18n( 'neowiki-property-editor-minimum' ).text()"
						:model-value="property.constraintSeverities?.minimum"
						@update:model-value="updateSeverity( 'minimum', $event )"
					/>
				</div>
			</CdxField>

			<CdxField
				class="date-attributes__maximum"
				:is-fieldset="true"
				:status="maximumError === null ? 'default' : 'error'"
				:messages="maximumError === null ? {} : { error: maximumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<div class="date-attributes__row">
					<DateTextInput
						ref="maximumField"
						:model-value="maximumInput"
						@update:model-value="updateMaximum"
					/>
					<SeverityInput
						v-if="property.maximum !== undefined"
						:constraint="$i18n( 'neowiki-property-editor-maximum' ).text()"
						:model-value="property.constraintSeverities?.maximum"
						@update:model-value="updateSeverity( 'maximum', $event )"
					/>
				</div>
			</CdxField>
		</NeoNestedField>

		<CdxField class="date-attributes__min-precision">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-min-precision' ).text() }}
			</template>

			<div class="date-attributes__row">
				<CdxSelect
					:selected="property.minPrecision ?? 'year'"
					:menu-items="minPrecisionItems"
					@update:selected="updateMinPrecision"
				/>
				<SeverityInput
					v-if="property.minPrecision !== undefined"
					:constraint="$i18n( 'neowiki-property-editor-min-precision' ).text()"
					:model-value="property.constraintSeverities?.minPrecision"
					@update:model-value="updateSeverity( 'minPrecision', $event )"
				/>
			</div>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxField, CdxSelect, type MenuItemData } from '@wikimedia/codex';
import { DateProperty } from '@/domain/propertyTypes/Date.ts';
import { minimumExcludesMaximum } from '@/domain/propertyTypes/PartialDate.ts';
import { AttributesEditorEmits, AttributesEditorExposes, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';
import DateTextInput from '@/components/Value/DateTextInput.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';

const props = defineProps<AttributesEditorProps<DateProperty>>();
const emit = defineEmits<AttributesEditorEmits<DateProperty>>();

const minimumField = ref<InstanceType<typeof DateTextInput> | null>( null );
const maximumField = ref<InstanceType<typeof DateTextInput> | null>( null );
const minimumInput = ref( props.property.minimum );
const maximumInput = ref( props.property.maximum );

watch( () => props.property.minimum, ( newVal ) => {
	minimumInput.value = newVal;
} );

watch( () => props.property.maximum, ( newVal ) => {
	maximumInput.value = newVal;
} );

const minimumUnreadable = computed( () => minimumField.value?.unreadableMessage() ?? null );
const maximumUnreadable = computed( () => maximumField.value?.unreadableMessage() ?? null );

const minimumUnreadableShown = computed( () => minimumField.value?.errorMessage() ?? null );
const maximumUnreadableShown = computed( () => maximumField.value?.errorMessage() ?? null );

// While a field's text does not read as a date, the Schema keeps the bound it holds, so that is
// the bound the other field must leave room for.
const minimumInEffect = computed(
	() => minimumUnreadable.value === null ? minimumInput.value : props.property.minimum
);
const maximumInEffect = computed(
	() => maximumUnreadable.value === null ? maximumInput.value : props.property.maximum
);

const boundsConflict = computed( () =>
	minimumInEffect.value !== undefined &&
	maximumInEffect.value !== undefined &&
	minimumExcludesMaximum( minimumInEffect.value, maximumInEffect.value )
);

// The conflict shows on the bound the Schema does not hold yet: that is the one held back.
const conflictMessageOn = ( inEffect: string | undefined, stored: string | undefined ): string | null =>
	boundsConflict.value && inEffect !== stored ?
		mw.message( 'neowiki-property-editor-min-exceeds-max' ).text() :
		null;

const minimumError = computed(
	() => minimumUnreadableShown.value ?? conflictMessageOn( minimumInEffect.value, props.property.minimum )
);
const maximumError = computed(
	() => maximumUnreadableShown.value ?? conflictMessageOn( maximumInEffect.value, props.property.maximum )
);

// A bound the user changed reaches the Schema once both bounds leave room for a date, and
// then together with the other bound when that one was held back by the conflict.
function emitBoundsInEffect(): void {
	if ( boundsConflict.value ) {
		return;
	}

	if ( minimumInEffect.value !== props.property.minimum || maximumInEffect.value !== props.property.maximum ) {
		emit( 'update:property', { minimum: minimumInEffect.value, maximum: maximumInEffect.value } );
	}
}

const updateMinimum = ( value: string | undefined ): void => {
	minimumInput.value = value;
	emitBoundsInEffect();
};

const updateMaximum = ( value: string | undefined ): void => {
	maximumInput.value = value;
	emitBoundsInEffect();
};

const minPrecisionItems = computed<MenuItemData[]>( () => [
	{ value: 'year', label: mw.message( 'neowiki-property-editor-min-precision-year' ).text() },
	{ value: 'month', label: mw.message( 'neowiki-property-editor-min-precision-month' ).text() },
	{ value: 'day', label: mw.message( 'neowiki-property-editor-min-precision-day' ).text() }
] );

// A year is the least a date can hold, so choosing it means having no minPrecision.
const updateMinPrecision = ( selected: 'year' | 'month' | 'day' ): void => {
	emit( 'update:property', { minPrecision: selected === 'year' ? undefined : selected } );
};

const updateSeverity = ( constraint: 'minimum' | 'maximum' | 'minPrecision', severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, constraint, severity ) );
};

defineExpose<AttributesEditorExposes>( {
	unparseableInputMessage: (): string | null =>
		minimumUnreadable.value ?? maximumUnreadable.value ?? minimumError.value ?? maximumError.value
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

// The severity input follows the control on its row rather than the label-row placement of
// `ext-neowiki-severity-field`: a date bound is a fieldset of three controls, and that helper's
// positioned field would also hold the month menus inside the scrolling dialog body.
.date-attributes__row {
	display: flex;
	align-items: center;
	gap: @spacing-50;

	> :first-child {
		flex: 1 1 auto;
		min-width: 0;
	}
}
</style>
