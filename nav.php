<?php
// 版本信息
const NAV_VERSION = '1.2.1';

// 配置文件最低兼容版本
const NAV_CONF_VERSION_OLDEST = 1;

// 最新配置文件版本
const NAV_CONF_VERSION_LATEST = 2;

// 设置基础文件下载地址
const URL_NAV_OL = 'https://raw.githubusercontent.com/jokin1999/my-navigation/'.NAV_VERSION.'/';
const URL_NAV_OL_CDN = 'https://cdn.jsdelivr.net/gh/jokin1999/my-navigation@'.NAV_VERSION.'/';

// 检测运行模式
define('IS_CLI', php_sapi_name() == 'cli');

// 读取设置
$GLOBALS['config'] = @parse_ini_file('./.own_conf');
// 不存在设置文件停止前端访问
if (!$GLOBALS['config']) {
  if (IS_CLI) {
    // 非设置模式提示
    if (($argv[1] ?? null) != 'dc') {
      echo "[NAV_ERROR]配置文件缺失，使用 php " . basename(__FILE__) . " dc 下载配置或恢复默认配置";
    }
  }else{
    exit('配置文件缺失，参考：<a href="https://github.com/jokin1999/my-navigation#%E9%85%8D%E7%BD%AE">配置MY Navigation</a>');
  }
}else{
  // 设置配置文件版本
  define('NAV_CONF_VERSION_CURRENT', $GLOBALS['config']['CONF_VERSION'] ?? 0);
  // 配置文件版本过低
  if (NAV_CONF_VERSION_CURRENT < NAV_CONF_VERSION_OLDEST) {
    exit('当前配置文件（.own_conf）版本不满足当前程序要求的最低版本，可以使用 php '. __DIR__ . 'dc 下载默认配置（会覆盖原有配置）');
  }
}

// 命令行模式
if (IS_CLI) {
  $method = $argv[1] ?? null;
  define('METHOD', $method);
  if (METHOD == null || METHOD == 'help') {
    echo 'php '.basename(__FILE__).' [COMMAND]'.PHP_EOL;
    echo '    dc          download default configuration file for navigation'.PHP_EOL;
    echo '    dces        download default configuration file for each subfolder of navigation'.PHP_EOL;
    echo PHP_EOL;
    echo 'GitHub          https://github.com/jokin1999/my-navigation';
  }

  // download config
  if (METHOD == 'dc') {
    // 检查文件是否可写
    if (!is_writable('./')) {
      exit('目录'. __DIR__ .'无法写入文件');
    }
    echo '正在获取默认配置文件...' . PHP_EOL;
    $own_conf = getOnlineFile('.own_conf.example');
    if ($own_conf) {
      file_put_contents('./.own_conf', $own_conf);
      echo '文件写入完成，编辑'. __DIR__. '/.own_conf 文件以自定义配置';
    }else{
      exit('获取默认配置文件失败，请稍候再试...');
    }
  }

  // download config of each subfolder
  if (METHOD == 'dces') {
    // 检查文件是否可写
    if (!is_writable('./')) {
      exit('目录'. __DIR__ .'无法写入文件');
    }
    echo '正在获取默认配置文件...' . PHP_EOL;
    $own_conf = getOnlineFile('.own_navi.example');
    if ($own_conf) {
      file_put_contents('./.own_navi.example', $own_conf);
      echo '文件写入完成！'.PHP_EOL.'复制'. __DIR__. '/.own_navi.example 至子目录中并命名为 .own_navi 以自定义配置';
    }else{
      exit('获取默认配置文件失败，请稍候再试...');
    }
  }
}else{
  define('METHOD', NULL);
}

// 获取合法目录
$dirs = getDirs();

// 读取配置
if (c('NAV_TRY_READ_CONF') == 1) {
  $dirs = getConf($dirs);
}

// 清理导航列表
foreach ($dirs as $group => $dir) {
  if (count($dirs[$group]) <= 0) {
    unset($dirs[$group]);
  }
}

/**
 * 获取文件
 * @param  string $filename
 * @param  bool   $use_cdn
 * @param  bool   $try_again
 * @return string|bool
 */
function getOnlineFile(string $filename, bool $use_cdn = true, bool $try_again = true) {
  $url = ($use_cdn === true ? URL_NAV_OL_CDN : URL_NAV_OL) . $filename;
  echo '文件获取地址：'. $url . PHP_EOL;
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, FALSE);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
  $res = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($res && $httpCode == 200) {
    echo '获取文件成功！' . PHP_EOL;
    return $res;
  }else{
    // 不使用cdn再次尝试
    if ($try_again === true) {
      echo '获取失败，使用原地址再次尝试...' . PHP_EOL;
      return getOnlineFile($filename, false, false);
    }else{
      return false;
    }
  }
}

/**
 * 读取目录配置
 * @param  array $dirs
 * @return array
 */
function getConf(array $dirs) : array {
  foreach ($dirs as $group => $g_dirs) {
    foreach ($g_dirs as $dir => $value) {
      $path = './' . $dir . '/.own_navi';
      if (is_file($path)) {
        $confs = parse_ini_file($path);
        if (c('NAV_ALLOW_GROUP') != 1){
          $cur_group = c('NAV_DEFAULT_GROUP', '默认分组');
        }else{
          $cur_group = $confs['GROUP'] ?? c('NAV_DEFAULT_GROUP', '默认分组');
        }
        if ($confs) {
          // 如果不是默认分组从默认分组删除
          if (isset($dirs[c('NAV_DEFAULT_GROUP', '默认分组')][$dir]) && $cur_group != c('NAV_DEFAULT_GROUP', '默认分组')) {
            unset($dirs[c('NAV_DEFAULT_GROUP', '默认分组')][$dir]);
          }
          foreach ($confs as $key => $value) {
            if ($key === 'TITLE' && $value === '') {
              $value = $dir;
            }
            $dirs[$cur_group][$dir][strtolower($key)] = $value;
          }
        }
      }else{
        continue;
      }
    }
  }
  return $dirs;
}

/**
 * 获取目录
 * @param  void
 * @return array
 */
function getDirs() : array {
  $_dirs = scandir('./');
  $dirs = [];
  foreach ($_dirs as $dir) {
    // 忽略.开头的文件夹
    if ($dir == '.' || $dir == '..' || substr($dir, 0, 1) == '.') {
      continue;
    }
    if (is_dir('./' . $dir)) {
      $dirs[c('NAV_DEFAULT_GROUP', '默认分组')][$dir]['title'] = $dir;
    }
  }
  return $dirs;
}

/**
 * 获取设置项
 * @param  string key
 * @param  mixed  default
 * @return mixed
 */
function c(string $key, $default = false) {
  if (isset($GLOBALS['config'][$key])) {
    return $GLOBALS['config'][$key];
  }else{
    return defined($key) ? constant($key) : $default;
  }
}

/**
 * 输出设置项
 * @param  string $key
 * @return void
 */
function ec(string $key) {
  echo c($key);
}
?>
<?php if(!IS_CLI || METHOD === 'static'): ?>
<?php
  // 缓冲区处理
  if (METHOD === 'static') {
    ob_start();
  }
?>
<!doctype html>
<html lang="zh-cn">
  <head data-n-head="">
    <title><?php ec('NAV_NAME'); ?></title>
    <meta charset="utf-8">
    <meta name="keywords" content="<?php ec('NAV_KEYWORDS'); ?>">
    <meta name="description" itemprop="description" content="<?php ec('NAV_DESCRIPTION'); ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
    /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0}main{display:block}h1{font-size:2em;margin:.67em 0}hr{box-sizing:content-box;height:0;overflow:visible}pre{font-family:monospace,monospace;font-size:1em}a{background-color:transparent}abbr[title]{border-bottom:none;text-decoration:underline;-webkit-text-decoration:underline dotted;text-decoration:underline dotted}b,strong{font-weight:bolder}code,kbd,samp{font-family:monospace,monospace;font-size:1em}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}img{border-style:none}button,input,optgroup,select,textarea{font-family:inherit;font-size:100%;line-height:1.15;margin:0}button,input{overflow:visible}button,select{text-transform:none}[type=button],[type=reset],[type=submit],button{-webkit-appearance:button}[type=button]::-moz-focus-inner,[type=reset]::-moz-focus-inner,[type=submit]::-moz-focus-inner,button::-moz-focus-inner{border-style:none;padding:0}[type=button]:-moz-focusring,[type=reset]:-moz-focusring,[type=submit]:-moz-focusring,button:-moz-focusring{outline:.0625rem dotted ButtonText}fieldset{padding:.35em .75em .625em}legend{box-sizing:border-box;color:inherit;display:table;max-width:100%;padding:0;white-space:normal}progress{vertical-align:baseline}textarea{overflow:auto}[type=checkbox],[type=radio]{box-sizing:border-box;padding:0}[type=number]::-webkit-inner-spin-button,[type=number]::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-.125rem}[type=search]::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}details{display:block}summary{display:list-item}[hidden],template{display:none}body,html{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}body{font-family:Source Sans Pro,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif;word-spacing:.0625rem;-moz-osx-font-smoothing:grayscale;-webkit-font-smoothing:antialiased;min-height:100vh}ul{padding-left:1.5625rem}ul li{margin:.3125rem 0}p{line-height:1.5}input[type=search]::-webkit-search-cancel-button{display:none}input{-webkit-appearance:none;border-radius:0}blockquote{padding-left:1.25rem;border-left:.25rem solid #e2e3e4;margin:1.25rem 0;color:#8a8989}.viewer-toolbar>ul>li{margin:0}.v-note-help-show{box-sizing:border-box}[type=button]{-webkit-appearance:none}.typo a{color:#249ffd;color:var(--theme);transition:all .35s;text-decoration:none;position:relative;padding-bottom:.125rem;word-wrap:break-word}.typo p{margin-bottom:1.25rem;line-height:1.7}.typo pre{margin:1em 0;padding:.625rem .9375rem;font-size:.875rem;font-family:Menlo,Monaco,Consolas,Andale Mono,lucida console,Courier New,monospace;background-color:#393e46;overflow-x:auto}.typo pre::-webkit-scrollbar{width:.625rem;height:.5rem}.typo pre::-webkit-scrollbar-thumb{border-radius:.625rem;background-color:#b6b4b4}.typo pre code{background-color:transparent}.typo code{font-family:Menlo,Monaco,Consolas,Andale Mono,lucida console,Courier New,monospace;background-color:var(--t1);color:var(--t2)}.typo img{max-width:100%;margin:.625rem 0;border-radius:.375rem}.typo h1,.typo h2,.typo h3,.typo h4,.typo h5,.typo h6{font-weight:400;color:var(--t1);margin:.9375rem 0}.typo h1{font-size:2rem}.typo h2{font-size:1.75rem}.typo h3{font-size:1.5rem}.typo h4{font-size:1.3125rem}.typo h5{font-size:1.1875rem}.typo h6{font-size:1.0625rem}.typo h4,.typo h5,.typo h6{font-weight:700}.typo h4:before,.typo h5:before,.typo h6:before{display:inline-block;width:1.25rem;content:"#";color:#aaa}.typo hr{height:.625rem;margin-bottom:.75rem;border:none;border-bottom:.0625rem dashed #393e46}.typo ol{padding-left:2em;margin:0 0 1.2em}.typo ol li{list-style:decimal}.typo ul{padding-left:2em;margin:0 0 1.2em;list-style:disc}.typo li{margin-top:.3125rem}.typo li ol,.typo li ul{margin:.8em 0}.typo li ul{list-style:circle}.typo table{color:#5b6064;border-spacing:0;border-radius:.375rem;text-align:center;border-collapse:collapse;box-shadow:0 0 0 .0625rem #eee;display:inline-block;max-width:100%;overflow:auto;white-space:nowrap;margin:auto}.typo table::-webkit-scrollbar{width:.625rem;height:.625rem}.typo table::-webkit-scrollbar-thumb{border-radius:.625rem;background-color:#888}.typo table thead{border-bottom:.0625rem solid #eee}.typo table th{padding:.4375rem .875rem;font-weight:400}.typo table td,.typo table th{border-right:.0625rem solid #eee}.typo table td{padding:.5rem .875rem}.typo table td:last-child,.typo table th:last-child{border:none}.typo table tr:nth-child(2n){background-color:#f8f8f8}html{overflow-y:scroll}.v--modal-block-scroll{height:100vh}.nya-c-theme{color:#249ffd;color:var(--theme)}.nya-c-success{color:#43cb4f;color:var(--theme-success)}.nya-c-danger{color:#fd766c;color:var(--theme-danger)}.nya-c-info{color:#9ed0ff;color:var(--theme-info)}.nya-c-wrning{color:#ffdd98;color:var(--theme-wrning)}:root{overflow-y:auto;overflow-x:hidden;--t0:transparent;--theme:#249ffd;--theme-success:#43cb4f;--theme-danger:#fd766c;--theme-info:#9ed0ff;--theme-wrning:#ffdd98;--border-color:#dcdee0}@media (max-width:500px){:root{font-size:.8125rem}}@media (max-width:380px){:root{font-size:.75rem}}@media (max-width:350px){:root{font-size:.625rem}}.sweetalert2{-webkit-animation:fadeIn .15s;animation:fadeIn .15s;box-shadow:0 1.25rem 3.75rem -.125rem rgba(27,33,58,.4)}.sweetalert2 input.swal2-input{width:100%;height:auto;padding:.625rem .9375rem;box-sizing:border-box;border:.0625rem solid #dcdee0;border:.0625rem solid var(--border-color);background-color:transparent;color:var(--t1);outline:0;transition:border-color .2s ease;box-shadow:none;border-radius:0;font-size:1rem}.sweetalert2 input.swal2-input[disabled=disabled]{opacity:.8;cursor:no-drop}.sweetalert2 input.swal2-input::-webkit-input-placeholder{color:#9e9e9e}.sweetalert2 input.swal2-input::-moz-placeholder{color:#9e9e9e}.sweetalert2 input.swal2-input:-ms-input-placeholder{color:#9e9e9e}.sweetalert2 input.swal2-input::-ms-input-placeholder{color:#9e9e9e}.sweetalert2 input.swal2-input::placeholder{color:#9e9e9e}.sweetalert2 input.swal2-input:focus{border-color:#249ffd;border-color:var(--theme)}.swal2-modal.tleft #swal2-content{text-align:left}div.swal2-container.swal2-shown{background-color:rgba(0,0,0,.2);-webkit-animation:fadeIn .2s;animation:fadeIn .2s}@-webkit-keyframes fadeIn{0%{opacity:0}to{opacity:1}}@keyframes fadeIn{0%{opacity:0}to{opacity:1}}*{-webkit-tap-highlight-color:transparent}body{width:100vw;overflow:hidden;background-color:#f4f8fb}a{color:#0366d6;word-break:break-all;text-decoration:none}a a[target=_blank]{cursor:alias}a:hover{text-decoration:underline}pre{margin:.9375rem 0;padding:.625rem .9375rem;line-height:1.5;font-size:.875rem;font-family:Menlo,Monaco,Consolas,Andale Mono,lucida console,Courier New,monospace;background-color:#f2f4f5;color:#314659;overflow-x:auto}pre::-webkit-scrollbar{width:.625rem;height:.5rem}pre::-webkit-scrollbar-thumb{background-color:#249ffd;background-color:var(--theme)}hr{height:.625rem;margin-top:0;margin-bottom:.625rem;border:none;border-bottom:.0625rem dashed #dcdee0;border-bottom:.0625rem dashed var(--border-color)}.toasted-container{top:6%!important}.toasted-container .miku-toasted{justify-content:center!important}@media (max-width:700px){.toasted-container .miku-toasted{padding:.3125rem 1.25rem!important;line-height:1.3!important}}::-webkit-scrollbar{width:.5rem;height:.625rem}::-webkit-scrollbar-thumb{background-color:#249ffd;background-color:var(--theme)}.hasbg{--border-color:#c2c2c2}.dark{background-color:var(--t2);--theme:#4183c4}.dark,.dark .hasbg{--border-color:#6a6a6a}.dark a{color:#4183c4}.dark .vue-dialog .vue-dialog-buttons{border-top:.0625rem solid #616161}.dark .pay img{background-color:hsla(0,0%,100%,.65);border-radius:.3125rem}.dark .pay img[alt=weixin]{border-radius:50%}.dark .nya-container{background-color:#282c34}.dark .nya-container.transparent{background-color:rgba(40,44,52,.65)}.dark .nya-container .nya-title{background-color:#161616;box-shadow:0 .5rem .625rem rgba(15,15,15,.30196)}.dark .nya-btn{color:inherit}.dark .nya-input .input-file::-webkit-input-placeholder,.dark .nya-input input::-webkit-input-placeholder,.dark .nya-input textarea::-webkit-input-placeholder{opacity:.5}.dark .nya-input .input-file::-moz-placeholder,.dark .nya-input input::-moz-placeholder,.dark .nya-input textarea::-moz-placeholder{opacity:.5}.dark .nya-input .input-file:-ms-input-placeholder,.dark .nya-input input:-ms-input-placeholder,.dark .nya-input textarea:-ms-input-placeholder{opacity:.5}.dark .nya-input .input-file::-ms-input-placeholder,.dark .nya-input input::-ms-input-placeholder,.dark .nya-input textarea::-ms-input-placeholder{opacity:.5}.dark .nya-input .input-file::placeholder,.dark .nya-input input::placeholder,.dark .nya-input textarea::placeholder{opacity:.5}.dark .search-component .search{background-color:#282c34}.dark .search-component .search.transparent{background-color:rgba(40,44,52,.65)}.dark pre{background-color:#acacac}.dark .float-btn{opacity:.8}.nya-subtitle{display:block;font-size:1.125rem;margin-bottom:.625rem;font-weight:700}.nya-bg-0{background-color:transparent;background-color:var(--t0)}.nya-bg-1{background-color:var(--t1)}.nya-bg-2{background-color:var(--t2)}.nya-bd-0{border-color:transparent;border-color:var(--t0)}.nya-bd-1{border-color:var(--t1)}.nya-bd-2{border-color:var(--t2)}.nya-co-0{color:transparent;color:var(--t0)}.nya-co-1{color:var(--t1)}.nya-co-2{color:var(--t2)}.vue-dialog{background-color:var(--t2)!important;color:var(--t1)!important;max-width:100%}a.nya-btn{color:var(--t1)}a.nya-btn:hover{text-decoration:none}.nya-btn{display:inline-block;padding:.625rem .9375rem;font-size:1rem;font-weight:700;cursor:pointer;outline:0;border:.0625rem solid #dcdee0;border:.0625rem solid var(--border-color);background-color:rgba(#249ffd,.1);background-color:rgba(var(--theme),.1);color:var(--t1);transition:border-color .2s ease,color .2s ease;letter-spacing:.0625rem}.nya-btn.active,.nya-btn:active{background-color:#249ffd;background-color:var(--theme);color:#fff;border-color:#249ffd;border-color:var(--theme)}.nya-btn:hover{border-color:#249ffd;border-color:var(--theme);color:#249ffd;color:var(--theme)}.nya-btn[disabled=disabled]{opacity:.8;cursor:no-drop}.nya-list{margin-left:0;padding-left:1.25rem}.nya-list li{margin:.625rem 0;line-height:1.3}.nya-table{border-spacing:0;border-color:#dcdee0;border-right:.0625rem solid;border-color:var(--border-color);border-top:.0625rem solid;border-top-color:var(--border-color);max-width:100%;margin:auto;border-collapse:collapse;white-space:nowrap;table-layout:fixed;overflow:auto}.nya-table td,.nya-table th{font-size:1.125rem;border-color:#dcdee0;border-left:.0625rem solid;border-left-color:var(--border-color);border-bottom:.0625rem solid;border-bottom-color:var(--border-color);border-right-color:var(--border-color);border-top-color:var(--border-color);border-collapse:collapse;padding:.625rem;text-align:left;text-overflow:ellipsis;overflow:hidden}@media (max-width:600px){.nya-table{display:block;white-space:nowrap;overflow:auto;width:100%;table-layout:auto}}.inputbtn{display:flex;align-items:flex-end;max-width:100%}.inputbtn .nya-btn,.inputbtn .nya-input{display:inline-block}.inputbtn .nya-input{flex:1;max-width:calc(100% - 7.5rem)}.inputbtn button{position:relative;left:-.0625rem;width:7.5rem;border-left-color:transparent}.nya-hide-scroll{-ms-overflow-style:none;overflow:-moz-scrollbars-none}.nya-hide-scroll::-webkit-scrollbar{width:0!important}fieldset{min-width:auto}.mb-15{margin-bottom:.9375rem}.mt-15{margin-top:.9375rem}.ml-15{margin-left:.9375rem}.mr-15{margin-right:.9375rem}img{max-width:100%}@font-face{font-family:Eva-Icons;src:url(/_nuxt/fonts/f910131.eot);src:url(/_nuxt/fonts/f910131.eot?#iefix) format("embedded-opentype"),url(/_nuxt/fonts/5073ed9.woff2) format("woff2"),url(/_nuxt/fonts/f8715d9.woff) format("woff"),url(/_nuxt/fonts/647aa99.ttf) format("truetype"),url(/_nuxt/img/c409411.svg#Eva-Icons) format("svg");font-style:normal;font-weight:400}.eva{display:inline-block;transform:translate(0);text-rendering:auto;font:normal normal 400 .875rem/1 Eva-Icons;font-size:inherit;-moz-osx-font-smoothing:grayscale;-webkit-font-smoothing:antialiased}.eva-lg{vertical-align:-15%;line-height:.75em;font-size:1.33333333em}.eva-2x{font-size:2em}.eva-3x{font-size:3em}.eva-4x{font-size:4em}.eva-5x{font-size:5em}.eva-fw{width:1.28571429em;text-align:center}.eva-activity:before{content:"\ea01"}.eva-activity-outline:before{content:"\ea02"}.eva-alert-circle:before{content:"\ea03"}.eva-alert-circle-outline:before{content:"\ea04"}.eva-alert-triangle:before{content:"\ea05"}.eva-alert-triangle-outline:before{content:"\ea06"}.eva-archive:before{content:"\ea07"}.eva-archive-outline:before{content:"\ea08"}.eva-arrow-back:before{content:"\ea09"}.eva-arrow-back-outline:before{content:"\ea0a"}.eva-arrow-circle-down:before{content:"\ea0b"}.eva-arrow-circle-down-outline:before{content:"\ea0c"}.eva-arrow-circle-left:before{content:"\ea0d"}.eva-arrow-circle-left-outline:before{content:"\ea0e"}.eva-arrow-circle-right:before{content:"\ea0f"}.eva-arrow-circle-right-outline:before{content:"\ea10"}.eva-arrow-circle-up:before{content:"\ea11"}.eva-arrow-circle-up-outline:before{content:"\ea12"}.eva-arrow-down:before{content:"\ea13"}.eva-arrow-down-outline:before{content:"\ea14"}.eva-arrow-downward:before{content:"\ea15"}.eva-arrow-downward-outline:before{content:"\ea16"}.eva-arrow-forward:before{content:"\ea17"}.eva-arrow-forward-outline:before{content:"\ea18"}.eva-arrow-ios-back:before{content:"\ea19"}.eva-arrow-ios-back-outline:before{content:"\ea1a"}.eva-arrow-ios-downward:before{content:"\ea1b"}.eva-arrow-ios-downward-outline:before{content:"\ea1c"}.eva-arrow-ios-forward:before{content:"\ea1d"}.eva-arrow-ios-forward-outline:before{content:"\ea1e"}.eva-arrow-ios-upward:before{content:"\ea1f"}.eva-arrow-ios-upward-outline:before{content:"\ea20"}.eva-arrow-left:before{content:"\ea21"}.eva-arrow-left-outline:before{content:"\ea22"}.eva-arrow-right:before{content:"\ea23"}.eva-arrow-right-outline:before{content:"\ea24"}.eva-arrow-up:before{content:"\ea25"}.eva-arrow-up-outline:before{content:"\ea26"}.eva-arrow-upward:before{content:"\ea27"}.eva-arrow-upward-outline:before{content:"\ea28"}.eva-arrowhead-down:before{content:"\ea29"}.eva-arrowhead-down-outline:before{content:"\ea2a"}.eva-arrowhead-left:before{content:"\ea2b"}.eva-arrowhead-left-outline:before{content:"\ea2c"}.eva-arrowhead-right:before{content:"\ea2d"}.eva-arrowhead-right-outline:before{content:"\ea2e"}.eva-arrowhead-up:before{content:"\ea2f"}.eva-arrowhead-up-outline:before{content:"\ea30"}.eva-at:before{content:"\ea31"}.eva-at-outline:before{content:"\ea32"}.eva-attach:before{content:"\ea33"}.eva-attach-2:before{content:"\ea34"}.eva-attach-2-outline:before{content:"\ea35"}.eva-attach-outline:before{content:"\ea36"}.eva-award:before{content:"\ea37"}.eva-award-outline:before{content:"\ea38"}.eva-backspace:before{content:"\ea39"}.eva-backspace-outline:before{content:"\ea3a"}.eva-bar-chart:before{content:"\ea3b"}.eva-bar-chart-2:before{content:"\ea3c"}.eva-bar-chart-2-outline:before{content:"\ea3d"}.eva-bar-chart-outline:before{content:"\ea3e"}.eva-battery:before{content:"\ea3f"}.eva-battery-outline:before{content:"\ea40"}.eva-behance:before{content:"\ea41"}.eva-behance-outline:before{content:"\ea42"}.eva-bell:before{content:"\ea43"}.eva-bell-off:before{content:"\ea44"}.eva-bell-off-outline:before{content:"\ea45"}.eva-bell-outline:before{content:"\ea46"}.eva-bluetooth:before{content:"\ea47"}.eva-bluetooth-outline:before{content:"\ea48"}.eva-book:before{content:"\ea49"}.eva-book-open:before{content:"\ea4a"}.eva-book-open-outline:before{content:"\ea4b"}.eva-book-outline:before{content:"\ea4c"}.eva-bookmark:before{content:"\ea4d"}.eva-bookmark-outline:before{content:"\ea4e"}.eva-briefcase:before{content:"\ea4f"}.eva-briefcase-outline:before{content:"\ea50"}.eva-browser:before{content:"\ea51"}.eva-browser-outline:before{content:"\ea52"}.eva-brush:before{content:"\ea53"}.eva-brush-outline:before{content:"\ea54"}.eva-bulb:before{content:"\ea55"}.eva-bulb-outline:before{content:"\ea56"}.eva-calendar:before{content:"\ea57"}.eva-calendar-outline:before{content:"\ea58"}.eva-camera:before{content:"\ea59"}.eva-camera-outline:before{content:"\ea5a"}.eva-car:before{content:"\ea5b"}.eva-car-outline:before{content:"\ea5c"}.eva-cast:before{content:"\ea5d"}.eva-cast-outline:before{content:"\ea5e"}.eva-charging:before{content:"\ea5f"}.eva-charging-outline:before{content:"\ea60"}.eva-checkmark:before{content:"\ea61"}.eva-checkmark-circle:before{content:"\ea62"}.eva-checkmark-circle-2:before{content:"\ea63"}.eva-checkmark-circle-2-outline:before{content:"\ea64"}.eva-checkmark-circle-outline:before{content:"\ea65"}.eva-checkmark-outline:before{content:"\ea66"}.eva-checkmark-square:before{content:"\ea67"}.eva-checkmark-square-2:before{content:"\ea68"}.eva-checkmark-square-2-outline:before{content:"\ea69"}.eva-checkmark-square-outline:before{content:"\ea6a"}.eva-chevron-down:before{content:"\ea6b"}.eva-chevron-down-outline:before{content:"\ea6c"}.eva-chevron-left:before{content:"\ea6d"}.eva-chevron-left-outline:before{content:"\ea6e"}.eva-chevron-right:before{content:"\ea6f"}.eva-chevron-right-outline:before{content:"\ea70"}.eva-chevron-up:before{content:"\ea71"}.eva-chevron-up-outline:before{content:"\ea72"}.eva-clipboard:before{content:"\ea73"}.eva-clipboard-outline:before{content:"\ea74"}.eva-clock:before{content:"\ea75"}.eva-clock-outline:before{content:"\ea76"}.eva-close:before{content:"\ea77"}.eva-close-circle:before{content:"\ea78"}.eva-close-circle-outline:before{content:"\ea79"}.eva-close-outline:before{content:"\ea7a"}.eva-close-square:before{content:"\ea7b"}.eva-close-square-outline:before{content:"\ea7c"}.eva-cloud-download:before{content:"\ea7d"}.eva-cloud-download-outline:before{content:"\ea7e"}.eva-cloud-upload:before{content:"\ea7f"}.eva-cloud-upload-outline:before{content:"\ea80"}.eva-code:before{content:"\ea81"}.eva-code-download:before{content:"\ea82"}.eva-code-download-outline:before{content:"\ea83"}.eva-code-outline:before{content:"\ea84"}.eva-collapse:before{content:"\ea85"}.eva-collapse-outline:before{content:"\ea86"}.eva-color-palette:before{content:"\ea87"}.eva-color-palette-outline:before{content:"\ea88"}.eva-color-picker:before{content:"\ea89"}.eva-color-picker-outline:before{content:"\ea8a"}.eva-compass:before{content:"\ea8b"}.eva-compass-outline:before{content:"\ea8c"}.eva-copy:before{content:"\ea8d"}.eva-copy-outline:before{content:"\ea8e"}.eva-corner-down-left:before{content:"\ea8f"}.eva-corner-down-left-outline:before{content:"\ea90"}.eva-corner-down-right:before{content:"\ea91"}.eva-corner-down-right-outline:before{content:"\ea92"}.eva-corner-left-down:before{content:"\ea93"}.eva-corner-left-down-outline:before{content:"\ea94"}.eva-corner-left-up:before{content:"\ea95"}.eva-corner-left-up-outline:before{content:"\ea96"}.eva-corner-right-down:before{content:"\ea97"}.eva-corner-right-down-outline:before{content:"\ea98"}.eva-corner-right-up:before{content:"\ea99"}.eva-corner-right-up-outline:before{content:"\ea9a"}.eva-corner-up-left:before{content:"\ea9b"}.eva-corner-up-left-outline:before{content:"\ea9c"}.eva-corner-up-right:before{content:"\ea9d"}.eva-corner-up-right-outline:before{content:"\ea9e"}.eva-credit-card:before{content:"\ea9f"}.eva-credit-card-outline:before{content:"\eaa0"}.eva-crop:before{content:"\eaa1"}.eva-crop-outline:before{content:"\eaa2"}.eva-cube:before{content:"\eaa3"}.eva-cube-outline:before{content:"\eaa4"}.eva-diagonal-arrow-left-down:before{content:"\eaa5"}.eva-diagonal-arrow-left-down-outline:before{content:"\eaa6"}.eva-diagonal-arrow-left-up:before{content:"\eaa7"}.eva-diagonal-arrow-left-up-outline:before{content:"\eaa8"}.eva-diagonal-arrow-right-down:before{content:"\eaa9"}.eva-diagonal-arrow-right-down-outline:before{content:"\eaaa"}.eva-diagonal-arrow-right-up:before{content:"\eaab"}.eva-diagonal-arrow-right-up-outline:before{content:"\eaac"}.eva-done-all:before{content:"\eaad"}.eva-done-all-outline:before{content:"\eaae"}.eva-download:before{content:"\eaaf"}.eva-download-outline:before{content:"\eab0"}.eva-droplet:before{content:"\eab1"}.eva-droplet-off:before{content:"\eab2"}.eva-droplet-off-outline:before{content:"\eab3"}.eva-droplet-outline:before{content:"\eab4"}.eva-edit:before{content:"\eab5"}.eva-edit-2:before{content:"\eab6"}.eva-edit-2-outline:before{content:"\eab7"}.eva-edit-outline:before{content:"\eab8"}.eva-email:before{content:"\eab9"}.eva-email-outline:before{content:"\eaba"}.eva-expand:before{content:"\eabb"}.eva-expand-outline:before{content:"\eabc"}.eva-external-link:before{content:"\eabd"}.eva-external-link-outline:before{content:"\eabe"}.eva-eye:before{content:"\eabf"}.eva-eye-off:before{content:"\eac0"}.eva-eye-off-2:before{content:"\eac1"}.eva-eye-off-2-outline:before{content:"\eac2"}.eva-eye-off-outline:before{content:"\eac3"}.eva-eye-outline:before{content:"\eac4"}.eva-facebook:before{content:"\eac5"}.eva-facebook-outline:before{content:"\eac6"}.eva-file:before{content:"\eac7"}.eva-file-add:before{content:"\eac8"}.eva-file-add-outline:before{content:"\eac9"}.eva-file-outline:before{content:"\eaca"}.eva-file-remove:before{content:"\eacb"}.eva-file-remove-outline:before{content:"\eacc"}.eva-file-text:before{content:"\eacd"}.eva-file-text-outline:before{content:"\eace"}.eva-film:before{content:"\eacf"}.eva-film-outline:before{content:"\ead0"}.eva-flag:before{content:"\ead1"}.eva-flag-outline:before{content:"\ead2"}.eva-flash:before{content:"\ead3"}.eva-flash-off:before{content:"\ead4"}.eva-flash-off-outline:before{content:"\ead5"}.eva-flash-outline:before{content:"\ead6"}.eva-flip:before{content:"\ead7"}.eva-flip-2:before{content:"\ead8"}.eva-flip-2-outline:before{content:"\ead9"}.eva-flip-outline:before{content:"\eada"}.eva-folder:before{content:"\eadb"}.eva-folder-add:before{content:"\eadc"}.eva-folder-add-outline:before{content:"\eadd"}.eva-folder-outline:before{content:"\eade"}.eva-folder-remove:before{content:"\eadf"}.eva-folder-remove-outline:before{content:"\eae0"}.eva-funnel:before{content:"\eae1"}.eva-funnel-outline:before{content:"\eae2"}.eva-gift:before{content:"\eae3"}.eva-gift-outline:before{content:"\eae4"}.eva-github:before{content:"\eae5"}.eva-github-outline:before{content:"\eae6"}.eva-globe:before{content:"\eae7"}.eva-globe-2:before{content:"\eae8"}.eva-globe-2-outline:before{content:"\eae9"}.eva-globe-3:before{content:"\eaea"}.eva-globe-outline:before{content:"\eaeb"}.eva-google:before{content:"\eaec"}.eva-google-outline:before{content:"\eaed"}.eva-grid:before{content:"\eaee"}.eva-grid-outline:before{content:"\eaef"}.eva-hard-drive:before{content:"\eaf0"}.eva-hard-drive-outline:before{content:"\eaf1"}.eva-hash:before{content:"\eaf2"}.eva-hash-outline:before{content:"\eaf3"}.eva-headphones:before{content:"\eaf4"}.eva-headphones-outline:before{content:"\eaf5"}.eva-heart:before{content:"\eaf6"}.eva-heart-outline:before{content:"\eaf7"}.eva-home:before{content:"\eaf8"}.eva-home-outline:before{content:"\eaf9"}.eva-image:before{content:"\eafa"}.eva-image-2:before{content:"\eafb"}.eva-image-outline:before{content:"\eafc"}.eva-inbox:before{content:"\eafd"}.eva-inbox-outline:before{content:"\eafe"}.eva-info:before{content:"\eaff"}.eva-info-outline:before{content:"\eb00"}.eva-keypad:before{content:"\eb01"}.eva-keypad-outline:before{content:"\eb02"}.eva-layers:before{content:"\eb03"}.eva-layers-outline:before{content:"\eb04"}.eva-layout:before{content:"\eb05"}.eva-layout-outline:before{content:"\eb06"}.eva-link:before{content:"\eb07"}.eva-link-2:before{content:"\eb08"}.eva-link-2-outline:before{content:"\eb09"}.eva-link-outline:before{content:"\eb0a"}.eva-linkedin:before{content:"\eb0b"}.eva-linkedin-outline:before{content:"\eb0c"}.eva-list:before{content:"\eb0d"}.eva-list-outline:before{content:"\eb0e"}.eva-loader-outline:before{content:"\eb0f"}.eva-lock:before{content:"\eb10"}.eva-lock-outline:before{content:"\eb11"}.eva-log-in:before{content:"\eb12"}.eva-log-in-outline:before{content:"\eb13"}.eva-log-out:before{content:"\eb14"}.eva-log-out-outline:before{content:"\eb15"}.eva-map:before{content:"\eb16"}.eva-map-outline:before{content:"\eb17"}.eva-maximize:before{content:"\eb18"}.eva-maximize-outline:before{content:"\eb19"}.eva-menu:before{content:"\eb1a"}.eva-menu-2:before{content:"\eb1b"}.eva-menu-2-outline:before{content:"\eb1c"}.eva-menu-arrow:before{content:"\eb1d"}.eva-menu-arrow-outline:before{content:"\eb1e"}.eva-menu-outline:before{content:"\eb1f"}.eva-message-circle:before{content:"\eb20"}.eva-message-circle-outline:before{content:"\eb21"}.eva-message-square:before{content:"\eb22"}.eva-message-square-outline:before{content:"\eb23"}.eva-mic:before{content:"\eb24"}.eva-mic-off:before{content:"\eb25"}.eva-mic-off-outline:before{content:"\eb26"}.eva-mic-outline:before{content:"\eb27"}.eva-minimize:before{content:"\eb28"}.eva-minimize-outline:before{content:"\eb29"}.eva-minus:before{content:"\eb2a"}.eva-minus-circle:before{content:"\eb2b"}.eva-minus-circle-outline:before{content:"\eb2c"}.eva-minus-outline:before{content:"\eb2d"}.eva-minus-square:before{content:"\eb2e"}.eva-minus-square-outline:before{content:"\eb2f"}.eva-monitor:before{content:"\eb30"}.eva-monitor-outline:before{content:"\eb31"}.eva-moon:before{content:"\eb32"}.eva-moon-outline:before{content:"\eb33"}.eva-more-horizontal:before{content:"\eb34"}.eva-more-horizontal-outline:before{content:"\eb35"}.eva-more-vertical:before{content:"\eb36"}.eva-more-vertical-outline:before{content:"\eb37"}.eva-move:before{content:"\eb38"}.eva-move-outline:before{content:"\eb39"}.eva-music:before{content:"\eb3a"}.eva-music-outline:before{content:"\eb3b"}.eva-navigation:before{content:"\eb3c"}.eva-navigation-2:before{content:"\eb3d"}.eva-navigation-2-outline:before{content:"\eb3e"}.eva-navigation-outline:before{content:"\eb3f"}.eva-npm:before{content:"\eb40"}.eva-npm-outline:before{content:"\eb41"}.eva-options:before{content:"\eb42"}.eva-options-2:before{content:"\eb43"}.eva-options-2-outline:before{content:"\eb44"}.eva-options-outline:before{content:"\eb45"}.eva-pantone:before{content:"\eb46"}.eva-pantone-outline:before{content:"\eb47"}.eva-paper-plane:before{content:"\eb48"}.eva-paper-plane-outline:before{content:"\eb49"}.eva-pause-circle:before{content:"\eb4a"}.eva-pause-circle-outline:before{content:"\eb4b"}.eva-people:before{content:"\eb4c"}.eva-people-outline:before{content:"\eb4d"}.eva-percent:before{content:"\eb4e"}.eva-percent-outline:before{content:"\eb4f"}.eva-person:before{content:"\eb50"}.eva-person-add:before{content:"\eb51"}.eva-person-add-outline:before{content:"\eb52"}.eva-person-delete:before{content:"\eb53"}.eva-person-delete-outline:before{content:"\eb54"}.eva-person-done:before{content:"\eb55"}.eva-person-done-outline:before{content:"\eb56"}.eva-person-outline:before{content:"\eb57"}.eva-person-remove:before{content:"\eb58"}.eva-person-remove-outline:before{content:"\eb59"}.eva-phone:before{content:"\eb5a"}.eva-phone-call:before{content:"\eb5b"}.eva-phone-call-outline:before{content:"\eb5c"}.eva-phone-missed:before{content:"\eb5d"}.eva-phone-missed-outline:before{content:"\eb5e"}.eva-phone-off:before{content:"\eb5f"}.eva-phone-off-outline:before{content:"\eb60"}.eva-phone-outline:before{content:"\eb61"}.eva-pie-chart:before{content:"\eb62"}.eva-pie-chart-2:before{content:"\eb63"}.eva-pie-chart-outline:before{content:"\eb64"}.eva-pin:before{content:"\eb65"}.eva-pin-outline:before{content:"\eb66"}.eva-play-circle:before{content:"\eb67"}.eva-play-circle-outline:before{content:"\eb68"}.eva-plus:before{content:"\eb69"}.eva-plus-circle:before{content:"\eb6a"}.eva-plus-circle-outline:before{content:"\eb6b"}.eva-plus-outline:before{content:"\eb6c"}.eva-plus-square:before{content:"\eb6d"}.eva-plus-square-outline:before{content:"\eb6e"}.eva-power:before{content:"\eb6f"}.eva-power-outline:before{content:"\eb70"}.eva-pricetags:before{content:"\eb71"}.eva-pricetags-outline:before{content:"\eb72"}.eva-printer:before{content:"\eb73"}.eva-printer-outline:before{content:"\eb74"}.eva-question-mark:before{content:"\eb75"}.eva-question-mark-circle:before{content:"\eb76"}.eva-question-mark-circle-outline:before{content:"\eb77"}.eva-question-mark-outline:before{content:"\eb78"}.eva-radio:before{content:"\eb79"}.eva-radio-button-off:before{content:"\eb7a"}.eva-radio-button-off-outline:before{content:"\eb7b"}.eva-radio-button-on:before{content:"\eb7c"}.eva-radio-button-on-outline:before{content:"\eb7d"}.eva-radio-outline:before{content:"\eb7e"}.eva-recording:before{content:"\eb7f"}.eva-recording-outline:before{content:"\eb80"}.eva-refresh:before{content:"\eb81"}.eva-refresh-outline:before{content:"\eb82"}.eva-repeat:before{content:"\eb83"}.eva-repeat-outline:before{content:"\eb84"}.eva-rewind-left:before{content:"\eb85"}.eva-rewind-left-outline:before{content:"\eb86"}.eva-rewind-right:before{content:"\eb87"}.eva-rewind-right-outline:before{content:"\eb88"}.eva-save:before{content:"\eb89"}.eva-save-outline:before{content:"\eb8a"}.eva-scissors:before{content:"\eb8b"}.eva-scissors-outline:before{content:"\eb8c"}.eva-search:before{content:"\eb8d"}.eva-search-outline:before{content:"\eb8e"}.eva-settings:before{content:"\eb8f"}.eva-settings-2:before{content:"\eb90"}.eva-settings-2-outline:before{content:"\eb91"}.eva-settings-outline:before{content:"\eb92"}.eva-shake:before{content:"\eb93"}.eva-shake-outline:before{content:"\eb94"}.eva-share:before{content:"\eb95"}.eva-share-outline:before{content:"\eb96"}.eva-shield:before{content:"\eb97"}.eva-shield-off:before{content:"\eb98"}.eva-shield-off-outline:before{content:"\eb99"}.eva-shield-outline:before{content:"\eb9a"}.eva-shopping-bag:before{content:"\eb9b"}.eva-shopping-bag-outline:before{content:"\eb9c"}.eva-shopping-cart:before{content:"\eb9d"}.eva-shopping-cart-outline:before{content:"\eb9e"}.eva-shuffle:before{content:"\eb9f"}.eva-shuffle-2:before{content:"\eba0"}.eva-shuffle-2-outline:before{content:"\eba1"}.eva-shuffle-outline:before{content:"\eba2"}.eva-skip-back:before{content:"\eba3"}.eva-skip-back-outline:before{content:"\eba4"}.eva-skip-forward:before{content:"\eba5"}.eva-skip-forward-outline:before{content:"\eba6"}.eva-slash:before{content:"\eba7"}.eva-slash-outline:before{content:"\eba8"}.eva-smartphone:before{content:"\eba9"}.eva-smartphone-outline:before{content:"\ebaa"}.eva-speaker:before{content:"\ebab"}.eva-speaker-outline:before{content:"\ebac"}.eva-square:before{content:"\ebad"}.eva-square-outline:before{content:"\ebae"}.eva-star:before{content:"\ebaf"}.eva-star-outline:before{content:"\ebb0"}.eva-stop-circle:before{content:"\ebb1"}.eva-stop-circle-outline:before{content:"\ebb2"}.eva-sun:before{content:"\ebb3"}.eva-sun-outline:before{content:"\ebb4"}.eva-swap:before{content:"\ebb5"}.eva-swap-outline:before{content:"\ebb6"}.eva-sync:before{content:"\ebb7"}.eva-sync-outline:before{content:"\ebb8"}.eva-text:before{content:"\ebb9"}.eva-text-outline:before{content:"\ebba"}.eva-thermometer:before{content:"\ebbb"}.eva-thermometer-minus:before{content:"\ebbc"}.eva-thermometer-minus-outline:before{content:"\ebbd"}.eva-thermometer-outline:before{content:"\ebbe"}.eva-thermometer-plus:before{content:"\ebbf"}.eva-thermometer-plus-outline:before{content:"\ebc0"}.eva-toggle-left:before{content:"\ebc1"}.eva-toggle-left-outline:before{content:"\ebc2"}.eva-toggle-right:before{content:"\ebc3"}.eva-toggle-right-outline:before{content:"\ebc4"}.eva-trash:before{content:"\ebc5"}.eva-trash-2:before{content:"\ebc6"}.eva-trash-2-outline:before{content:"\ebc7"}.eva-trash-outline:before{content:"\ebc8"}.eva-trending-down:before{content:"\ebc9"}.eva-trending-down-outline:before{content:"\ebca"}.eva-trending-up:before{content:"\ebcb"}.eva-trending-up-outline:before{content:"\ebcc"}.eva-tv:before{content:"\ebcd"}.eva-tv-outline:before{content:"\ebce"}.eva-twitter:before{content:"\ebcf"}.eva-twitter-outline:before{content:"\ebd0"}.eva-umbrella:before{content:"\ebd1"}.eva-umbrella-outline:before{content:"\ebd2"}.eva-undo:before{content:"\ebd3"}.eva-undo-outline:before{content:"\ebd4"}.eva-unlock:before{content:"\ebd5"}.eva-unlock-outline:before{content:"\ebd6"}.eva-upload:before{content:"\ebd7"}.eva-upload-outline:before{content:"\ebd8"}.eva-video:before{content:"\ebd9"}.eva-video-off:before{content:"\ebda"}.eva-video-off-outline:before{content:"\ebdb"}.eva-video-outline:before{content:"\ebdc"}.eva-volume-down:before{content:"\ebdd"}.eva-volume-down-outline:before{content:"\ebde"}.eva-volume-mute:before{content:"\ebdf"}.eva-volume-mute-outline:before{content:"\ebe0"}.eva-volume-off:before{content:"\ebe1"}.eva-volume-off-outline:before{content:"\ebe2"}.eva-volume-up:before{content:"\ebe3"}.eva-volume-up-outline:before{content:"\ebe4"}.eva-wifi:before{content:"\ebe5"}.eva-wifi-off:before{content:"\ebe6"}.eva-wifi-off-outline:before{content:"\ebe7"}.eva-wifi-outline:before{content:"\ebe8"}.nuxt-progress{position:fixed;top:0;left:0;right:0;height:.25rem;width:0;opacity:1;transition:width .1s,opacity .4s;background-color:#00adb5;z-index:999999}.nuxt-progress.nuxt-progress-notransition{transition:none}.nuxt-progress-failed{background-color:red}.in-frames main{padding:0!important;margin:0!important}.in-frames .nya-container{margin-top:1.125rem;box-shadow:none;border:.0625rem solid #ebebeb}.in-frames .float-btn,.in-frames .navbar,.in-frames .vfooter{display:none!important}.index_page{min-height:100%}.index_page.hide{opacity:.5}.index_page .view+.nya-container{margin-top:3.125rem}.index_page .dark-layer{pointer-events:none;background-color:rgba(0,0,0,.3)}.index_page .dark-layer,.index_page .view-loading{position:fixed;width:100%;height:100%;left:0;top:0;z-index:999}.index_page .view-loading{background-color:hsla(0,0%,100%,.8);display:flex;align-items:center;justify-content:center}.index_page main{position:relative;max-width:75rem;margin:0 auto;box-sizing:border-box;padding:0 1.25rem 1.25rem}.index_page .bgimg{position:fixed;background-repeat:no-repeat;background-size:cover;background-position:50%}.index_page .bg-layer,.index_page .bgimg{z-index:-1;left:0;top:0;width:100%;height:100%}.index_page .bg-layer{opacity:.75;position:absolute;min-height:100vh}.navbar{box-sizing:border-box;padding-top:1.25rem;padding-bottom:.625rem}.navbar h2{font-size:1.25rem;margin-top:-2.5rem}.navbar button{font-weight:700}.navbar header{width:100%;display:flex;align-items:center;justify-content:center;flex-direction:column}.navbar header .title{margin-bottom:.3125rem;text-shadow:.0625rem .0625rem .0625rem rgba(0,0,0,.15)}.navbar header .title,.navbar header .title a{display:flex;align-items:center;color:var(--t1);cursor:pointer}.navbar header .title a:hover{text-decoration:none}.navbar header .subtitle{cursor:pointer;display:flex;align-items:center;font-size:1rem;margin-top:.3125rem;opacity:.8;color:var(--theme);letter-spacing:.0625rem;text-shadow:.0625rem .0625rem .0625rem hsla(0,0%,50.2%,.2)}.navbar header .subtitle i{font-size:1.25rem;margin-right:.3125rem}.panel{box-sizing:border-box;border-radius:3.125rem;margin:.3125rem auto 0}.panel .login-text{display:flex;align-items:center;color:var(--theme);cursor:pointer}.panel .login-text i{margin-right:.3125rem}.home span.mb{display:block;margin-bottom:.9375rem}.home table{width:100%;table-layout:auto}.home .ad{padding:0;padding-top:0!important;height:12.5rem}.home .ad a{cursor:pointer;display:block;width:100%;height:100%;background-image:url(/_nuxt/img/b8b0321.jpg);background-repeat:no-repeat;background-size:cover;background-position:bottom;border-radius:.5rem}@media (max-width:700px){.home .ad{height:9.375rem}}@media (max-width:470px){.home .ad{height:6.25rem}}.home ._ad{height:6.25rem;display:flex;align-items:center;justify-content:center}.home .nya-btn{position:relative;margin:.4375rem;width:calc(20% - .875rem);box-sizing:border-box;overflow:hidden;text-align:center;text-overflow:ellipsis;white-space:nowrap;transition:all .3s ease;background-color:transparent;font-size:1.125rem;border-radius:.25rem}.home .nya-btn:hover{transform:translateY(-.125rem);box-shadow:0 .5rem 1rem 0 rgba(10,14,29,.04),0 .5rem 4rem 0 rgba(10,14,29,.08)}@media (max-width:1050px){.home .nya-btn{width:calc(25% - .875rem)}}@media (max-width:900px){.home .nya-btn{width:calc(33.33333% - .875rem)}}@media (max-width:700px){.home .nya-btn{box-shadow:none;margin:.3125rem;width:calc(50% - .625rem)}}.home .pay{width:100%;padding:0;margin:0;display:flex;justify-content:space-around}.home .pay li{margin:0;list-style:none;padding:.625rem}.home .pay li .name{text-align:center;font-size:1.5625rem;font-weight:700;margin-top:.3125rem}.home .pay li img{width:12.5rem;max-width:100%}.home .badge:after{content:"";position:absolute;top:.3125rem;right:.3125rem;color:#fff;font-weight:lighter;text-shadow:.0625rem .0625rem .0625rem rgba(0,0,0,.2);width:.5rem;height:.5rem;border-radius:50%}.home .badge.new:after{background-color:var(--theme-success)}.home .badge.hot:after{background-color:var(--theme-danger)}.home .badge.vip:after{background-color:#f79817}.home .badge.recommend:after{background-color:var(--theme)}.home .badge-info,.home .badge-info .badge{display:inline-flex;align-items:center}.home .badge-info .badge{margin-right:.625rem}.home .badge-info .badge:after{position:relative;left:auto;margin-left:.625rem;top:auto;display:inline-block}.nya-container.welcome{margin-bottom:2rem;margin-top:1.125rem}.nya-container.welcome .close{cursor:pointer;position:absolute;right:-.75rem;top:-.75rem;display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:1.875rem;height:1.875rem;border-radius:50%;color:#fff;background-color:var(--theme);box-shadow:0 .5rem .625rem rgba(36,159,253,.30196)}.nya-container{position:relative;padding:1.5625rem 2rem;margin-top:1.125rem;margin-bottom:3.125rem;box-shadow:.5rem .875rem 2.375rem rgba(39,44,49,.06),.0625rem .1875rem .5rem rgba(39,44,49,.03);background-color:#fff;border:none;border-radius:.5rem}.nya-container.pt{padding-top:2.1875rem}.nya-container.transparent{background-color:hsla(0,0%,100%,.65)}.nya-container:last-child{margin-bottom:0}.nya-container .nya-stitle{position:absolute;right:.9375rem;top:.5rem;font-size:.8125rem;color:#b1b1b1}.nya-container .nya-title{position:absolute;left:1.875rem;top:-1.125rem;padding:.5rem .9375rem;font-weight:700;font-size:0;background-color:var(--theme);color:#fff;box-shadow:0 .5rem .625rem rgba(36,159,253,.30196);border-radius:.5rem}.nya-container .nya-title i{margin-right:.3125rem;font-size:1.25rem;vertical-align:middle}.nya-container .nya-title span{font-size:1.0625rem;line-height:1.25rem;vertical-align:middle}@media (max-width:600px){.nya-container{padding:.9375rem}.nya-container.pt{padding-top:1.875rem}.nya-container .nya-title{left:1.25rem}}.nya-container .nya-list{margin:0}.search-component .nya-container{margin-bottom:2.1875rem!important}.search-component .search{margin-bottom:3.125rem;margin-top:1.125rem;width:100%;padding:1rem;display:flex;align-items:center;background-color:#fff;box-shadow:.5rem .875rem 2.375rem rgba(39,44,49,.06),.0625rem .1875rem .5rem rgba(39,44,49,.03);box-sizing:border-box;border-radius:.5rem;transition:all .3s ease}.search-component .search.transparent{background-color:hsla(0,0%,100%,.65)}.search-component .search.focus{background-color:var(--theme);color:#fff;transform:scale(1.02);box-shadow:0 .5rem .625rem rgba(36,159,253,.30196)}.search-component .search.focus input{color:#fff}.search-component .search.focus input::-webkit-input-placeholder{color:#fff}.search-component .search.focus input::-moz-placeholder{color:#fff}.search-component .search.focus input:-ms-input-placeholder{color:#fff}.search-component .search.focus input::-ms-input-placeholder{color:#fff}.search-component .search.focus input::placeholder{color:#fff}.search-component .search i{font-size:1.5625rem;margin-right:.9375rem}.search-component .search input{width:100%;outline:0;border:none;box-shadow:none;background-color:transparent;color:var(--t1)}.search-component .search input::-webkit-input-placeholder{transition:color .3s ease;padding-left:.3125rem}.search-component .search input::-moz-placeholder{transition:color .3s ease;padding-left:.3125rem}.search-component .search input:-ms-input-placeholder{transition:color .3s ease;padding-left:.3125rem}.search-component .search input::-ms-input-placeholder{transition:color .3s ease;padding-left:.3125rem}.search-component .search input::placeholder{transition:color .3s ease;padding-left:.3125rem}.search-component .search-placeholder{position:relative;text-align:center;font-size:1.25rem;font-weight:700;top:-.5rem;letter-spacing:.09375rem;width:100%}.vfooter{margin:1.875rem auto 1.25rem;display:flex;align-items:center;justify-content:center}.vfooter a{color:inherit}.vfooter .icon{cursor:pointer;width:1.5625rem;height:1.5625rem;margin:0 .4375rem;vertical-align:-.15em;fill:currentColor;overflow:hidden;background-color:hsla(0,0%,100%,.65);color:#222831;border-radius:50%;padding:.3125rem;box-shadow:.5rem .875rem 2.375rem rgba(39,44,49,.06),.0625rem .1875rem .5rem rgba(39,44,49,.03)}.nya-loading{width:2.5rem;height:2.5rem;position:relative}.nya-loading .sk-child{width:100%;height:100%;position:absolute;left:0;top:0}.nya-loading .sk-child:before{content:"";display:block;margin:0 auto;width:15%;height:15%;background-color:#333;border-radius:100%;-webkit-animation:sk-circleBounceDelay 1.2s ease-in-out infinite both;animation:sk-circleBounceDelay 1.2s ease-in-out infinite both}.nya-loading .sk-circle2{transform:rotate(30deg)}.nya-loading .sk-circle3{transform:rotate(60deg)}.nya-loading .sk-circle4{transform:rotate(90deg)}.nya-loading .sk-circle5{transform:rotate(120deg)}.nya-loading .sk-circle6{transform:rotate(150deg)}.nya-loading .sk-circle7{transform:rotate(180deg)}.nya-loading .sk-circle8{transform:rotate(210deg)}.nya-loading .sk-circle9{transform:rotate(240deg)}.nya-loading .sk-circle10{transform:rotate(270deg)}.nya-loading .sk-circle11{transform:rotate(300deg)}.nya-loading .sk-circle12{transform:rotate(330deg)}.nya-loading .sk-circle2:before{-webkit-animation-delay:-1.1s;animation-delay:-1.1s}.nya-loading .sk-circle3:before{-webkit-animation-delay:-1s;animation-delay:-1s}.nya-loading .sk-circle4:before{-webkit-animation-delay:-.9s;animation-delay:-.9s}.nya-loading .sk-circle5:before{-webkit-animation-delay:-.8s;animation-delay:-.8s}.nya-loading .sk-circle6:before{-webkit-animation-delay:-.7s;animation-delay:-.7s}.nya-loading .sk-circle7:before{-webkit-animation-delay:-.6s;animation-delay:-.6s}.nya-loading .sk-circle8:before{-webkit-animation-delay:-.5s;animation-delay:-.5s}.nya-loading .sk-circle9:before{-webkit-animation-delay:-.4s;animation-delay:-.4s}.nya-loading .sk-circle10:before{-webkit-animation-delay:-.3s;animation-delay:-.3s}.nya-loading .sk-circle11:before{-webkit-animation-delay:-.2s;animation-delay:-.2s}.nya-loading .sk-circle12:before{-webkit-animation-delay:-.1s;animation-delay:-.1s}@-webkit-keyframes sk-circleBounceDelay{0%,80%,to{transform:scale(0)}40%{transform:scale(1)}}@keyframes sk-circleBounceDelay{0%,80%,to{transform:scale(0)}40%{transform:scale(1)}}.float-btn{position:relative;z-index:999;position:fixed;right:1.25rem;bottom:1.875rem}.float-btn:hover ul{opacity:1}.float-btn .code pre{margin-bottom:0;margin-top:0;border:none;background-color:#282c34}.float-btn ul{margin:0 auto;padding:0;display:flex;align-items:center;flex-direction:column;opacity:0;transition:all .3s ease}@media (max-width:600px){.float-btn ul{display:none}}.float-btn ul li{list-style:none;margin-bottom:.9375rem;padding:0;font-size:1.5625rem;color:#fff;width:2.5rem;height:2.5rem;box-sizing:border-box}.float-btn .main,.float-btn ul li{cursor:pointer;background-color:var(--theme);border-radius:50%;display:flex;justify-content:center;align-items:center}.float-btn .main{width:3.4375rem;height:3.4375rem;box-shadow:.5rem .875rem 2.375rem rgba(39,44,49,.06),.0625rem .1875rem .5rem rgba(39,44,49,.03)}.float-btn .main i{font-size:1.25rem;color:#eee}.float-btn .title{font-size:1.125rem;font-weight:700;text-align:center;margin-top:.9375rem;padding-bottom:.9375rem;border-bottom:.0625rem solid #d8d8d8;color:#222831}.float-btn .pay img,.float-btn .phone img{padding:1.25rem;box-sizing:border-box;width:100%}.float-btn .share .list{padding:.5rem}.float-btn .share .list a{display:inline-block;margin:.5rem;font-size:1.125rem}.theme-btn{position:fixed;left:2.5rem;top:-.625rem;transition:all .3s ease;opacity:.6}@media (min-width:600px){.theme-btn:hover{top:0;opacity:1}}@media (max-width:600px){.theme-btn:active{top:0;opacity:1}}.theme-btn .line{width:0;border:.125rem dashed #5ca0d3;height:3.125rem}.theme-btn .type-icon{position:relative;left:-1.125rem;width:2.5rem;height:2.5rem;background-color:#5ca0d3;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;cursor:pointer;font-size:1.25rem}.home{max-width:90%;margin:auto;}.text-center{text-align:center;}p.grey{color:grey;}p.grey a{color:grey;}.bottom{font-size:12px;}
    </style>
  </head>
  <body>
    <!-- TITLE -->
    <div class="navbar">
      <header>
        <h1 class="title">
          <a href="javascript:;" class="nuxt-link-exact-active nuxt-link-active">
            <?php ec('NAV_NAME'); ?>
          </a>
        </h1>
        <div icon="person-outline" class="panel">
          <div class="login-text">
            <span><?php ec('NAV_SUBTITLE'); ?></span>
          </div>
        </div>
      </header>
    </div>
    <!-- ./TITLE -->

    <!-- BODY -->
    <div class="home view">

      <!-- WELCOME -->
      <div class="nya-container welcome">
        <h2><?php ec('NAV_WELCOME_TITLE'); ?></h2>
        <p>
          <?php ec('NAV_WELCOME_CONTENT'); ?>
        </p>
      </div>
      <!-- ./WELCOME -->

      <!-- NAV_PART -->
      <?php foreach($dirs as $group => $g_dirs) : ?>
        <div class="nya-container pt">
          <div class="nya-title">
            <span><?php echo $group; ?></span>
          </div>
          <?php if(count($dirs) <= 0): ?>
            暂无其他目录呢~
          <?php endif; ?>
          <?php foreach($g_dirs as $dir => $value): ?>
            <?php if(!isset($value['hidden']) || $value['hidden'] == 0): ?>
              <a href="<?php if(isset($value['entry']) && !empty($value['entry'])){echo './'.$dir.'/'.$value['entry'];}else{echo './'.$dir;} ?>"
                class="nya-btn <?php echo $value['mark'] ?? ''; ?> badge"
                target="_blank"
                title="<?php echo $value['title']; ?>">
                <?php echo $value['title']; ?>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <!-- ./NAV_PART -->

      <!-- FOOTER -->
      <footer class="nya-container pt">
        <div class="nya-title">
          <span>关于与鸣谢</span>
        </div>
        <ul class="nya-list">
          <li>
            <div class="badge-info">
              <span class="badge hot">热门</span>
              <span class="badge vip">VIP</span>
              <span class="badge new">新功能</span>
              <span class="badge recommend">推荐</span>
            </div>
          </li>
          <li>
            界面借鉴：<a href="https://github.com/Ice-Hazymoon/MikuTools" target="_blank">MikuTools</a>
          </li>
          <li>
            项目地址：<a href="https://github.com/jokin1999/my-navagation" target="_blank">jokin1999/my-navagation</a>
          </li>
          <li>导航版本：<?php ec('NAV_VERSION'); ?> / v<?php ec('NAV_CONF_VERSION_LATEST'); ?></li>
          <li>配置版本：v<?php ec('NAV_CONF_VERSION_CURRENT'); ?>
            <?php if(NAV_CONF_VERSION_LATEST > NAV_CONF_VERSION_CURRENT): ?>
              <small style="color:red;">（版本较低）</small>
            <?php endif; ?>
          </li>
        </ul>
      </footer>
      <!-- ./FOOTER -->

      <!-- BOTTOM -->
      <p class="bottom text-center grey">
        Powered by <a href="https://github.com/jokin1999">Jokin</a>
      </p>
      <!-- ./BOTTOM -->

    </div>
    <!-- ./BODY -->

  </body>
</html>
<?php endif; ?>
<?php
if (METHOD === 'static') {
  $content = ob_get_contents();
  file_put_contents('./index.html', $content);
  ob_clean();
}
?>
