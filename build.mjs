import esbuild from "esbuild";
import { sassPlugin } from "esbuild-sass-plugin";

const watch = true;

/* ---------- TS build ---------- */
const tsCtx = await esbuild.context({
	entryPoints: [
		"assets/src/ts/main.ts"
	],
  	outdir: "assets/dist/js",
  	bundle: true,
  	minify: true,
  	sourcemap: false,
	entryNames: "[name]",
	tsconfig: "tsconfig.json"
});

/* ---------- SCSS build ---------- */
const scssCtx = await esbuild.context({
	entryPoints: [
		"assets/src/scss/main.scss"
	],
	entryNames: "[name]",
  	outdir: "assets/dist/css",
  	bundle: true,
  	minify: true,
  	sourcemap: false,
  	plugins: [
		sassPlugin({
			loadPaths: ["assets/src/scss"]
		})
	]
});

/* ---------- WATCH ---------- */
if (watch) {
	await tsCtx.watch();
	await scssCtx.watch();
}