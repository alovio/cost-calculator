const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/index.js',
		frontend: './src/frontend.js',
		// Named 'block/index' so the bundle lands beside the copied block.json
		// (its "editorScript": "file:./index.js" resolves relative to the json).
		'block/index': './src/block/index.js',
	},
};
