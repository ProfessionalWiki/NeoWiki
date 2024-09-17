<template>
	<div class="infobox">
		<div class="infobox-title">
			{{ title }}
		</div>
		<div class="infobox-statements">
			<div
				v-for="( statement, index ) in statements"
				:key="index"
				class="infobox-statement">
				<div class="infobox-statement-property">
					{{ statement.property }}
				</div>
				<div class="infobox-statement-value">
					{{ statement.value }}
				</div>
			</div>
		</div>
		<div class="infobox-edit">
			<a href="#" @click.prevent="openEditor">
				{{ $i18n( 'neowiki-infobox-edit-link' ).text() }}
			</a>
		</div>
		<InfoboxEditor
			ref="infoboxEditorDialog"
			:selected-type="title"
			:initial-statements="statements"
			:is-edit-mode="true"
			@complete="onEditComplete"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import InfoboxEditor from '@/components/Infobox/InfoboxEditor.vue';

defineProps<{
	title: string;
	statements?: { property: string; value: string }[];
}>();

const infoboxEditorDialog = ref<typeof InfoboxEditor | null>( null );

const openEditor = (): void => {
	if ( infoboxEditorDialog.value ) {
		infoboxEditorDialog.value.openDialog();
	}
};

const onEditComplete = ( updatedStatements: { property: string; value: string }[] ): void => {
	console.log( 'Updated statements:', updatedStatements );
};
</script>

<style scoped lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.infobox {
	border: $border-base;
	max-width: 300px;
}

.infobox-title {
	text-align: center;
}

.infobox-statement {
	display: flex;
}

.infobox-edit {
	text-align: right;
	padding: 5px;
}

.infobox-edit a {
	color: #0645ad;
	text-decoration: none;
}

.infobox-edit a:hover {
	text-decoration: underline;
}
</style>
