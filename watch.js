const chokidar = require('chokidar');
const { exec } = require('child_process');
const path = require('path');

const watchDir = 'resources/ext.neowiki.addButton/ts';

const watcher = chokidar.watch(watchDir, {
	persistent: true,
	ignoreInitial: true,
	ignored: /(^|[\/\\])\../ // ignore dotfiles
});

console.log(`Watching for file changes in ${watchDir}...`);

watcher.on('change', (filePath) => {
	console.log(`File ${path.basename(filePath)} has been changed`);
	exec('node build.js', (error, stdout, stderr) => {
		if (error) {
			console.error(`Error: ${error.message}`);
			return;
		}
		if (stderr) {
			console.error(`Stderr: ${stderr}`);
			return;
		}
		console.log(`Build output: ${stdout}`);
	});
});

watcher.on('add', (filePath) => {
	console.log(`New file ${path.basename(filePath)} has been added`);
	exec('node build.js', (error, stdout, stderr) => {
		if (error) {
			console.error(`Error: ${error.message}`);
			return;
		}
		if (stderr) {
			console.error(`Stderr: ${stderr}`);
			return;
		}
		console.log(`Build output: ${stdout}`);
	});
});

watcher.on('unlink', (filePath) => {
	console.log(`File ${path.basename(filePath)} has been removed`);
	exec('node build.js', (error, stdout, stderr) => {
		if (error) {
			console.error(`Error: ${error.message}`);
			return;
		}
		if (stderr) {
			console.error(`Stderr: ${stderr}`);
			return;
		}
		console.log(`Build output: ${stdout}`);
	});
});
