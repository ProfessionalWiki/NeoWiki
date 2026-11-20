<template>
	<EditorPanes
		class="ext-neowiki-layout-editor"
		:start-pane="{ ...SETTINGS_PANE_SIZE, resizeLabel: $i18n( 'neowiki-layout-editor-resize' ).text() }"
		:stacks-below-desktop="true"
	>
		<template #start>
			<div class="ext-neowiki-layout-editor__settings">
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
						@update:display-rules="onDisplayRulesChanged"
						@hover="hoveredProperty = $event"
						@select="onSelectedInList"
						@reordering="draggedOrder = $event"
					/>
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
import { computed, ref, shallowRef, watch } from 'vue';
import { Layout, type DisplayRule } from '@/domain/Layout.ts';
import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { CdxField, CdxMessage, CdxTextArea } from '@wikimedia/codex';
import EditorPanes, { type StartPaneOptions } from '@/components/common/EditorPanes.vue';
import DisplayRuleList, { type DisplayRuleListExposes, type DraggedOrder, type PreviewMarks } from './DisplayRuleList.vue';
import LayoutPreview from './LayoutPreview.vue';
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

const schemaRepo = NeoWikiServices.getSchemaRepository();
const schemaStore = useSchemaStore();

const description = ref( props.initialLayout.getDescription() );
const currentDisplayRules = shallowRef<DisplayRule[]>( [ ...props.initialLayout.getDisplayRules() ] );
const schemaProperties = shallowRef<PropertyDefinition[]>( [] );
const targetingDependentSubjects = shallowRef<PropertyName[]>( [] );
const schemaFetchFailed = ref( false );

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

function onSelectedInPreview( name: string ): void {
	selectedProperty.value = name;
	displayRuleList.value?.focusProperty( name );
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
	}
}
</style>
