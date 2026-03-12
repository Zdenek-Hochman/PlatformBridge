import esbuild from "esbuild";
import { sassPlugin } from "esbuild-sass-plugin";

const watch = process.argv.includes("--watch");
const isProd = process.argv.includes("--prod");

// ✅ Output vždy do dist/ (ne do public/)
const tsCtx = await esbuild.context({
	entryPoints: ["assets/ts/pb-main.ts"],
	outdir: "dist/js",
	bundle: true,
	minify: isProd,
	sourcemap: !isProd,
	entryNames: "[name]",
	tsconfig: "tsconfig.json"
});

const scssCtx = await esbuild.context({
	entryPoints: ["assets/scss/pb-main.scss"],
	entryNames: "[name]",
	outdir: "dist/css",
	bundle: true,
	minify: isProd,
	sourcemap: !isProd,
	plugins: [sassPlugin({ loadPaths: ["assets/scss"] })]
});

if (watch) {
	await tsCtx.watch();
	await scssCtx.watch();
	console.log("[esbuild] Watching for changes...");
} else {
	await tsCtx.rebuild();
	await scssCtx.rebuild();
	await tsCtx.dispose();
	await scssCtx.dispose();
	console.log("[esbuild] Build complete → dist/");
}