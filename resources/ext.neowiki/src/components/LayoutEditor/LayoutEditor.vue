<template>
	<EditorPanes
		class="ext-neowiki-layout-editor"
		:start-pane="{ ...SETTINGS_PANE_SIZE, resizeLabel: $i18n( 'neowiki-layout-editor-resize' ).text() }"
		:stacks-below-desktop="true"
	>
		<template #start>
			<div class="ext-neowiki-layout-editor__settings">
				<DisplayRuleEditor
					v-if="openedDefinition !== undefined"
					ref="displayRuleEditor"
					:property="openedDefinition"
					:display-attributes="openedDisplayAttributes"
					@update:display-attributes="onDisplayAttributesChanged"
					@back="closeDisplayAttributes"
				/>
				<div v-show="openedDefinition === undefined">
					<div class="ext-neowiki-layout-editor__metadata">
						<span class="ext-neowiki-layout-editor__metadata-item">
							<span class="ext-neowiki-layout-editor__metadata-label">
								{{ $i18n( 'neowiki-layout-editor-view-type' ).text() }}
							</span>
							{{ initialLayout.getType() }}
						</span>
					</div>

					<div class="ext-neowiki-layout-editor__description">
						<CdxField :optional="true">
							<template #label>
								{{ $i18n( 'neowiki-layout-editor-description' ).text() }}
							</template>
							<CdxTextArea
								:model-value="description"
								:placeholder="$i18n( 'neowiki-layout-editor-description-placeholder' ).text()"
								@update:model-value="onDescriptionChanged"
							/>
						</CdxField>
					</div>

					<div class="ext-neowiki-layout-editor__display-rules">
						<CdxMessage
							v-if="schemaFetchFailed"
							type="error"
							:inline="true"
						>
							{{ $i18n( 'neowiki-layout-editor-schema-fetch-error' ).text() }}
						</CdxMessage>
						<DisplayRuleList
							v-else
							ref="displayRuleList"
							:schema-properties="schemaProperties"
							:display-rules="currentDisplayRules"
							:properties-targeting-dependent-subjects="targetingDependentSubjects"
							:preview="previewMarks"
							:properties-with-display-attributes="propertiesWithDisplayAttributes"
							:unsaved-properties="unsavedProperties"
							@update:display-rules="onDisplayRulesChanged"
							@hover="hoveredProperty = $event"
							@select="onSelectedInList"
							@open="openDisplayAttributes"
							@reordering="draggedOrder = $event"
						/>
					</div>
				</div>
			</div>
		</template>

		<LayoutPreview
			:layout="previewedLayout"
			:hovered-property="draggedOrder?.property ?? hoveredProperty"
			:selected-property="selectedProperty"
			@hover="hoveredProperty = $event"
			@select="onSelectedInPreview"
		/>
	</EditorPanes>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, watch } from 'vue';
import { Layout, type DisplayRule } from '@/domain/Layout.ts';
import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { CdxField, CdxMessage, CdxTextArea } from '@wikimedia/codex';
import EditorPanes, { type StartPaneOptions } from '@/components/common/EditorPanes.vue';
import DisplayRuleList, { type DisplayRuleListExposes, type DraggedOrder, type PreviewMarks } from './DisplayRuleList.vue';
import LayoutPreview from './LayoutPreview.vue';
import DisplayRuleEditor, { type DisplayRuleEditorExposes } from './DisplayRuleEditor.vue';
import { attributesAfterPatch, rulesInEffect as rulesInEffectFor, rulesAfterSettingDisplayAttributes } from './displayRuleEditing.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { propertiesTargetingDependentSubjects } from '@/domain/propertyTypes/Relation.ts';
import { subjectsAreDependentIn } from '@/application/SchemaLookup.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

// The preview takes the rest of the dialog, which is as wide as Views of any View Type need.
const SETTINGS_PANE_SIZE: Omit<StartPaneOptions, 'resizeLabel'> = {
	defaultSize: 440,
	minSize: 320,
	minOtherSize: 360,
	storageKey: 'neowiki-layout-editor-pane-size'
};

const props = defineProps<{
	initialLayout: Layout;
}>();

const emit = defineEmits<{
	change: [];
}>();

const displayRuleList = ref<DisplayRuleListExposes | null>( null );
const displayRuleEditor = ref<DisplayRuleEditorExposes | null>( null );

const componentRegistry = NeoWikiServices.getComponentRegistry();

const schemaRepo = NeoWikiServices.getSchemaRepository();
const schemaStore = useSchemaStore();

const description = ref( props.initialLayout.getDescription() );
const currentDisplayRules = shallowRef<DisplayRule[]>( [ ...props.initialLayout.getDisplayRules() ] );
const schemaProperties = shallowRef<PropertyDefinition[]>( [] );
const targetingDependentSubjects = shallowRef<PropertyName[]>( [] );
const schemaFetchFailed = ref( false );

const openedProperty = ref<string | null>( null );

async function fetchSchemaProperties( schemaName: string ): Promise<void> {
	schemaFetchFailed.value = false;
	try {
		const [ schema, summaries ] = await Promise.all( [
			schemaRepo.getSchema( schemaName ),
			schemaStore.fetchAllSchemaSummaries()
		] );
		schemaProperties.value = [ ...schema.getPropertyDefinitions() ];
		targetingDependentSubjects.value = propertiesTargetingDependentSubjects( schema, subjectsAreDependentIn( summaries ) )
			.map( ( property ) => property.name );
	} catch ( error ) {
		console.error( 'Failed to fetch schema:', error );
		schemaFetchFailed.value = true;
	}
}

watch( () => props.initialLayout, ( layout ) => {
	openedProperty.value = null;
	description.value = layout.getDescription();
	currentDisplayRules.value = [ ...layout.getDisplayRules() ];
	fetchSchemaProperties( layout.getSchema() );
}, { immediate: true } );

function onDescriptionChanged( value: string ): void {
	description.value = value;
	emit( 'change' );
}

function onDisplayRulesChanged( rules: DisplayRule[] ): void {
	currentDisplayRules.value = rules;
	emit( 'change' );
}

function layoutWith( rules: DisplayRule[] ): Layout {
	return new Layout(
		props.initialLayout.getName(),
		props.initialLayout.getSchema(),
		props.initialLayout.getType(),
		description.value,
		rules,
		props.initialLayout.getSettings()
	);
}

const currentLayout = computed( (): Layout => layoutWith( currentDisplayRules.value ) );

// While a row is dragged, the preview shows the order dropping it there would give, before the drop commits it.
const draggedOrder = shallowRef<DraggedOrder | null>( null );

const previewedLayout = computed( (): Layout =>
	draggedOrder.value === null ? currentLayout.value : layoutWith( draggedOrder.value.rules ) );

const hoveredProperty = ref<string | null>( null );
const selectedProperty = ref<string | null>( null );

const previewMarks = computed( (): PreviewMarks => ( {
	hoveredProperty: hoveredProperty.value,
	selectedProperty: selectedProperty.value
} ) );

function onSelectedInList( name: string ): void {
	selectedProperty.value = selectedProperty.value === name ? null : name;
}

async function onSelectedInPreview( name: string ): Promise<void> {
	if ( propertiesWithDisplayAttributes.value.includes( name ) ) {
		await openDisplayAttributes( name );
		return;
	}

	openedProperty.value = null;
	selectedProperty.value = name;
	await nextTick();
	displayRuleList.value?.focusProperty( name );
}

const rulesInEffect = computed( (): DisplayRule[] =>
	rulesInEffectFor( schemaProperties.value, currentDisplayRules.value, targetingDependentSubjects.value ) );

// The list opens them for shown properties alone, and the preview shows no other.
const propertiesWithDisplayAttributes = computed( (): string[] => schemaProperties.value
	.filter( ( property ) => componentRegistry.getDisplayAttributesEditor( property.type ) !== undefined )
	.map( ( property ) => property.name.toString() ) );

const savedRules = computed( (): DisplayRule[] =>
	rulesInEffectFor( schemaProperties.value, props.initialLayout.getDisplayRules(), targetingDependentSubjects.value ) );

function attributesIn( rules: DisplayRule[], name: string ): Record<string, unknown> {
	return rules.find( ( rule ) => rule.property.toString() === name )?.displayAttributes ?? {};
}

const unsavedProperties = computed( (): string[] => rulesInEffect.value
	.map( ( rule ) => rule.property.toString() )
	.filter( ( name ) => !sameAttributes( attributesIn( rulesInEffect.value, name ), attributesIn( savedRules.value, name ) ) ) );

const openedDefinition = computed( (): PropertyDefinition | undefined =>
	schemaProperties.value.find( ( property ) => property.name.toString() === openedProperty.value ) );

const openedDisplayAttributes = computed( (): Record<string, unknown> => attributesIn( rulesInEffect.value, openedProperty.value! ) );

async function openDisplayAttributes( name: string ): Promise<void> {
	openedProperty.value = name;
	selectedProperty.value = name;
	hoveredProperty.value = null;
	await nextTick();
	displayRuleEditor.value?.focusFirstField();
}

async function closeDisplayAttributes(): Promise<void> {
	const name = openedProperty.value!;

	openedProperty.value = null;
	selectedProperty.value = null;
	await nextTick();
	displayRuleList.value?.focusProperty( name );
}

// An edit that sets nothing new changes nothing to save: written out, it would also turn a Layout without Display
// Rules into rules, which show no property added to the Schema later.
function onDisplayAttributesChanged( patch: Record<string, unknown> ): void {
	const displayAttributes = attributesAfterPatch( openedDisplayAttributes.value, patch );

	if ( sameAttributes( displayAttributes, openedDisplayAttributes.value ) ) {
		return;
	}

	onDisplayRulesChanged( rulesAfterSettingDisplayAttributes( rulesInEffect.value, openedProperty.value!, displayAttributes ) );
}

function sameAttributes( some: Record<string, unknown>, others: Record<string, unknown> ): boolean {
	const names = Object.keys( some );

	return names.length === Object.keys( others ).length && names.every( ( name ) => some[ name ] === others[ name ] );
}

export interface LayoutEditorExposes {
	getLayout: () => Layout;
}

defineExpose( {
	getLayout: (): Layout => currentLayout.value
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-layout-editor {
	&__settings {
		padding: @spacing-50 @spacing-100 @spacing-100;

		@media ( min-width: @min-width-breakpoint-desktop ) {
			padding: @spacing-50 @spacing-150 @spacing-100;
		}
	}

	&__description {
		margin-block-end: @spacing-100;
	}

	&__metadata {
		display: flex;
		margin-block-end: @spacing-100;
	}

	&__metadata-item {
		display: inline-flex;
		gap: @spacing-50;
	}

	&__metadata-label {
		color: @color-subtle;
	}

	&__display-rules {
		border-block-start: @border-subtle;
		padding-block-start: @spacing-100;

		.ext-neowiki-display-rule-list {
			.ext-neowiki-edge-to-edge-in-dialog();
		}
	}
}
</style>
