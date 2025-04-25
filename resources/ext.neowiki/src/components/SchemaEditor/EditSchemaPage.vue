<template>
	<div class="ext-neowiki-edit-schema-action">
		<SchemaEditor
			v-model:schema="schema"
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
	</div>
</template>

<script setup lang="ts">
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';

const props = defineProps<{ initialSchema: Schema }>();

const schema = ref( props.initialSchema );

const saveSchema = async (): Promise<void> => {
	console.log( 'TODO: save schema', schema );
};
</script>
