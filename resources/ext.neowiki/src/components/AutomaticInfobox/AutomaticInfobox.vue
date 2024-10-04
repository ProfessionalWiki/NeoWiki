<template>
	<div class="fancy-infobox">
		<div class="fancy-infobox__header">
			<h2 class="fancy-infobox__title">
				{{ subject.getLabel() }}
			</h2>
			<CdxInfoChip class="fancy-infobox__schema-badge">
				{{ schema.getName() }}
			</CdxInfoChip>
		</div>
		<div class="fancy-infobox__content">
			<div
				v-for="( propertyDefinition, propertyName ) in propertiesToDisplay"
				:key="propertyName"
				class="fancy-infobox__item"
			>
				<div class="fancy-infobox__property">
					{{ propertyName }}
				</div>
				<div class="fancy-infobox__value">
					<component
						:is="getComponent( propertyDefinition.format )"
						:key="`${propertyDefinition.name}${subjectRef?.getStatementValue( propertyDefinition.name )}-fancy-infobox`"
						:value="subjectRef?.getStatementValue( propertyDefinition.name )"
						:property="propertyDefinition"
					/>
				</div>
			</div>
		</div>
		<div v-if="canEditSubject" class="fancy-infobox__footer">
			<CdxButton
				action="progressive"
				weight="quiet"
				class=""
				@click="editInfoBox">
				{{ $i18n( 'neowiki-infobox-edit-link' ).text() }}
			</CdxButton>
		</div>
	</div>

	<InfoboxEditor
		v-if="canEditSubject"
		ref="infoboxEditorDialog"
		:is-edit-mode="true"
		:subject="subjectRef as Subject"
		:can-edit-schema="canEditSchema"
		@save="saveSubject"
	/>
</template>

<script setup lang="ts">
import { computed, ref, PropType, onMounted } from 'vue';
import { Subject } from '@neo/domain/Subject';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';
import { Schema } from '@neo/domain/Schema';
import { Component } from 'vue';
import InfoboxEditor from '@/components/Infobox/InfoboxEditor.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps( {
	subject: {
		type: Object as PropType<Subject>,
		required: true
	},
	schema: {
		type: Object as PropType<Schema>,
		required: true
	},
	canEditSubject: {
		type: Boolean,
		required: true
	}
} );

const canEditSchema = ref( false );

const infoboxEditorDialog = ref<typeof InfoboxEditor|null>( null );
const subjectRef = ref( props.subject );

const schemaStore = useSchemaStore();

const getComponent = ( formatName: string ): Component => NeoWikiServices.getComponentRegistry().getValueDisplayComponent( formatName );

const propertiesToDisplay = computed( (): Record<string, PropertyDefinition> => {
	if ( !subjectRef.value ) {
		console.log( 'subjectRef is null or undefined' );
		return {};
	}

	const schemaName = subjectRef.value.getSchemaName();
	const schema = schemaStore.getSchema( schemaName );

	if ( !schema ) {
		console.error( `Schema not found for name: ${ schemaName }` );
		return {};
	}

	const nonEmptyProperties = subjectRef.value.getNamesOfNonEmptyProperties();

	return schema.getPropertyDefinitions()
		.withNames( nonEmptyProperties )
		.asRecord();
} );

const editInfoBox = (): void => {
	infoboxEditorDialog.value?.openDialog();
	console.log( props.subject?.getId() );
};

const saveSubject = ( savedSubject: Subject ): void => {
	console.log( 'Saved Subject:', savedSubject );
	console.log( 'Saved Subject Statements:', savedSubject.getStatements() );
	subjectRef.value = savedSubject;
	console.log( 'Updated subjectRef:', subjectRef.value );
};

onMounted( async (): Promise<void> => {
	canEditSchema.value = await NeoWikiServices.getSchemaAuthorizer().canEditSchema( props.schema.getName() );
} );

</script>

<style lang="scss">
.fancy-infobox {
	margin-bottom: 16px;
	max-width: 450px;
	border-radius: 8px;
	background-color: #f8f9fa;
	box-shadow: 0 1px 3px rgb(255, 255, 255);
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Lato', 'Helvetica', 'Arial', sans-serif;
	overflow: hidden;
	transition: all 0.3s ease;

	&__header {
		background-color: #eaecf0; // Changed to a light gray color similar to MediaWiki/Codex
		color: #202122; // Adjusted text color for better contrast
		padding: 20px;
		position: relative;
		overflow: hidden;

		&::after {
			content: '';
			position: absolute;
			top: -50%;
			left: -50%;
			width: 200%;
			height: 200%;
		}
	}

	&__title {
		font-size: 1.5em;
		margin: 0;
		position: relative;
		z-index: 1;
		border: none;
	}

	&__schema-badge {
		position: absolute;
		top: 10px;
		right: 10px;
		border: 1px solid #202122b5;

		.cdx-info-chip--text {
			color: #202122b5;

		}
	}

	&__content {
		padding: 20px;
	}

	&__item {
		display: flex;
		align-items: flex-start;
		margin-bottom: 15px;
		padding-bottom: 15px;
		border-bottom: 1px solid #e0e0e0;

		&:last-child {
			border-bottom: none;
			margin-bottom: 0;
			padding-bottom: 0;
		}
	}

	&__property {
		flex: 0 0 40%;
		font-weight: bold;
		color: #333;
		font-size: 0.9em;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	&__value {
		flex: 1;
		color: #555;
		font-size: 0.95em;
		line-height: 1.4;
	}

	&__footer {
		background-color: #f8f9fa;
		padding: 15px 20px;
		text-align: right;
	}

	&__edit-icon {
		margin-right: 8px;
		font-size: 1.1em;
	}
}
</style>
