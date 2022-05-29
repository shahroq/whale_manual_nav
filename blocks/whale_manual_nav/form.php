<?php 
defined('C5_EXECUTE') or die("Access Denied."); 

$app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
$jh = $app->make('helper/json');
?>

<div class="ccm-tab-content" id="ccm-tab-content-mega-menu-menu" style="display: block">

    <input type='hidden' id='navItems' name='navItems' value='<?php echo h($navItems) ?>'>

    <div style="margin-bottom: 20px;" class="d-flex justify-content-end-">
        <a class="btn btn-success btn-sm ccm-add-menu-item ms-auto">
            <i class="fas fa-plus-circle"></i>
            <?php echo t('Add Item') ?>
        </a>
    </div>

    <div class="dd">
        <ol class="dd-list" id="nestableContainer">
        </ol>
    </div>

    <div style="margin-top: 20px;" class="d-flex justify-content-end">
        <a class="btn btn-success btn-sm ccm-add-menu-item">
            <i class="fas fa-plus-circle"></i>
            <?php echo t('Add Item') ?>
        </a>
    </div>

</div>

<script type="text/javascript">
$(function() {

    var nestableContainer = $('#nestableContainer');
    var _templateLIOpen = _.template($('#templateILOpen').html());
    var _templateLIClose = _.template($('#templateILClose').html());
    var _templateOLOpen = _.template($('#templateOLOpen').html());
    var _templateOLClose = _.template($('#templateOLClose').html());

    // toggle betwwen internal/external/file url
    $('.dd').on('change', 'select[data-field=item-url-type-select]', function() {
        var container = $(this).closest('.dd-item');
        var internalContainer = container.find('>.dd-content div[data-field=item-url-internal-container]');
        var externalContainer = container.find('>.dd-content div[data-field=item-url-external-container]');
        var fileContainer = container.find('>.dd-content div[data-field=item-url-file-container]');
        switch($(this).val()) {
            case 'internal':
                internalContainer.show();
                externalContainer.hide();
                fileContainer.hide();
                break;
            case 'external':
                internalContainer.hide();
                externalContainer.show();
                fileContainer.hide();
                break;
            case 'file':
                internalContainer.hide();
                externalContainer.hide();
                fileContainer.show();
                break;
            default:
                internalContainer.hide();
                externalContainer.hide();
                fileContainer.hide();
                break;
        }
    });

    var updateNavField = function(e) {
        // first set data-* attributes based on input/select fields
        $('.dd').find('input, select').each(function() {
            itemContainer = $(this).closest('.dd-item');
            value = $(this).val();
            name = $(this).attr('name');
            name = name.replace(/\[\]/g,'');
            itemContainer.data(name, value);
        });

        var list = e.length ? e : $(e.target);
        if (e.length) {
            var list = e;
        } else {
            var list = $(e.target);
            list = list.closest('.dd');
        }

        var rslt = JSON.stringify(list.nestable('serialize'));
        $('#navItems').val(rslt);
    };

    // generate each nav item ui
    var generateItem = function(item) {

        var htmlCode = '';

        htmlCode += _templateLIOpen({
            itemID: item.id,
            itemName: item.itemName,
            itemUrlNewWindow: item.itemUrlNewWindow,
            itemUrlType: item.itemUrlType,
            itemUrlInternal: item.itemUrlInternal,
            itemUrlExternal: item.itemUrlExternal,
            itemUrlFile: item.itemUrlFile,
        });

        if(typeof item.children == 'object' && item.children.length > 0) {
            htmlCode += _templateOLOpen();
            for(var i in item.children) {
                htmlCode += generateItem(item.children[i])
            }
            htmlCode += _templateOLClose();
        }

        htmlCode += _templateLIClose();

        return htmlCode;
    }

    <?php foreach ($navItemsAr as $item) { ?>
        itemStr = '<?php echo addslashes($jh->encode($item)) ?>';
        itemJson = JSON.parse(itemStr);
        htmlCode = generateItem(itemJson);
        nestableContainer.append(htmlCode);
    <?php } ?>

    // fire url type selector
    nestableContainer.find('select[data-field=item-url-type-select]').trigger('change');
    // fire page selector
    nestableContainer.find('[data-field=item-url-internal-wrapper]').each(function() {
        cID = $(this).closest('.dd-item').data('item-url-internal');
        $(this).concretePageSelector({
            'cID': cID,
            'inputName': 'itemUrlInternal[]'
        });
    });

    // fire file selector
    /*
    nestableContainer.find('[data-field=item-url-file-wrapper]').each(function() {
        fID = $(this).closest('.dd-item').data('item-url-file');
        $(this).concreteFileSelector({
            'chooseText': '<?php echo t("Choose File") ?>',
            'fID': fID,
            'inputName': 'itemUrlFile[]'
        });
    });
    */

    // add item to list
    $('a.ccm-add-menu-item').click(function() {
        var nestableCount = $('li.dd-item').length + 1;
        var newItem = JSON.parse('{"itemName":"Item '+nestableCount+'","itemUrlNewWindow":"0","itemUrlType":"internal","itemUrlInternal":"0","itemUrlExternal":"","itemUrlFile":"0","id":'+nestableCount+'}');
        htmlCode = generateItem(newItem);
        nestableContainer.append(htmlCode);

        var newItem = $('.dd-item').last().hide().fadeIn();
        var thisModal = $(this).closest('.ui-dialog-content');
        thisModal.animate({scrollTop: newItem.offset().top},'slow');
        newItem.find('[data-field=item-url-internal-wrapper]').concretePageSelector({
           'inputName': 'itemUrlInternal[]'
        });
        /*
        // not available on v9? #tocheck
        newItem.find('[data-field=item-url-file-wrapper]').concreteFileSelector({
           'chooseText': '<?php echo t('Choose File') ?>',
           'inputName': 'itemUrlFile[]'
        });
        */

        nestableContainer.find('select[data-field=item-url-type-select]:last-child').trigger('change');

        updateNavField($('.dd'));
    });

    // nestable
    $('.dd').nestable({
        maxDepth:<?php echo $maxDepth ?>,
        group: 1
    })
    .on('change', updateNavField); // it also fires when a form element change (textfield, select)

    // debug: Concrete.event.debug(true)
    // fire when user select an internal page (update data atrributes)
    Concrete.event.bind('ConcreteSitemap', function(e, instance) {
    Concrete.event.bind('SitemapSelectPage', function(e, data) {
        if (data.instance == instance) {
            Concrete.event.unbind(e);
            updateNavField($('.dd'));
        }
    });
    });

    /*
    // fire when user selects a file (update data atrributes)
    Concrete.event.bind('FileManagerBeforeSelectFile', function(e, instance) {
    Concrete.event.bind('FileManagerSelectFile', function(e, data) {
        if (data.fID > 0) {
            Concrete.event.unbind(e);
            updateNavField($('.dd'));
        }
    });
    });
    */

    // clear internal page: not working!
    $('.dd').on('click', 'a.ccm-item-selector-clear', function(e) {
        e.preventDefault();
        return false;
    });

    // expand/collapse items
    $('.dd').on('click', 'a.show-hide', function(e) {
        e.preventDefault();
        var target = $(this).closest('div.wmn-content').find('.form-options');
        if (target.is(':visible')) {
            $(this).find('i').attr('class', 'fas fa-chevron-down');
            target.stop(true, true).slideUp();
        } else {
            $(this).find('i').attr('class', 'fas fa-chevron-up');
            target.stop(true, true).slideDown();
        }
        return false;
    });

    // remove items
    $('.dd').on('click', 'a.remove-item', function(e) {
        e.preventDefault();
        if(confirm('<?php echo t("Do you want to remove this item?") ?>')) {
            $(this).closest('.dd-item').fadeOut(300, function(){
                $(this).closest('.dd-item').remove();
                updateNavField($('.dd'));
            });
        }
        return false;
    });

    // clone items
    $('.dd').on('click', 'a.clone-item', function(e) {
        e.preventDefault();
        if(confirm('<?php echo t("Do you want to clone this item?") ?>')) {
            $(this)
                .closest('.dd-item')
                .clone(true)
                .attr('data-id', $('li.dd-item').length + 1)
                .appendTo($(this).closest('.dd-list'))
                .hide()
                .fadeIn()
                .find('ol').remove(); // do not clone children
            updateNavField($('.dd'));
        }
        return false;
    });

    // copy selected internal page title to name field
    $('.dd').on('click', 'a.copy-page-title', function(e) {
        e.preventDefault();
        <?php if (version_compare(\Config::get('concrete.version'), '8.0', '>=')) { ?>
            // v8+
            name = $(this).closest('.dd-item').find('>.dd-content .ccm-item-selector-item-selected-title').text();
        <?php } else { ?>
            // v7
            name = $(this).closest('.dd-item').find('>.dd-content .ccm-page-selector-page-selected-title').text();
        <?php } ?>
        $(this).closest('.dd-item').find('.item-name').first().val(name);
        updateNavField($('.dd'));
        updateHeader($(this).closest('.dd-item'));
        return false;
    });

    // copy selected file title to name field
    $('.dd').on('click', 'a.copy-file-title', function(e) {
        e.preventDefault();
        name = $(this).closest('.dd-item').find('>.dd-content .ccm-file-selector-file-selected-title div').text();
        $(this).closest('.dd-item').find('.item-name').first().val(name);
        updateNavField($('.dd'));
        updateHeader($(this).closest('.dd-item'));
        return false;
    });

    // update item header based on 'Name' field
    var updateHeader = function(item) {
        item.find('>.dd-content .item-header').text(item.find('>.dd-content .item-name').val());
    };

    // fire updateHeader when 'Name' field changes
    $('.dd').on('change', 'input.item-name', function(e) {
        updateHeader($(this).closest('.dd-item'));
    });

});
</script>
<script type="text/template" id="templateILOpen">
            <li class="dd-item dd3-item wmn-item"
                data-id="<%=itemID%>"
                data-item-name="<%=_.escape(itemName)%>"
                data-item-url-new-window="<%=itemUrlNewWindow%>"
                data-item-url-type="<%=itemUrlType%>"
                data-item-url-internal="<%=itemUrlInternal%>"
                data-item-url-external="<%=itemUrlExternal%>"
                data-item-url-file="<%=itemUrlFile%>"
            >
                <div class="dd-handle dd3-handle wmn-handle" title="<?php echo t('Move/Nest Item') ?>"><i class="fas fa-arrows-alt"></i></div>
                <div class="dd-content dd3-content wmn-content well bg-light p-3">
                    <h2 class="">
                        <span class="item-header"><%=_.escape(itemName)%></span>
                        <a class="show-hide float-end" title="<?php echo t('Click to Show/Hide fields') ?>"><i class="fas fa-chevron-down"></i></a>
                        <a class="remove-item float-end" title="<?php echo t('Click to Remove item') ?>"><i class="fas fa-times"></i></a>
                        <a class="clone-item float-end" title="<?php echo t('Click to Clone item') ?>"><i class="far fa-clone"></i></a>
                    </h2>
                    <div class="form-options" style="display:none;">
                        <div class="form-group" >
                            <label class="form-label"><?php echo t('Name'); ?></label>
                            <input class="form-control ccm-input-text item-name" type="text" name="itemName[]" value="<%=_.escape(itemName)%>" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <?php echo t('URL') ?>
                                <small class="text-muted"><?php echo t('Target (New Windows)') . " | " . t('Type') . " | " . t('URL') ?></small>
                            </label>
                            <div class="row">

                                <div class="col-2" style="padding-right:0px;">
                                    <select data-field="item-url-new-window-select" name="itemUrlNewWindow[]" class="form-control" style="">
                                        <option value="0" <% if (itemUrlNewWindow == 0) { %>selected<% } %>><?php echo t('No') ?></option>
                                        <option value="1" <% if (itemUrlNewWindow == 1) { %>selected<% } %>><?php echo t('Yes') ?></option>
                                    </select>
                                </div>
                                <div class="col-2" style="padding-right:0px;">
                                    <select data-field="item-url-type-select" name="itemUrlType[]" class="form-control" style="">
                                        <option value="" <% if (!itemUrlType) { %>selected<% } %>>[ <?php echo t('None')?> ]</option>
                                        <option value="internal" <% if (itemUrlType == 'internal') { %>selected<% } %>><?php echo t('Internal') ?></option>
                                        <option value="external" <% if (itemUrlType == 'external') { %>selected<% } %>><?php echo t('External') ?></option>
                                        <!--
                                        <option value="file" <% if (itemUrlType == 'file') { %>selected<% } %>><?php echo t('File') ?></option>
                                        -->
                                    </select>
                                </div>
                                <div class="col-8 ccm-url-container">
                                    <div style="display: none;" data-field="item-url-internal-container">
                                        <div data-field="item-url-internal-wrapper" class="item-url-internal-wrapper"></div>
                                        <a class="btn btn-sm btn-outline-secondary copy-page-title" title="<?php echo t('Copy page title to Name field') ?>"><i class="fas fa-share-square"></i></a>
                                    </div>
                                    <div style="display: none;" data-field="item-url-external-container">
                                        <input type="text" name="itemUrlExternal[]" value="<%=itemUrlExternal%>" class="form-control" placeholder="http://">
                                    </div>
                                    <div style="display: none;" data-field="item-url-file-container">
                                        <div data-field="item-url-file-wrapper" class="item-url-file-wrapper ccm-file-selector"></div>
                                        <a class="btn btn-sm btn-outline-secondary copy-file-title" title="<?php echo t('Copy file title to Name field') ?>"><i class="fas fa-share-square"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
</script>
<script type="text/template" id="templateILClose">
            </li>
</script>
<script type="text/template" id="templateOLOpen">
    <ol class="dd-list">
</script>
<script type="text/template" id="templateOLClose">
    </ol>
</script>

<style type="text/css">
/**
 * Nestable
 * wmn: whale manual nav
 */
#nestableContainer { padding-left: 0;}
.dd {  }
.dd-list .wmn-content { padding: 10px; margin-bottom: 5px; color: #555;}
.dd-list .wmn-content a { color: #555;}
.dd-list .wmn-content h2, .dd-dragl .wmn-content h2 { margin-top: 0px!important; margin-bottom: 0px!important; font-size: 15px!important; font-weight: bold; padding-left: 30px;}
.dd-list .wmn-content h2 .item-header { color: #555; display: inline-table;}
.dd-list .nest-item { cursor:ew-resize; margin-right: 5px;}
.dd-list .clone-item, .dd-list .remove-item, .dd-list .show-hide { cursor:pointer; margin-left: 5px;}
.dd-list hr, .dd-dragl hr { margin: 15px 0 15px 0!important; }

.dd-list { display: block; position: relative; margin: 0; padding: 0; list-style: none; }
.dd-list .dd-list { padding-left: 30px; }
.dd-collapsed .dd-list { display: none; }

.dd-item,
.dd-empty,
.dd-placeholder { display: block; position: relative; margin: 0; padding: 0; min-height: 20px; }

.dd-handle {
    box-sizing: border-box; -moz-box-sizing: border-box;
}
.dd-handle:hover { }

.dd-item > button { display: block; position: relative; cursor: pointer; float: left; width: 25px; height: 40px; margin: 5px 0; padding: 0; text-indent: 100%; white-space: nowrap; overflow: hidden; border: 0; background: transparent; font-size: 20px; line-height: 1; text-align: center; font-weight: normal; }
.dd-item > button:before { content: '+'; display: block; position: absolute; width: 100%; text-align: center; text-indent: 0; }
.dd-item > button[data-action="collapse"]:before { content: '-'; }

.dd-placeholder,
.dd-empty { margin: 5px 0; padding: 0; min-height: 30px; background: #f2fbff; border: 1px dashed #b6bcbf; box-sizing: border-box; -moz-box-sizing: border-box; }
.dd-empty { border: 1px dashed #bbb; min-height: 100px; background-color: #e5e5e5;
    background-size: 60px 60px;
    background-position: 0 0, 30px 30px;
}

.dd-dragel { position: absolute; pointer-events: none; z-index: 9999; }
.dd-dragel > .dd-item .dd-handle { margin-top: 0; }
.dd-dragel .dd-handle {  }

/**
 * Nestable Draggable Handles
 */

.dd3-content {
    display: block;
    box-sizing: border-box;
    -moz-box-sizing: border-box;
}
.dd3-content:hover {}

.dd-dragel > .dd3-item > .dd3-content { margin: 0; }

.dd3-item > button { margin-left: 30px; }

.dd3-handle {
    position: absolute; margin: 0; left: 0; top: 0; cursor: pointer;
    width: 30px;
    height: 50px;
    line-height: 50px;
    text-align: center;
    white-space: nowrap; overflow: hidden;
    border: 1px dashed rgba(256, 256, 256, 1);
    /*border-right: 1px solid #ddd;*/
    /*border-bottom: 1px solid #ddd;*/
    /*background: #ddd;*/
    /*border-radius: 2px 0 0 2px;*/
    font-size: 14px;
}
.dd3-handle:hover { background: #ddd; }

.dd small { font-size: 70%; }
div.form-options { margin-top: 25px; }
.item-url-internal-wrapper { margin-right: 50px!important; }
.item-url-file-wrapper { margin-right: 50px!important; }
.item-url-file-wrapper div.ccm-file-selector-file-selected-thumbnail img { max-width: 20px!important;max-height: 20px!important; }
.copy-page-title, .copy-file-title { position: absolute; top:0; right: 15px; line-height:1.75!important;}
.ccm-page-selector { margin-top:0; } /** for 5.7 selector */
.ccm-url-container { position: relative; }
.btn.copy-page-title, .btn.copy-file-title { padding: .375rem 0.75rem;}
</style>
