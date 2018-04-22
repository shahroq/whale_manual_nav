<?php
namespace Concrete\Package\WhaleManualNav\Block\WhaleManualNav;

use Core;
use Page;
use Concrete\Core\Block\BlockController;

defined('C5_EXECUTE') or die("Access Denied.");

class Controller extends BlockController
{
    protected $btTable = 'btWhaleManualNav';
    protected $btInterfaceWidth = 700;
    protected $btInterfaceHeight = 600;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = true;
    protected $btExportPageColumns = array('internalLinkCID');
    protected $btDefaultSet = 'navigation';

    protected $maxDepth = 5; //nestable max alllowed depth

    protected $nav = array();
    protected $level;
    protected $cIDCurrent;
    protected $selectedPathCIDs;

    public function getBlockTypeDescription()
    {
        return t("Whale Manual Nav");
    }

    public function getBlockTypeName()
    {
        return t("Nestable Manual Nav");
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->requireAsset('core/sitemap');
        $this->setVariables();
    }

    //set vars use in view
    private function setVariables()
    {
        $jh = Core::make('helper/json');
        $navItemsAr = ($this->navItems) ? $jh->decode($this->navItems) : array();
        if(!is_array($navItemsAr)) $navItemsAr = array();

        //reindex ids
        $navItemsAr = $this->reindexNavItems($navItemsAr);

        $this->set('navItemsAr', $navItemsAr );
        $this->set('maxDepth', $this->maxDepth );
    }

    private function reindexNavItems($navItemsAr, &$i=0)
    {
        foreach ($navItemsAr as $key => $item) {
            $i++;
            $navItemsAr[$key]->id = $i;
            if (isset($item->children) && is_array($item->children) && count($item->children)>0){
                $this->reindexNavItems($item->children, $i);
            }
        }
        return $navItemsAr;
    }

    private function getNavItemInfo($item)
    {
        $nh = Core::make('helper/navigation');

        $navItem = new \stdClass();

        $navItem->name = isset($item->itemName) ? $item->itemName : '*';

        $navItem->itemUrlType = $item->itemUrlType;
        $navItem->cObj = false;
        $navItem->cID = false;
        $navItem->url = '#';
        $navItem->isHome = false;
        $navItem->isCurrent = false;
        $navItem->inPath = false;
        $navItem->attrClass = '';
        if ($item->itemUrlType == 'external'){
            $navItem->url = $item->itemUrlExternal;
        } elseif ($item->itemUrlType == 'internal') {
            $page = Page::getByID((int)$item->itemUrlInternal);
            if (isset($page->cID)) {
                $navItem->cObj = $page;
                $navItem->cID = $page->cID;
                $navItem->url = $nh->getCollectionURL($page);

                if ($page->getAttribute('replace_link_with_first_in_nav')) {
                    $subPage = $page->getFirstChild();
                    if ($subPage instanceof Page) {
                        $pageLink = $nh->getLinkToCollection($subPage);
                        if ($pageLink) $navItem->url = $pageLink;
                    }
                }

                if ($page->cID == HOME_CID) $navItem->isHome = true;
                if ($page->cID == $this->cIDCurrent) {
                    $navItem->isCurrent = true;
                    $navItem->inPath = true;
                } elseif (in_array($page->cID, $this->selectedPathCIDs)) {
                    $navItem->inPath = true;
                }
                $attribute_class = $page->getAttribute('nav_item_class');
                if (!empty($attribute_class)) $navItem->attrClass = $attribute_class;
            }
        }

        $navItem->target = isset($item->itemUrlNewWindow) ? $item->itemUrlNewWindow == 1 ? '_blank' : '_self' : '_self';

        $navItem->level = $this->level;

        $this->nav[] = $navItem;

        //children:
        $navItem->hasSubmenu = false;
        if (isset($item->children) && count($item->children)>0) {
            $navItem->hasSubmenu = true;
            $this->level++;
            foreach ($item->children as $key => $item) {
                $this->getNavItemInfo($item);
            }
            $this->level--;

        }
    }

    public function getNavItems()
    {
        $jh = Core::make('helper/json');

        $this->level = 1;
        $this->cIDCurrent = Page::getCurrentPage()->getCollectionID();
        $this->selectedPathCIDs = array($this->cIDCurrent);

        //store parent ids
        $parentCIDnotZero = true;
        $inspectC = Page::getCurrentPage();

        if (version_compare(\Config::get('concrete.version'), '8.0', '>=')) {
            // if v8+
            $homePageID = $inspectC->getSiteHomePageID();
        } else {
            // if not v8
            $homePageID = HOME_CID;
        }

        while ($parentCIDnotZero) {
            $cParentID = $inspectC->getCollectionParentID();
            if (!intval($cParentID)) {
                $parentCIDnotZero = false;
            } else {
                if ($cParentID != $homePageID) {
                    $this->selectedPathCIDs[] = $cParentID; //Don't want home page in nav-path-selected
                }
                $inspectC = Page::getById($cParentID, 'ACTIVE');
            }
        }

        //Prep all data and put it into a clean structure so markup output is as simple as possible
        $navItemsAr = ($this->navItems) ? $jh->decode($this->navItems) : array();
        if(!is_array($navItemsAr)) $navItemsAr = array();

        //get each item infos
        foreach ($navItemsAr as $key => $item) {
            $this->getNavItemInfo($item);
        }

        //add extra infos to each item
        for ($i = 0; $i < count($this->nav); $i++) {

            $current_level = $this->nav[$i]->level;
            $prev_level = isset($this->nav[$i - 1]) ? $this->nav[$i - 1]->level : -1;
            $next_level = isset($this->nav[$i + 1]) ? $this->nav[$i + 1]->level : 1;

            //Calculate difference between this item's level and next item's level so we know how many closing tags to output in the markup
            $this->nav[$i]->subDepth = $current_level - $next_level; //echo $current_level."-".$next_level."-".$this->nav[$i]->subDepth."<br>";
            //Calculate if this is the first item in its level (useful for CSS classes)
            $this->nav[$i]->isFirst = $current_level > $prev_level;
            //Calculate if this is the last item in its level (useful for CSS classes)
            $this->nav[$i]->isLast = true;
            for ($j = $i + 1; $j < count($this->nav); ++$j) {
                if ($this->nav[$j]->level == $current_level) {
                    //we found a subsequent item at this level (before this level "ended"), so this is NOT the last in its level
                    $this->nav[$i]->isLast = false;
                    break;
                }
                if ($this->nav[$j]->level < $current_level) {
                    //we found a previous level before any other items in this level, so this IS the last in its level
                    $this->nav[$i]->isLast = true;
                    break;
                }
            } //If loop ends before one of the "if" conditions is hit, then this is the last in its level (and $is_last_in_level stays true)

        }

        return $this->nav;
    }
}
