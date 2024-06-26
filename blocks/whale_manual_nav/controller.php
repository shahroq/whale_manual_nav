<?php

namespace Concrete\Package\WhaleManualNav\Block\WhaleManualNav;

use Concrete\Core\File\File;
use Concrete\Core\Page\Page;
use Concrete\Core\Block\BlockController;

class Controller extends BlockController
{
    protected $btTable = 'btWhaleManualNav';
    protected $btInterfaceWidth = 700;
    protected $btInterfaceHeight = 600;
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = false;
    protected $btExportPageColumns = array('internalLinkCID');
    protected $btDefaultSet = 'navigation';

    // nestable max alllowed depth
    protected $maxDepth = 5;

    protected $nav = array();
    protected $level;
    protected $cIDCurrent;
    protected $selectedPathCIDs;

    public $navItems;
        
    public function getBlockTypeName()
    {
        return t("Nestable Manual Nav");
    }

    public function getBlockTypeDescription()
    {
        return t("Whale Manual Nav");
    }

    public function add()
    {
        $this->edit();
    }

    public function edit()
    {
        $this->requireAsset('core/sitemap');
        $this->requireAsset('core/file-manager');
        $this->setVariables();
    }

    // set vars for using in the view
    private function setVariables()
    {
        $jh = $this->app->make('helper/json');
        
        $navItemsAr = !isset($this->navItems) || !$this->navItems ? array() : $jh->decode($this->navItems);

        // reindex ids
        $navItemsAr = $this->reindexNavItems($navItemsAr);

        $this->set('navItemsAr', $navItemsAr);
        $this->set('maxDepth', $this->maxDepth);
    }

    private function reindexNavItems($navItemsAr, &$i = 0)
    {
        foreach ($navItemsAr as $key => $item) {
            $i++;
            $navItemsAr[$key]->id = $i;
            if (isset($item->children) && is_array($item->children) && count($item->children) > 0) {
                $this->reindexNavItems($item->children, $i);
            }
        }
        return $navItemsAr;
    }

    private function getNavItemInfo($item)
    {
        $nh = $this->app->make('helper/navigation');

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
        if ($item->itemUrlType == 'external') {
            $navItem->url = $item->itemUrlExternal;
        } elseif ($item->itemUrlType == 'internal') {
            $page = Page::getByID((int) $item->itemUrlInternal);
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
        } elseif ($item->itemUrlType == 'file') {
            $f = File::getByID((int) $item->itemUrlFile);
            if ($f) {
                $navItem->url = (empty($f)) ? '' : $f->getDownloadURL();
            }
        }

        $navItem->target = isset($item->itemUrlNewWindow) ? $item->itemUrlNewWindow == 1 ? '_blank' : '_self' : '_self';

        $navItem->level = $this->level;

        $this->nav[] = $navItem;

        // children
        $navItem->hasSubmenu = false;
        if (isset($item->children) && count($item->children) > 0) {
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
        $jh = $this->app->make('helper/json');

        $this->nav = array();
        $this->level = 1;
        $this->cIDCurrent = Page::getCurrentPage()->getCollectionID();
        $this->selectedPathCIDs = array($this->cIDCurrent);

        // store parent ids
        $parentCIDnotZero = true;
        $inspectC = Page::getCurrentPage();

        $homePageID = $inspectC->getSiteHomePageID();

        while ($parentCIDnotZero) {
            $cParentID = $inspectC->getCollectionParentID();
            if (!intval($cParentID)) {
                $parentCIDnotZero = false;
            } else {
                if ($cParentID != $homePageID) {
                    $this->selectedPathCIDs[] = $cParentID; // don't want home page in nav-path-selected
                }
                $inspectC = Page::getById($cParentID, 'ACTIVE');
            }
        }

        // prep all data and put them into a clean structure so markup output can be as simple as possible
        $navItemsAr = ($this->navItems) ? $jh->decode($this->navItems) : array();
        if (!is_array($navItemsAr)) $navItemsAr = array();

        // get each item info
        foreach ($navItemsAr as $key => $item) {
            $this->getNavItemInfo($item);
        }

        // add extra info to each item
        for ($i = 0; $i < count($this->nav); $i++) {

            $current_level = $this->nav[$i]->level;
            $prev_level = isset($this->nav[$i - 1]) ? $this->nav[$i - 1]->level : -1;
            $next_level = isset($this->nav[$i + 1]) ? $this->nav[$i + 1]->level : 1;

            // calculate the difference between this item's level and next item's level so we know how many closing tags to output in the markup
            $this->nav[$i]->subDepth = $current_level - $next_level; //echo $current_level."-".$next_level."-".$this->nav[$i]->subDepth."<br>";
            // calculate if this is the first item in its level (useful for CSS classes)
            $this->nav[$i]->isFirst = $current_level > $prev_level;
            // calculate if this is the last item in its level (useful for CSS classes)
            $this->nav[$i]->isLast = true;
            for ($j = $i + 1; $j < count($this->nav); ++$j) {
                if ($this->nav[$j]->level == $current_level) {
                    // we found a subsequent item at this level (before this level 'ended'), so this is NOT the last in its level
                    $this->nav[$i]->isLast = false;
                    break;
                }
                if ($this->nav[$j]->level < $current_level) {
                    // we found a previous level before any other items in this level, so this IS the last in its level
                    $this->nav[$i]->isLast = true;
                    break;
                }
            } // if loop ends before one of the "if" conditions is hit, then this is the last in its level (and $is_last_in_level stays true)

        }

        return $this->nav;
    }
}
