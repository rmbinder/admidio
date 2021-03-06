<?php
/**
 ***********************************************************************************************
 * Show a list of all photo albums
 *
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * pho_id    : Id of album which photos should be shown
 * headline  : Headline of the module that will be displayed
 *             (Default) PHO_PHOTO_ALBUMS
 * start_thumbnail : Number of the thumbnail which is the first that should be shown
 * start     : Position of query recordset where the visual output should start
 * locked    : das Album soll freigegeben/gesperrt werden
 *
 *****************************************************************************/

require_once('../../system/common.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}
elseif ($gPreferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Initialize and check the parameters
$getPhotoId        = admFuncVariableIsValid($_GET, 'pho_id',          'int');
$getHeadline       = admFuncVariableIsValid($_GET, 'headline',        'string', array('defaultValue' => $gL10n->get('PHO_PHOTO_ALBUMS')));
$getStart          = admFuncVariableIsValid($_GET, 'start',           'int');
$getStartThumbnail = admFuncVariableIsValid($_GET, 'start_thumbnail', 'int', array('defaultValue' => 1));
$getLocked         = admFuncVariableIsValid($_GET, 'locked',          'int', array('defaultValue' => -1));
$getPhotoNr        = admFuncVariableIsValid($_GET, 'photo_nr',        'int');

unset($_SESSION['photo_album_request'], $_SESSION['ecard_request']);

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if (isset($_SESSION['photo_album']) && (int) $_SESSION['photo_album']->getValue('pho_id') === $getPhotoId)
{
    $photoAlbum =& $_SESSION['photo_album'];
    $photoAlbum->setDatabase($gDb);
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photoAlbum = new TablePhotos($gDb);
    if($getPhotoId > 0)
    {
        $photoAlbum->readDataById($getPhotoId);
    }

    $_SESSION['photo_album'] = $photoAlbum;
}

// set headline of module
if($getPhotoId > 0)
{
    $headline = $photoAlbum->getValue('pho_name');
}
else
{
    $headline = $getHeadline;
}

// Wurde keine Album uebergeben kann das Navigationsstack zurueckgesetzt werden
if ($getPhotoId === 0)
{
    $gNavigation->clear();
}

// URL auf Navigationstack ablegen
$gNavigation->addUrl(CURRENT_URL, $headline);

// pruefen, ob Album zur aktuellen Organisation gehoert
if($getPhotoId > 0 && (int) $photoAlbum->getValue('pho_org_id') !== (int) $gCurrentOrganization->getValue('org_id'))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    // => EXIT
}

/*********************LOCKED************************************/
// Falls gefordert und Foto-edit-rechte, aendern der Freigabe
if($getLocked === 0 || $getLocked === 1)
{
    // erst pruefen, ob der User Fotoberarbeitungsrechte hat
    if(!$gCurrentUser->editPhotoRight())
    {
        $gMessage->show($gL10n->get('PHO_NO_RIGHTS'));
        // => EXIT
    }

    $photoAlbum->setValue('pho_locked', $getLocked);
    $photoAlbum->save();

    // Zurueck zum Elternalbum
    $getPhotoId = $photoAlbum->getValue('pho_pho_id_parent');
    $photoAlbum->readDataById($getPhotoId);
}

/*********************HTML_PART*******************************/

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

// add rss feed to announcements
if($gPreferences['enable_rss'] == 1)
{
    $page->addRssFile(
        ADMIDIO_URL.FOLDER_MODULES.'/photos/rss_photos.php?headline='.$getHeadline,
        $gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline)
    );
}

if($gCurrentUser->editPhotoRight())
{
    $page->addJavascript('
        // rotate image
        function imgrotate(img, direction) {
            $.get("'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_function.php", {pho_id: '.$getPhotoId.', photo_nr: img, job: "rotate", direction: direction}, function(data) {
                // Anhängen der Zufallszahl ist nötig um den Browsercache zu überlisten
                $("#img_"+img).attr("src", "photo_show.php?pho_id='.$getPhotoId.'&photo_nr="+img+"&thumb=1&rand="+Math.random());
                return false;
            });
        }'
    );
}

// integrate bootstrap ekko lightbox addon
if($gPreferences['photo_show_mode'] == 1)
{
    $page->addCssFile('adm_program/libs/lightbox/ekko-lightbox.css');
    $page->addJavascriptFile('adm_program/libs/lightbox/ekko-lightbox.js');

    $page->addJavascript('
        $(document).delegate(
            "*[data-toggle=\"lightbox\"]",
            "click",
            function(event) {
                event.preventDefault();
                $(this).ekkoLightbox();
            }
        );',
        true
    );
}

$page->addJavascript('
    $("body").on("hidden.bs.modal", ".modal", function () { $(this).removeData("bs.modal"); location.reload(); });
    $("#menu_item_upload_photo").attr("data-toggle", "modal");
    $("#menu_item_upload_photo").attr("data-target", "#admidio_modal");
    $(".admidio-btn-album-upload").click(function(event) {
        $.get("'.ADMIDIO_URL.'/adm_program/system/file_upload.php?module=photos&id=" + $(this).attr("data-pho-id"),
            function(response) {
                $(".modal-content").html(response);
                $("#admidio_modal").modal();
            }
        );
    });
    ',
    true
);

// if a photo number was committed then simulate a left mouse click
if($getPhotoNr > 0)
{
    $page->addJavascript('$("#img_'.$getPhotoNr.'").trigger("click");', true);
}

// get module menu
$photosMenu = $page->getMenu();

if($photoAlbum->getValue('pho_id') > 0)
{
    $photosMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
}

if($gCurrentUser->editPhotoRight())
{
    // show link to create new album
    $photosMenu->addItem(
        'menu_item_new_album',
        ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_new.php?mode=new&amp;pho_id='.$getPhotoId,
        $gL10n->get('PHO_CREATE_ALBUM'),
        'add.png'
    );

    if($getPhotoId > 0)
    {
        // show link to upload photos
        $photosMenu->addItem(
            'menu_item_upload_photo',
            ADMIDIO_URL.'/adm_program/system/file_upload.php?module=photos&id='.$getPhotoId,
            $gL10n->get('PHO_UPLOAD_PHOTOS'),
            'photo_upload.png'
        );
    }
}

// show link to download photos if enabled
if($gPreferences['photo_download_enabled'] == 1 && $photoAlbum->getValue('pho_quantity') > 0)
{
    // show link to download photos
    $photosMenu->addItem(
        'menu_item_download_photos',
        ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_download.php?pho_id='.$getPhotoId,
        $gL10n->get('PHO_DOWNLOAD_PHOTOS'),
        'page_white_compressed.png'
    );
}

if($gCurrentUser->isAdministrator())
{
    // show link to system preferences of photos
    $photosMenu->addItem(
        'menu_item_preferences_photos',
        ADMIDIO_URL.FOLDER_MODULES.'/preferences/preferences.php?show_option=photos',
        $gL10n->get('SYS_MODULE_PREFERENCES'),
        'options.png',
        'right'
    );
}

// Breadcrump bauen
$navilink = '';
$phoParentId = $photoAlbum->getValue('pho_pho_id_parent');
$photoAlbum_parent = new TablePhotos($gDb);

while ($phoParentId > 0)
{
    // Einlesen des Eltern Albums
    $photoAlbum_parent->readDataById($phoParentId);

    // Link zusammensetzen
    $navilink = '<li><a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$photoAlbum_parent->getValue('pho_id').'">'.
        $photoAlbum_parent->getValue('pho_name').'</a></li>'.$navilink;

    // Elternveranst
    $phoParentId = $photoAlbum_parent->getValue('pho_pho_id_parent');
}

if($getPhotoId > 0)
{
    // Ausgabe des Linkpfads
    $page->addHtml('
        <ol class="breadcrumb">
            <li>
                <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php"><img src="'. THEME_URL. '/icons/application_view_tile.png" alt="'.$gL10n->get('PHO_PHOTO_ALBUMS').'" /></a>
                <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php">'.$gL10n->get('PHO_PHOTO_ALBUMS').'</a>
            </li>'.
            $navilink.'&nbsp;&gt;&nbsp;'.$photoAlbum->getValue('pho_name').'
        </ol>
    ');
}

/*************************THUMBNAILS**********************************/
// Nur wenn uebergebenes Album Bilder enthaelt
if($photoAlbum->getValue('pho_quantity') > 0)
{
    $photoThumbnailTable = '';
    $firstPhotoNr        = 1;
    $lastPhotoNr         = $gPreferences['photo_thumbs_page'];

    // Wenn Bild übergeben wurde richtige Albenseite öffnen
    if($getPhotoNr > 0)
    {
        $firstPhotoNr = (round(($getPhotoNr - 1) / $gPreferences['photo_thumbs_page'], 0) * $gPreferences['photo_thumbs_page']) + 1;
        $lastPhotoNr  = $firstPhotoNr + $gPreferences['photo_thumbs_page'] - 1;
    }

    // create thumbnail container
    $page->addHtml('<div class="row album-container">');

    for($actThumbnail = $firstPhotoNr; $actThumbnail <= $lastPhotoNr && $actThumbnail <= $photoAlbum->getValue('pho_quantity'); ++$actThumbnail)
    {
        if($actThumbnail <= $photoAlbum->getValue('pho_quantity'))
        {
            $photoThumbnailTable .= '<div class="col-sm-6 col-md-3 admidio-album-thumbnail" id="div_image_'.$actThumbnail.'">';

                // Popup window
                if ($gPreferences['photo_show_mode'] == 0)
                {
                    $photoThumbnailTable .= '
                        <img class="thumbnail center-block" id="img_'.$actThumbnail.'" style="cursor: pointer"
                            onclick="window.open(\''.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_presenter.php?photo_nr='.$actThumbnail.'&amp;pho_id='.$getPhotoId.'\',\'msg\', \'height='.($gPreferences['photo_show_height']+210).', width='.($gPreferences['photo_show_width']+70).',left=162,top=5\')"
                            src="photo_show.php?pho_id='.$getPhotoId.'&photo_nr='.$actThumbnail.'&thumb=1" alt="'.$actThumbnail.'" />';
                }

                // Bootstrap modal with lightbox
                elseif ($gPreferences['photo_show_mode'] == 1)
                {
                    $photoThumbnailTable .= '
                        <a data-gallery="admidio-gallery" data-type="image" data-parent=".album-container" data-toggle="lightbox" data-title="'.$headline.'"
                            href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$actThumbnail.'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'].'"><img
                            class="center-block thumbnail" id="img_'.$actThumbnail.'" src="photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$actThumbnail.'&amp;thumb=1" alt="'.$actThumbnail.'" /></a>';
                }

                // Same window
                elseif ($gPreferences['photo_show_mode'] == 2)
                {
                    $photoThumbnailTable .= '
                        <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_presenter.php?photo_nr='.$actThumbnail.'&amp;pho_id='.$getPhotoId.'"><img
                            class="thumbnail center-block" id="img_'.$actThumbnail.'" src="photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$actThumbnail.'&amp;thumb=1" />
                        </a>';
                }

                if($gCurrentUser->editPhotoRight() || ($gValidLogin && $gPreferences['enable_ecard_module'] == 1) || $gPreferences['photo_download_enabled'] == 1)
                {
                    $photoThumbnailTable .= '<div class="text-center" id="image_preferences_'.$actThumbnail.'">';
                }

                // Buttons fuer Moderatoren
                if($gCurrentUser->editPhotoRight())
                {
                    $photoThumbnailTable .= '
                        <a class="admidio-icon-link"  href="javascript:void(0)" onclick="return imgrotate('.$actThumbnail.', \'left\')"><img
                            src="'. THEME_URL. '/icons/arrow_turn_left.png" alt="'.$gL10n->get('PHO_PHOTO_ROTATE_LEFT').'" title="'.$gL10n->get('PHO_PHOTO_ROTATE_LEFT').'" /></a>
                        <a class="admidio-icon-link" href="javascript:void(0)" onclick="return imgrotate('.$actThumbnail.', \'right\')"><img
                            src="'. THEME_URL. '/icons/arrow_turn_right.png" alt="'.$gL10n->get('PHO_PHOTO_ROTATE_RIGHT').'" title="'.$gL10n->get('PHO_PHOTO_ROTATE_RIGHT').'" /></a>
                        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                            href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=pho&amp;element_id=div_image_'.
                            $actThumbnail.'&amp;database_id='.$actThumbnail.'&amp;database_id_2='.$getPhotoId.'"><img
                            src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                }

                if($gValidLogin && $gPreferences['enable_ecard_module'] == 1)
                {
                    $photoThumbnailTable .= '
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/ecards/ecards.php?photo_nr='.$actThumbnail.'&amp;pho_id='.$getPhotoId.'&amp;show_page='.$getPhotoNr.'"><img
                            src="'. THEME_URL. '/icons/ecard.png" alt="'.$gL10n->get('PHO_PHOTO_SEND_ECARD').'" title="'.$gL10n->get('PHO_PHOTO_SEND_ECARD').'" /></a>';
                }

                if($gPreferences['photo_download_enabled'] == 1)
                {
                    // show link to download photo
                    $photoThumbnailTable .= '
                        <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_download.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$actThumbnail.'"><img
                            src="'. THEME_URL. '/icons/disk.png" alt="'.$gL10n->get('PHO_DOWNLOAD_SINGLE_PHOTO').'" title="'.$gL10n->get('PHO_DOWNLOAD_SINGLE_PHOTO').'"  /></a>';
                }

                if($gCurrentUser->editPhotoRight() || ($gValidLogin && $gPreferences['enable_ecard_module'] == 1) || $gPreferences['photo_download_enabled'] == 1)
                {
                    $photoThumbnailTable .= '</div>';
                }
            $photoThumbnailTable .= '</div>';
        }
    }

    // the lightbox should be able to go through the whole album, therefore we must
    // integrate links to the photos of the album pages to this page and container but hidden
    if ($gPreferences['photo_show_mode'] == 1)
    {
        $photoThumbnailTable_shown = false;

        for ($hiddenPhotoNr = 1; $hiddenPhotoNr <= $photoAlbum->getValue('pho_quantity'); ++$hiddenPhotoNr)
        {
            if($hiddenPhotoNr >= $firstPhotoNr && $hiddenPhotoNr <= $actThumbnail)
            {
                if(!$photoThumbnailTable_shown)
                {
                    $page->addHtml($photoThumbnailTable);
                    $photoThumbnailTable_shown = true;
                }
            }
            else
            {
                $page->addHtml('
                    <a class="hidden" data-gallery="admidio-gallery" data-type="image" data-toggle="lightbox" data-title="'.$headline.'"
                        href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php?pho_id='.$getPhotoId.'&amp;photo_nr='.$hiddenPhotoNr.'&amp;max_width='.$gPreferences['photo_show_width'].'&amp;max_height='.$gPreferences['photo_show_height'].'">&nbsp;</a>
                ');
            }
        }
        $page->addHtml('</div>');   // close album-container
    }
    else
    {
        // show photos if lightbox is not used
        $photoThumbnailTable .= '</div>';   // close album-container
        $page->addHtml($photoThumbnailTable);
    }

    // show additional album information
    $datePeriod = $photoAlbum->getValue('pho_begin', $gPreferences['system_date']);

    if($photoAlbum->getValue('pho_end') !== $photoAlbum->getValue('pho_begin') && strlen($photoAlbum->getValue('pho_end')) > 0)
    {
        $datePeriod .= ' '.$gL10n->get('SYS_DATE_TO').' '.$photoAlbum->getValue('pho_end', $gPreferences['system_date']);
    }

    $page->addHtml('
        <br />
        <div class="row">
            <div class="col-sm-2 col-xs-4">'.$gL10n->get('SYS_DATE').'</div>
            <div class="col-sm-4 col-xs-8"><strong>'.$datePeriod.'</strong></div>
        </div>
        <div class="row">
            <div class="col-sm-2 col-xs-4">'.$gL10n->get('PHO_PHOTOGRAPHER').'</div>
            <div class="col-sm-4 col-xs-8"><strong>'.$photoAlbum->getValue('pho_photographers').'</strong></div>
        </div>
    ');

    // show information about user who creates the recordset and changed it
    $page->addHtml(admFuncShowCreateChangeInfoById(
        $photoAlbum->getValue('pho_usr_id_create'),
        $photoAlbum->getValue('pho_timestamp_create'),
        $photoAlbum->getValue('pho_usr_id_change'),
        $photoAlbum->getValue('pho_timestamp_change')
    ));

    // show page navigations through thumbnails
    $page->addHtml(admFuncGeneratePagination(
        ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$photoAlbum->getValue('pho_id'),
        $photoAlbum->getValue('pho_quantity'),
        $gPreferences['photo_thumbs_page'],
        $getPhotoNr,
        true,
        'photo_nr'
    ));

}
/************************Albumliste*************************************/

// erfassen der Alben die in der Albentabelle ausgegeben werden sollen
$sql = 'SELECT *
          FROM '.TBL_PHOTOS.'
         WHERE pho_org_id = '.$gCurrentOrganization->getValue('org_id');
if($getPhotoId === 0)
{
    $sql .= ' AND (pho_pho_id_parent IS NULL) ';
}
if($getPhotoId > 0)
{
    $sql .= ' AND pho_pho_id_parent = '.$getPhotoId.'';
}
if(!$gCurrentUser->editPhotoRight())
{
    $sql .= ' AND pho_locked = 0 ';
}

$sql .= ' ORDER BY pho_begin DESC';

$albumStatement = $gDb->query($sql);
$albumList      = $albumStatement->fetchAll();

// Gesamtzahl der auszugebenden Alben
$albumsCount = $albumStatement->rowCount();

// falls zum aktuellen Album Fotos und Unteralben existieren,
// dann einen Trennstrich zeichnen
if($albumsCount > 0 && $photoAlbum->getValue('pho_quantity') > 0)
{
    $page->addHtml('<hr />');
}

$childPhotoAlbum = new TablePhotos($gDb);

$page->addHtml('<div class="row">');

for($x = $getStart; $x <= $getStart + $gPreferences['photo_albums_per_page'] - 1 && $x < $albumsCount; ++$x)
{
    // Daten in ein Photo-Objekt uebertragen
    $childPhotoAlbum->clear();
    $childPhotoAlbum->setArray($albumList[$x]);

    // folder of the album
    $ordner = ADMIDIO_PATH . FOLDER_DATA . '/photos/' . $childPhotoAlbum->getValue('pho_begin', 'Y-m-d') . '_' . $childPhotoAlbum->getValue('pho_id');

    // show album if album is not locked or it has child albums or the user has the photo module edit right
    if((is_dir($ordner) && $childPhotoAlbum->getValue('pho_locked') == 0)
    || $childPhotoAlbum->hasChildAlbums() || $gCurrentUser->editPhotoRight())
    {
        // Zufallsbild fuer die Vorschau ermitteln
        $shuffle_image = $childPhotoAlbum->shuffleImage();

        // Album angaben
        if(is_dir($ordner) || $childPhotoAlbum->hasChildAlbums())
        {
            $albumTitle = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'">'.$childPhotoAlbum->getValue('pho_name').'</a><br />';
        }
        else
        {
            $albumTitle = $childPhotoAlbum->getValue('pho_name');
        }

        $albumDate = $childPhotoAlbum->getValue('pho_begin', $gPreferences['system_date']);
        if($childPhotoAlbum->getValue('pho_end') !== $childPhotoAlbum->getValue('pho_begin'))
        {
            $albumDate .= ' '.$gL10n->get('SYS_DATE_TO').' '.$childPhotoAlbum->getValue('pho_end', $gPreferences['system_date']);
        }

        $page->addHtml('
            <div class="col-sm-6 admidio-album-card" id="panel_pho_'.$childPhotoAlbum->getValue('pho_id').'">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <div class="pull-left"><h4 class="panel-title">'.$albumTitle.'</h4></div>
                        <div class="pull-right text-right">
        ');

        // check if download option is enabled
        if($gPreferences['photo_download_enabled'] == 1 && $childPhotoAlbum->getValue('pho_quantity') > 0)
        {
            $page->addHtml('
                <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_download.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'"><img
                        src="'. THEME_URL. '/icons/page_white_compressed.png" alt="'.$gL10n->get('PHO_DOWNLOAD_PHOTOS').'" title="'.$gL10n->get('PHO_DOWNLOAD_PHOTOS').'"  /></a>
            ');
        }

        // if user has admin rights for photo module then show some functions
        if ($gCurrentUser->editPhotoRight())
        {
            $page->addHtml('
                <a class="admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_album_new.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'&amp;mode=change"><img
                    src="'. THEME_URL. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
                <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                    href="'.ADMIDIO_URL.'/adm_program/system/popup_message.php?type=pho_album&amp;element_id=panel_pho_'.
                    $childPhotoAlbum->getValue('pho_id').'&amp;name='.urlencode($childPhotoAlbum->getValue('pho_name')).'&amp;database_id='.$childPhotoAlbum->getValue('pho_id').'"><img
                    src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>
            ');
        }

        $page->addHtml('
                </div>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-xs-12 col-sm-12 col-md-6 admidio-album-card-preview">
                        <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'"><img
                            class="thumbnail" src="'.ADMIDIO_URL.FOLDER_MODULES.'/photos/photo_show.php?pho_id='.$shuffle_image['shuffle_pho_id'].'&amp;photo_nr='.$shuffle_image['shuffle_img_nr'].'&amp;thumb=1" alt="'.$gL10n->get('PHO_PHOTOS').'" /></a>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-6 admidio-album-card-description">
        ');

        $form = new HtmlForm('form_album_'.$childPhotoAlbum->getValue('pho_id'), null, $page, array('type' => 'vertical'));
        $form->addStaticControl('pho_date', $gL10n->get('SYS_DATE'), $albumDate);
        $form->addStaticControl('pho_count', $gL10n->get('SYS_PHOTOS'), $childPhotoAlbum->countImages());
        if(strlen($childPhotoAlbum->getValue('pho_photographers')) > 0)
        {
            $form->addStaticControl('pho_photographer', $gL10n->get('PHO_PHOTOGRAPHER'), $childPhotoAlbum->getValue('pho_photographers'));
        }
        $page->addHtml($form->show(false));
        $page->addHtml('</div></div>');

        // Notice for users with foto edit rights that the folder of the album doesn't exists
        if(!is_dir($ordner) && !$childPhotoAlbum->hasChildAlbums() && $gCurrentUser->editPhotoRight())
        {
            $page->addHtml('<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('PHO_FOLDER_NOT_FOUND').'</div>');
        }

        // Notice for users with foto edit right that this album is locked
        if($childPhotoAlbum->getValue('pho_locked') == 1 && is_dir($ordner))
        {
            $page->addHtml('<div class="alert alert-warning alert-small" role="alert"><span class="glyphicon glyphicon-warning-sign"></span>'.$gL10n->get('PHO_ALBUM_NOT_APPROVED').'</div>');
        }

        // if user has admin rights for photo module then show some functions
        if ($gCurrentUser->editPhotoRight())
        {
            $page->addHtml('
                <div class="btn-group" role="group" style="width: 100%;">
                    <button class="btn btn-default admidio-btn-album-upload" style="width: 50%;"
                        data-pho-id="'.$childPhotoAlbum->getValue('pho_id').'" data-toggle="modal" data-target="#admidio_modal"><img
                        src="'. THEME_URL. '/icons/photo_upload.png" alt="'.$gL10n->get('PHO_UPLOAD_PHOTOS').'" />'.$gL10n->get('PHO_UPLOAD_PHOTOS').'
                    </button>
            ');

            if($childPhotoAlbum->getValue('pho_locked') == 1)
            {
                $page->addHtml('
                    <button class="btn btn-default" style="width: 50%;" onclick="window.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'&amp;locked=0\'"><img
                        src="'. THEME_URL. '/icons/key.png"  alt="'.$gL10n->get('PHO_ALBUM_UNLOCK').'" />'.$gL10n->get('PHO_ALBUM_UNLOCK').'</button>
                ');
            }
            elseif($childPhotoAlbum->getValue('pho_locked') == 0)
            {
                $page->addHtml('
                    <button class="btn btn-default" style="width: 50%;" onclick="window.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$childPhotoAlbum->getValue('pho_id').'&amp;locked=1\'"><img
                        src="'. THEME_URL. '/icons/key.png" alt="'.$gL10n->get('PHO_ALBUM_LOCK').'" />'.$gL10n->get('PHO_ALBUM_LOCK').'</button>
                ');
            }

            $page->addHtml('</div>');
        }

        $page->addHtml('</div></div></div>');
    }//Ende wenn Ordner existiert
}//for

$page->addHtml('</div>');

/****************************Leeres Album****************/
// Falls das Album weder Fotos noch Unterordner enthaelt
if($albumsCount === 0 && ($photoAlbum->getValue('pho_quantity') == 0 || strlen($photoAlbum->getValue('pho_quantity')) === 0))  // alle vorhandenen Albumen werden ignoriert
{
    $page->addHtml($gL10n->get('PHO_NO_ALBUM_CONTENT'));
}

// If necessary show links to navigate to next and previous albums of the query
$base_url = ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php?pho_id='.$getPhotoId;
$page->addHtml(admFuncGeneratePagination($base_url, $albumsCount, $gPreferences['photo_albums_per_page'], $getStart, true));

// show html of complete page
$page->show();
