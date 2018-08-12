<?php
/**
 * @author 		shahroq <shahroq \at\ yahoo.com>
 * @copyright  	Copyright (c) 2017 shahroq.
 * http://concrete5.killerwhalesoft.com/addons/
 */
namespace Concrete\Package\WhaleManualNav;

use Loader;
use Package;
use Database;
use BlockType;

defined('C5_EXECUTE') or die(_("Access Denied."));

class Controller extends Package
{
	protected $pkgHandle = 'whale_manual_nav';
    protected $appVersionRequired = '5.7.3';
    protected $pkgVersion = '1.3.0';

	public function getPackageDescription()
    {
    	return t("Whale Manual Nav");
    }

    public function getPackageName()
    {
    	return t("Nestable Manual Nav");
    }

    public function install()
    {
    	$pkg = parent::install();

        // install block
        BlockType::installBlockType('whale_manual_nav', $pkg);
    }

	public function uninstall()
    {
		parent::uninstall();

        //drop tables
        $db = Database::connection();
        $db->executeQuery('DROP TABLE IF EXISTS `btWhaleManualNav`');
	}
}
