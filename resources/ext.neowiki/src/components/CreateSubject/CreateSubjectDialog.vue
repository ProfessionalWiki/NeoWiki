<template>
	<CdxDialog v-model:open="isOpen" :title="$i18n( 'neowiki-create-subject-dialog-title' ).text()">
		<CdxButton @click="proceedWithBlank">
			{{ $i18n( 'neowiki-create-subject-dialog-start-blank' ).text() }}
		</CdxButton>
		<p>{{ $i18n( 'neowiki-create-subject-dialog-or-select' ).text() }}</p>
		<ul class="type-list">
			<li
				v-for="type in typeOptions"
				:key="type.value"
				@click="proceedWithType( type.value )"
			>
				{{ type.label }}
			</li>
		</ul>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxDialog, CdxButton } from '@wikimedia/codex';

const emit = defineEmits( [ 'next' ] );
const isOpen = ref( false );

const typeOptions = [
	{ value: 'person', label: 'Person' },
	{ value: 'organization', label: 'Organization' },
	{ value: 'place', label: 'Place' }
];

const openDialog = (): void => {
	isOpen.value = true;
};

const proceedWithBlank = (): void => {
	isOpen.value = false;
	emit( 'next', '' );
};

const proceedWithType = ( type: string ): void => {
	isOpen.value = false;
	emit( 'next', type );
};

defineExpose( { openDialog } );
</script>

<style scoped>
/* TODO: replace styles */
.type-list {
	padding: 0;
	margin: 0;
}

.type-list li {
	padding: 8px;
	cursor: pointer;
	transition: background-color 0.2s;
	list-style: none;
}

.type-list li:hover {
	background-color: #f0f0f0;
}
</style>
