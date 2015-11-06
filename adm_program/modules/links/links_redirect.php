<?php
/******************************************************************************
 * Redirect to choosen weblink‚
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * lnk_id    - ID of the weblink that should be redirected
 *
 *****************************************************************************/

require_once('../../system/common.php');

// Initialize and check the parameters
$getLinkId   = admFuncVariableIsValid($_GET, 'lnk_id', 'numeric', array('requireValue' => true));

// check if the module is enabled for use
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // module is disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
if($gPreferences['enable_weblinks_module'] == 2)
{
    // avaiable only with valid login
    require('../../system/login_valid.php');
}

// read link from id
$weblink = new TableWeblink($gDb, $getLinkId);

// Wenn kein Link gefunden wurde Fehler ausgeben
if(strlen($weblink->getValue('lnk_url')) == 0
|| ($gValidLogin == false && $weblink->getValue('cat_hidden') == 1))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Wenn Link gültig ist, Counter um eine Position erhöhen
$weblink->setValue('lnk_counter', $weblink->getValue('lnk_counter') + 1);
$weblink->save();

// MR: Neue Prüfung für direkte Weiterleitung oder mit Anzeige
if ($gPreferences['weblinks_redirect_seconds'] > 0)
{
    // create html page object
    $page = new HtmlPage($gL10n->get('LNK_REDIRECT'));

    // add special header for automatic redirection after x seconds
    $page->addHeader('<meta http-equiv="refresh" content="'. $gPreferences['weblinks_redirect_seconds'].'; url='.$weblink->getValue('lnk_url').'">');

    //Counter zählt die sekunden bis zur Weiterleitung runter
    $page->addJavascript('
        function countDown(init) {
            if (init || --document.getElementById("counter").firstChild.nodeValue > 0 ) {
                window.setTimeout( "countDown()" , 1000 );
            }
        };
        countDown(true);');

    // Html des Modules ausgeben
    $page->addHtml('
    <p class="lead">'.$gL10n->get('LNK_REDIRECT_DESC', $gCurrentOrganization->getValue('org_longname'),
        '<span id="counter">'.$gPreferences['weblinks_redirect_seconds'].'</span>', '<strong>'.$weblink->getValue('lnk_name').'</strong> ('.$weblink->getValue('lnk_url').')',
        '<a href="'.$weblink->getValue('lnk_url').'" target="_self">hier</a>').'
    </p>');

    // show html of complete page
    $page->show();
}
else
{
    header('Location:'.$weblink->getValue('lnk_url'));
}
