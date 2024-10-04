<template>
	<div>
		<div class="infobox">
			<div class="infobox-title">
				{{ subject.getLabel() }}
			</div>
			<div class="infobox-statements">
				<div class="infobox-statement">
					<div class="infobox-statement-property">
						{{ $i18n( 'neowiki-infobox-type' ).text() }}
					</div>
					<div class="infobox-statement-value">
						{{ schema.getName() }}
					</div>
				</div>
				<div
					v-for="( propertyDefinition, propertyName ) in propertiesToDisplay"
					:key="propertyName"
					class="infobox-statement"
				>
					<div class="infobox-statement-property">
						{{ propertyName }}
					</div>
					<div class="infobox-statement-value">
						<component
							:is="getComponent( propertyDefinition.format )"
							:key="`${propertyDefinition.name}${subjectRef?.getStatementValue( propertyDefinition.name )}-automatic-infobox`"
							:value="subjectRef?.getStatementValue( propertyDefinition.name )"
							:property="propertyDefinition"
						/>
					</div>
				</div>
				<a
					v-if="canEditSubject"
					class="cdx-docs-link"
					@click="editInfoBox">{{ $i18n( 'neowiki-infobox-edit-link' ).text() }}</a>
				<!-- TODO: statements not in schema -->
			</div>
		</div>

		<InfoboxEditor
			v-if="canEditSubject"
			ref="infoboxEditorDialog"
			:is-edit-mode="true"
			:subject="subjectRef as Subject"
			:can-edit-schema="canEditSchema"
			@save="saveSubject" />
	</div>
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

<style scoped lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.infobox {
	border: $border-base;
	max-width: 300px;
}

.cdx-docs-link {
	margin-left: 40%;
	margin-top: 15px;
	margin-bottom: 15px;
}

.infobox-title {
	text-align: center;
	font-weight: bold;
	padding: 5px;
}

.infobox-statement {
	display: flex;
	padding: 5px;
}

.infobox-statement-property {
	font-weight: bold;
	margin-right: 5px;
}

.infobox-statement-value {
	flex: 1;
}

a {
	color: $color-progressive;
	text-decoration: none;

	&:hover {
		text-decoration: underline;
	}
}
</style>
