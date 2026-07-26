module.exports = {
	presets: [ [ '@babel/preset-env', { bugfixes: true } ] ],
	plugins: [
		[
			'@babel/plugin-transform-react-jsx',
			{
				pragma: 'createElement',
				pragmaFrag: 'Fragment',
				runtime: 'classic',
			},
		],
		[
			'@babel/plugin-transform-runtime',
			{ helpers: true, useESModules: false },
		],
	],
};
