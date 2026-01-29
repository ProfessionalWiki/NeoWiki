<template>
	<div class="ext-neowiki-subject-creator">
		<p>
			{{ $i18n( 'neowiki-subject-creator-schema-title' ).text() }}
		</p>

		<CdxToggleButtonGroup
			v-model="selectedValue"
			class="ext-neowiki-subject-creator-schema-options"
			:buttons="buttons"
		/>

		<div
			v-if="selectedValue === 'existing'"
			class="ext-neowiki-subject-creator-existing"
		>
			<SchemaLookup
				ref="schemaLookupRef"
				@select="onSchemaSelected"
			/>
		</div>

		<div v-if="selectedValue === 'new'">
			TODO: New schema UI
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted } from 'vue';
import { CdxToggleButtonGroup } from '@wikimedia/codex';
import { cdxIconSearch, cdxIconAdd } from '@wikimedia/codex-icons';
import type { ButtonGroupItem } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { Subject } from '@/domain/Subject.ts';
import SchemaLookup from '@/components/SubjectCreator/SchemaLookup.vue';

const emit = defineEmits<{
	'create': [ subject: Subject ];
}>();

const subjectStore = useSubjectStore();
const selectedValue = ref( 'existing' );
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const schemaLookupRef = ref<any | null>( null );

const buttons = [
	{
		value: 'existing',
		label: mw.msg( 'neowiki-subject-creator-existing-schema' ),
		icon: cdxIconSearch
	},
	{
		value: 'new',
		label: mw.msg( 'neowiki-subject-creator-new-schema' ),
		icon: cdxIconAdd
	}
] as ButtonGroupItem[];

onMounted( () => {
	focusSchemaLookup( selectedValue.value );
} );

watch( selectedValue, focusSchemaLookup );

async function focusSchemaLookup( newValue: string ): Promise<void> {
	await nextTick();
	if ( newValue === 'existing' && schemaLookupRef.value ) {
		schemaLookupRef.value.focus();
	}
}

async function onSchemaSelected( schemaName: string ): Promise<void> {
	if ( !schemaName ) {
		return;
	}

	try {
		const subject = await subjectStore.initNewSubject( schemaName );
		emit( 'create', subject );
	} catch ( error ) {
		console.error( 'Error preparing subject:', error );
		mw.notify( 'Error preparing subject: ' + String( error ), { type: 'error' } );
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-creator {
	&-schema-options.cdx-toggle-button-group {
		width: inherit;
		display: flex;
		flex-wrap: wrap;

		.cdx-toggle-button {
			flex-grow: 1;
		}
	}

	&-existing {
		margin-top: @spacing-100;
	}
}
</style>
