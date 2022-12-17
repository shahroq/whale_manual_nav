<?php
/**
 * Nestable Manual Nav Add-on
 * Manually choose pages, links, and files for your navigation menu
 * For concrete5
 * 
 * @author      Shahroq <shahroq \at\ yahoo.com>
 * @copyright   Copyright 2017-2022 Shahroq
 * @link        https://github.com/shahroq/whale_manual_nav
 * @link        https://www.concrete5.org/marketplace/addons/nestable-manual-nav
 */

namespace Concrete\Package\WhaleManualNav;

use Concrete\Core\Block\BlockType\BlockType;
use Concrete\Core\Package\Package;

defined('C5_EXECUTE') or die(_("Access Denied."));

class Controller extends Package
{
    protected $pkgHandle = 'whale_manual_nav';
    protected $appVersionRequired = '5.7.3';
    protected $pkgVersion = '1.4.3';

    public function getPackageName()
    {
        return t("Nestable Manual Nav");
    }

    public function getPackageDescription()
    {
        return t("Manually choose pages, links, and files for your navigation menu");
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
        $db = $this->app->make('database')->connection();
        
        // drop tables
        $db->executeQuery('DROP TABLE IF EXISTS `btWhaleManualNav`');
    }
}
