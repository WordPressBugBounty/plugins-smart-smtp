'use strict';

require('dotenv').config();
const fs = require('fs');
const pkg = JSON.parse(fs.readFileSync('./package.json'));
const { dest, series, src, watch, parallel, task } = require('gulp');
const { exec } = require('child_process');
const browserSync = require('browser-sync').create();
const bump = require('gulp-bump');

if (!process.env.WORDPRESS_URL && process.env.DEVELOPMENT) {
    console.error('Please set WORDPRESS_URL on your environment variable');
    process.exit(1);
}

const fileList = {
    includes: {
        src: 'includes/**/*',
        dest: `build/${pkg.name}/includes`,
    },
    languages: {
        src: 'languages/**/*',
        dest: `build/${pkg.name}/languages`,
    },
    composer: {
        src: ['composer.json', 'composer.lock'],
        dest: `build/${pkg.name}/`,
    },
    npm: {
        src: ['package.json'],
        dest: `build/${pkg.name}/`,
    },
    other: {
        src: ['readme.txt', 'changelog.txt', 'smart-smtp.php'],
        dest: `build/${pkg.name}/`,
    },
    dist: {
        src: 'dist/**/*',
        dest: `build/${pkg.name}/dist`,
    }
};

// Load ES modules dynamically
async function loadModules() {
    const autoprefixer = (await import('gulp-autoprefixer')).default;
    const zip = (await import('gulp-zip')).default;
    return { autoprefixer, zip };
}

const paths = {
    frontEndJs: { src: 'js/**/*.js' },
    php: { src: '**/*.php' },
    images: { src: 'images/**/*' },
};

function startBrowserSync(cb) {
    browserSync.init({
        proxy: process.env.WORDPRESS_URL,
    });
    cb();
}

function reloadBrowserSync(cb) {
    browserSync.reload();
    cb();
}

function watchChanges() {
    watch(paths.frontEndJs.src, series(reloadBrowserSync));
    watch(paths.php.src, reloadBrowserSync);
    watch(paths.images.src, series(reloadBrowserSync));
}

function removeDirectory(directory) {
    return new Promise((resolve, reject) => {
        exec(`rm -rf ${directory}`, (err) => {
            if (err) reject(err);
            resolve();
        });
    });
}

function clean() {
    return Promise.all([
        removeDirectory('dist'),
        removeDirectory('build'),
        removeDirectory('release'),
        removeDirectory('languages/smart-smtp*')
    ]);
}

function runBuild() {
    return new Promise((resolve, reject) => {
        exec('npm install && npm run build', (err) => {
            if (err) reject(err);
            resolve();
        });
    });
}

const copyToBuild = [
    () => src(fileList.includes.src).pipe(dest(fileList.includes.dest)),
    () => src(fileList.languages.src).pipe(dest(fileList.languages.dest)),
    () => src(fileList.composer.src).pipe(dest(fileList.composer.dest)),
    () => src(fileList.npm.src).pipe(dest(fileList.npm.dest)),
    () => src(fileList.other.src, { allowEmpty: true }).pipe(dest(fileList.other.dest)),
    () => src(fileList.dist.src).pipe(dest(fileList.dist.dest)),
];

function runComposerInBuild() {
    return new Promise((resolve, reject) => {
        exec(`cd build/${pkg.name} && composer install --no-dev --prefer-dist --optimize-autoloader`, (err) => {
            if (err) reject(err);
            resolve();
        });
    });
}

async function compressBuildWithoutVersion() {
    const { zip } = await loadModules();
    return src('build/**/*')
        .pipe(zip(`${pkg.name}.zip`))
        .pipe(dest('release'));
}

async function compressBuildWithVersion() {
    const { zip } = await loadModules();
    return src('build/**/*')
        .pipe(zip(`${pkg.name}-${pkg.version}.zip`))
        .pipe(dest('release'));
}

async function bumpVersion(type) {
    const versionBump = bump({ keys: ['version'], type });
    const readmeBump = bump({
        keys: ['Stable tag', '\\*\\*Stable tag\\*\\*'],
        type,
    });
    const smartSmtpBump = bump({
        key: 'SMART_SMTP_VERSION',
        regex: new RegExp(
            `([<|\'|"]?(SMART_SMTP_VERSION)[>|\'|"]?[ ]*[:=,]?[ ]*[\'|"]?[a-z]?)(\\d+.\\d+.\\d+)(-[0-9A-Za-z.-]+)?(\\+[0-9A-Za-z\\.-]+)?([\'|"|<]?)`,
            'i',
        ),
        type,
    });

    return Promise.all([
        src(['./package.json', './composer.json']).pipe(versionBump).pipe(dest('./')),
        src(['./README.md'], { allowEmpty: true }).pipe(readmeBump).pipe(dest('./')),
        src('./smart-smtp.php').pipe(smartSmtpBump).pipe(dest('./')),
    ]);
}

const build = series(
    clean,
    runBuild,
    copyToBuild
);

const dev = series(startBrowserSync, watchChanges);
const release = series(
    clean,
    build,
    runComposerInBuild,
    parallel(compressBuildWithVersion, compressBuildWithoutVersion),
);

const bumpPatch = task('bumpPatch', () => bumpVersion('patch'));
const bumpMinor = task('bumpMinor', () => bumpVersion('minor'));

exports.clean = clean;
exports.dev = dev;
exports.build = build;
exports.release = release;
exports.bumpPatch = bumpPatch;
exports.bumpMinor = bumpMinor;
