<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the groups and roles module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleGroupsRoles('admidio-groups-roles', $headline);
 * $page->createContentRegistrationList();
 * $page->show();
 * ```
 */
class ModuleGroupsRoles extends HtmlPage
{

    /**
     * @var array Plain text of email
     */
    protected $data = array();
    /**
     * @var int Type of the role e.g. ROLE_TYPE_INACTIVE, ROLE_TYPE_ACTIVE, ROLE_TYPE_EVENT_PARTICIPATION
     */
    public const ROLE_TYPE_INACTIVE = 0;
    public const ROLE_TYPE_ACTIVE = 1;
    public const ROLE_TYPE_EVENT_PARTICIPATION = 2;
    protected $roleType;

    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct(string $id, string $headline = '')
    {
        parent::__construct($id, $headline);
    }

    /**
     * Returns the number of roles that where read in this class.
     * @return int Returns the number of roles
     */
    public function countRoles(): int
    {
        return count($this->data);
    }

    /**
     * Show all roles of the organization in card view. The roles must be read before with the method readData.
     * The cards will show various functions like activate, deactivate, vcard export, edit or delete. Also, the
     * role information e.g. description, start and end date, number of active and former members. A button with
     * the link to the default list will be shown.
     * @throws SmartyException|AdmException
     */
    public function createContentCards()
    {
        global $gSettingsManager, $gCurrentUser, $gL10n, $gDb;

        $templateData = array();

        foreach($this->data as $row) {
            $role = new TableRoles($gDb);
            $role->setArray($row);

            $templateRow = array();
            $templateRow['category'] = $row['cat_name'];
            $templateRow['id'] = 'role_'.$row['rol_uuid'];
            $templateRow['title'] = $row['rol_name'];

            // send a mail to all role members
            if ($gCurrentUser->hasRightSendMailToRole($row['rol_id']) && $gSettingsManager->getBool('enable_mail_module')) {
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php', array('role_uuid' => $row['rol_uuid'])),
                    'icon' => 'fas fa-envelope',
                    'tooltip' => $gL10n->get('SYS_EMAIL_TO_MEMBERS')
                );
            }

            // show link to export vCard if user is allowed to see members and the role has members
            if ($row['num_members'] > 0 || $row['num_leader'] > 0) {
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_function.php', array('mode' => '6', 'role_uuid' => $row['rol_uuid'])),
                    'icon' => 'fas fa-download',
                    'tooltip' => $gL10n->get('SYS_EXPORT_VCARD_FROM_VAR', array($row['rol_name']))
                    );
            }

            // link to assign or remove members if you are allowed to do it
            if ($role->allowedToAssignMembers($gCurrentUser)) {
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('role_uuid' => $row['rol_uuid'])),
                    'icon' => 'fas fa-user-plus',
                    'tooltip' => $gL10n->get('SYS_ASSIGN_MEMBERS')
                );
            }

            if ($gCurrentUser->manageRoles()) {
                // set role active or inactive
                if ($this->roleType === ModuleGroupsRoles::ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
                    $templateRow['actions'][] = array(
                        'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'role_'.$row['rol_uuid'], 'name' => $row['rol_name'], 'database_id' => $row['rol_uuid'])),
                        'icon' => 'fas fa-user-check',
                        'tooltip' => $gL10n->get('SYS_ACTIVATE_ROLE')
                    );
                } elseif ($this->roleType === ModuleGroupsRoles::ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
                    $templateRow['actions'][] = array(
                        'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'role_'.$row['rol_uuid'], 'name' => $row['rol_name'], 'database_id' => $row['rol_uuid'])),
                        'icon' => 'fas fa-user-slash',
                        'tooltip' => $gL10n->get('SYS_DEACTIVATE_ROLE')
                    );
                }

                // edit roles of you are allowed to assign roles
                $templateRow['actions'][] = array(
                    'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $row['rol_uuid'])),
                    'icon' => 'fas fa-edit',
                    'tooltip' => $gL10n->get('SYS_EDIT_ROLE')
                );
                $templateRow['actions'][] = array(
                    'dataHref' => SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'role_'.$row['rol_uuid'], 'name' => $row['rol_name'], 'database_id' => $row['rol_uuid'])),
                    'icon' => 'fas fa-trash-alt',
                    'tooltip' => $gL10n->get('SYS_DELETE_ROLE')
                );
            }

            if (!empty($role->getValue('rol_description'))) {
                $roleDescription = strip_tags($role->getValue('rol_description'));

                if (strlen($roleDescription) > 200) {
                    // read first 200 chars of text, then search for last space and cut the text there. After that add a "more" link
                    $textPrev = substr($roleDescription, 0, 200);
                    $maxPosPrev = strrpos($textPrev, ' ');
                    $roleDescription = substr($textPrev, 0, $maxPosPrev).
                        ' <span class="collapse" id="viewdetails-'.$row['rol_uuid'].'">'.substr($roleDescription, $maxPosPrev).'.
                                </span> <a class="admidio-icon-link" data-toggle="collapse" data-target="#viewdetails-'.$row['rol_uuid'].'"><i class="fas fa-angle-double-right" data-toggle="tooltip" title="'.$gL10n->get('SYS_MORE').'"></i></a>';
                }

                $templateRow['information'][] = $roleDescription;
            }

            // block with information about events and meeting-point
            if (!empty($role->getValue('rol_start_date')) || $role->getValue('rol_weekday') > 0
                || !empty($role->getValue('rol_start_time')) || !empty($role->getValue('rol_location'))) {
                $html = '<h6>'.$gL10n->get('SYS_APPOINTMENTS').' / '.$gL10n->get('SYS_MEETINGS').'</h6>';
                if ($role->getValue('rol_start_date') !== '') {
                    $html .= '<span class="d-block">'.$gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))).'</span>';
                }

                if ($role->getValue('rol_weekday') > 0 || !empty($role->getValue('rol_start_time'))) {
                    if ($role->getValue('rol_weekday') > 0) {
                        $html .= DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                    }
                    if (!empty($role->getValue('rol_start_time'))) {
                        $html .= $gL10n->get('SYS_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
                    }
                    $html = '<span class="d-block">'.$html.'</span>';
                }

                // Meeting point
                if (!empty($role->getValue('rol_location'))) {
                    $html .= '<span class="d-block"><i class="fas fa-map-marker-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_LOCATION').'"></i> '. $role->getValue('rol_location').'</span>';
                }
                $templateRow['information'][] = $html;
            }

            // show members fee
            if (!empty($role->getValue('rol_cost')) || $role->getValue('rol_cost_period') > 0) {
                $html = '';

                // Member fee
                if (!empty($role->getValue('rol_cost'))) {
                    $html .= (float) $role->getValue('rol_cost').' '.$gSettingsManager->getString('system_currency');
                }

                // Contributory period
                if (!empty($role->getValue('rol_cost_period')) && $role->getValue('rol_cost_period') != 0) {
                    $html .= ' - ' . TableRoles::getCostPeriods($role->getValue('rol_cost_period'));
                }

                $templateRow['information'][] = '<h6>' . $gL10n->get('SYS_CONTRIBUTION') . '</h6><span class="d-block">' . $html . '</span></li>';
            }

            // show count of members and leaders of this role
            $html = '';
            $htmlLeader = '';

            if ($role->getValue('rol_max_members') > 0) {
                $html .= $gL10n->get('SYS_MAX_PARTICIPANTS_OF_ROLE', array((int) $row['num_members'], (int) $role->getValue('rol_max_members')));
            } else {
                $html .= $row['num_members'] . ' ' . $gL10n->get('SYS_PARTICIPANTS');
            }

            if ($gCurrentUser->hasRightViewFormerRolesMembers($row['rol_id']) && $this->roleType === $this::ROLE_TYPE_ACTIVE && $row['num_former'] > 0) {
                // show former members
                if ($row['num_former'] == 1) {
                    $textFormerMembers = $gL10n->get('SYS_FORMER');
                } else {
                    $textFormerMembers = $gL10n->get('SYS_FORMER_PL');
                }

                $html .= '&nbsp;&nbsp;(<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => $row['rol_id'], 'show_former_members' => 1)) . '">'.$row['num_former'].' '.$textFormerMembers.'</a>) ';
            }

            if ($row['num_leader'] > 0) {
                $htmlLeader = '<span class="d-block">' . $row['num_leader'] . ' ' . $gL10n->get('SYS_LEADERS') . '</span>';
            }
            $templateRow['information'][] = '<span class="d-block">' . $html . '</span>' . $htmlLeader;

            $templateRow['buttons'][] = array(
                'url' => SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('rol_ids' => $row['rol_id'])),
                'name' => $gL10n->get('SYS_SHOW_MEMBER_LIST')
            );

            $templateData[] = $templateRow;
        }

        $this->assign('cards', $templateData);
        $this->assign('l10n', $gL10n);
        $this->pageContent .= $this->fetch('modules/groups-roles.cards.tpl');
    }

    /**
     * Show all roles of the organization in card view. The roles must be read before with the method readData.
     * The cards will show various functions like activate, deactivate, vcard export, edit or delete. Also, the
     * role information e.g. description, start and end date, number of active and former members. A button with
     * the link to the default list will be shown.
     * @throws SmartyException|AdmException
     */
    public function createContentPermissionsList()
    {
        global $gSettingsManager, $gCurrentUser, $gL10n, $gDb;

        $templateData = array();

        foreach($this->data as $row) {
            $role = new TableRoles($gDb);
            $role->setArray($row);

            $templateRow = array();
            $templateRow['category'] = $row['cat_name'];
            $templateRow['categoryOrder'] = $row['cat_sequence'];
            $templateRow['role'] = $row['rol_name'];
            $templateRow['roleUrl'] = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $row['rol_uuid']));
/*
            $assignRoles        = '';
            $listView           = '';
            $linkAdministration = '';

            // Add data to role object
            $role->setArray($row);

            if ($role->getValue('rol_assign_roles') == 1) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-user-tie" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_ASSIGN_ROLES').'"></i>';
            }
            if ($role->getValue('rol_all_lists_view') == 1) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-list" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW').'"></i>';
            }
            if ($role->getValue('rol_approve_users') == 1) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-address-card" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_APPROVE_USERS').'"></i>';
            }
            if ($role->getValue('rol_mail_to_all') == 1) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-envelope" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_MAIL_TO_ALL').'"></i>';
            }
            if ($role->getValue('rol_edit_user') == 1) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-user-friends" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_EDIT_USER').'"></i>';
            }
            if ($role->getValue('rol_profile') == 1) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-user" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_PROFILE').'"></i>';
            }
            if ($role->getValue('rol_announcements') == 1 && (int) $gSettingsManager->get('announcements_module_enabled') > 0) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-newspaper" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_ANNOUNCEMENTS').'"></i>';
            }
            if ($role->getValue('rol_dates') == 1 && (int) $gSettingsManager->get('events_module_enabled') > 0) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-calendar-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_DATES').'"></i>';
            }
            if ($role->getValue('rol_photo') == 1 && (int) $gSettingsManager->get('photo_module_enabled') > 0) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-image" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_PHOTOS').'"></i>';
            }
            if ($role->getValue('rol_documents_files') == 1 && (int) $gSettingsManager->getBool('documents_files_module_enabled')) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-download" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_DOCUMENTS_FILES').'"></i>';
            }
            if ($role->getValue('rol_guestbook') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-book" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_GUESTBOOK').'"></i>';
            }
            // If allowed to write anonymous guestbook entries, then we don´t need to set rights for the roles
            if ($role->getValue('rol_guestbook_comments') == 1 && (int) $gSettingsManager->get('enable_guestbook_module') > 0 && !$gSettingsManager->getBool('enable_gbook_comments4all')) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-comment" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_GUESTBOOK_COMMENTS').'"></i>';
            }
            if ($role->getValue('rol_weblinks') == 1 && (int) $gSettingsManager->get('enable_weblinks_module') > 0) {
                $assignRoles .= '<i class="admidio-icon-chain fas fa-link" data-toggle="tooltip" title="'.$gL10n->get('SYS_RIGHT_WEBLINKS').'"></i>';
            }
            // if no assigned roles
            if ($assignRoles === '') {
                $assignRoles = '&nbsp;';
            }

            $viewEmail = '';
            $viewMemberships  = '';
            $viewProfiles = '';
            $leaderRights = '';

            switch ($role->getValue('rol_mail_this_role')) {
                case 0:
                    $viewEmail = 'SYS_NOBODY';
                    break;
                case 1:
                    $viewEmail = 'SYS_ROLE_MEMBERS';
                    break;
                case 2:
                    $viewEmail = 'ORG_REGISTERED_USERS';
                    break;
                case 3:
                    $viewEmail = 'SYS_ALSO_VISITORS';
                    break;
            }

            switch ($role->getValue('rol_view_memberships')) {
                case 0:
                    $viewMemberships = 'SYS_NOBODY';
                    break;
                case 1:
                    $viewMemberships = 'SYS_ROLE_MEMBERS';
                    break;
                case 2:
                    $viewMemberships = 'ORG_REGISTERED_USERS';
                    break;
            }

            switch ($role->getValue('rol_view_members_profiles')) {
                case 0:
                    $viewProfiles = 'SYS_NOBODY';
                    break;
                case 1:
                    $viewProfiles = 'SYS_ROLE_MEMBERS';
                    break;
                case 2:
                    $viewProfiles = 'ORG_REGISTERED_USERS';
                    break;
            }

            switch ($role->getValue('rol_leader_rights')) {
                case 0:
                    $leaderRights = 'SYS_NO_ADDITIONAL_RIGHTS';
                    break;
                case 1:
                    $leaderRights = 'SYS_ASSIGN_MEMBERS';
                    break;
                case 2:
                    $leaderRights = 'SYS_EDIT_MEMBERS';
                    break;
                case 3:
                    $leaderRights = 'SYS_ASSIGN_EDIT_MEMBERS';
                    break;
            }

            $rolId = (int) $role->getValue('rol_id');
            $rolName = $role->getValue('rol_name');

            $linkAdministration .= '<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('mode' => 'html', 'rol_ids' => $rolId)).'">'.
                '<i class="fas fa-list-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_SHOW_ROLE_MEMBERSHIP').'"></i></a>';
            if ($getRoleType === ROLE_TYPE_INACTIVE && !$role->getValue('rol_administrator')) {
                $linkAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_enable', 'element_id' => 'row_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                    '<i class="fas fa-user-check" data-toggle="tooltip" title="'.$gL10n->get('SYS_ACTIVATE_ROLE').'"></i></a>';
            } elseif ($getRoleType === ROLE_TYPE_ACTIVE && !$role->getValue('rol_administrator')) {
                $linkAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol_disable', 'element_id' => 'row_'.$roleUuid, 'name' => $role->getValue('rol_name'), 'database_id' => $roleUuid)).'">'.
                    '<i class="fas fa-user-slash" data-toggle="tooltip" title="'.$gL10n->get('SYS_DEACTIVATE_ROLE').'"></i></a>';
            }

            $linkAdministration .= '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                    data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'rol', 'element_id' => 'row_'.$roleUuid, 'name' => $rolName, 'database_id' => $roleUuid)).'">'.
                '<i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_ROLE').'"></i></a>';

            // create array with all column values
            $columnValues = array(
                array('value' => $role->getValue('cat_name'), 'order' => (int) $role->getValue('cat_sequence')),
                '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $roleUuid)).'">'.$rolName.'</a>',
                $assignRoles,
                $gL10n->get($viewEmail),
                $gL10n->get($viewMemberships),
                $gL10n->get($viewProfiles),
                $gL10n->get($leaderRights),
                $linkAdministration
            );

            $table->addRowByArray($columnValues, 'row_'. $roleUuid);
*/
            $templateData[] = $templateRow;
        }

        $this->assign('list', $templateData);
        $this->assign('l10n', $gL10n);
        $this->pageContent .= $this->fetch('modules/groups-roles.permissions-list.tpl');
    }

    /**
     * Creates an array with all available groups and roles. The array contains the following entries:
     * array(userID, userUUID, loginName, registrationTimestamp, lastName, firstName, email, validationID)
     * @param int $roleType
     * @param string $categoryUUID
     */
    public function readData(int $roleType = ModuleGroupsRoles::ROLE_TYPE_ACTIVE, string $categoryUUID = '')
    {
        global $gDb, $gCurrentOrgId, $gCurrentUser;

        $this->roleType = $roleType;

        $sql = 'SELECT rol.*, cat.*,
                       COALESCE((SELECT COUNT(*) + SUM(mem_count_guests) AS count
                          FROM '.TBL_MEMBERS.' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem.mem_begin  <= ? -- DATE_NOW
                           AND mem.mem_end     > ? -- DATE_NOW
                           AND (mem.mem_approved IS NULL
                            OR mem.mem_approved < 3)
                           AND mem.mem_leader = false), 0) AS num_members,
                       COALESCE((SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem.mem_begin  <= ? -- DATE_NOW
                           AND mem.mem_end     > ? -- DATE_NOW
                           AND mem.mem_leader = true), 0) AS num_leader,
                       COALESCE((SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem_end < ?  -- DATE_NOW
                           AND NOT EXISTS (
                               SELECT 1
                                 FROM '.TBL_MEMBERS.' AS act
                                WHERE act.mem_rol_id = mem.mem_rol_id
                                  AND act.mem_usr_id = mem.mem_usr_id
                                  AND ? BETWEEN act.mem_begin AND act.mem_end -- DATE_NOW
                           )), 0) AS num_former -- DATE_NOW
                  FROM '.TBL_ROLES.' AS rol
            INNER JOIN '.TBL_CATEGORIES.' AS cat
                    ON cat_id = rol_cat_id
                       '.(strlen($categoryUUID) > 1 ? ' AND cat_uuid = '.$categoryUUID : '').'
             LEFT JOIN '.TBL_EVENTS.' ON dat_rol_id = rol_id
                 WHERE (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )';

        switch ($this->roleType) {
            case ModuleGroupsRoles::ROLE_TYPE_INACTIVE:
                $sql .= ' AND rol_valid   = false
                         AND cat_name_intern <> \'EVENTS\' ';
                break;

            case ModuleGroupsRoles::ROLE_TYPE_ACTIVE:
                $sql .= ' AND rol_valid   = true
                         AND cat_name_intern <> \'EVENTS\' ';
                break;

            case ModuleGroupsRoles::ROLE_TYPE_EVENT_PARTICIPATION:
                $sql .= ' AND cat_name_intern = \'EVENTS\' ';
                break;
        }

        if ($this->roleType == ModuleGroupsRoles::ROLE_TYPE_INACTIVE && $gCurrentUser->isAdministrator()) {
            // if inactive roles should be shown, then show all of them to administrator
            $sql .= '';
        } else {
            // create a list with all rol_ids that the user is allowed to view
            $visibleRoles = implode(',', $gCurrentUser->getRolesViewMemberships());
            if ($visibleRoles !== '') {
                $sql .= ' AND rol_id IN (' . $visibleRoles . ')';
            } else {
                $sql .=' AND rol_id = 0 ';
            }
        }

        if ($this->roleType === ModuleGroupsRoles::ROLE_TYPE_EVENT_PARTICIPATION) {
            $sql .= ' ORDER BY cat_sequence, dat_begin DESC, rol_name ';
        } else {
            $sql .= ' ORDER BY cat_sequence, rol_name ';
        }

        $queryParameters = array(
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            DATE_NOW,
            $gCurrentOrgId
        );

        $this->data = $gDb->getArrayFromSql($sql, $queryParameters);
    }
}
