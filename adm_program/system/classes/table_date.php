<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_dates
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Terminobjekt zu erstellen. 
 * Ein Termin kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getDescription($type = 'HTML') - liefert die Beschreibung je nach Type zurueck
 *                 type = 'PLAIN'  : reiner Text ohne Html oder BBCode
 *                 type = 'HTML'   : BB-Code in HTML umgewandelt
 *                 type = 'BBCODE' : Beschreibung mit BBCode-Tags
 * getIcal($domain)  - gibt String mit dem Termin im iCal-Format zurueck
 * editRight()       - prueft, ob der Termin von der aktuellen Orga bearbeitet werden darf
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/ubb_parser.php');

class TableDate extends TableAccess
{
    protected $max_members_role = array();
    
    // Standard für Date ist alle Rollen aktiv => 0=Gast hinzufügen
    public $visible_for = array(0);    
    protected $bbCode;

    // Array mit Keys für Sichtbarkeit der Termine
    protected $visibility = array('0' => 'Gäste');
    
    // Konstruktor
    public function __construct(&$db, $dat_id = 0)
    {
        parent::__construct($db, TBL_DATES, 'dat', $dat_id);
        
        // Rollenname und ID aller Rollen mit Sichtbarkeit des Termins einlesen
        $sql = 'SELECT rol_id, rol_name 
                  FROM '.TBL_ROLES.' 
                 WHERE rol_visible = 1';
        $result = $db->query($sql);
        while($row = $db->fetch_array($result))
        {
            $this->visibility[$row['rol_id']] = $row['rol_name'];
            $this->visible_for[] = $row['rol_id'];
        }
    }

    // Benutzerdefiniertes Feld mit der uebergebenen ID aus der Datenbank auslesen
    public function readData($dat_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        if(is_numeric($dat_id))
        {
            $sql_additional_tables .= TBL_CATEGORIES;
            $sql_where_condition   .= '    dat_cat_id = cat_id
                                       AND dat_id     = '.$dat_id;
            parent::readData($dat_id, $sql_where_condition, $sql_additional_tables);
            
            $this->visible_for = array();
            $sql = 'SELECT DISTINCT dtr_rol_id FROM '.TBL_DATE_ROLE.' WHERE dtr_dat_id="'.$dat_id.'"';
            $result = $this->db->query($sql);

            while($row = $this->db->fetch_array($result))
            {
                $this->visible_for[] = intval($row['dtr_rol_id']);
            }
        }
    }

    // prueft die Gueltigkeit der uebergebenen Werte und nimmt ggf. Anpassungen vor
    public function setValue($field_name, $field_value)
    {
        if($field_name == 'dat_end' && $this->getValue('dat_all_day') == 1)
        {
            // hier muss bei ganztaegigen Terminen das bis-Datum um einen Tag hochgesetzt werden
            // damit der Termin bei SQL-Abfragen richtig beruecksichtigt wird
            list($year, $month, $day, $hour, $minute, $second) = split('[- :]', $field_value);
            $field_value = date('Y-m-d H:i:s', mktime($hour, $minute, $second, $month, $day, $year) + 86400);
        }
        parent::setValue($field_name, $field_value);
    }
    
    // liefert die Beschreibung je nach Type zurueck
    // type = 'PLAIN'  : reiner Text ohne Html oder BBCode
    // type = 'HTML'   : BB-Code in HTML umgewandelt
    // type = 'BBCODE' : Beschreibung mit BBCode-Tags
    public function getDescription($type = 'HTML')
    {
        global $g_preferences;
        $description = '';

        // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
        if($g_preferences['enable_bbcode'] == 1 && $type != 'BBCODE')
        {
            if(is_object($this->bbCode) == false)
            {
                $this->bbCode = new ubbParser();
            }

            $description = $this->bbCode->parse($this->getValue('dat_description'));

            if($type == 'PLAIN')
            {
                $description = strStripTags($description);
            }
        }
        else
        {
            $description = nl2br($this->getValue('dat_description'));
        }
        return $description;
    }

    public function getValue($field_name, $format = '')
    {
        if($field_name == 'dat_end' && $this->dbColumns['dat_all_day'] == 1)
        {
            // bei ganztaegigen Terminen wird das Enddatum immer 1 Tag zurueckgesetzt
            list($year, $month, $day, $hour, $minute, $second) = split('[- :]', $this->dbColumns['dat_end']);
            $value = date($format, mktime($hour, $minute, $second, $month, $day, $year) - 86400);
        }
        else
        {
            $value = parent::getValue($field_name, $format);
        }

        return $value;
    }
    
    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save()
    {
        global $g_current_organization, $g_current_user;

        if($this->new_record)
        {
            $this->setValue('dat_timestamp_create', DATETIME_NOW);
            $this->setValue('dat_usr_id_create', $g_current_user->getValue('usr_id'));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue('dat_timestamp_create')) + 900)
            || $g_current_user->getValue('usr_id') != $this->getValue('dat_usr_id_create') )
            {
                $this->setValue('dat_timestamp_change', DATETIME_NOW);
                $this->setValue('dat_usr_id_change', $g_current_user->getValue('usr_id'));
            }
        }
        parent::save();
    }
    
    // Methode, die den Termin in der DB loescht
    public function delete()
    {
        $sql = 'DELETE FROM '.TBL_DATE_ROLE.' WHERE dtr_dat_id = '.$this->getValue('dat_id');
        $result = $this->db->query($sql);

        // haben diesem Termin Mitglieder zugesagt, so muessen diese Zusagen noch geloescht werden
        if($this->getValue('dat_rol_id') > 0)
        {
            $sql = 'DELETE FROM '.TBL_MEMBERS.' WHERE mem_rol_id = '.$this->getValue('dat_rol_id');
            $this->db->query($sql);
            
            $sql = 'DELETE FROM '.TBL_ROLES.' WHERE rol_id = '.$this->getValue('dat_rol_id');
            $this->db->query($sql);
        }
        
        parent::delete();
    }    
   
    // gibt einen Termin im iCal-Format zurueck
    public function getIcal($domain)
    {
        $prodid = '-//www.admidio.org//Admidio' . ADMIDIO_VERSION . '//DE';
        $uid = $this->getValue('dat_timestamp_create', 'ymdThis') . '+' . $this->getValue('dat_usr_id_create') . '@' . $domain;
        
        $ical = "BEGIN:VCALENDAR\n".
                "METHOD:PUBLISH\n".
                "PRODID:". $prodid. "\n".
                "VERSION:2.0\n".
                "BEGIN:VEVENT\n".
                "UID:". $uid. "\n".
                "SUMMARY:". $this->getValue('dat_headline'). "\n".
                "DESCRIPTION:". str_replace("\r\n", '\n', $this->getDescription('PLAIN')). "\n".
                "DTSTAMP:". $this->getValue('dat_timestamp_create', 'ymdThisZ'). "\n".
                "LOCATION:". $this->getValue('dat_location'). "\n";
        if($this->getValue('dat_all_day') == 1)
        {
            // das Ende-Datum bei mehrtaegigen Terminen muss im iCal auch + 1 Tag sein
            // Outlook und Co. zeigen es erst dann korrekt an
            $ical .= "DTSTART;VALUE=DATE:". $this->getValue('dat_begin', 'ymd'). "\n".
                     "DTEND;VALUE=DATE:". $this->getValue('dat_end', 'ymd'). "\n";
        }
        else
        {
            $ical .= "DTSTART:". $this->getValue('dat_begin', 'ymdThis'). "\n".
                     "DTEND:". $this->getValue('dat_end', 'ymdThis'). "\n";
        }
        $ical .= "END:VEVENT\n".
                 "END:VCALENDAR";

        return $ical;
    }
    
    // prueft, ob der Termin von der aktuellen Orga bearbeitet werden darf
    public function editRight()
    {
        global $g_current_organization;
        
        // Termine der eigenen Orga darf bearbeitet werden
        if($this->getValue('cat_org_id') == $g_current_organization->getValue('org_id'))
        {
            return true;
        }
        // Termine von Kinder-Orgas darf bearbeitet werden, wenn diese als global definiert wurden
        elseif($this->getValue('dat_global') == true
        && $g_current_organization->isChildOrganization($this->getValue('cat_org_id')))
        {
            return true;
        }
    
        return false;
    }
    
    // gibt die Anzahl der maximalen Teilnehmer einer Rolle zurueck
    public function getMaxMembers($rol_id)
    {
        if(array_key_exists($rol_id, $this->max_members_role))
        {
            return $this->max_members_role[$rol_id];
        }
        else
        {
            return '';
        }
    }
    
    // prueft, ob der Termin fuer eine Rolle sichtbar ist
    public function isVisibleFor($rol_id)
    {
        return in_array($rol_id, $this->visible_for);
    }
    
    public function getVisibilityMode($mode)
    {
        return $this->visibility[$mode];
    }
    
    public function getVisibilityArray()
    {
        return $this->visibility;
    }    
}
?>