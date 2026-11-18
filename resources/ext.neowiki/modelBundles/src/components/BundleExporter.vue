<template>
	<div class="ext-neowiki-bundle-export">
		<h2 class="ext-neowiki-bundle-export__title">
			{{ $i18n( 'neowiki-bundle-export-title' ).text() }}
		</h2>

		<CdxMessage
			v-if="error !== null"
			type="error"
		>
			{{ error }}
		</CdxMessage>

		<p v-if="schemas === null">
			<CdxProgressBar :inline="true" />
		</p>

		<template v-else>
			<section class="ext-neowiki-bundle-export__section">
				<h3>{{ $i18n( 'neowiki-bundle-type-schema' ).text() }}</h3>
				<CdxCheckbox
					:model-value="chosen.length === schemas.length && schemas.length > 0"
					:indeterminate="chosen.length > 0 && chosen.length < schemas.length"
					:disabled="exporting"
					@update:model-value="chosen = $event ? schemas.map( ( schema ) => schema.name ) : []"
				>
					<strong>{{ $i18n( 'neowiki-bundle-preview-all', schemas.length ).text() }}</strong>
				</CdxCheckbox>
				<div class="ext-neowiki-bundle-export__schemas">
					<div
						v-for="schema in schemas"
						:key="schema.name"
						class="ext-neowiki-bundle-export__schema"
					>
						<CdxCheckbox
							v-model="chosen"
							:input-value="schema.name"
							:disabled="exporting"
						>
							{{ schema.name }}
						</CdxCheckbox>
						<span
							v-if="schema.subjectsAreDependent"
							class="ext-neowiki-bundle-export__note"
						>
							{{ $i18n( 'neowiki-bundle-export-dependent-subjects' ).text() }}
						</span>
						<span
							v-else-if="subjectCountOf( schema.name ) === 0"
							class="ext-neowiki-bundle-export__note"
						>
							{{ $i18n( 'neowiki-bundle-export-no-subjects' ).text() }}
						</span>
						<CdxToggleSwitch
							v-else
							v-model="withSubjects"
							:input-value="schema.name"
							:disabled="exporting || !chosen.includes( schema.name )"
						>
							{{ subjectToggleLabel( schema.name ) }}
						</CdxToggleSwitch>
						<span
							v-if="addedSchemasNote( schema.name ) !== null"
							class="ext-neowiki-bundle-export__note"
						>
							{{ addedSchemasNote( schema.name ) }}
						</span>
					</div>
				</div>
			</section>

			<section class="ext-neowiki-bundle-export__section">
				<CdxCheckbox
					v-model="layouts"
					:disabled="exporting"
				>
					{{ $i18n( 'neowiki-bundle-export-layouts' ).text() }}
				</CdxCheckbox>
			</section>

			<section
				v-if="mappingNames.length > 0"
				class="ext-neowiki-bundle-export__section"
			>
				<h3>{{ $i18n( 'neowiki-bundle-type-mapping' ).text() }}</h3>
				<CdxCheckbox
					:model-value="chosenMappings.length === mappingNames.length"
					:indeterminate="chosenMappings.length > 0 && chosenMappings.length < mappingNames.length"
					:disabled="exporting"
					@update:model-value="chosenMappings = $event ? [ ...mappingNames ] : []"
				>
					<strong>{{ $i18n( 'neowiki-bundle-preview-all', mappingNames.length ).text() }}</strong>
				</CdxCheckbox>
				<div class="ext-neowiki-bundle-export__mappings">
					<CdxCheckbox
						v-for="name in mappingNames"
						:key="name"
						v-model="chosenMappings"
						:input-value="name"
						:disabled="exporting"
					>
						{{ name }}
					</CdxCheckbox>
				</div>
			</section>

			<section class="ext-neowiki-bundle-export__section">
				<h3>{{ $i18n( 'neowiki-bundle-export-metadata' ).text() }}</h3>
				<p class="ext-neowiki-bundle-export__note">
					{{ $i18n( 'neowiki-bundle-export-metadata-help' ).text() }}
				</p>
				<CdxField
					:status="nameIsUsable ? 'default' : 'error'"
					:messages="nameIsUsable ? {} : { error: $i18n( 'neowiki-bundle-name-unusable' ).text() }"
				>
					<template #label>
						{{ $i18n( 'neowiki-bundle-name' ).text() }}
					</template>
					<CdxTextInput
						v-model="bundleName"
						:disabled="exporting"
					/>
				</CdxField>
				<CdxField>
					<template #label>
						{{ $i18n( 'neowiki-bundle-export-version' ).text() }}
					</template>
					<CdxTextInput
						v-model="version"
						:disabled="exporting || bundleName.trim() === ''"
					/>
				</CdxField>
				<CdxField>
					<template #label>
						{{ $i18n( 'neowiki-bundle-export-description' ).text() }}
					</template>
					<CdxTextArea
						v-model="description"
						:disabled="exporting || bundleName.trim() === ''"
					/>
				</CdxField>
			</section>

			<p v-if="exporting">
				<CdxProgressBar :inline="true" />
			</p>
		</template>

		<div class="ext-neowiki-bundle-export__actions">
			<CdxButton
				action="progressive"
				weight="primary"
				:disabled="exporting || schemas === null || ( chosen.length === 0 && chosenMappings.length === 0 ) || !nameIsUsable"
				@click="exportFile"
			>
				<CdxIcon :icon="cdxIconDownload" />
				{{ $i18n( 'neowiki-bundle-export-button' ).text() }}
			</CdxButton>
			<CdxButton
				:disabled="exporting"
				@click="emit( 'close' )"
			>
				{{ $i18n( 'cancel' ).text() }}
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import {
	CdxButton,
	CdxCheckbox,
	CdxField,
	CdxIcon,
	CdxMessage,
	CdxProgressBar,
	CdxTextArea,
	CdxTextInput,
	CdxToggleSwitch
} from '@wikimedia/codex';
import { cdxIconDownload } from '@wikimedia/codex-icons';
import { ModelBundleServices } from '../ModelBundleServices.ts';
import { downloadFile, useSubjectCounts, type SchemaSummary } from 'ext.neowiki';
import { dependentSchemasOf, exportBundle } from '../application/bundleExport.ts';
import { serializeBundleFile } from '../persistence/bundleFile.ts';
import { isUsableBundleName, normalizeBundleName } from '../application/bundleName.ts';

const emit = defineEmits<{
	close: [];
}>();

const repository = ModelBundleServices.getDataModelRepository();
const { subjectCountOf, loadSubjectCounts } = useSubjectCounts();

const schemas = ref<SchemaSummary[] | null>( null );
const chosen = ref<string[]>( [] );
const withSubjects = ref<string[]>( [] );
const layouts = ref( true );
const mappingNames = ref<string[]>( [] );
const chosenMappings = ref<string[]>( [] );
const bundleName = ref( '' );
const version = ref( '' );
const description = ref( '' );
const exporting = ref( false );
const error = ref<string | null>( null );
// By Schema name, the dependent Schemas its Subjects bring, read once its Subjects are switched on.
const addedSchemas = ref<Record<string, string[]>>( {} );
const schemasRead = new Set<string>();

// Without a name the file is a plain export, which takes no bundle name.
const nameIsUsable = computed( () => bundleName.value.trim() === '' || isUsableBundleName( bundleName.value ) );

function subjectToggleLabel( schemaName: string ): string {
	const count = subjectCountOf( schemaName );

	return count === null ?
		mw.msg( 'neowiki-bundle-export-subjects' ) :
		mw.msg( 'neowiki-bundle-export-subjects-count', mw.language.convertNumber( count ), count );
}

watch( withSubjects, ( names ) => {
	names.filter( ( name ) => !schemasRead.has( name ) ).forEach( readAddedSchemas );
} );

async function readAddedSchemas( name: string ): Promise<void> {
	schemasRead.add( name );
	const dependent = new Set( ( schemas.value ?? [] ).filter( ( schema ) => schema.subjectsAreDependent ).map( ( schema ) => schema.name ) );

	try {
		addedSchemas.value = { ...addedSchemas.value, [ name ]: dependentSchemasOf( ( await repository.getSchema( name ) ).json, dependent ) };
	} catch ( _error ) {
		schemasRead.delete( name );
	}
}

/**
 * Names the dependent Schemas that including a Schema's Subjects adds to the export, unless they are chosen.
 */
function addedSchemasNote( name: string ): string | null {
	const added = ( addedSchemas.value[ name ] ?? [] ).filter( ( schema ) => !chosen.value.includes( schema ) );

	return withSubjects.value.includes( name ) && added.length > 0 ?
		mw.msg( 'neowiki-bundle-export-adds-schemas', mw.language.listToText( added ), added.length ) :
		null;
}

async function exportFile(): Promise<void> {
	exporting.value = true;
	error.value = null;

	try {
		const name = normalizeBundleName( bundleName.value );
		const metadata = name === '' ? null : { name, version: version.value.trim(), description: description.value.trim() };
		const bundle = await exportBundle( {
			schemas: ( schemas.value ?? [] )
				.filter( ( schema ) => chosen.value.includes( schema.name ) )
				.map( ( schema ) => ( {
					name: schema.name,
					withSubjects: withSubjects.value.includes( schema.name )
				} ) ),
			layouts: layouts.value,
			mappings: mappingNames.value.filter( ( name ) => chosenMappings.value.includes( name ) ),
			metadata
		}, repository );

		downloadFile( fileNameOf( metadata ), serializeBundleFile( bundle ), 'application/json' );
	} catch ( exportError ) {
		error.value = exportError instanceof Error ? exportError.message : String( exportError );
	} finally {
		exporting.value = false;
	}
}

function fileNameOf( metadata: { name: string; version: string } | null ): string {
	if ( metadata === null ) {
		return `${ mw.config.get( 'wgServerName' ) }-data-model.json`;
	}

	return `${ metadata.name }${ metadata.version === '' ? '' : `-${ metadata.version }` }.json`;
}

onMounted( async () => {
	loadSubjectCounts();

	try {
		const [ summaries, mappings ] = await Promise.all( [ repository.getSchemaSummaries(), repository.getMappingNames() ] );
		mappingNames.value = mappings.sort( ( a, b ) => a.localeCompare( b ) );
		chosenMappings.value = [ ...mappingNames.value ];
		schemas.value = summaries.sort( ( a, b ) => a.name.localeCompare( b.name ) );
		chosen.value = schemas.value.map( ( schema ) => schema.name );
	} catch ( loadError ) {
		error.value = loadError instanceof Error ? loadError.message : String( loadError );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-bundle-export {
	&__title {
		margin-top: 0;
	}

	&__section + &__section {
		margin-top: @spacing-150;
	}

	&__schemas,
	&__mappings {
		padding-inline-start: calc( @min-size-input-binary + @spacing-50 );
	}

	&__schema {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		column-gap: @spacing-200;

		.cdx-checkbox {
			min-width: 16rem;
		}
	}

	&__note {
		color: @color-subtle;
	}

	&__actions {
		display: flex;
		gap: @spacing-50;
		margin-top: @spacing-150;
	}
}
</style>
