<template>
	<div>
		<CdxDialog title="TODO">
			<SchemaEditor
				v-model:schema="schema as Schema"
			/>

			<CdxTextArea />

			<CdxButton
				action="progressive"
				weight="primary"
				@click="saveSchema"
			>
				<CdxIcon :icon="cdxIconCheck" />
				{{ $i18n( 'neowiki-save-schema' ).text() }}
			</CdxButton>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxDialog, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';

const props = defineProps<{ initialSchema: Schema }>();

const schema = ref( props.initialSchema );

const saveSchema = async (): Promise<void> => {
	console.log( 'TODO: saving schema', schema );
	// TODO: emit event
};
</script>
