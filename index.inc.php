<?php
/**
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2007-2011 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    1.0.0
 * @version    $Id: v2.4.1-78-ge1f29df 2011-02-13 22:21:43 +0100 $
 */

// include functions
Loader::Load(APPDIR.'/functions.php');
// Configurations
Loader::Load(APPDIR.'/init.php');
Loader::Load(LOCALDIR.'/custom/init.php', TRUE, FALSE);

HTMLPage::$Debug = (DEVELOP OR Registry::get('cURL.Verbose'));

// Emulate register_globals off
unregister_GLOBALS();

// >> Debug
$sDebugFile = np('%s/%s.debug', TEMPDIR, APPID);
if (isset($_GET['DEBUG'])) {
  touch($sDebugFile);
} elseif (isset($_GET['TRACE'])) {
  $_TRACE = np('%s/trace-%s-%s.csv', TEMPDIR, ESF_VERSION, date('Ymd-Hi'));
  File::write($sDebugFile, $_TRACE);
} elseif (isset($_GET['STOP'])) {
  @unlink($sDebugFile);
  Messages::Info('Debug off');
}

define('_DEBUG', file_exists($sDebugFile));

$_TRACE = _DEBUG ? file_get_contents($sDebugFile) : FALSE;

Yryie::Active(_DEBUG);
#Yryie::$TimeUnit = Yryie::MICROSECONDS;

if ($_TRACE) {
  Messages::Success('Debug trace is active: '.$_TRACE, TRUE);
} elseif (_DEBUG) {
  Messages::Success('Debug active!', TRUE);
}
unset($sDebugFile);
// << Debug

// Prepare caching
Loader::Load(LIBDIR.'/cache/cache.class.php');

$aCacheOptions = array('cachedir'=>TEMPDIR, 'token'=>'es-f');
$oXML = new XML_Array_Configuration(Cache::create($aCacheOptions, 'Files'));
$aConfiguration = $oXML->ParseXMLFile(LOCALDIR.'/config/config.xml');
if (!$aConfiguration) die($oXML->Error);

foreach ($aConfiguration['users'] as $aUser)
  esf_User::set($aUser['name'], $aUser['auth']);
unset($aConfiguration['users']);

foreach ($aConfiguration['esniper'] as $key => $value)
  Esniper::set($key, $value);
unset($aConfiguration['esniper']);

// Set all other into registry
Registry::set($aConfiguration);
unset($oXML, $aConfiguration, $aUser, $key, $value);

Registry::set('cfg_esniper', Registry::get('bin_esniper').' -c .c');

Loader::Load(LIBDIR.'/cache/cache/packer/gz.class.php');
$aCacheOptions['packer'] = new Cache_Packer_GZ;
Core::$Cache = Cache::create($aCacheOptions, Registry::get('CacheClass'));
if (Registry::get('ClearCache')) Core::$Cache->flush();
unset($aCacheOptions);
/// Yryie::Debug(Core::$Cache->info());

if (Registry::get('CfgVersion') < ESF_CONFIG_VERSION) {
  Core::$Cache->flush();
  Core::Redirect('setup/index.php?msg='
                .urlencode('Need reconfiguration because of configuration changes!'));
} elseif (count(esf_User::getAll()) == 0) {
  Core::$Cache->flush();
  Core::Redirect('setup/index.php?msg='
                .urlencode('At least one user account have to be defined!'));
}

esf_Extensions::Init();

Exec::InitInstance(ESF_OS, Core::$Cache, Registry::get('bin_sh'));

checkDir(TEMPDIR);

Loader::Load(APPDIR.'/ebay.php');

// include additional configuration, mostly for development
Core::ReadConfigs('local');

if (!_DEBUG) {
  ErrorHandler::register();
#  ErrorHandler::attach(new ErrorHandler_Debug());
#  ErrorHandler::attach(new ErrorHandler_Echo());
  ErrorHandler::attach(new ErrorHandler_File('local/tmp/error.{TS}.log'));
} else {
  Yryie::Register();
}

// since PHP 5.1.0
if (function_exists('date_default_timezone_set'))
  // http://php.net/manual/en/timezones.php
  date_default_timezone_set(Registry::get('TimeZone'));

$sPath = dirname($_SERVER['PHP_SELF']);
if (substr($sPath, -1) != '/') $sPath .= '/';
define('BASEHTML', sprintf('%s://%s%s', ServerProtocol(), $_SERVER['HTTP_HOST'], $sPath));
unset($sPath);

TplData::setConstant('BASEHTML', BASEHTML);

Loader::Load('module/modules.php');
Loader::Load('plugin/plugins.php');

$oExec = Exec::getInstance();

// Load core and custom Exec commands
$oExec->setCommandsFromXMLFile(sprintf('%s/exec.%s.xml', APPDIR, ESF_OS));
$oExec->setCommandsFromXMLFile(BASEDIR.'/local/custom/exec.xml', FALSE);

if (IniFile::Parse(APPDIR.'/language/languages.ini')) {
  $esf_Languages = IniFile::$Data;
  Registry::set('esf.Languages', IniFile::$Data);
} else {
  Messages::Error(IniFile::$Error);
  $esf_Languages = array('en' => 'English');
  Registry::set('esf.Languages', array('en' => 'English'));
}

/// Yryie::StartTimer('LoadPlugins', 'Load plugins');

Core::ReadConfigs(esf_Extensions::MODULE);

Core::IncludeSpecial(esf_Extensions::MODULE, 'plugin.class', TRUE);

// include all plugin configs
################################
#Core::IncludeSpecial(esf_Extensions::PLUGIN, 'config');
Core::ReadConfigs(esf_Extensions::PLUGIN);

Event::ProcessInform('PluginConfigsLoaded');

Core::IncludeSpecial(esf_Extensions::PLUGIN, 'plugin.class');

Event::ProcessInform('PluginsLoaded');
Event::ProcessInform('ModuleConfigsLoaded');

/// Yryie::StopTimer('LoadPlugins');

################################
Core::StartSession();

if (!Session::get('language')) {
  if (!$language = HTTPlanguage::getMatch(array_keys(Registry::get('esf.Languages'), TRUE)))
    $language = 'en';
  Session::set('language', $language);
}

Event::ProcessInform('SessionStarted');

if (Session::get('Mobile'))
  Session::setP('Layout', 'mobile');
elseif (!Session::getP('Layout'))
  Session::setP('Layout', 'default');

if (PluginEnabled('Validate')) {
  DefineValidator('module', 'Regex', array('pattern'=>'\w*'));
  DefineValidator('action', 'Regex', array('pattern'=>'\w*'));
}

/// Yryie::StartTimer('AnalyseRequestParams', 'Analyse request parameters');

//_dbg($_COOKIE, '$_COOKIE');
//_dbg($_SERVER, '$_SERVER');
//_dbg($_SESSION);
//_dbg($_REQUEST);

Core::StripSlashes($_REQUEST);

/// Yryie::Debug('$_REQUEST : '.print_r($_REQUEST, TRUE));

if (strtoupper($_SERVER['REQUEST_METHOD']) == 'GET') {
  /// Yryie::Debug('$_GET before: '.print_r($_GET, TRUE));
  Core::StripSlashes($_GET);
  Event::Process('UrlUnRewrite', $_GET);
  Event::Process('AnalyseRequest', $_GET);
  /// Yryie::Debug('$_GET after analyse: '.print_r($_GET, TRUE));
  $_POST = array();
} else {
  /// Yryie::Debug('$_POST : '.print_r($_POST, TRUE));
  Core::StripSlashes($_POST);
  Event::Process('UrlUnRewrite', $_POST);
  Event::Process('AnalyseRequest', $_POST);
  /// Yryie::Debug('$_POST after analyse: '.print_r($_POST, TRUE));
  $_GET = array();
}
Event::Process('UrlUnRewrite', $_REQUEST);
Event::Process('AnalyseRequest', $_REQUEST);

// analyse request
Registry::set('esf.Module', checkR('module', STARTMODULE));
$sModule = Registry::get('esf.Module');

// check module for mobile capability
if ($sModule != STARTMODULE AND
    Session::get('Mobile') AND
    Registry::get('Module.'.$sModule.'.Mobile') === FALSE)
  Core::Redirect(Core::URL(array('module'=>STARTMODULE)));

Registry::set('esf.Action',      checkR('action',      'index'));
Registry::set('esf.contentonly', checkR('contentonly', FALSE));

/// Yryie::StopTimer('AnalyseRequestParams');

// ----------------------------------------------------------------------------
// initialize application
// ----------------------------------------------------------------------------
Event::ProcessInform('Start');

Session::checkRequest('language', 'en');

// only initiate empty array data to mark as array, empty strings not required
TplData::set('HtmlHeader.JS', array());
TplData::set('HtmlHeader.CSS', array());
TplData::set('HtmlHeader.Script', array());

/// Yryie::StartTimer('CoreLangLoad', 'Load core languages');

$sLanguage = Session::get('language');

if (Registry::get('EnglishAsDefault') AND $sLanguage != 'en') {
  // load as default english texts and config
  foreach (glob(APPDIR.'/language/*en.tmx') as $file)
    Translation::LoadTMXFile($file, 'en', Core::$Cache);
  // Settings
  Loader::Load(APPDIR.'/language/en.php');
}

// include translation
if (file_exists(APPDIR.'/language/core.'.$sLanguage.'.tmx')) {
  foreach (glob(APPDIR.'/language/*'.$sLanguage.'.tmx') as $file)
    Translation::LoadTMXFile($file, $sLanguage, Core::$Cache);
  // Settings
  Loader::Load(APPDIR.'/language/'.$sLanguage.'.php');
} else {
  Messages::Error(sprintf('Unknown language [%s]! Fallback to english!', $sLanguage));
  Session::set('language', 'en');
}
unset($sLanguage);

if ($locale = Session::get('locale')) {
  setlocale(LC_ALL, $locale);
} else {
  if (!setlocale(LC_ALL, Registry::get('locale'))) {
    Messages::Error(sprintf('Locale <tt>['.Registry::get('locale').']</tt> not found on your system! '
                              .'Please <a href="setup/">reconfigure</a> your system and select a correct locale!'),
                       TRUE);
    Messages::Info(sprintf('Fall back for now to locale <tt>[%s]</tt>.', setlocale(LC_ALL, 0)), TRUE);
  }
  Session::set('locale', setlocale(LC_ALL, 0));
}

/// Yryie::StopTimer('CoreLangLoad');

// Init template engine
$oTemplate = esf_Template::getInstance();
if (Registry::get('Template.ClearCache')) $oTemplate->Template->ClearCache();

// check if requested module is enabled
if (!ModuleEnabled($sModule)) {
  if ($sModule == STARTMODULE) {
    die('<p>Default module "'.$sModule.'" is disabled!</p>'
       .'<p>Please <a href="setup/">configure</a> a different default module!</p>');
  }
  Messages::Error(Translation::get('Core.ModuleNotFound', $sModule));
  Core::Redirect(Core::URL(array('module'=>STARTMODULE)));
} elseif (!Core::CheckRequired('module', $sModule, $Err)) {
  Messages::Error($Err);
  Core::Redirect(Core::URL(array('module'=>STARTMODULE)));
}

/// Yryie::StartTimer('MoreLangLoad', 'Load plugin / module languages');

$sLanguage = Session::get('language');

foreach (esf_Extensions::$Types as $Scope) {
  foreach (esf_Extensions::getExtensions($Scope) as $Extension) {
    if (esf_Extensions::checkState($Scope, $Extension, esf_Extensions::BIT_ENABLED) AND
        ($Scope != esf_Extensions::MODULE OR
         !Registry::get('Module.'.$Extension.'.LoginRequired') OR
         esf_User::isValid())) {
      $path = sprintf(BASEDIR.'%1$s%2$s%1$s%3$s%1$s', DIRECTORY_SEPARATOR, $Scope, $Extension);
      if (Registry::get('EnglishAsDefault') AND $sLanguage != 'en') {
        // include as default all english texts
        foreach (glob($path.'language/*en.tmx') as $file) {
          Translation::LoadTMXFile($file, 'en', Core::$Cache);
        }
      }
      // include only for enabled modules and plugins
      foreach (glob($path.'language/*'.$sLanguage.'.tmx') as $file) {
        Translation::LoadTMXFile($file, $sLanguage, Core::$Cache);
      }
    }
  }
}

/// Yryie::StopTimer('MoreLangLoad');

// ----------------------------------------------------------------------------
// pre process
// ----------------------------------------------------------------------------
if (esf_User::isValid()) esf_Auctions::Load();

Event::ProcessInform('PageStart');

if (!esf_User::isValid() AND
    Registry::get('Module.'.$sModule.'.LoginRequired')) {
  /// Yryie::Info('Forward: '.$sModule.' -> Login');
  if ($sModule != 'login') {
    unset($_REQUEST['ESFSESSID']);
    Session::setP('LoginReturn', $_REQUEST);
  }
  Core::Forward('login');
} elseif (esf_User::isValid() AND $sLoginReturn = Session::getP('LoginReturn')) {
  Session::setP('LoginReturn');
  Core::Forward(NULL, NULL, $sLoginReturn);
}

$sModule = Registry::get('esf.Module');

// generic styles/scripts, from defined layout or fallback layout
TplData::add('HtmlHeader.raw', StylesAndScripts('.', Session::getP('Layout')));

if (strtoupper($_SERVER['REQUEST_METHOD']) == 'GET' AND isset($_REQUEST['returnto']))
  Session::setP('returnto', $_REQUEST['returnto']);

$sModuleLast = FALSE;

// module preparation
do {

  $sTitle = Translation::getNVL($sModule.'.Title', ucwords($sModule));
  TplData::set('Title', $sTitle);
  TplData::set('SubTitle1', $sTitle);
  /// TplData::set('SubTitle1',
  ///              $sTitle.' <tt style="font-size:80%;color:red">['
  ///             .exec('git branch | grep \* | cut -d" " -f2').']</tt>');
  unset($sTitle);

  TplData::set('SubTitle2', '');

  // >> Debug
  Yryie::Info('Processing '.$sModule.' / '.Registry::get('esf.Action'));
  // << Debug

  if ($sModuleLast != $sModule) unset($oModule);

  $sModuleLast = $sModule;

  if (!isset($oModule) AND
      Loader::Load('module/'.$sModule.'/module.class.php', TRUE, FALSE)) {
    $sClass = 'esf_Module_'.$sModule;
    $oModule = new $sClass;
  }
  if (isset($oModule)) $oModule->handle(Registry::get('esf.Action'));

  // handle ReturnTo=...
  $sReturnTo = decodeReturnTo(Session::getP('returnto'));

  if (!empty($sReturnTo) AND
      (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' OR
       strpos($sReturnTo, 'force') !== FALSE )) {
    Session::setP('returnto');
    Core::Redirect($sReturnTo);
  }

  // module specific styles/scripts, from defined layout
  TplData::add('HtmlHeader.raw', StylesAndScripts('module/'.$sModule, Session::getP('Layout')));

  // loop until no more forwarded
} while ($sModuleLast != $sModule);
unset($sModuleLast);

// ----------------------------------------------------------------------------
// process
// ----------------------------------------------------------------------------

Event::ProcessInform('ProcessStart');

if (esf_User::isValid()) {
  // reread auctions AND GROUPS!!
  esf_Auctions::Load();

  if ($sLastUpdate = Event::ProcessReturn('getLastUpdate')) {
    TplData::set('LastUpdate', strftime(Registry::get('Format.DateTimeS'), $sLastUpdate));
    unset($sLastUpdate);
  }
  TplData::set('User', esf_User::getActual());
}

TplData::setConstant('ESF.TITLE', ESF_TITLE);
TplData::setConstant('ESF.SLOGAN', ESF_SLOGAN);
TplData::setConstant('ESF.VERSION', ESF_VERSION);
TplData::setConstant('ESF.RELEASE', ESF_RELEASE);
TplData::setConstant('ESF.HOMEPAGE', ESF_HOMEPAGE);
TplData::setConstant('ESF.AUTHOR', ESF_AUTHOR);
TplData::setConstant('ESF.EMAIL', ESF_EMAIL);
TplData::setConstant('ESF.LONG_TITLE', ESF_LONG_TITLE);
TplData::setConstant('ESF.FULL_VERSION', ESF_FULL_VERSION);
TplData::setConstant('ESF.FULL_TITLE', ESF_FULL_TITLE);
TplData::setConstant('ESF.APPID', APPID);
TplData::setConstant('ESF.LANGUAGE', Session::get('language'));
TplData::setConstant('ESF.MODULE', Registry::get('esf.Module'));
TplData::setConstant('YEAR', date('Y'));
TplData::setConstant('ESNIPER.VERSION', Session::get('esniperVersion'));
TplData::setConstant('YUELO_VERSION', 'Yuelo - Template engine V. '.Yuelo::VERSION);
TplData::setConstant('PHP.VERSION', PHP_VERSION);

// Store server into cache
while (Core::$Cache->save('Server', $server)) {
  reset($GLOBALS['Servers']);
  while ($s = current($GLOBALS['Servers']) AND empty($server)) {
    if (stristr($_SERVER['SERVER_SOFTWARE'], $s[0])) {
      $server = array('NAME' => $s[0], 'URL' => $s[1]);
      break;
    }
    next($GLOBALS['Servers']);
  }
  if (!$server) {
    preg_match('~^[\w\s]+~', $_SERVER['SERVER_SOFTWARE'], $args);
    $server = array('NAME' => strtoupper(trim($args[0])), 'URL' => NULL);
  }
  Core::$Cache->set('Server', $server);
}
TplData::setConstant('SERVER', $server);
unset($server, $s);

TplData::setConstant('SERVER.VERSION', $_SERVER['SERVER_SOFTWARE']);

TplData::set('Layouts', getLayouts());
TplData::set('Layout', Session::getP('Layout'));

TplData::set('Ebay_Homepage', Registry::get('ebay.Homepage'));
TplData::set('FormAction', Core::URL(array('module'=>$sModule)));
TplData::set('NoJS', Registry::get('NoJS'));
TplData::set('GetCategoryFromGroup', FROMGROUP);

// ----------------------------------------------------------------------------
// post process / output
// ----------------------------------------------------------------------------
// load additional layout specific code for all layouts, if exists
$path = 'module/'.$sModule.'/layout/';
Loader::Load($path . 'layout.php', TRUE, FALSE);
// for actual module layout, if exists
Loader::Load($path . Session::getP('Layout').'.php', TRUE, FALSE);

foreach (esf_Extensions::$Types as $ExtType) {
  foreach (esf_Extensions::getExtensions($ExtType) as $Ext) {
    $ExtKey = strtoupper($ExtType).'S.'.strtoupper($Ext);
    $enabled = esf_Extensions::checkState($ExtType, $Ext, esf_Extensions::BIT_ENABLED);
    TplData::setConstant($ExtKey.'.ENABLED', $enabled);
    if ($enabled AND $vars = Registry::get($ExtType.'.'.$Ext)) {
      foreach ($vars as $key => $val) {
        $key = strtoupper($key);
        if (strpos('|NAME|AUTHOR|EMAIL|VERSION|', "|$key|") === FALSE) {
          TplData::setConstant($ExtKey.'.'.$key, $val);
        }
      }
    }
  }
}

// put actual module configuration in separate (common) array
TplData::set('MODULE', $sModule);
foreach (getModuleVar($sModule) as $key => $val)
  TplData::setConstant('MODULE.'.strtoupper($key), $val);
unset($ExtType, $Event, $pExt, $vars, $key, $val);

Event::ProcessInform('OutputStart');

/// Yryie::StartTimer('LoadPluginStyles', 'Load plugin styles and scripts');

// plugin specific styles/scripts, from defined layout or fallback layout
foreach (esf_Extensions::getExtensions(esf_Extensions::PLUGIN) as $plugin)
  if (PluginEnabled($plugin))
    TplData::add('HtmlHeader.raw', StylesAndScripts('plugin/'.$plugin, Session::getP('Layout')));

/// Yryie::StopTimer('LoadPluginStyles');
/// Yryie::StartTimer('BuildMenus', 'Build menus');

// language selectors
foreach ((array)$esf_Languages as $name => $desc) {
  TplData::set('Language.'.$name.'.Name', $name);
  TplData::set('Language.'.$name.'.Desc', $desc);
  TplData::set('Language.'.$name.'.URL',  Core::URL(array('params'=>array('language' => $name))));
}

// build menu
$menustyle = Registry::get('MenuStyle');
if (!is_array($menustyle)) $menustyle = explode(',', $menustyle);

Event::ProcessInform('BuildMenu');

TplData::set('Menu.Main',   esf_Menu::getMain($menustyle[0]));
TplData::set('Menu.Module', esf_Menu::getModule($menustyle[1]));
TplData::set('Menu.System', esf_Menu::getSystem($menustyle[2]));

/// Yryie::StopTimer('BuildMenus');
/// Yryie::StartTimer('esniperBugs', 'Check for esniper bug reports');

if (esf_User::isValid()) {
  // check for encountered esniper bug
  $aBugReports = glob(np(esf_User::UserDir().'/esniper.*.html'));
  $sBugDir = np(esf_User::UserDir().'/esniper.bug/');
  if (count($aBugReports)) {
    checkDir($sBugDir);
    foreach ($aBugReports as $sFile) {
      $sTo = sprintf('%s%s', $sBugDir, basename($sFile));
      if ($oExec->Move($sFile, $sTo, $sResult)) {
        Messages::Error($sResult);
      }
    }
    Messages::Error(Translation::get('Core.EsniperEncounteredBug', $sBugDir));
  }
}
unset($aBugReports, $sBugDir, $sFile, $sTo, $sResult);

/// Yryie::StopTimer('esniperBugs');
/// Yryie::StartTimer('HTMLHead');

if (!DEVELOP) ob_start();

Yuelo::set('Language', Session::get('language'));
Yuelo::set('Layout', Session::getP('Layout'));
$RootDir = array(
  BASEDIR.'/module/'.$sModule.'/layout',
  BASEDIR.'/layout',
);

$oTemplate->Output('doctype', FALSE, $RootDir);

$html = $oTemplate->Render('html.head', TRUE, $RootDir);
Event::Process('OutputFilterHtmlHead', $html);
Event::Process('OutputFilter', $html);
echo $html;

/// Yryie::StopTimer('HTMLHead');
/// Yryie::StartTimer('HTMLStart');

TplData::set('esf_MessagesErrors', Messages::count(Messages::ERROR));
TplData::set('esf_Messages', implode((array)Messages::get()));

$html = $oTemplate->Render('html.start', TRUE, $RootDir);
Event::Process('OutputFilterHtmlStart', $html);
Event::Process('OutputFilter', $html);
echo $html;

$steps = Registry::get('esf.contentonly')
       ? array('content')
       : array('header', 'content', 'footer');

/// Yryie::StopTimer('HTMLStart');

if (!DEVELOP) ob_end_flush();

/// Yryie::StartTimer('IndexSteps', 'HTML Content steps');

foreach ($steps as $step) {
  /// $block = $step.'block';
  /// Yryie::StartTimer($block, ucwords($step).' block');
  Event::ProcessInform('Output'.$step);

  if (isset($oModule)) $oModule->handle(Registry::get('esf.Action'), $step);

  if (!DEVELOP) ob_start();

  if ($step == 'content') {
    // if actual module is only a display module,
    // try to render 'content'.Registry::get('esf.Action') ...
    if (TplData::isEmpty('Content')) {
      $content = $oTemplate->Render('content.'.Registry::get('esf.Action'), FALSE, $RootDir);
      TplData::set('Content', $content);
    }
    // Render general template 'content', if exists
    $content = $oTemplate->Render('content', FALSE, $RootDir);
    if ($content) TplData::set('Content', $content);
  }

  $html = $oTemplate->Render('html.'.$step, TRUE, $RootDir);
  Event::Process('OutputFilter'.$step, $html);
  Event::Process('OutputFilter', $html);
  echo $html;

  if (!DEVELOP) ob_end_flush();
  /// Yryie::StopTimer($block);
}

/// Yryie::StopTimer('IndexSteps');

if (!DEVELOP) ob_start();

if (_DEBUG) {
  Yryie::Finalize();
  if ($_TRACE) Yryie::Save($_TRACE);
  echo '<div id="Yryie_title"
             style="margin-top:1em;cursor:pointer;text-align:center;padding:0.25em"
             onclick="$(\'Yryie_wrap\').toggle()"><tt><strong>Yryie</strong></tt></div>
        <div id="Yryie_wrap" style="height:40em;overflow:auto;display:none">';
  Yryie::Output(TRUE, TRUE);
  echo '</div>';
}

$html = $oTemplate->Render('html.end', TRUE, $RootDir);
Event::Process('OutputFilterHtmlEnd', $html);
Event::Process('OutputFilter', $html);
echo $html;

Event::ProcessInform('PageEnded');

Session::close();
