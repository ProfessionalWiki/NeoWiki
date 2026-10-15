<template>
	<div class="ext-neowiki-layout-editor">
		<div class="ext-neowiki-layout-editor__metadata">
			<span class="ext-neowiki-layout-editor__metadata-item">
				<span class="ext-neowiki-layout-editor__metadata-label">
					{{ $i18n( 'neowiki-layout-editor-schema' ).text() }}
				</span>
				{{ initialLayout.getSchema() }}
			</span>
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
				:schema-properties="schemaProperties"
				:display-rules="currentDisplayRules"
				:properties-targeting-dependent-subjects="targetingDependentSubjects"
				@update:display-rules="onDisplayRulesChanged"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, shallowRef, watch } from 'vue';
import { Layout, type DisplayRule } from '@/domain/Layout.ts';
import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { CdxField, CdxMessage, CdxTextArea } from '@wikimedia/codex';
import DisplayRuleList from './DisplayRuleList.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { propertiesTargetingDependentSubjects } from '@/domain/propertyTypes/Relation.ts';
import { subjectsAreDependentIn } from '@/application/SchemaLookup.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

const props = defineProps<{
	initialLayout: Layout;
}>();

const emit = defineEmits<{
	change: [];
}>();

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

export interface LayoutEditorExposes {
	getLayout: () => Layout;
}

defineExpose( {
	getLayout: function(): Layout {
		return new Layout(
			props.initialLayout.getName(),
			props.initialLayout.getSchema(),
			props.initialLayout.getType(),
			description.value,
			currentDisplayRules.value,
			props.initialLayout.getSettings()
		);
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-layout-editor {
	padding: @spacing-100;

	@media ( min-width: @min-width-breakpoint-desktop ) {
		padding: @spacing-150;
	}

	&__description {
		margin-block-end: @spacing-100;
	}

	&__metadata {
		display: flex;
		gap: @spacing-200;
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
