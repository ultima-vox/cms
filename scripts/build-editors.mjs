import { build } from 'esbuild';
import { mkdir } from 'node:fs/promises';

await mkdir('public/assets', { recursive: true });
await build({
  entryPoints: ['frontend/editors/index.js'],
  bundle: true,
  format: 'iife',
  platform: 'browser',
  target: ['es2022'],
  minify: true,
  sourcemap: false,
  outfile: 'public/assets/cms-editors.js',
});
