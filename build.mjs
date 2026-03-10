import esbuild from "esbuild";
import { sassPlugin } from "esbuild-sass-plugin";

const watch = process.argv.includes("--watch");
const isProd = process.argv.includes("--prod");

/* ---------- TS build ---------- */
const tsCtx = await esbuild.context({
	entryPoints: [
		"assets/ts/pb-main.ts"
	],
	outdir: "public/platformbridge/js",
	bundle: true,
	minify: isProd,
	sourcemap: !isProd,
	entryNames: "[name]",
	tsconfig: "tsconfig.json"
});

/* ---------- SCSS build ---------- */
const scssCtx = await esbuild.context({
	entryPoints: [
		"assets/scss/pb-main.scss"
	],
	entryNames: "[name]",
	outdir: "public/platformbridge/css",
	bundle: true,
	minify: isProd,
	sourcemap: !isProd,
	plugins: [
		sassPlugin({
			loadPaths: ["assets/scss"]
		})
	]
});

/* ---------- BUILD / WATCH ---------- */
if (watch) {
	await tsCtx.watch();
	await scssCtx.watch();
	console.log("[esbuild] Watching for changes...");
} else {
	await tsCtx.rebuild();
	await scssCtx.rebuild();
	await tsCtx.dispose();
	await scssCtx.dispose();
	console.log("[esbuild] Build complete.");
}