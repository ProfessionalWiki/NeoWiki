<template>
	<CdxDialog v-model:open="isOpen" :title="$i18n( 'neowiki-create-subject-dialog-title' ).text()">
		<CdxButton @click="proceedWithBlank">
			{{ $i18n( 'neowiki-create-subject-dialog-start-blank' ).text() }}
		</CdxButton>
		<p>{{ $i18n( 'neowiki-create-subject-dialog-or-select' ).text() }}</p>
		<ul class="schema-list">
			<li
				v-for="( [ key, schema ] ) in schemas"
				:key="key"
				@click="proceedWithSchema( schema.getName() )"
			>
				{{ schema.getName() }}
			</li>
		</ul>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { CdxDialog, CdxButton } from '@wikimedia/codex';
import { useSchemaStore } from '@/stores/SchemaStore';

const emit = defineEmits<( e: 'next', schemaName: string ) => void>();

const isOpen = ref( false );
const schemaStore = useSchemaStore();
const schemas = computed( () => schemaStore.getSchemas );

const openDialog = (): void => {
	isOpen.value = true;
};

const proceedWithBlank = (): void => {
	isOpen.value = false;
	emit( 'next', '' );
};

const proceedWithSchema = ( schemaName: string ): void => {
	isOpen.value = false;
	emit( 'next', schemaName );
};

defineExpose( { openDialog } );
</script>

<style scoped>
.schema-list {
	padding: 0;
	margin: 0;
}

.schema-list li {
	padding: 8px;
	cursor: pointer;
	transition: background-color 0.2s;
	list-style: none;
}

.schema-list li:hover {
	background-color: #f0f0f0;
}
</style>
