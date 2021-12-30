<?php
/**
 ***********************************************************************************************
 * Various functions for roles handling
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * role_uuid : UUID of role, that should be edited
 * mode :  2 - create or edit role
 *         3 - set role inaktive
 *         4 - delete role
 *         5 - set role active
 *
 *****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getRoleUuid = admFuncVariableIsValid($_GET, 'role_uuid', 'string');
$getMode     = admFuncVariableIsValid($_GET, 'mode', 'int', array('requireValue' => true));

// only members who are allowed to create and edit roles should have access to
// most of these functions
if (!$gCurrentUser->manageRoles()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

try {
    // check the CSRF token of the form against the session token
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token']);
} catch (AdmException $exception) {
    if ($getMode === 2) {
        $exception->showHtml();
    } else {
        $exception->showText();
    }
    // => EXIT
}

$eventRole = false;
$role = new TableRoles($gDb);

if ($getRoleUuid !== '') {
    $role->readDataByUuid($getRoleUuid);
    $eventRole = $role->getValue('cat_name_intern') === 'EVENTS';

    // Check if the role belongs to the current organization
    if ((int) $role->getValue('cat_org_id') !== $gCurrentOrgId && $role->getValue('cat_org_id') > 0) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

$_SESSION['roles_request'] = $_POST;
$rolName = $role->getValue('rol_name');

if ($getMode === 2) {
    // create or edit role

    if (!array_key_exists('rol_name', $_POST) || $_POST['rol_name'] === '') {
        // not all fields are filled
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME'))));
        // => EXIT
    }
    if ((int) $_POST['rol_cat_id'] === 0) {
        // not all fields are filled
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_CATEGORY'))));
        // => EXIT
    }

    if ($rolName !== $_POST['rol_name']) {
        // check if the role already exists
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_name   = ? -- $_POST[\'rol_name\']
                   AND rol_cat_id = ? -- $_POST[\'rol_cat_id\']
                   AND rol_id    <> ? -- $role->getValue(\'rol_id\')
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )';
        $queryParams = array(
            $_POST['rol_name'],
            (int) $_POST['rol_cat_id'],
            $role->getValue('rol_id'),
            $gCurrentOrgId
        );
        $pdoStatement = $gDb->queryPrepared($sql, $queryParams);

        if ($pdoStatement->fetchColumn() > 0) {
            $gMessage->show($gL10n->get('SYS_ROLE_NAME_EXISTS'));
            // => EXIT
        }
    }

    // Administrator role need some more flags
    if ($role->getValue('rol_administrator') == 1) {
        $_POST['rol_name']           = $role->getValue('rol_name');
        $_POST['rol_assign_roles']   = 1;
        $_POST['rol_all_lists_view'] = 1;
    }

    if ($eventRole) {
        $_POST['rol_name']        = $role->getValue('rol_name');
        $_POST['rol_description'] = $role->getValue('rol_description');
        $_POST['rol_cat_id']      = $role->getValue('rol_cat_id');
        $_POST['rol_start_date']  = '';
        $_POST['rol_start_time']  = '';
        $_POST['rol_end_date']    = '';
        $_POST['rol_end_time']    = '';
        $_POST['rol_max_members'] = '';
    }

    // bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
    // falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird

    $checkboxes = array(
        'rol_assign_roles',
        'rol_approve_users',
        'rol_announcements',
        'rol_dates',
        'rol_default_registration',
        'rol_photo',
        'rol_documents_files',
        'rol_guestbook',
        'rol_guestbook_comments',
        'rol_edit_user',
        'rol_weblinks',
        'rol_all_lists_view',
        'rol_mail_to_all',
        'rol_profile'
    );

    foreach ($checkboxes as $value) {
        // initialize the roles rights if value not set or not = 1 or its a event role
        if (!isset($_POST[$value]) || $_POST[$value] != 1 || $eventRole) {
            $_POST[$value] = 0;
        }
    }

    // ------------------------------------------------
    // Check valid format of date input
    // ------------------------------------------------

    $validFromDate = '';
    $validToDate   = '';

    if (strlen($_POST['rol_start_date']) > 0) {
        $validFromDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $_POST['rol_start_date']);
        if (!$validFromDate) {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_VALID_FROM'), $gSettingsManager->getString('system_date'))));
        // => EXIT
        } else {
            // now write date and time with database format to date object
            $_POST['rol_start_date'] = $validFromDate->format('Y-m-d');
        }
    }

    if (strlen($_POST['rol_end_date']) > 0) {
        $validToDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $_POST['rol_end_date']);
        if (!$validToDate) {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', array($gL10n->get('SYS_VALID_TO'), $gSettingsManager->getString('system_date'))));
        // => EXIT
        } else {
            // now write date and time with database format to date object
            $_POST['rol_end_date'] = $validToDate->format('Y-m-d');
        }
    }

    // DateTo should be greater than DateFrom (Timestamp must be less)
    if (strlen($_POST['rol_start_date']) > 0 && strlen($_POST['rol_end_date']) > 0) {
        if ($validFromDate > $validToDate) {
            $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
            // => EXIT
        }
    }

    // ------------------------------------------------
    // Check valid format of time input
    // ------------------------------------------------

    if (strlen($_POST['rol_start_time']) > 0) {
        $validFromTime = \DateTime::createFromFormat('Y-m-d '.$gSettingsManager->getString('system_time'), DATE_NOW.' '.$_POST['rol_start_time']);
        if (!$validFromTime) {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', array($gL10n->get('ROL_TIME_FROM'), $gSettingsManager->getString('system_time'))));
        // => EXIT
        } else {
            // now write date and time with database format to date object
            $_POST['rol_start_time'] = $validFromTime->format('H:i:s');
        }
    }

    if (strlen($_POST['rol_end_time']) > 0) {
        $validToTime = \DateTime::createFromFormat('Y-m-d '.$gSettingsManager->getString('system_time'), DATE_NOW.' '.$_POST['rol_end_time']);
        if (!$validToTime) {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', array($gL10n->get('ROL_TIME_TO'), $gSettingsManager->getString('system_time'))));
        // => EXIT
        } else {
            // now write date and time with database format to date object
            $_POST['rol_end_time'] = $validToTime->format('H:i:s');
        }
    }

    // Kontrollieren ob bei nachtraeglicher Senkung der maximalen Mitgliederzahl diese nicht bereits ueberschritten wurde
    if ($getRoleUuid !== '' && (int) $_POST['rol_max_members'] !== (int) $role->getValue('rol_max_members')) {
        // Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
        $role->setValue('rol_max_members', (int) $_POST['rol_max_members']);
        $numFreePlaces = $role->countVacancies();

        if ($numFreePlaces < 0) {
            $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', array($rolName)));
            // => EXIT
        }
    }

    try {
        // POST Variablen in das Role-Objekt schreiben
        foreach ($_POST as $key => $value) { // TODO possible security issue
            if (str_starts_with($key, 'rol_')) {
                $role->setValue($key, $value);
            }
        }
    } catch (AdmException $e) {
        $e->showHtml();
    }

    $gDb->startTransaction();
    $role->save();

    // save role dependencies in database
    if (array_key_exists('dependent_roles', $_POST) && !$eventRole) {
        $sentChildRoles = array_map('intval', $_POST['dependent_roles']);

        $roleDep = new RoleDependency($gDb);

        // Fetches a list of the selected dependent roles
        $dbChildRoles = RoleDependency::getChildRoles($gDb, $role->getValue('rol_id'));

        // remove all roles that are no longer selected
        if (count($dbChildRoles) > 0) {
            foreach ($dbChildRoles as $dbChildRole) {
                if (!in_array($dbChildRole, $sentChildRoles, true)) {
                    $roleDep->get($dbChildRole, $role->getValue('rol_id'));
                    $roleDep->delete();
                }
            }
        }

        // add all new role dependencies to database
        if (count($sentChildRoles) > 0) {
            foreach ($sentChildRoles as $sentChildRole) {
                if ($sentChildRole > 0 && !in_array($sentChildRole, $dbChildRoles, true)) {
                    $roleDep->clear();
                    $roleDep->setChild($sentChildRole);
                    $roleDep->setParent($role->getValue('rol_id'));
                    $roleDep->insert($gCurrentUserId);

                    // füge alle Mitglieder der ChildRole der ParentRole zu
                    $roleDep->updateMembership();
                }
            }
        }
    } else {
        RoleDependency::removeChildRoles($gDb, $role->getValue('rol_id'));
    }

    $gDb->endTransaction();

    $gNavigation->deleteLastUrl();
    unset($_SESSION['roles_request']);

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
// => EXIT
} elseif ($getMode === 3) { // set role inactive
    // event roles should not set inactive
    // all other roles could now set inactive
    if (!$eventRole && $role->setInactive()) {
        echo 'done';
    } else {
        echo $gL10n->get('SYS_NO_RIGHTS');
    }
    exit();
} elseif ($getMode === 4) {
    // delete role from database
    try {
        if ($role->delete()) {
            echo 'done';
        }
    } catch (AdmException $e) {
        $e->showText();
        // => EXIT
    }
    exit();
} elseif ($getMode === 5) { // set role active
    // event roles should not set active
    // all other roles could now set active
    if (!$eventRole && $role->setActive()) {
        echo 'done';
    } else {
        $gL10n->get('SYS_NO_RIGHTS');
    }
    exit();
}
