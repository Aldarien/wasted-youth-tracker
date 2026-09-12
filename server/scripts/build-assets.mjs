import { build } from 'esbuild';

await build({
  entryPoints: ['resources/scripts/index.js'],
  outfile: 'public/assets/js/index.js',
  bundle: true,
  minify: true,
  legalComments: 'none',
});

await build({
  entryPoints: ['resources/styles/wasted.css'],
  outfile: 'public/assets/css/wasted.css',
  minify: true,
  legalComments: 'none',
});
