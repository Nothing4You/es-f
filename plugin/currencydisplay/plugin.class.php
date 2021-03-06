<?php
/** @defgroup Plugin-CurrencyDisplay Plugin CurrencyDisplay

*/

/**
 * Plugin CurrencyDisplay
 *
 * @ingroup    Plugin
 * @ingroup    Plugin-CurrencyDisplay
 * @author     Knut Kohl <knutkohl@users.sourceforge.net>
 * @copyright  2009-2011 Knut Kohl
 * @license    GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version    1.0.0
 * @version    $Id: v2.4.1-80-g4acbac1 2011-02-15 22:22:16 +0100 $
 */
class esf_Plugin_CurrencyDisplay extends esf_Plugin {

  /**
   * Class constructor
   */
  public function __construct() {
    parent::__construct();
    if ($this->Mapping) {
      foreach (explode('|', $this->Mapping) as $value) {
        @list($c1, $c2) = @explode('=', $value);
        $this->Mappings[$c1] = $c2;
      }
    }
  }

  /**
   * @return array Array of events handled by the plugin
   */
  public function handles() {
    return array('DisplayAuction');
  }

  /**
   *
   */
  public function DisplayAuction( &$auction ) {
    // Don't replace default currency!
    if ($auction['currency'] == Registry::get('Currency')) return;

    if (isset($this->Mappings[$auction['currency']]))
      esf_Auctions::setDisplay($auction, 'currency', $this->Mappings[$auction['currency']]);
  }

  //--------------------------------------------------------------------------
  // PRIVATE
  //--------------------------------------------------------------------------

  private $Mappings = array();
}

Event::attach(new esf_Plugin_CurrencyDisplay);