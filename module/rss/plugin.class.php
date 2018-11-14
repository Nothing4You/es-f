<?php
/**
 * Module RSS plugin
 *
 * @ingroup    Plugin
 * @ingroup    Module-RSS
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2009 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    0.1.0
 * @version    $Id: v2.4.1-62-gb38404e 2011-01-30 22:35:34 +0100 $
 */
class esf_Plugin_Module_RSS extends esf_Plugin {

  /**
   * @return array Array of events handled by the plugin
   */
  public function handles() {
    return array('Start', 'OutputStart', 'OutputFilterFooter');
  }

  /**
   *
   */
  function Start() {
    ModuleRequireModule( 'RSS', 'Auction', '0.6.0' );
  }

  /**
   * Add alternate link for RSS feed
   */
  function OutputStart() {
    if (!$user = esf_User::getActual()) return;

    TplData::add('HtmlHeader.Raw',
      sprintf('<link rel="alternate" type="application/rss+xml" '
             .'href="index.php?module=rss&amp;%1$s=%3$s" '
             .'title="RSS Feed of auctions for %2$s">'."\n",
              urlencode(APPID), $user, urlencode(MD5Encryptor::encrypt($user))));
  }

  /**
   * Add RSS icon to footer
   */
  function OutputFilterFooter( &$content ) {
    // disable on mobile layouts
    if (Session::get('Mobile') AND !$this->Mobile) return;

    if (!$user = esf_User::getActual()) return;

    $data = array(
      'APPID'   => APPID,
      'URLUSER' => MD5Encryptor::encrypt($user),
      'USER'    => $user
    );
    $html = ParseModuleTemplate('rss', 'content', $data);
    $content = preg_replace('~<div[^>]+id=(["\'])footer\1[^>]+>~',
                            '$0' . $html, $content);
  }

}

Event::attach(new esf_Plugin_Module_RSS);
