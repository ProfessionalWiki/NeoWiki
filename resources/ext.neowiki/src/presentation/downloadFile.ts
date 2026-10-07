export function downloadFile( fileName: string, content: string, type: string ): void {
	const url = URL.createObjectURL( new Blob( [ content ], { type } ) );
	const link = document.createElement( 'a' );

	link.href = url;
	link.download = fileName;
	link.click();

	// Some browsers read the file only after the click has returned, so it is released late.
	setTimeout( () => URL.revokeObjectURL( url ), 40000 );
}
