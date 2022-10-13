const gulp = require("gulp");
const livereload = require("gulp-livereload");

function livereloadStartServer(done) {
  livereload.listen({ 'port': 35777 });
  done();
}

function watchFiles(done) {
  var lr_watcher = gulp.watch([
    'web/themes/custom/union_base/**/*.css'
  ]);

  lr_watcher.on('change', livereload.changed);

  done();
}

const watch = gulp.parallel(watchFiles, livereloadStartServer);

exports.default = watch
