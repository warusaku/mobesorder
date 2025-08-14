const path = require('path');

module.exports = {
  mode: 'production',
  // liff-init.js だけを圧縮・上書きビルド
  entry: {
    'liff-init': './order/js/liff-init.js'
  },
  output: {
    // /order ディレクトリ配下に js/liff-init.js として出力
    path: path.resolve(__dirname, 'order'),
    filename: 'js/[name].js',
    clean: false // 既存ファイルをそのままにする
  },
  optimization: {
    minimize: true
  }
}; 